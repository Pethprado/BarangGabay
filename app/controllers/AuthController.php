<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\AiPrediction;
use App\Models\LoginAttempt;
use App\Models\UserSession;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Services\TwoFactorService;
use App\Services\MailService;
use App\Services\IdVerificationService;

class AuthController
{
    // Fallbacks used when the settings table is unavailable. The live values
    // come from Super Admin → Settings via loginMaxAttempts()/loginCooldownMin().
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_COOLDOWN_MIN = 15;

    /**
     * Minimum password length, applied at registration, when an admin creates
     * a staff account, and when anyone changes their own password on
     * /admin/account. Public so those callers share this one rule instead of
     * each hard-coding a number that could drift.
     */
    public const PASSWORD_MIN_LENGTH = 8;

    /**
     * Login entry points.
     *
     * A school portal puts "Student" and "Faculty" doors on one login page, and
     * this is the same idea: the heading, icon and accent tell you where you
     * are — and `roles` says who that door actually admits. A resident account
     * cannot sign in on the staff page, and a staff account cannot sign in on
     * the resident page.
     *
     * Two things about that gate matter more than the gate itself:
     *
     *   It runs AFTER the password is verified. That is deliberate. Checking
     *   the role first — looking an email up and rejecting it before testing
     *   the password — would let anyone type addresses at the staff door and
     *   learn which ones are staff accounts, with no password at all. As
     *   written, the only person who can tell the doors apart is someone who
     *   already holds working credentials, and they could simply have used the
     *   right door.
     *
     *   The role still comes from the user record, never from ?as=. The query
     *   string can only ever make a sign-in fail; it can never grant anything.
     *   That asymmetry is the whole safety property here: there is no value a
     *   client can send that turns a resident account into a staff session.
     *
     * Still one authentication endpoint, POST /login, with one CSRF check, one
     * rate limiter and one 2FA challenge. Separate forms per role would triple
     * the surface to protect and secure nothing, since anyone can open any of
     * these URLs regardless.
     *
     * 'system' is the superadmin door and is deliberately absent from
     * PUBLIC_ENTRIES below, so nothing on the page links to it. A public link
     * saying "Super Admin" would point every scanner at the highest-value
     * account and buy nothing.
     *
     * Superadmin is admitted by the staff door too. Excluding it would force a
     * rejection message that pointed at the unadvertised door — advertising it
     * by accident, to say nothing of stranding the one account that cannot ask
     * anyone else for help.
     */
    private const LOGIN_ENTRIES = [
        'resident' => [
            'icon'     => 'bi-people-fill',
            'accent'   => '#7a5c11',   // gold-ink — white on it 6.24:1
            'register' => true,
            'roles'    => ['resident'],
        ],
        'staff' => [
            'icon'     => 'bi-shield-lock-fill',
            'accent'   => '#2f5d3a',   // earthy green — white on it 7.64:1
            'register' => false,
            'roles'    => ['staff', 'admin', 'superadmin'],
        ],
        'system' => [
            'icon'     => 'bi-hdd-network-fill',
            'accent'   => '#5e161a',   // maroon-dark — white on it 13.08:1
            'register' => false,
            'roles'    => ['superadmin'],
        ],
    ];

    /** Entries the page is allowed to link to. Note the absent 'system'. */
    private const PUBLIC_ENTRIES = ['resident', 'staff'];

    private const DEFAULT_ENTRY = 'resident';

    /**
     * Resolve ?as= to a known entry, or the default.
     *
     * Whitelisted rather than sanitised: the value only ever selects a key in
     * LOGIN_ENTRIES, so nothing a client sends is echoed into the page or
     * reaches a query.
     */
    private function loginEntry(mixed $raw): string
    {
        $key = is_string($raw) ? strtolower(trim($raw)) : '';

        return isset(self::LOGIN_ENTRIES[$key]) ? $key : self::DEFAULT_ENTRY;
    }

    /**
     * Where a failed sign-in goes back to.
     *
     * Keeps the door someone came through, so a staff member who mistypes a
     * password is not silently moved to the resident screen. Purely cosmetic:
     * the path is rebuilt from the whitelist, never from the raw parameter.
     */
    private function loginRedirectPath(?string $entry = null): string
    {
        $entry ??= $this->loginEntry($_GET['as'] ?? null);

        return $entry === self::DEFAULT_ENTRY ? '/login' : '/login?as=' . $entry;
    }

    /** Whether an entry point admits a given role. */
    private function entryAdmits(string $entry, string $role): bool
    {
        return \in_array($role, self::LOGIN_ENTRIES[$entry]['roles'] ?? [], true);
    }

    /**
     * The door that does admit this role, for the "you want that page instead"
     * link on a rejection.
     *
     * Only ever names a door the page already links to, so a rejection can
     * never disclose that the unadvertised system entry exists.
     */
    private function entryForRole(string $role): string
    {
        return $role === 'resident' ? 'resident' : 'staff';
    }

    /** Configured failed-attempt ceiling, clamped to a sane range. */
    private function loginMaxAttempts(): int
    {
        $value = (int) setting('login_max_attempts', self::LOGIN_MAX_ATTEMPTS);

        return $value >= 1 && $value <= 100 ? $value : self::LOGIN_MAX_ATTEMPTS;
    }

    /** Configured lockout window in minutes, clamped to a sane range. */
    private function loginCooldownMin(): int
    {
        $value = (int) setting('login_lockout_min', self::LOGIN_COOLDOWN_MIN);

        return $value >= 1 && $value <= 1440 ? $value : self::LOGIN_COOLDOWN_MIN;
    }

    /**
     * Failed-attempt ceiling per IP address.
     *
     * Higher than the per-email limit on purpose: a barangay hall or internet
     * cafe puts many legitimate residents behind one address, and a tight IP
     * limit would lock out the whole building.
     */
    private function loginMaxAttemptsIp(): int
    {
        $value = (int) setting('login_max_attempts_ip', 20);

        return $value >= 1 && $value <= 500 ? $value : 20;
    }

    /**
     * True when this IP has produced too many failures across any accounts.
     * Catches password-spraying, which a per-email limit alone misses.
     */
    private function isIpRateLimited(): bool
    {
        if (!setting('login_ip_limit_enabled', true)) {
            return false;
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return false;
        }

        try {
            return LoginAttempt::countRecentFailuresByIp($ip, $this->loginCooldownMin())
                >= $this->loginMaxAttemptsIp();
        } catch (\Throwable) {
            return false; // fail open — never block sign-in on a DB error
        }
    }

    // ──────────────────────────────────────────────────────────
    //  SHOW FORMS
    // ──────────────────────────────────────────────────────────

    /**
     * GET /login — optionally GET /login?as=staff (or ?as=system, unadvertised).
     *
     * Passes presentation variables only. See LOGIN_ENTRIES.
     */
    public function showLogin(): void
    {
        $entry = $this->loginEntry($_GET['as'] ?? null);

        view('auth/login', [
            'entry'       => $entry,
            'entryIcon'   => self::LOGIN_ENTRIES[$entry]['icon'],
            'entryAccent' => self::LOGIN_ENTRIES[$entry]['accent'],
            'showRegister'=> self::LOGIN_ENTRIES[$entry]['register'],
            // Everything the page may link to, minus the door already open.
            // 'system' is never in this list.
            'otherEntries' => array_values(array_diff(self::PUBLIC_ENTRIES, [$entry])),
        ]);
    }

    public function showRegister(): void
    {
        $errors   = $_SESSION['errors']  ?? [];
        $oldInput = $_SESSION['_old']    ?? [];
        unset($_SESSION['errors'], $_SESSION['_old']);
        view('auth/register', compact('errors', 'oldInput'));
    }

    public function pending(): void
    {
        view('auth/pending');
    }

    /** GET /api/check-status — polled by the pending page every 10 s. */
    public function checkStatus(): void
    {
        header('Content-Type: application/json');
        $user = User::find((int) ($_SESSION['user_id'] ?? 0));
        if (!$user) {
            echo json_encode(['status' => 'unknown']);
            return;
        }
        // Keep session in sync so the next page load doesn't need a DB round-trip.
        $_SESSION['status'] = $user['status'];
        echo json_encode(['status' => $user['status']]);
    }

    // ──────────────────────────────────────────────────────────
    //  LOGIN
    // ──────────────────────────────────────────────────────────

    /**
     * POST /login — the single authentication endpoint for every role.
     *
     * The form may be reached through any of the entry points in
     * LOGIN_ENTRIES, and every one of them lands here: same CSRF check, same
     * per-IP and per-email rate limiting, same 2FA challenge, same redirect
     * rules in completeLogin().
     *
     * The door decides two things and no others: where a failed attempt is
     * sent back to, and — once the password has been verified — whether this
     * role is admitted here at all. It can never grant access; only withhold
     * it. See LOGIN_ENTRIES.
     */
    public function login(): void
    {
        check_csrf();

        // Read once so every path below agrees on which door this is.
        $entry = $this->loginEntry($_GET['as'] ?? null);
        $back  = $this->loginRedirectPath($entry);

        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            flash('error', t('login.err_missing_fields'));
            redirect($back);
        }

        // Per-IP ceiling first: one machine spraying passwords across many
        // accounts never trips a per-email limit.
        if ($this->isIpRateLimited()) {
            LoginAttempt::record((string) $email, false, LoginAttempt::REASON_IP_RATE_LIMITED);
            AuditLog::record(null, 'auth.ip_blocked', 'IP rate limit hit for ' . $email);
            flash('error', t('login.err_ip_rate_limited', ['minutes' => $this->loginCooldownMin()]));
            flash('error_kind', 'rate_limited');
            redirect($back);
        }

        // Rate-limit: block if too many recent failed attempts for this email.
        if ($this->isLoginRateLimited((string) $email)) {
            // Logged for the Super Admin history, but excluded from the
            // failure count so a lockout cannot renew itself indefinitely.
            LoginAttempt::record((string) $email, false, LoginAttempt::REASON_RATE_LIMITED);
            flash('error', t('login.err_rate_limited', ['minutes' => $this->loginCooldownMin()]));
            flash('error_kind', 'rate_limited');
            redirect($back);
        }

        $user = User::findByEmail((string) $email);

        // Wrong credentials.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            LoginAttempt::record(
                (string) $email,
                false,
                $user ? LoginAttempt::REASON_BAD_CREDENTIALS : LoginAttempt::REASON_UNKNOWN_EMAIL,
                $user ? (int) $user['id'] : null
            );
            AuditLog::record(null, 'auth.failed', "Failed login for {$email}");
            // One message for "no such account" and "wrong password" alike, on
            // every entry point — the door someone used must never become a way
            // to ask whether an account exists or what role it holds.
            flash('error', t('login.err_bad_credentials'));
            redirect($back);
        }

        // Suspended account.
        if ($user['status'] === 'suspended') {
            LoginAttempt::record((string) $email, false, LoginAttempt::REASON_SUSPENDED, (int) $user['id']);
            AuditLog::record((int) $user['id'], 'auth.failed', "Login attempt on suspended account: {$email}");
            flash('error', t('login.err_suspended'));
            // Said explicitly rather than sniffed out of the message text. The
            // view used to look for the English word "suspended" in a Tagalog
            // sentence ("nasuspinde"), so the calmer styling for this case
            // never actually fired — and translating the message would have
            // broken the match in two more languages.
            flash('error_kind', 'suspended');
            redirect($back);
        }

        /*
         * Right credentials, wrong door.
         *
         * Only reachable once the password has already been verified, so this
         * cannot be used to ask whether an address belongs to a staff account:
         * without the password every door still answers "wrong email or
         * password" and nothing else.
         *
         * Nothing is written to the session and no 2FA challenge is started —
         * the attempt simply ends here, and they are pointed at the page that
         * does admit them.
         */
        if (!$this->entryAdmits($entry, (string) $user['role'])) {
            LoginAttempt::record(
                (string) $email,
                false,
                LoginAttempt::REASON_WRONG_ENTRY,
                (int) $user['id']
            );
            AuditLog::record(
                (int) $user['id'],
                'auth.wrong_entry',
                "Correct password offered at the '{$entry}' entry point by a {$user['role']} account"
            );

            flash('error', t('login.err_wrong_entry_' . $entry));
            flash('error_kind', 'wrong_entry');
            // Which door to offer them. Never 'system'.
            flash('error_entry', $this->entryForRole((string) $user['role']));
            redirect($back);
        }

        // Password is correct. If this account has 2FA, stop here and hold the
        // user in a half-authenticated state: no user_id is written to the
        // session, so every protected route still treats them as a guest until
        // they pass the second factor.
        if (TwoFactorService::featureEnabled() && !empty($user['totp_enabled'])) {
            session_regenerate_id(true);
            $_SESSION['2fa_pending_user_id'] = (int) $user['id'];
            $_SESSION['2fa_pending_email']   = (string) $email;
            $_SESSION['2fa_pending_at']      = time();
            redirect('/two-factor/challenge');
        }

        $this->completeLogin($user, (string) $email);
    }

    /**
     * Entry point for TwoFactorController once the second factor is accepted.
     * Keeps completeLogin() private while giving the challenge one clear way
     * in — the session is only ever populated from that single method.
     */
    public function finishTwoFactorLogin(array $user, string $email): void
    {
        $this->completeLogin($user, $email);
    }

    /**
     * Finish signing a user in, after the password — and the second factor
     * where one is configured — have both been accepted.
     *
     * Shared by login() and the 2FA challenge so the session, logging and
     * redirect rules can never drift apart between the two paths.
     */
    private function completeLogin(array $user, string $email): void
    {
        // Regenerate to prevent fixation. Safe to call twice: the 2FA path
        // already regenerated once when it parked the pending user id.
        session_regenerate_id(true);

        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_email'], $_SESSION['2fa_pending_at']);

        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['status']    = $user['status'];
        $_SESSION['full_name'] = $user['full_name'];

        // Restore the UI language saved on the account (/admin/account →
        // Preferences). Without this the choice would only live as long as the
        // session and be lost on every sign-out. set_locale() ignores unknown
        // codes, and a NULL column simply leaves the default in place.
        if (!empty($user['locale'])) {
            set_locale((string) $user['locale']);
        }

        LoginAttempt::record($email, true, null, (int) $user['id']);
        UserSession::start((int) $user['id'], session_id());
        User::touchLastLogin((int) $user['id']);
        AuditLog::record((int) $user['id'], 'auth.login', 'User login');

        // Staff and admin roles go to the admin panel; residents go to the portal.
        if ($user['status'] === 'verified') {
            $adminRoles = ['admin', 'superadmin', 'staff'];
            redirect(in_array($user['role'], $adminRoles, true) ? '/admin' : '/');
        }
        redirect('/pending');
    }

    // ──────────────────────────────────────────────────────────
    //  REGISTER
    // ──────────────────────────────────────────────────────────

    public function register(): void
    {
        check_csrf();

        $fullName = trim($_POST['full_name'] ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        /*
         * Purok, optional, and checked against the barangay's own list.
         *
         * The form renders a dropdown when barangay_subdivisions() is
         * populated, but a dropdown is a suggestion to the browser and not a
         * constraint on what arrives here — so anything that is not one of the
         * seven puroks is dropped rather than stored. When the list is empty
         * the field is a free-text box and the value is simply trimmed.
         */
        $zone     = mb_substr(trim($_POST['zone'] ?? ''), 0, 50);
        $puroks   = barangay_subdivisions();
        if ($puroks !== [] && $zone !== '' && !in_array($zone, $puroks, true)) {
            $zone = '';
        }
        $terms    = !empty($_POST['terms']);

        $errors = [];

        if (!$fullName) {
            $errors['full_name'] = 'Buong pangalan ay kinakailangan.';
        } elseif (mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Ang pangalan ay hindi dapat humigit sa 150 karakter.';
        }

        if (!$email) {
            $errors['email'] = 'Kinakailangan ang wastong email address.';
        } elseif (User::findByEmail((string) $email)) {
            $errors['email'] = 'Ang email na ito ay nairehistro na.';
        }

        if (!$password) {
            $errors['password'] = 'Password ay kinakailangan.';
        } elseif (\strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors['password'] = 'Ang password ay dapat hindi bababa sa '
                . self::PASSWORD_MIN_LENGTH . ' karakter.';
        }

        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Hindi magkatugma ang mga password.';
        }

        if (!$phone) {
            $errors['phone'] = 'Numero ng telepono ay kinakailangan.';
        }

        if (!$address) {
            $errors['address'] = 'Kumpletong address ay kinakailangan.';
        }

        if (empty($_FILES['id_photo']['tmp_name'])) {
            $errors['id_photo'] = 'Kinakailangan ang valid ID upload.';
        }

        if (!$terms) {
            $errors['terms'] = 'Kailangan mong tanggapin ang mga tuntunin.';
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['_old']   = [
                'full_name' => $fullName,
                'email'     => $_POST['email'] ?? '',
                'phone'     => $phone,
                'address'   => $address,
                'zone'      => $zone,
            ];
            flash('error', 'Mangyaring itama ang mga may markang patlang at subukan muli.');
            redirect('/register');
        }

        // ── AI ID Verification (runs on tmp file before it is permanently saved) ──
        $aiResult    = null;
        $idAiStatus  = null;
        $isPdfUpload = false;

        if (!empty($_FILES['id_photo']['tmp_name'])) {
            // Detect real MIME type
            $finfo   = \finfo_open(FILEINFO_MIME_TYPE);
            $mime    = (string) \finfo_file($finfo, $_FILES['id_photo']['tmp_name']);
            \finfo_close($finfo);

            $isPdfUpload = ($mime === 'application/pdf');

            if (!$isPdfUpload) {
                // Only image files can be analysed by Claude vision
                $verifier = new IdVerificationService();
                $aiResult = $verifier->verifyId($_FILES['id_photo']['tmp_name'], $mime);

                if (isset($aiResult['is_valid']) && !$aiResult['is_valid']) {
                    // Hard rejection: AI is confident this is not a real ID
                    $reason = $aiResult['reason'] ?? 'Hindi valid ang na-upload na ID.';
                    $issues = $aiResult['issues'] ?? [];

                    $errorMsg = '❌ Hindi tinanggap ang inyong Valid ID. ' . $reason;
                    if (!empty($issues)) {
                        $errorMsg .= ' (' . implode(', ', array_slice($issues, 0, 3)) . ')';
                    }
                    $errorMsg .= ' Pakiupload ng tunay na Philippine government-issued ID na may malinaw na mukha at pangalan.';

                    $_SESSION['errors'] = ['id_photo' => $errorMsg];
                    $_SESSION['_old']   = [
                        'full_name' => $fullName,
                        'email'     => $_POST['email'] ?? '',
                        'phone'     => $phone,
                        'address'   => $address,
                        'zone'      => $zone,
                    ];
                    flash('error', 'Hindi tinanggap ang iyong Valid ID. Pakiupload ng tunay na government ID.');
                    redirect('/register');
                }

                $idAiStatus = $aiResult['id_ai_status'] ?? 'ai_passed';
            } else {
                // PDFs cannot be vision-analysed — flag for manual staff review
                $idAiStatus = 'pdf_manual';
                $aiResult   = [
                    'is_valid'     => true,
                    'id_ai_status' => 'pdf_manual',
                    'confidence'   => 'unknown',
                    'id_type'      => 'PDF document',
                    'reason'       => 'PDF ID uploaded — requires manual staff review.',
                    'has_photo'    => null,
                    'has_name'     => null,
                    'issues'       => [],
                ];
            }
        }

        // Upload valid ID via FileService.
        $idPhotoUrl = null;
        if (!empty($_FILES['id_photo']['tmp_name'])) {
            try {
                $idPhotoUrl = (new FileService())->upload($_FILES['id_photo'], 'id-photos');
            } catch (\Throwable $e) {
                $_SESSION['errors'] = ['id_photo' => 'Nabigong i-upload ang ID. Mag-upload ng JPEG, PNG, o PDF (max 10MB).'];
                $_SESSION['_old']   = [
                    'full_name' => $fullName,
                    'email'     => $_POST['email'] ?? '',
                    'phone'     => $phone,
                    'address'   => $address,
                    'zone'      => $zone,
                ];
                flash('error', 'Nabigong i-upload ang ID. Subukan muli.');
                redirect('/register');
            }
        }

        $userId = User::create([
            'full_name'        => $fullName,
            'email'            => (string) $email,
            'password_hash'    => password_hash($password, PASSWORD_BCRYPT),
            'phone'            => $phone,
            'address'          => $address,
            'zone'             => $zone,
            'id_photo_url'     => $idPhotoUrl,
            'id_verified_by_ai'=> $aiResult !== null ? json_encode($aiResult) : null,
            'id_ai_status'     => $idAiStatus,
            'status'           => 'pending',
            'role'             => 'resident',
        ]);

        AuditLog::record($userId, 'user.register', "New resident registration: {$email}");

        // Tell the back-office someone is waiting to be verified — but only
        // those who still want that alert (/admin/account → Preferences).
        (new NotificationService())->notifyBackOffice(
            'notify_registrations',
            'verification',
            'Bagong residenteng naghihintay ng beripikasyon',
            "Nagrehistro si {$fullName}. Suriin ang na-upload na ID sa Residents page.",
            $userId,
            'user'
        );

        // Log the AI's ID verdict so its accuracy can be scored once staff
        // verify or suspend this resident (Super Admin → AI Accuracy).
        if ($aiResult !== null) {
            AiPrediction::record($userId, $aiResult);
        }

        // Send email-verification link via the MailService template method.
        $token = self::emailVerificationToken($userId, (string) $email);
        (new MailService())->sendVerificationEmail(
            ['email' => (string) $email, 'full_name' => $fullName],
            $token
        );

        flash('success', 'Matagumpay na nairehistro! Suriin ang iyong email para ma-verify ang iyong account.');
        redirect('/login');
    }

    // ──────────────────────────────────────────────────────────
    //  EMAIL VERIFICATION
    // ──────────────────────────────────────────────────────────

    public function verifyEmail(): void
    {
        $token   = $_GET['token'] ?? '';
        $payload = json_decode((string) base64_decode($token), true);

        if (!\is_array($payload)
            || empty($payload['id'])
            || empty($payload['email'])
            || empty($payload['hash'])
        ) {
            flash('error', 'Invalid na verification link.');
            redirect('/login');
        }

        $secret   = env('JWT_SECRET', 'secret');
        $expected = hash_hmac('sha256', "{$payload['id']}:{$payload['email']}", $secret);

        if (!hash_equals($expected, $payload['hash'])) {
            flash('error', 'Invalid na verification token.');
            redirect('/login');
        }

        $user = User::find((int) $payload['id']);
        if (!$user || $user['email'] !== $payload['email']) {
            flash('error', 'Hindi nahanap ang user.');
            redirect('/login');
        }

        if ((int) $user['email_verified'] === 1) {
            flash('success', 'Ang iyong email ay nai-verify na. Mag-login na.');
            redirect('/login');
        }

        $stmt = db()->prepare('UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$payload['id']]);

        AuditLog::record((int) $payload['id'], 'user.email_verified', "Email verified: {$payload['email']}");

        flash('success', 'Email na-verify! Ang iyong account ay naghihintay na ng pag-apruba ng barangay admin.');
        redirect('/login');
    }

    /**
     * POST /resend-verification
     *
     * Re-sends the verification link to the signed-in user's own address. The
     * resident dashboard prompts for this, because an unverified resident is
     * invisible to the email notification system.
     *
     * Acts only on $_SESSION['user_id'] — no address is accepted from the
     * request, so this can never be used to mail an arbitrary third party.
     */
    public function resendVerification(): void
    {
        check_csrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $user   = User::find($userId);

        if (!$user) {
            redirect('/logout');
        }

        if ((int) $user['email_verified'] === 1) {
            flash('success', t('resident_home.verify_already'));
            redirect('/');
        }

        // Throttle: sending mail is an outbound side effect, and a button a
        // frustrated resident will tap repeatedly. One send per 2 minutes.
        $last = (int) ($_SESSION['verify_resent_at'] ?? 0);
        if ($last > 0 && time() - $last < 120) {
            flash('error', t('resident_home.verify_wait'));
            redirect('/');
        }
        $_SESSION['verify_resent_at'] = time();

        $sent = false;
        try {
            $token = self::emailVerificationToken($userId, (string) $user['email']);
            $sent  = (new MailService())->sendVerificationEmail($user, $token);
        } catch (\Throwable $e) {
            error_log('[AuthController::resendVerification] ' . $e->getMessage());
        }

        AuditLog::record($userId, 'user.verification_resent', 'Verification email re-sent to ' . $user['email']);

        flash(
            $sent ? 'success' : 'error',
            $sent
                ? t('resident_home.verify_sent', ['email' => (string) $user['email']])
                : t('resident_home.verify_failed')
        );
        redirect('/');
    }

    // ──────────────────────────────────────────────────────────
    //  LOGOUT
    // ──────────────────────────────────────────────────────────

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            UserSession::end(session_id());
            AuditLog::record((int) $userId, 'auth.logout', 'User logout');
        }

        session_unset();
        session_destroy();
        redirect('/login');
    }

    // ──────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────

    /**
     * Returns true when the email has hit the failed-attempt ceiling
     * within the cooldown window. Uses the dedicated login_attempts table.
     */
    private function isLoginRateLimited(string $email): bool
    {
        try {
            // Counting is delegated to the model so successes and
            // "already locked out" rows are excluded — see
            // LoginAttempt::countRecentFailures().
            return LoginAttempt::countRecentFailures($email, $this->loginCooldownMin())
                >= $this->loginMaxAttempts();
        } catch (\Throwable) {
            return false; // fail open — never block due to a DB error
        }
    }

    /**
     * Creates a signed, base64-encoded email verification token.
     *
     * Static and public so the account-settings page can re-send the very same
     * link when a user changes their email, rather than growing a parallel
     * confirmation mechanism — verifyEmail() above stays the only consumer.
     */
    public static function emailVerificationToken(int $userId, string $email): string
    {
        $secret  = env('JWT_SECRET', 'secret');
        $payload = [
            'id'    => $userId,
            'email' => $email,
            'hash'  => hash_hmac('sha256', "{$userId}:{$email}", $secret),
        ];
        return base64_encode((string) json_encode($payload));
    }

}