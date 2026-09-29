<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AiPrediction;
use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\EvacuationCenter;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\LoginAttempt;
use App\Models\Ordinance;
use App\Models\SafetyCheckin;
use App\Models\User;
use App\Models\UserSession;
use App\Models\Notification;
use App\Models\AuditLog;
use App\Services\MailService;
use App\Services\SemaphoreSmsService;

class ResidentController
{
    // ── Resident-facing ──────────────────────────────────────────────

    public function home(): void
    {
        // Guest → show public landing page
        if (empty($_SESSION['user_id'])) {
            view('public/landing');
            return;
        }

        // Admin / staff → redirect to admin dashboard
        $role = $_SESSION['role'] ?? 'resident';
        if (in_array($role, ['superadmin', 'admin', 'staff'], true)) {
            redirect('/admin');
            return;
        }

        // Unverified / pending → holding page
        if (($_SESSION['status'] ?? '') !== 'verified') {
            redirect('/pending');
            return;
        }

        // ── Verified resident → personal dashboard ───────────────────
        //
        // Every query below is scoped to $userId, which comes from the session
        // and never from the request. Each widget is one query; nothing here
        // loops into a per-row lookup, because this page loads on every visit.
        $userId = (int) $_SESSION['user_id'];
        $user   = User::find($userId);

        // "New since your last visit" needs a marker that holds still while the
        // resident is actually here. If we compared against last_seen_at after
        // updating it, refreshing the page would instantly zero the count. So
        // the marker is frozen in the session for the length of this visit and
        // the column is advanced to now.
        if (!isset($_SESSION['dash_since'])) {
            $_SESSION['dash_since'] = $user['last_seen_at'] ?? $user['created_at'] ?? null;
        }
        $newAnnouncements = self::newSinceCount($_SESSION['dash_since'] ?? null);

        // Personal counts — the headline tiles.
        $unreadNotifications  = Notification::unreadCount($userId);
        $recentNotifications  = Notification::unreadForUser($userId, 4);
        Feedback::ensureMessagesTable();
        $feedbackThreads      = Feedback::dashboardForUser($userId, 3);
        $openFeedbackCount    = Feedback::countForUser($userId);
        $unreadFeedbackCount  = Feedback::unreadThreadCountForUser($userId);

        // Emergencies get pinned above everything else on the page.
        //
        // Two separate things, deliberately not merged:
        //   - the advisory is a STANDING STATE set by staff ("Signal No. 2 is
        //     up") that stays until someone takes it down;
        //   - an urgent announcement is a POST, published once, that ages out
        //     of the 14-day window on its own.
        // A typhoon is the first kind; a one-off urgent notice is the second.
        $advisoryText  = trim((string) setting('hazard_advisory_text', ''));
        $advisoryLevel = (string) setting('hazard_advisory_level', 'warning');
        if (!in_array($advisoryLevel, AdminController::ADVISORY_LEVELS, true)) {
            $advisoryLevel = 'warning';
        }

        $urgentAnnouncements = Announcement::activeUrgent(14, 3);

        // Community content — kept, but demoted to context below the personal row.
        $latestAnnouncements = Announcement::getPublished(3);
        $upcomingEvents      = Event::upcomingFrom(6);
        $totalAnnouncements  = Announcement::countPublished();
        $upcomingEventsCount = Event::countUpcoming();
        $activeOrdinances    = (int) db()->query(
            "SELECT COUNT(*) FROM ordinances WHERE status = 'active'"
        )->fetchColumn();

        // Announcements and ordinances are barangay-wide by design — there is
        // no per-zone content in this system. The one number that genuinely
        // differs by zone is how many verified neighbours share it, so that is
        // the only zone-scoped figure shown, and it is labelled as such rather
        // than being passed off as a filtered version of the totals above.
        $zone          = trim((string) ($user['zone'] ?? ''));
        $zoneNeighbours = $zone !== '' ? User::countVerifiedInZone($zone, $userId) : null;

        // One already-generated ordinance summary, surfaced as a teaser. This
        // reads a CACHED summary only — it never triggers an AI call, so the
        // dashboard's cost and load time are unaffected. Null until something
        // has actually been summarised, and the card is then simply omitted.
        $featuredOrdinance = Ordinance::latestSummarised();

        // Age of the account, for the first-visit getting-started panel.
        $createdAt      = (string) ($user['created_at'] ?? '');
        $accountAgeDays = $createdAt !== ''
            ? (int) floor((time() - strtotime($createdAt)) / 86400)
            : 999;

        // Split "upcoming" into what needs attention now vs. what is merely
        // scheduled. An event today and an event in March are not the same
        // thing and must not look the same.
        $buckets     = self::bucketEventsByProximity($upcomingEvents);
        $todayEvents = $buckets['today'];
        $weekEvents  = $buckets['week'];
        $laterEvents = $buckets['later'];

        /*
         * The resident's document requests, for the dashboard card.
         *
         * Two separate things, because they answer two different questions:
         *
         *   $openDocRequest — the one still moving. This is what a resident
         *   opens the portal to check, so it goes on the dashboard rather than
         *   making them navigate to /documents just to read one word.
         *
         *   $readyDocCount — how many are waiting to be collected. That is the
         *   one a resident should act on today, so it is counted separately
         *   and shown louder.
         *
         * Never throws: a dashboard must render even on a database that has
         * not had migration 027 applied.
         */
        $openDocRequest = null;
        $readyDocCount  = 0;
        try {
            foreach (DocumentRequest::forUser($userId) as $docRow) {
                if (($docRow['status'] ?? '') === 'ready') {
                    $readyDocCount++;
                }
                if ($openDocRequest === null
                    && \in_array($docRow['status'] ?? '', ['pending', 'processing', 'ready'], true)) {
                    $openDocRequest = $docRow;
                }
            }
        } catch (\Throwable $e) {
            error_log('[ResidentController] document requests unavailable: ' . $e->getMessage());
        }

        /*
         * "Are you safe?" — and, on every other day, "where would I go?"
         *
         * This used to live only on the announcement's own page, which meant
         * a resident had to find the advisory before they could answer it.
         * The whole point of a check-in is that it costs one tap from
         * wherever you already are, so it belongs on the page people open.
         *
         * The card renders in BOTH states. It first shipped only while an
         * advisory was asking, which hid the entire feature until a storm —
         * so the first time anyone met it was the worst possible moment to
         * be learning a new screen, and on an ordinary day it could not be
         * found at all. Quiet state names their evacuation centre instead,
         * which is the question they actually have when nothing is wrong.
         *
         * $safetyPulse is counts only, for the resident's own purok. It
         * tells a neighbour how many people nearby have not answered yet,
         * which is the number that gets someone to knock on a door. It
         * deliberately carries no names, phones or addresses — that list
         * exists, and stays behind /admin/safety.
         *
         * Never throws: like the document card below it, this page must
         * still render on a database that predates migration 027.
         */
        $safetyAdvisory = null;
        $safetyPulse    = null;
        $safetyCentre   = null;
        try {
            $safetyAdvisory = SafetyCheckin::openForUser($userId);
            if ($safetyAdvisory !== null) {
                $safetyPulse = SafetyCheckin::purokPulse($zone !== '' ? $zone : null, $safetyAdvisory['id']);
            }

            // Their own purok's centre first — forPurok() already orders it
            // that way, so the first row is the one to name.
            $safetyCentre = EvacuationCenter::forPurok($zone !== '' ? $zone : null)[0] ?? null;
        } catch (\Throwable $e) {
            error_log('[ResidentController] safety check-in unavailable: ' . $e->getMessage());
        }

        // Record the visit last, so nothing above is affected by it.
        User::touchLastSeen($userId);

        view('resident/home', compact(
            'user',
            'advisoryText',
            'advisoryLevel',
            'zone',
            'zoneNeighbours',
            'featuredOrdinance',
            'accountAgeDays',
            'newAnnouncements',
            'unreadNotifications',
            'recentNotifications',
            'feedbackThreads',
            'openFeedbackCount',
            'unreadFeedbackCount',
            'urgentAnnouncements',
            'latestAnnouncements',
            'upcomingEvents',
            'todayEvents',
            'weekEvents',
            'laterEvents',
            'totalAnnouncements',
            'upcomingEventsCount',
            'activeOrdinances',
            'openDocRequest',
            'readyDocCount',
            'safetyAdvisory',
            'safetyPulse',
            'safetyCentre'
        ));
    }

    // ── Dashboard helpers (pure — no DB, no session, no clock of their own) ──
    //
    // These live outside home() so the boundary rules they encode can be
    // asserted directly (tests/Unit/ResidentDashboardTest.php). home() reads
    // like prose; the fiddly edge cases are pinned down here.

    /**
     * Group upcoming events into "today", "this week" and "later".
     *
     * Boundaries, stated once so they cannot drift:
     *   - today : the event's calendar day equals the reference day. An event
     *             at 00:00 today counts as today, not as "this week".
     *   - week  : after today, up to AND INCLUDING the 7th day ahead.
     *   - later : the 8th day ahead onwards.
     *   - past  : anything before the reference day. Callers feed this from
     *             Event::upcomingFrom(), which already excludes finished
     *             events, so this bucket is normally empty — but it exists so
     *             a stray past row is dropped deliberately instead of being
     *             silently swept into "this week", which is what a plain
     *             `<= weekEnd` comparison would do.
     *
     * Comparison is on calendar days (Y-m-d strings), not raw timestamps, so
     * "today" means the whole day rather than the next 24 hours.
     *
     * @param  list<array<string,mixed>> $events Rows with an 'event_date'
     * @param  int|null $now Reference timestamp; defaults to the current time
     * @return array{today: list<array<string,mixed>>, week: list<array<string,mixed>>, later: list<array<string,mixed>>, past: list<array<string,mixed>>}
     */
    public static function bucketEventsByProximity(array $events, ?int $now = null): array
    {
        $now        = $now ?? time();
        $todayKey   = date('Y-m-d', $now);
        $weekEndKey = date('Y-m-d', strtotime('+7 days', $now));

        $buckets = ['today' => [], 'week' => [], 'later' => [], 'past' => []];

        foreach ($events as $event) {
            $raw = (string) ($event['event_date'] ?? '');
            $ts  = $raw !== '' ? strtotime($raw) : false;
            if ($ts === false) {
                continue; // unparseable date — not something to show a resident
            }

            $dayKey = date('Y-m-d', $ts);

            if ($dayKey === $todayKey) {
                $buckets['today'][] = $event;
            } elseif ($dayKey < $todayKey) {
                $buckets['past'][] = $event;
            } elseif ($dayKey <= $weekEndKey) {
                $buckets['week'][] = $event;
            } else {
                $buckets['later'][] = $event;
            }
        }

        return $buckets;
    }

    /**
     * How many announcements are new since the resident's frozen visit marker.
     *
     * A null or blank marker means we have no trustworthy "last visit" to
     * compare against — a brand-new account, or a row predating the
     * last_seen_at column. Reporting 0 is the honest answer there; counting
     * every published announcement would tell the resident that the entire
     * archive is unread.
     */
    public static function newSinceCount(?string $since): int
    {
        $since = trim((string) $since);

        return $since === '' ? 0 : Announcement::countPublishedSince($since);
    }

    public function profile(): void
    {
        $user = User::find((int) $_SESSION['user_id']);
        view('resident/profile', ['user' => $user]);
    }

    public function updateProfile(): void
    {
        check_csrf();

        $userId = (int) $_SESSION['user_id'];
        $action = \trim($_POST['_action'] ?? 'update_profile');

        if ($action === 'change_password') {
            $this->handlePasswordChange($userId);
            return;
        }

        if ($action === 'upload_avatar') {
            $this->handleAvatarUpload($userId);
            return;
        }

        // Default: update basic profile fields
        $fullName = \trim($_POST['full_name'] ?? '');
        $phone    = \trim($_POST['phone']     ?? '');
        $address  = \trim($_POST['address']   ?? '');

        if (!$fullName) {
            flash('error', 'Kinakailangan ang pangalan.');
            redirect('/profile');
        }

        $stmt = db()->prepare(
            'UPDATE users SET full_name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$fullName, $phone, $address, $userId]);

        // Keep session name in sync
        $_SESSION['full_name'] = $fullName;

        AuditLog::record($userId, 'user.profile.update', 'Profile updated');
        flash('success', 'Na-update na ang iyong profile.');
        redirect('/profile');
    }

    private function handlePasswordChange(int $userId): void
    {
        $user    = User::find($userId);
        $current = $_POST['current_password'] ?? '';
        $new     = \trim($_POST['new_password']     ?? '');
        $confirm = \trim($_POST['confirm_password'] ?? '');

        if (!$user || !\password_verify($current, (string) $user['password_hash'])) {
            flash('error', 'Mali ang kasalukuyang password.');
            redirect('/profile');
        }

        if (\strlen($new) < 8) {
            flash('error', 'Ang bagong password ay dapat hindi bababa sa 8 karakter.');
            redirect('/profile');
        }

        if ($new !== $confirm) {
            flash('error', 'Hindi tumutugma ang bagong password at kumpirmasyon.');
            redirect('/profile');
        }

        $hash = \password_hash($new, PASSWORD_BCRYPT);
        db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$hash, $userId]);

        AuditLog::record($userId, 'user.password.change', 'Password changed');
        flash('success', 'Matagumpay na nabago ang iyong password.');
        redirect('/profile');
    }

    private function handleAvatarUpload(int $userId): void
    {
        if (empty($_FILES['avatar']['tmp_name'])) {
            flash('error', 'Walang na-upload na larawan.');
            redirect('/profile');
        }

        $file     = $_FILES['avatar'];
        $finfo    = \finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = \finfo_file($finfo, $file['tmp_name']);
        \finfo_close($finfo);

        if (!\in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            flash('error', 'Tanggap lamang ang JPG o PNG na larawan.');
            redirect('/profile');
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            flash('error', 'Ang larawan ay hindi dapat lumampas sa 2MB.');
            redirect('/profile');
        }

        $ext      = $mimeType === 'image/png' ? 'png' : 'jpg';
        $filename = \bin2hex(\random_bytes(12)) . '.' . $ext;
        $destDir  = \dirname(__DIR__, 2) . '/public/uploads/avatars';
        if (!\is_dir($destDir)) {
            \mkdir($destDir, 0775, true);
        }
        \move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename);

        $url = '/uploads/avatars/' . $filename;
        db()->prepare('UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$url, $userId]);

        flash('success', 'Na-update na ang iyong profile photo.');
        redirect('/profile');
    }

    // ── Admin: staff account creation ────────────────────────────────

    /**
     * GET /admin/staff/create
     * Show the create-staff form.
     */
    public function createStaff(): void
    {
        $errors   = $_SESSION['staff_errors'] ?? [];
        $oldInput = $_SESSION['staff_old']    ?? [];
        unset($_SESSION['staff_errors'], $_SESSION['staff_old']);

        $pendingCount = User::countByStatus('pending');
        $pageTitle    = t('staff_create.title');

        view('admin/staff/create', compact('errors', 'oldInput', 'pendingCount', 'pageTitle'));
    }

    /**
     * POST /admin/staff
     * Store a new staff or admin account (no ID upload, immediately verified).
     */
    public function storeStaff(): void
    {
        check_csrf();

        $fullName = trim($_POST['full_name'] ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $role     = $_POST['role']     ?? 'staff';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirmation'] ?? '';
        $phone    = trim($_POST['phone']   ?? '');
        $address  = trim($_POST['address'] ?? '');

        $errors = [];

        if (!$fullName) {
            $errors['full_name'] = t('flash.name_required');
        }
        if (!$email) {
            $errors['email'] = t('flash.email_required');
        } elseif (User::findByEmail((string) $email)) {
            $errors['email'] = t('flash.email_taken');
        }
        if (!in_array($role, ['staff', 'admin'], true)) {
            $errors['role'] = t('flash.pick_staff_or_admin');
        }
        if (strlen($password) < AuthController::PASSWORD_MIN_LENGTH) {
            $errors['password'] = t('flash.password_min', ['n' => AuthController::PASSWORD_MIN_LENGTH]);
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'] = t('flash.password_mismatch');
        }

        if (!empty($errors)) {
            $_SESSION['staff_errors'] = $errors;
            $_SESSION['staff_old']    = [
                'full_name' => $fullName,
                'email'     => $_POST['email'] ?? '',
                'role'      => $role,
                'phone'     => $phone,
                'address'   => $address,
            ];
            flash('error', t('flash.fix_marked_fields'));
            redirect('/admin/staff/create');
        }

        // Insert — immediately verified, email_verified = 1, no ID upload required
        $stmt = db()->prepare(
            'INSERT INTO users (full_name, email, password_hash, phone, address, zone, role, status, email_verified, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );
        $stmt->execute([
            $fullName,
            (string) $email,
            password_hash($password, PASSWORD_BCRYPT),
            $phone ?: null,
            $address ?: null,
            // No purok for a staff account: it is a role, not a household.
            // See barangay_subdivisions() in app/helpers.php.
            null,
            $role,
            'verified',
        ]);
        $newUserId = (int) db()->lastInsertId();

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'staff.created',
            "New {$role} account created: {$email} (ID #{$newUserId})"
        );

        flash('success', "Matagumpay na nalikha ang {$role} account para kay {$fullName}. Maaari na siyang mag-login.");
        redirect('/admin/residents');
    }

    // ── Admin: listing ────────────────────────────────────────────────

    /**
     * GET /admin/residents
     * Full resident table with status tabs, search, and bulk action.
     */
    public function adminIndex(): void
    {
        $status = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');

        if (!in_array($status, ['', 'pending', 'verified', 'suspended'], true)) {
            $status = '';
        }

        $residents = User::allResidents($status, $search);

        $counts = [
            'all'       => User::countByStatus(''),
            'pending'   => User::countByStatus('pending'),
            'verified'  => User::countByStatus('verified'),
            'suspended' => User::countByStatus('suspended'),
        ];

        $pendingCount = $counts['pending'];
        $pageTitle    = t('residents.title');

        view('admin/residents/index', compact(
            'residents', 'counts', 'status', 'search', 'pendingCount', 'pageTitle'
        ));
    }

    // ── Admin: detail / verify page ──────────────────────────────────

    /**
     * GET /admin/residents/{id}
     * Full resident profile page with approve / suspend actions.
     */
    public function show(array $params): void
    {
        $resident = User::find((int) $params['id']);
        if (!$resident || $resident['role'] !== 'resident') {
            flash('error', t('flash.resident_not_found'));
            redirect('/admin/residents');
        }

        $pendingCount = User::countByStatus('pending');
        $pageTitle    = t('residents_detail.info_title') . ': ' . $resident['full_name'];

        view('admin/residents/verify', compact('resident', 'pendingCount', 'pageTitle'));
    }

    // ── Admin: actions ────────────────────────────────────────────────

    /**
     * POST /admin/residents/{id}/verify
     * Verify a resident account, send in-app notification and welcome email.
     */
    public function verify(array $params): void
    {
        check_csrf();

        $userId   = (int) $params['id'];
        $adminId  = (int) $_SESSION['user_id'];
        $resident = User::find($userId);

        if (!$resident) {
            flash('error', t('flash.resident_not_found'));
            redirect('/admin/residents');
        }

        // 1. Update status
        User::verify($userId);

        // Staff approval is the ground truth for the AI's ID verdict.
        AiPrediction::resolve($userId, 'verified', $adminId);

        // 2. In-app notification
        Notification::create([
            'user_id'      => $userId,
            'title'        => 'Account Verified!',
            'message'      => 'Ang iyong account ay na-verify na! Maaari ka nang mag-login at i-access ang lahat ng features ng BarangGabay.',
            'type'         => 'verification',
            'related_id'   => null,
            'related_type' => null,
        ]);

        // 3. Welcome email via MailService template (non-blocking)
        try {
            (new MailService())->sendApprovalEmail($resident);
        } catch (\Throwable $e) {
            error_log('Approval email failed for user #' . $userId . ': ' . $e->getMessage());
        }

        // 4. Welcome SMS if the resident registered a phone number
        if (!empty($resident['phone'])) {
            try {
                $firstName = explode(' ', $resident['full_name'])[0];
                $message   = "[BarangGabay] Kumusta {$firstName}! Ang iyong account ay na-verify na. "
                           . "Maaari ka nang mag-login sa portal. - Brgy. Bayogo, Madrid";
                (new SemaphoreSmsService())->send(
                    $resident['phone'],
                    $message,
                    'verification',
                    $userId
                );
            } catch (\Throwable $e) {
                error_log('Verification SMS failed for user #' . $userId . ': ' . $e->getMessage());
            }
        }

        // 4. Audit log
        AuditLog::record(
            $adminId,
            'resident.verified',
            'Resident #' . $userId . ' (' . $resident['full_name'] . ') verified by admin #' . $adminId
        );

        // 5. Flash + redirect
        flash('success', t('flash.resident_verified', ['name' => $resident['full_name']]));
        redirect('/admin/residents');
    }

    /**
     * POST /admin/residents/{id}/suspend
     * Suspend a resident. Accepts optional POST[reason].
     */
    public function suspend(array $params): void
    {
        check_csrf();

        $userId   = (int) $params['id'];
        $adminId  = (int) $_SESSION['user_id'];
        $reason   = trim($_POST['reason'] ?? '');
        $resident = User::find($userId);

        if (!$resident) {
            flash('error', t('flash.resident_not_found'));
            redirect('/admin/residents');
        }

        User::suspend($userId);
        AiPrediction::resolve($userId, 'suspended', $adminId);

        $notifMessage = 'Ang iyong account ay na-suspend.'
            . ($reason !== '' ? ' Dahilan: ' . $reason : '')
            . ' Para sa mga katanungan, makipag-ugnayan sa Barangay Hall.';

        Notification::create([
            'user_id'      => $userId,
            'title'        => 'Account Suspended',
            'message'      => $notifMessage,
            'type'         => 'verification',
            'related_id'   => null,
            'related_type' => null,
        ]);

        AuditLog::record(
            $adminId,
            'resident.suspended',
            'Resident #' . $userId . ' (' . $resident['full_name'] . ') suspended. Reason: ' . ($reason ?: 'none given')
        );

        flash('success', t('flash.resident_suspended', ['name' => $resident['full_name']]));
        redirect('/admin/residents');
    }

    /**
     * GET /admin/staff
     *
     * The back-office account list. Until now staff could be created at
     * /admin/staff/create but never seen again: there was no index, and
     * /admin/residents deliberately lists residents only. That made a staff
     * account unrecoverable the moment its password was forgotten, since the
     * system has no forgot-password flow either.
     *
     * Read-only apart from the password reset, which is the whole point of
     * the page. Role and status are shown but not editable here — changing
     * someone's role is a bigger decision than this screen should carry.
     */
    public function staffIndex(): void
    {
        $pendingCount = User::countByStatus('pending');
        $pageTitle    = t('staff_list.title');

        view('admin/staff/index', [
            'accounts'     => User::backOfficeAccounts(),
            'pendingCount' => $pendingCount,
            'pageTitle'    => $pageTitle,
        ]);
    }

    /**
     * POST /admin/residents/{id}/reset-password
     *
     * Issue a new temporary password for an account whose own password is
     * lost. Without this the system has no recovery path at all — there is no
     * forgot-password flow — so a staff member who forgets their password is
     * locked out of their account permanently.
     *
     * Security notes, in the order they matter:
     *   - Route is role:admin,superadmin. Staff must never reset passwords:
     *     a staff account could otherwise reset an admin's password and take
     *     over the account.
     *   - An admin cannot reset a superadmin's password. That would be a
     *     straight privilege escalation — admin resets superadmin, logs in as
     *     superadmin. Only a superadmin can reset a superadmin.
     *   - Nobody resets their own password here; that path requires proving
     *     the current password (AccountController::updatePassword).
     *   - The new password is generated server-side and shown to the admin
     *     exactly once, then only its hash is stored.
     *   - Every session belonging to the target is revoked, so a stolen
     *     session cannot outlive the reset, and the login lockout is cleared
     *     so the user can actually use the new password immediately.
     */
    public function resetPassword(array $params): void
    {
        check_csrf();

        $userId    = (int) ($params['id'] ?? 0);
        $adminId   = (int) $_SESSION['user_id'];
        $adminRole = (string) ($_SESSION['role'] ?? '');
        $target    = User::find($userId);

        if (!$target) {
            flash('error', t('residents_detail.reset_pw_not_found'));
            redirect('/admin/residents');
        }

        // Residents are managed from their detail page, back-office accounts
        // from the staff list. Send the admin back where they came from.
        $back = ($target['role'] ?? 'resident') === 'resident'
            ? '/admin/residents/' . $userId
            : '/admin/staff';

        if ($userId === $adminId) {
            flash('error', t('residents_detail.reset_pw_not_self'));
            redirect($back);
        }

        if (($target['role'] ?? '') === 'superadmin' && $adminRole !== 'superadmin') {
            AuditLog::record(
                $adminId,
                'account.reset_password_denied',
                'Blocked ' . $adminRole . ' attempt to reset superadmin #' . $userId
            );
            flash('error', t('residents_detail.reset_pw_denied'));
            redirect($back);
        }

        // 12 bytes of randomness, base64'd, plus a fixed suffix so the result
        // always satisfies the minimum length and any future complexity rule.
        $temporary = rtrim(strtr(base64_encode(random_bytes(9)), '+/', 'Az'), '=') . '9!';

        try {
            User::setPasswordHashByAdmin($userId, password_hash($temporary, PASSWORD_BCRYPT));
            $revoked = UserSession::revokeAllForUser($userId);
            $cleared = LoginAttempt::clearFor((string) $target['email']);
        } catch (\Throwable $e) {
            error_log('[ResidentController::resetPassword] ' . $e->getMessage());
            flash('error', t('residents_detail.reset_pw_failed'));
            redirect($back);
        }

        AuditLog::record(
            $adminId,
            'account.password_reset',
            sprintf(
                'Reset password for #%d (%s, %s). Revoked %d session(s), cleared %d failed attempt(s).',
                $userId,
                $target['full_name'],
                $target['email'],
                $revoked,
                $cleared
            )
        );

        // Shown once, on the next page load only. Never logged, never mailed:
        // mail delivery is not configured, so putting it on screen for the
        // admin to hand over is the only route that actually works here.
        flash('reset_password', $temporary);
        flash('reset_password_for', (string) $target['full_name'] . ' — ' . (string) $target['email']);
        flash('success', t('residents_detail.reset_pw_done', ['name' => $target['full_name']]));
        redirect($back);
    }

    /**
     * POST /admin/residents/bulk
     * Bulk verify or suspend multiple residents at once.
     */
    public function bulk(array $params): void
    {
        check_csrf();

        $action  = $_POST['bulk_action'] ?? '';
        $rawIds  = $_POST['selected_ids'] ?? [];
        $ids     = array_filter(array_map('intval', is_array($rawIds) ? $rawIds : []));
        $adminId = (int) $_SESSION['user_id'];

        if (!$ids || !in_array($action, ['verify', 'suspend'], true)) {
            flash('error', 'Invalid action or no residents selected.');
            redirect('/admin/residents');
        }

        $count = 0;
        foreach ($ids as $userId) {
            $resident = User::find($userId);
            if (!$resident) {
                continue;
            }

            if ($action === 'verify') {
                User::verify($userId);
                AiPrediction::resolve($userId, 'verified', $adminId);
                Notification::create([
                    'user_id' => $userId,
                    'title'   => 'Account Verified!',
                    'message' => 'Ang iyong account ay na-verify na! Maaari ka nang mag-login at i-access ang lahat ng features ng BarangGabay.',
                    'type'    => 'verification',
                ]);
                AuditLog::record(
                    $adminId,
                    'resident.verified',
                    'Bulk verify: Resident #' . $userId . ' (' . $resident['full_name'] . ')'
                );
            } elseif ($action === 'suspend') {
                User::suspend($userId);
                AiPrediction::resolve($userId, 'suspended', $adminId);
                Notification::create([
                    'user_id' => $userId,
                    'title'   => 'Account Suspended',
                    'message' => 'Ang iyong account ay na-suspend. Para sa mga katanungan, makipag-ugnayan sa Barangay Hall.',
                    'type'    => 'verification',
                ]);
                AuditLog::record(
                    $adminId,
                    'resident.suspended',
                    'Bulk suspend: Resident #' . $userId . ' (' . $resident['full_name'] . ')'
                );
            }
            $count++;
        }

        $actionLabel = $action === 'verify' ? 'na-verify' : 'na-suspend';
        flash('success', $count . ' na residente ang ' . $actionLabel . '.');
        redirect('/admin/residents');
    }

}
