<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSession;
use PDO;

/**
 * "Remember me" sign-in.
 *
 * Split-token design: the cookie holds selector:validator. The selector finds
 * the row; only a SHA-256 of the validator is stored, compared in constant
 * time, so a leaked table cannot be replayed. Each use rotates the token, a
 * password reset or sign-out deletes it, and suspended accounts never
 * restore.
 */
final class RememberMe
{
    public const COOKIE = 'bg_remember';
    private const DAYS  = 30;

    /** Issue a token for a user who ticked "Remember me". */
    public static function issue(int $userId): void
    {
        try {
            $selector  = bin2hex(random_bytes(9));
            $validator = bin2hex(random_bytes(32));
            $expires   = time() + self::DAYS * 86400;

            db()->prepare(
                'INSERT INTO remember_tokens (user_id, selector, validator_hash, user_agent, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            )->execute([
                $userId, $selector, hash('sha256', $validator),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                date('Y-m-d H:i:s', $expires),
            ]);

            self::setCookie($selector . ':' . $validator, $expires);
        } catch (\Throwable $e) {
            error_log('[RememberMe::issue] ' . $e->getMessage());
        }
    }

    /**
     * Sign a returning visitor in from their cookie. Call once per request,
     * after the session has started and before routing. Never throws.
     */
    public static function attempt(): void
    {
        if (!empty($_SESSION['user_id']) || empty($_COOKIE[self::COOKIE])) {
            return;
        }

        try {
            [$selector, $validator] = array_pad(explode(':', (string) $_COOKIE[self::COOKIE], 2), 2, '');
            if (!preg_match('/^[a-f0-9]{18}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
                self::clearCookie();
                return;
            }

            $stmt = db()->prepare('SELECT * FROM remember_tokens WHERE selector = ? LIMIT 1');
            $stmt->execute([$selector]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || strtotime((string) $row['expires_at']) < time()
                || !hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
                if ($row) {
                    // A wrong validator for a real selector suggests theft: drop the token.
                    db()->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([(int) $row['id']]);
                }
                self::clearCookie();
                return;
            }

            $user = User::find((int) $row['user_id']);
            db()->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([(int) $row['id']]);
            if (!$user || ($user['status'] ?? '') === 'suspended') {
                self::clearCookie();
                return;
            }

            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['status']    = $user['status'];
            $_SESSION['full_name'] = $user['full_name'];
            if (!empty($user['locale'])) {
                set_locale((string) $user['locale']);
            }
            UserSession::start((int) $user['id'], session_id());
            User::touchLastLogin((int) $user['id']);
            AuditLog::record((int) $user['id'], 'auth.remember_login', 'Signed in with a remembered device');

            self::issue((int) $user['id']);   // rotate
        } catch (\Throwable $e) {
            error_log('[RememberMe::attempt] ' . $e->getMessage());
        }
    }

    /** Forget this device (sign-out). */
    public static function forget(): void
    {
        try {
            $selector = explode(':', (string) ($_COOKIE[self::COOKIE] ?? ''), 2)[0];
            if ($selector !== '') {
                db()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
            }
        } catch (\Throwable $e) {
            error_log('[RememberMe::forget] ' . $e->getMessage());
        }
        self::clearCookie();
    }

    /** Forget every device of a user (password reset). */
    public static function forgetUser(int $userId): void
    {
        try {
            db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
        } catch (\Throwable $e) {
            error_log('[RememberMe::forgetUser] ' . $e->getMessage());
        }
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(self::COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value;
    }

    private static function clearCookie(): void
    {
        self::setCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE]);
    }
}
