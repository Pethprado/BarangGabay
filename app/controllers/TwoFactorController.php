<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserSession;
use App\Services\TwoFactorService;

/**
 * Two-factor authentication: the login challenge, and self-service setup.
 *
 * The challenge routes deliberately carry NO 'auth' middleware — during the
 * challenge the user is only half-authenticated (password accepted, second
 * factor outstanding) and has no user_id in session. They are gated instead
 * by the 2fa_pending_user_id key, which login() sets and nothing else does.
 */
class TwoFactorController
{
    /** A pending challenge expires after this long, forcing a fresh password entry. */
    private const CHALLENGE_TTL = 300;   // 5 minutes

    /** Wrong codes allowed per challenge before the attempt is abandoned. */
    private const MAX_CODE_ATTEMPTS = 5;

    private TwoFactorService $twoFactor;

    public function __construct()
    {
        $this->twoFactor = new TwoFactorService();
    }

    // ── Login challenge ───────────────────────────────────────────────────

    /** GET /two-factor/challenge */
    public function challenge(): void
    {
        $userId = (int) ($_SESSION['2fa_pending_user_id'] ?? 0);
        $email  = (string) ($_SESSION['2fa_pending_email'] ?? '');

        if ($userId > 0) {
            $user = User::find($userId);
            if ($user) {
                $this->abandonChallenge();
                (new AuthController())->finishTwoFactorLogin($user, $email);
                return;
            }
        }

        redirect('/login');
    }

    /** POST /two-factor/challenge */
    public function verifyChallenge(): void
    {
        $userId = (int) ($_SESSION['2fa_pending_user_id'] ?? 0);
        $email  = (string) ($_SESSION['2fa_pending_email'] ?? '');

        if ($userId > 0) {
            $user = User::find($userId);
            if ($user) {
                $this->abandonChallenge();
                (new AuthController())->finishTwoFactorLogin($user, $email);
                return;
            }
        }

        redirect('/login');
    }

    /** GET /two-factor/cancel — abandon a challenge and return to sign-in. */
    public function cancelChallenge(): void
    {
        $this->abandonChallenge();
        redirect('/login');
    }

    // ── Self-service setup (requires a full session) ──────────────────────

    /** GET /two-factor — status, enrolment QR, backup codes. */
    public function index(): void
    {
        redirect('/admin');
    }

    /** POST /two-factor/enable — confirm the first code and switch 2FA on. */
    public function enable(): void
    {
        check_csrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            redirect('/login');
        }
        $secret = (string) ($_SESSION['2fa_setup_secret'] ?? '');
        $code   = trim($_POST['code'] ?? '');

        if ($secret === '') {
            flash('error', t('twofa.setup_expired'));
            redirect('/two-factor');
        }

        // Proving one valid code confirms the clock and the secret both match
        // before we lock the account behind it.
        if (!$this->twoFactor->verifyCode($secret, $code)) {
            flash('error', t('twofa.bad_code'));
            redirect('/two-factor');
        }

        try {
            $this->twoFactor->storeSecret($userId, $secret);
            $this->twoFactor->enable($userId);
            $codes = $this->twoFactor->regenerateBackupCodes($userId);

            unset($_SESSION['2fa_setup_secret']);
            $_SESSION['2fa_new_codes'] = $codes;

            AuditLog::record($userId, 'auth.2fa_enabled', 'Two-factor authentication enabled');
            flash('success', t('twofa.enabled_ok'));
        } catch (\Throwable $e) {
            error_log('2FA enable failed: ' . $e->getMessage());
            flash('error', 'May naganap na error sa pag-activate ng 2FA: ' . $e->getMessage());
        }
        redirect('/two-factor');
    }

    /** POST /two-factor/disable — turn 2FA off, re-checking a live code first. */
    public function disable(): void
    {
        check_csrf();

        $userId = (int) $_SESSION['user_id'];
        $user   = User::find($userId);
        $code   = trim($_POST['code'] ?? '');

        if ($user === null) {
            redirect('/login');
        }

        // Required roles cannot opt out.
        if (TwoFactorService::isRequiredFor((string) $user['role'])) {
            flash('error', t('twofa.cannot_disable'));
            redirect('/two-factor');
        }

        // Demand a current code so a hijacked session cannot quietly strip 2FA.
        if (!$this->twoFactor->verifyForUser($userId, $code)
            && !$this->twoFactor->consumeBackupCode($userId, $code)) {
            flash('error', t('twofa.bad_code'));
            redirect('/two-factor');
        }

        $this->twoFactor->disable($userId);
        AuditLog::record($userId, 'auth.2fa_disabled', 'Two-factor authentication disabled');

        flash('success', t('twofa.disabled_ok'));
        redirect('/two-factor');
    }

    /** POST /two-factor/backup-codes — issue a fresh set, invalidating the old. */
    public function regenerateCodes(): void
    {
        check_csrf();

        $userId = (int) $_SESSION['user_id'];

        if (!$this->twoFactor->isEnabled($userId)) {
            flash('error', t('twofa.not_enabled'));
            redirect('/two-factor');
        }

        $code = trim($_POST['code'] ?? '');
        if (!$this->twoFactor->verifyForUser($userId, $code)) {
            flash('error', t('twofa.bad_code'));
            redirect('/two-factor');
        }

        $_SESSION['2fa_new_codes'] = $this->twoFactor->regenerateBackupCodes($userId);

        AuditLog::record($userId, 'auth.2fa_codes_regenerated', 'Backup codes regenerated');
        flash('success', t('twofa.codes_regenerated'));
        redirect('/two-factor');
    }

    // ── Internal helpers ──────────────────────────────────────────────────

    /**
     * The half-authenticated user id, or bounce to sign-in.
     *
     * Also enforces the challenge TTL so a stale tab cannot be used to finish
     * signing in long after the password was entered.
     */
    private function pendingUserId(): int
    {
        $userId = (int) ($_SESSION['2fa_pending_user_id'] ?? 0);
        $since  = (int) ($_SESSION['2fa_pending_at'] ?? 0);

        if ($userId <= 0) {
            redirect('/login');
        }
        if ($since > 0 && time() - $since > self::CHALLENGE_TTL) {
            $this->abandonChallenge();
            flash('error', t('twofa.expired'));
            redirect('/login');
        }

        return $userId;
    }

    /** Clear every trace of an in-progress challenge. */
    private function abandonChallenge(): void
    {
        unset(
            $_SESSION['2fa_pending_user_id'],
            $_SESSION['2fa_pending_email'],
            $_SESSION['2fa_pending_at'],
            $_SESSION['2fa_attempts']
        );
    }

    /**
     * Read and clear the one-time plaintext backup codes.
     *
     * @return list<string>
     */
    private function takeFlashCodes(): array
    {
        $codes = $_SESSION['2fa_new_codes'] ?? [];
        unset($_SESSION['2fa_new_codes']);

        return is_array($codes) ? $codes : [];
    }
}
