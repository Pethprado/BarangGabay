<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserSession;
use App\Services\FileService;
use App\Services\MailService;
use App\Services\TwoFactorService;

/**
 * "My Account" — self-service settings for back-office users.
 *
 * Residents have /profile (ResidentController); staff, admins and super admins
 * had no equivalent until this page. One controller serves all three
 * back-office roles on purpose: an admin needs to change their own password
 * exactly as much as a staff member does, and two near-identical pages would
 * drift apart.
 *
 * SECURITY CONTRACT — every method here obeys all four rules:
 *   1. The acting user is ALWAYS $_SESSION['user_id']. No method reads a user
 *      id from the request; there is no route parameter to tamper with.
 *   2. Writes go through the User::updateOwn*() methods, which each write a
 *      FIXED column list — full_name, phone, designation, avatar_url, email,
 *      email_verified, password_hash, locale and the notify_* flags. `role`
 *      and `status` appear in none of them, so a forged `role=superadmin`
 *      field in the POST body is inert: nothing reads it, and no query here
 *      can be made to write those columns without editing the model.
 *   3. check_csrf() opens every POST action.
 *   4. Successful changes are written to audit_logs.
 */
class AccountController
{
    /**
     * Suggestions offered in the designation field's datalist. The column is
     * free text — a barangay can have a title not on this list — but these
     * cover the usual back-office roles.
     */
    private const DESIGNATIONS = [
        'Punong Barangay',
        'Barangay Secretary',
        'Barangay Treasurer',
        'Barangay Kagawad',
        'SK Chairperson',
        'SK Kagawad',
        'Barangay Health Worker',
        'Barangay Nutrition Scholar',
        'Barangay Tanod',
        'Lupon Tagapamayapa',
        'Administrative Aide',
        'Barangay Record Keeper',
        'IT / System Administrator',
    ];

    /** Avatars are images only — the shared FileService also allows PDFs. */
    private const AVATAR_MIMES = ['image/jpeg', 'image/png'];

    // ── Page ──────────────────────────────────────────────────────────────

    /**
     * GET /admin/account
     * Profile, email, password, security, preferences and recent activity.
     */
    public function index(): void
    {
        $userId = $this->currentUserId();
        $user   = User::find($userId);

        if (!$user) {
            // Session points at a deleted account — end it rather than render.
            redirect('/logout');
        }

        // Sessions and activity are nice-to-have panels: a migration that has
        // not run yet must not take the whole settings page down with it.
        try {
            $sessions = UserSession::activeForUser($userId);
        } catch (\Throwable $e) {
            error_log('[AccountController::index] sessions: ' . $e->getMessage());
            $sessions = [];
        }

        try {
            $activity = AuditLog::recentForUser($userId, 20);
        } catch (\Throwable $e) {
            error_log('[AccountController::index] activity: ' . $e->getMessage());
            $activity = [];
        }

        view('admin/account/index', [
            'user'          => $user,
            'sessions'      => $sessions,
            'activity'      => $activity,
            'currentHash'   => hash('sha256', session_id()),
            'idleMinutes'   => UserSession::IDLE_MINUTES,
            'designations'  => self::DESIGNATIONS,
            'twoFaEnabled'  => !empty($user['totp_enabled']),
            'twoFaRequired' => TwoFactorService::isRequiredFor((string) $user['role']),
            'twoFaFeature'  => TwoFactorService::featureEnabled(),
            'locales'       => available_locales(),
            'pendingCount'  => User::countByStatus('pending'),
            'pageTitle'     => t('account.title'),
        ]);
    }

    // ── Profile details ───────────────────────────────────────────────────

    /**
     * POST /admin/account/profile
     * Name, phone, designation and an optional avatar, in one submit.
     */
    public function updateProfile(): void
    {
        check_csrf();

        $userId      = $this->currentUserId();
        $fullName    = trim($_POST['full_name']   ?? '');
        $phone       = trim($_POST['phone']       ?? '');
        $designation = trim($_POST['designation'] ?? '');

        if ($fullName === '') {
            flash('error', t('account.err_name_required'));
            redirect('/admin/account');
        }
        if (mb_strlen($fullName) > 150) {
            flash('error', t('account.err_name_long'));
            redirect('/admin/account');
        }
        if (mb_strlen($designation) > 100) {
            flash('error', t('account.err_designation_long'));
            redirect('/admin/account');
        }

        // The avatar is optional; a failure here must not silently discard the
        // text fields the user also just typed, so it is reported and the rest
        // of the form is still saved.
        $avatarError = null;
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $avatarError = $this->storeAvatar($userId, $_FILES['avatar']);
        }

        User::updateOwnProfile($userId, [
            'full_name'   => $fullName,
            'phone'       => $phone       !== '' ? $phone       : null,
            'designation' => $designation !== '' ? $designation : null,
        ]);

        // Keep the name in the admin chrome in sync with what was just saved.
        $_SESSION['full_name'] = $fullName;

        AuditLog::record($userId, 'account.profile.updated', 'Own profile details updated');

        if ($avatarError !== null) {
            flash('error', $avatarError);
        } else {
            flash('success', t('account.ok_profile'));
        }
        redirect('/admin/account');
    }

    // ── Email ─────────────────────────────────────────────────────────────

    /**
     * POST /admin/account/email
     * Changing the email un-verifies it and re-sends the verification link.
     */
    public function updateEmail(): void
    {
        check_csrf();

        $userId = $this->currentUserId();
        $user   = User::find($userId);
        if (!$user) {
            redirect('/logout');
        }

        $newEmail = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['current_password'] ?? '';

        if ($this->isChangeRateLimited((string) $user['email'])) {
            redirect('/admin/account');
        }

        if (!$newEmail) {
            flash('error', t('account.err_email_invalid'));
            redirect('/admin/account');
        }
        if (mb_strlen((string) $newEmail) > 191) {
            flash('error', t('account.err_email_long'));
            redirect('/admin/account');
        }

        // Confirming with the current password stops a hijacked session from
        // quietly moving the account to an attacker's address.
        if (!$this->confirmPassword($user, (string) $password)) {
            redirect('/admin/account');
        }

        if (strcasecmp((string) $newEmail, (string) $user['email']) === 0) {
            flash('error', t('account.err_email_same'));
            redirect('/admin/account');
        }
        if (User::emailTakenByAnother((string) $newEmail, $userId)) {
            flash('error', t('account.err_email_taken'));
            redirect('/admin/account');
        }

        $oldEmail = (string) $user['email'];
        User::updateOwnEmail($userId, (string) $newEmail);

        // Re-use the existing /verify-email flow rather than inventing a
        // second confirmation mechanism.
        $mailed = false;
        try {
            $token  = AuthController::emailVerificationToken($userId, (string) $newEmail);
            $mailed = (new MailService())->sendVerificationEmail(
                ['email' => (string) $newEmail, 'full_name' => (string) $user['full_name']],
                $token
            );
        } catch (\Throwable $e) {
            error_log('[AccountController::updateEmail] verification mail: ' . $e->getMessage());
        }

        AuditLog::record(
            $userId,
            'account.email.changed',
            'Email changed from ' . $oldEmail . ' to ' . $newEmail
        );

        // Say plainly that the address is unverified until the link is clicked
        // — and say so honestly when the mail itself could not be sent.
        flash(
            $mailed ? 'success' : 'error',
            $mailed
                ? t('account.ok_email', ['email' => (string) $newEmail])
                : t('account.warn_email_nomail', ['email' => (string) $newEmail])
        );
        redirect('/admin/account');
    }

    // ── Password ──────────────────────────────────────────────────────────

    /**
     * POST /admin/account/password
     * Confirms the current password, re-hashes, then re-issues this session
     * and kills every other one.
     */
    public function updatePassword(): void
    {
        check_csrf();

        $userId = $this->currentUserId();
        $user   = User::find($userId);
        if (!$user) {
            redirect('/logout');
        }

        $current = $_POST['current_password'] ?? '';
        $new     = (string) ($_POST['new_password']     ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($this->isChangeRateLimited((string) $user['email'])) {
            redirect('/admin/account');
        }

        if (!$this->confirmPassword($user, (string) $current)) {
            redirect('/admin/account');
        }

        // Same minimum as registration — deliberately the same constant, so the
        // two rules cannot drift apart.
        if (strlen($new) < AuthController::PASSWORD_MIN_LENGTH) {
            flash('error', t('account.err_password_short', ['n' => AuthController::PASSWORD_MIN_LENGTH]));
            redirect('/admin/account');
        }
        if ($new !== $confirm) {
            flash('error', t('account.err_password_mismatch'));
            redirect('/admin/account');
        }
        if (password_verify($new, (string) $user['password_hash'])) {
            flash('error', t('account.err_password_same'));
            redirect('/admin/account');
        }

        User::updateOwnPassword($userId, password_hash($new, PASSWORD_BCRYPT));

        // A password change that leaves old sessions alive is not a password
        // change: a stolen cookie would outlive the credential it came from.
        $revoked = $this->rotateSessions($userId);

        AuditLog::record(
            $userId,
            'account.password.changed',
            'Password changed; ' . $revoked . ' other session(s) signed out'
        );

        flash('success', t('account.ok_password', ['n' => $revoked]));
        redirect('/admin/account');
    }

    // ── Preferences ───────────────────────────────────────────────────────

    /**
     * POST /admin/account/preferences
     * Notification toggles plus the preferred UI language, persisted to the
     * account so the choice survives signing out.
     */
    public function updatePreferences(): void
    {
        check_csrf();

        $userId = $this->currentUserId();
        $locale = (string) ($_POST['locale'] ?? '');

        // Unknown locale codes are dropped, matching set_locale()'s behaviour.
        if (!array_key_exists($locale, available_locales())) {
            $locale = '';
        }

        User::updateOwnPreferences($userId, [
            'locale'               => $locale,
            'notify_feedback'      => !empty($_POST['notify_feedback']),
            'notify_registrations' => !empty($_POST['notify_registrations']),
            'notify_content'       => !empty($_POST['notify_content']),
        ]);

        if ($locale !== '') {
            set_locale($locale);
        }

        AuditLog::record($userId, 'account.preferences.updated', 'Notification and language preferences updated');

        flash('success', t('account.ok_preferences'));
        redirect('/admin/account');
    }

    // ── Security ──────────────────────────────────────────────────────────

    /**
     * POST /admin/account/sessions/revoke
     * "Sign out other devices" — same UserSession module the super admin
     * sessions panel uses, scoped to the caller's own rows.
     */
    public function revokeSessions(): void
    {
        check_csrf();

        $userId = $this->currentUserId();

        try {
            $revoked = UserSession::revokeOthersForUser($userId, session_id());
        } catch (\Throwable $e) {
            error_log('[AccountController::revokeSessions] ' . $e->getMessage());
            flash('error', t('account.err_sessions_failed'));
            redirect('/admin/account');
        }

        AuditLog::record($userId, 'account.sessions.revoked', 'Signed out ' . $revoked . ' other session(s)');

        flash('success', t('account.ok_sessions', ['n' => $revoked]));
        redirect('/admin/account');
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * The acting user. The single place a user id enters this controller —
     * always the session, never the request.
     */
    private function currentUserId(): int
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            redirect('/login');
        }

        return $userId;
    }

    /**
     * Verify the supplied current password, logging and flashing on failure.
     * Returns false when the caller should bail out.
     */
    private function confirmPassword(array $user, string $password): bool
    {
        if ($password !== '' && password_verify($password, (string) $user['password_hash'])) {
            return true;
        }

        LoginAttempt::record(
            (string) $user['email'],
            false,
            LoginAttempt::REASON_ACCOUNT_CONFIRM,
            (int) $user['id']
        );
        AuditLog::record(
            (int) $user['id'],
            'account.confirm.failed',
            'Wrong current password on the account settings page'
        );

        flash('error', t('account.err_current_password'));

        return false;
    }

    /**
     * True when this account has made too many wrong current-password
     * confirmations recently. Uses the same ceiling and cooldown as the login
     * flow (Super Admin → Settings), so there is one configurable rule rather
     * than a second invented one. Flashes the reason before returning true.
     */
    private function isChangeRateLimited(string $email): bool
    {
        $maxAttempts = (int) setting('login_max_attempts', 5);
        $cooldown    = (int) setting('login_lockout_min', 15);
        $maxAttempts = ($maxAttempts >= 1 && $maxAttempts <= 100)  ? $maxAttempts : 5;
        $cooldown    = ($cooldown    >= 1 && $cooldown    <= 1440) ? $cooldown    : 15;

        try {
            $failures = LoginAttempt::countRecentByReason(
                $email,
                LoginAttempt::REASON_ACCOUNT_CONFIRM,
                $cooldown
            );
        } catch (\Throwable) {
            return false; // fail open — a DB blip must not lock the page
        }

        if ($failures < $maxAttempts) {
            return false;
        }

        flash('error', t('account.err_rate_limited', ['n' => $cooldown]));

        return true;
    }

    /**
     * Re-issue the current session and end every other one for this user.
     * Called after a password change. Returns how many others were ended.
     */
    private function rotateSessions(int $userId): int
    {
        $revoked = 0;

        try {
            $revoked = UserSession::revokeOthersForUser($userId, session_id());
            // Close out the tracking row for the id we are about to replace,
            // then track the new one — otherwise the old row would linger as
            // an "active" session nobody is using.
            UserSession::end(session_id());
        } catch (\Throwable $e) {
            error_log('[AccountController::rotateSessions] ' . $e->getMessage());
        }

        session_regenerate_id(true);
        UserSession::start($userId, session_id());

        return $revoked;
    }

    /**
     * Validate and store an uploaded avatar.
     *
     * Delegates to FileService for the real work (finfo MIME sniffing, size
     * cap, random filename) and adds the one constraint an avatar needs on top
     * of it: images only, where FileService also accepts PDFs.
     *
     * @return string|null Error message, or null on success.
     */
    private function storeAvatar(int $userId, array $file): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, self::AVATAR_MIMES, true)) {
            return t('account.err_avatar_type');
        }

        try {
            $url = (new FileService())->upload($file, 'avatars');
        } catch (\Throwable $e) {
            error_log('[AccountController::storeAvatar] ' . $e->getMessage());

            return $e->getMessage();
        }

        User::updateOwnAvatar($userId, $url);

        return null;
    }
}
