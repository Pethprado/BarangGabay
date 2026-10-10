<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSession;
use App\Services\MailService;
use App\Services\RememberMe;
use PDO;

/**
 * Self-service "Forgot password?".
 *
 * A single-use link valid for one hour is emailed to the address on file.
 * The response is identical whether or not the email exists, so the form
 * cannot be used to discover accounts. Requests are rate-limited per email
 * and per IP. Completing a reset signs every device out.
 */
class PasswordResetController
{
    private const TTL_MINUTES  = 60;
    private const MAX_PER_HOUR = 3;

    /** GET /forgot-password */
    public function showForgot(): void
    {
        view('auth/forgot', ['pageTitle' => 'Forgot password']);
    }

    /** POST /forgot-password */
    public function sendLink(): void
    {
        check_csrf();
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $ip    = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $done  = 'If an account uses that email, we sent a link to reset the password. It expires in one hour — check your inbox and spam folder.';

        if (!$email) {
            flash('error', 'Enter a valid email address.');
            redirect('/forgot-password');
        }

        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE requested_ip = ? AND created_at > ?');
            $stmt->execute([$ip, date('Y-m-d H:i:s', time() - 3600)]);
            if ((int) $stmt->fetchColumn() >= self::MAX_PER_HOUR * 3) {
                flash('success', $done);
                redirect('/forgot-password');
            }

            $user = User::findByEmail((string) $email);
            if ($user && ($user['status'] ?? '') !== 'suspended') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > ?');
                $stmt->execute([(int) $user['id'], date('Y-m-d H:i:s', time() - 3600)]);
                if ((int) $stmt->fetchColumn() < self::MAX_PER_HOUR) {
                    $token = bin2hex(random_bytes(32));
                    db()->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, requested_ip, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())'
                    )->execute([(int) $user['id'], hash('sha256', $token), $ip, date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60)]);

                    $link = rtrim(base_url(), '/') . '/reset-password?token=' . $token;
                    $name = (string) $user['full_name'];
                    $body = '<p>Hello ' . e($name) . ',</p>'
                          . '<p>Someone asked to reset the password for your BarangGabay account. If it was you, open this link within one hour:</p>'
                          . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
                          . '<p>If you did not ask for this, ignore this email — your password stays the same.</p>'
                          . '<p>— BarangGabay, ' . e(system_location()) . '</p>';
                    $sent = (new MailService())->send((string) $user['email'], $name, 'Reset your BarangGabay password', $body);
                    AuditLog::record((int) $user['id'], 'auth.password_reset_requested', $sent ? 'Reset link emailed' : 'Reset link could not be emailed');
                }
            }
        } catch (\Throwable $e) {
            error_log('[PasswordResetController::sendLink] ' . $e->getMessage());
        }

        flash('success', $done);
        redirect('/forgot-password');
    }

    /** GET /reset-password?token=… */
    public function showReset(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        view('auth/reset', ['pageTitle' => 'Reset password', 'token' => $token, 'valid' => $this->findToken($token) !== null]);
    }

    /** POST /reset-password */
    public function reset(): void
    {
        check_csrf();
        $token    = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirmation'] ?? '');
        $row      = $this->findToken($token);
        $back     = '/reset-password?token=' . rawurlencode($token);

        if ($row === null) {
            flash('error', 'This reset link is invalid or has expired. Ask for a new one.');
            redirect('/forgot-password');
        }
        if (AuthController::passwordRuleFailures($password) !== []) {
            flash('error', AuthController::passwordRuleSentence());
            redirect($back);
        }
        if (!hash_equals($password, $confirm)) {
            flash('error', 'The two passwords do not match.');
            redirect($back);
        }

        $userId = (int) $row['user_id'];
        try {
            User::setPasswordHashByResetToken($userId, password_hash($password, PASSWORD_BCRYPT));
            db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
            // Sign every device out: whoever had the old password is out too.
            RememberMe::forgetUser($userId);
            UserSession::revokeAllForUser($userId);
            AuditLog::record($userId, 'auth.password_reset', 'Password reset with an emailed link');
        } catch (\Throwable $e) {
            error_log('[PasswordResetController::reset] ' . $e->getMessage());
            flash('error', 'The password could not be changed because of a server error. Try again.');
            redirect($back);
        }

        flash('success', 'Your password was changed. Sign in with the new password.');
        redirect('/login');
    }

    /** @return array<string,mixed>|null */
    private function findToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        try {
            $stmt = db()->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ? LIMIT 1');
            $stmt->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('[PasswordResetController::findToken] ' . $e->getMessage());
            return null;
        }
    }
}
