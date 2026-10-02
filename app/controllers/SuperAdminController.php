<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AiPrediction;
use App\Models\AuditLog;
use App\Models\ErrorLog;
use App\Models\LoginAttempt;
use App\Models\UserSession;
use App\Models\Setting;
use App\Services\BackupService;
use App\Services\ErrorRemedy;

/**
 * Super Admin system module.
 *
 * Every route here is gated on ['auth', 'role:superadmin']. RoleMiddleware
 * lets superadmin through any check and blocks every other role, so admins
 * and staff cannot reach these pages even by typing the URL.
 *
 * Feature 1 of the module: System Error Logs.
 */
class SuperAdminController
{
    private const PER_PAGE = 25;

    // ── Pages ─────────────────────────────────────────────────────────────

    /**
     * GET /superadmin/errors
     * Searchable, filterable error log viewer.
     */
    public function errorLogs(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'severity'   => trim($_GET['severity'] ?? ''),
            'from'       => trim($_GET['from']     ?? ''),
            'to'         => trim($_GET['to']       ?? ''),
            'q'          => trim($_GET['q']        ?? ''),
            'unresolved' => !empty($_GET['unresolved']),
        ];

        try {
            $result = ErrorLog::paginate($page, self::PER_PAGE, $filters);
            $stats  = ErrorLog::stats();
            $ready  = true;
        } catch (\Throwable $e) {
            // Almost always "table doesn't exist" — tell the user to migrate
            // rather than showing a 500 on the page meant to diagnose 500s.
            error_log('[SuperAdminController::errorLogs] ' . $e->getMessage());
            $result = ['items' => [], 'total' => 0];
            $stats  = ['total' => 0, 'today' => 0, 'week' => 0, 'unresolved' => 0, 'critical' => 0];
            $ready  = false;
        }

        // A row is only expanded on demand; the list query omits stack traces.
        $detail = null;
        if (!empty($_GET['view']) && $ready) {
            $detail = ErrorLog::find((int) $_GET['view']);
        }

        view('admin/superadmin/errors', [
            'logs'       => $result['items'],
            'total'      => $result['total'],
            'page'       => $page,
            'perPage'    => self::PER_PAGE,
            'stats'      => $stats,
            'filters'    => $filters,
            'severities' => ErrorLog::SEVERITIES,
            'detail'     => $detail,
            'ready'      => $ready,
        ]);
    }

    /**
     * GET /superadmin/sessions
     * Active sessions, login history and a system health snapshot.
     */
    public function sessions(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'email'  => trim($_GET['email']  ?? ''),
            'result' => trim($_GET['result'] ?? ''),
            'from'   => trim($_GET['from']   ?? ''),
            'to'     => trim($_GET['to']     ?? ''),
        ];

        // Thresholds come from Settings, so the panel always reflects the
        // limits actually being enforced at sign-in.
        $maxAttempts   = max(1, (int) setting('login_max_attempts', 5));
        $lockoutMin    = max(1, (int) setting('login_lockout_min', 15));
        $maxAttemptsIp = max(1, (int) setting('login_max_attempts_ip', 20));

        try {
            $active       = UserSession::active();
            $sessionStats = UserSession::stats();
            $history      = LoginAttempt::paginate($page, self::PER_PAGE, $filters);
            $loginStats   = LoginAttempt::stats();
            $suspicious   = LoginAttempt::suspicious();
            $lockedUsers  = LoginAttempt::lockedAccounts($maxAttempts, $lockoutMin);
            $lockedIps    = LoginAttempt::lockedIps($maxAttemptsIp, $lockoutMin);
            $ready        = true;
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::sessions] ' . $e->getMessage());
            $active       = [];
            $sessionStats = ['active' => 0, 'online' => 0, 'today' => 0];
            $history      = ['items' => [], 'total' => 0];
            $loginStats   = ['failed_24h' => 0, 'success_24h' => 0, 'distinct_ips_24h' => 0];
            $suspicious   = [];
            $lockedUsers  = [];
            $lockedIps    = [];
            $ready        = false;
        }

        view('admin/superadmin/sessions', [
            'active'        => $active,
            'sessionStats'  => $sessionStats,
            'history'       => $history['items'],
            'historyTotal'  => $history['total'],
            'loginStats'    => $loginStats,
            'suspicious'    => $suspicious,
            'lockedUsers'   => $lockedUsers,
            'lockedIps'     => $lockedIps,
            'maxAttempts'   => $maxAttempts,
            'maxAttemptsIp' => $maxAttemptsIp,
            'lockoutMin'    => $lockoutMin,
            'health'        => $this->systemHealth(),
            'page'          => $page,
            'perPage'       => self::PER_PAGE,
            'filters'       => $filters,
            'idleMinutes'   => UserSession::IDLE_MINUTES,
            'currentHash'   => hash('sha256', session_id()),
            'ready'         => $ready,
        ]);
    }

    /**
     * GET /superadmin/backups
     * Backup history, disk usage and the on-demand trigger.
     */
    public function backups(): void
    {
        $service = new BackupService();

        view('admin/superadmin/backups', [
            'backups'     => $service->all(),
            'lastBackup'  => $service->lastBackupAt(),
            'overdue'     => $service->isOverdue(),
            'totalSize'   => $service->totalSize(),
            'available'   => $service->isAvailable(),
            'directory'   => $service->directory(),
            'keepLatest'  => BackupService::KEEP_LATEST,
            'staleHours'  => BackupService::STALE_AFTER_HOURS,
            'scheduled'   => true,
            'driver'      => $service->getDriver(),
            'binaryPath'  => $service->isAvailable() ? $service->getBinaryPath() : null,
        ]);
    }

    /**
     * GET /superadmin/settings
     * Branding, location, date/time and maintenance mode.
     */
    public function settings(): void
    {
        // Whether the signed-in super admin has actually enrolled — used to
        // warn before they require 2FA and lock themselves into setup.
        $selfEnrolled = false;
        try {
            $selfEnrolled = (new \App\Services\TwoFactorService())->isEnabled((int) $_SESSION['user_id']);
        } catch (\Throwable $e) {
            // Leave as false; the view only uses it for a warning.
        }

        view('admin/superadmin/settings', [
            'settings'      => Setting::all(),
            'logoUrl'       => system_logo_url(),
            'timezones'     => \DateTimeZone::listIdentifiers(),
            'lastUpdated'   => Setting::lastUpdatedAt(),
            'twofaRoles'    => \App\Services\TwoFactorService::requiredRoles(),
            'selfEnrolled'  => $selfEnrolled,
        ]);
    }

    /**
     * GET /superadmin/ai-accuracy
     * How often the AI's ID verdicts matched what staff actually decided.
     */
    public function aiAccuracy(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $outcome = trim($_GET['outcome'] ?? '');

        $result = AiPrediction::paginate($page, self::PER_PAGE, ['outcome' => $outcome]);

        view('admin/superadmin/ai-accuracy', [
            'stats'        => AiPrediction::stats(),
            'byConfidence' => AiPrediction::byConfidence(),
            'trend'        => AiPrediction::trend(6),
            'predictions'  => $result['items'],
            'total'        => $result['total'],
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'outcome'      => $outcome,
        ]);
    }

    // ── Actions ───────────────────────────────────────────────────────────

    /** POST /superadmin/settings — save the settings form. */
    public function updateSettings(): void
    {
        check_csrf();

        $userId = (int) $_SESSION['user_id'];

        // Only these keys can be written from the form — anything else posted
        // is ignored rather than blindly persisted.
        $text = [
            'system_name', 'system_tagline', 'location_name', 'location_full',
            'maintenance_message', 'date_format', 'time_format',
            'barangay_captain_name', 'barangay_secretary_name',
        ];

        $values = [];
        foreach ($text as $key) {
            if (array_key_exists($key, $_POST)) {
                $values[$key] = trim((string) $_POST[$key]);
            }
        }

        // Document Services & Delivery Settings
        $values['doc_delivery_enabled'] = !empty($_POST['doc_delivery_enabled']) ? 1 : 0;
        $values['doc_pickup_enabled']   = !empty($_POST['doc_pickup_enabled']) ? 1 : 0;
        $values['doc_digital_enabled']  = !empty($_POST['doc_digital_enabled']) ? 1 : 0;
        if (isset($_POST['doc_delivery_fee'])) {
            $values['doc_delivery_fee'] = max(0.0, (float) $_POST['doc_delivery_fee']);
        }

        if (($values['system_name'] ?? 'x') === '') {
            flash('error', t('superadmin.set_name_required'));
            redirect('/superadmin/settings');
        }

        // Timezone must be a real identifier, or date_default_timezone_set()
        // would warn on every request from here on.
        $timezone = trim((string) ($_POST['timezone'] ?? ''));
        if ($timezone !== '') {
            if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                flash('error', t('superadmin.set_bad_timezone'));
                redirect('/superadmin/settings');
            }
            $values['timezone'] = $timezone;
        }

        $values['maintenance_mode'] = !empty($_POST['maintenance_mode']);

        // Free machine translation for the English version of posts. It sends
        // the post's text to a third-party service, which is acceptable for
        // public notices but is the barangay's decision to make, so it is a
        // switch rather than something hardcoded on.
        $values['free_translation_enabled'] = !empty($_POST['free_translation_enabled']) ? 1 : 0;

        // Two-factor policy. Only these roles may be required — residents are
        // excluded on purpose: they sign in from shared phones and a lost
        // authenticator would flood staff with lockout requests.
        $values['twofa_enabled'] = !empty($_POST['twofa_enabled']);

        $submittedRoles = $_POST['twofa_required_roles'] ?? [];
        $allowedRoles   = ['superadmin', 'admin', 'staff'];
        $requiredRoles  = is_array($submittedRoles)
            ? array_values(array_intersect($allowedRoles, $submittedRoles))
            : [];
        $values['twofa_required_roles'] = implode(',', $requiredRoles);

        $maxAttempts = (int) ($_POST['login_max_attempts'] ?? 0);
        $lockoutMin  = (int) ($_POST['login_lockout_min']  ?? 0);
        if ($maxAttempts >= 1 && $maxAttempts <= 100) {
            $values['login_max_attempts'] = $maxAttempts;
        }
        if ($lockoutMin >= 1 && $lockoutMin <= 1440) {
            $values['login_lockout_min'] = $lockoutMin;
        }

        $wasMaintenance = (bool) Setting::get('maintenance_mode', false);

        try {
            Setting::setMany($values, $userId);
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::updateSettings] ' . $e->getMessage());
            flash('error', t('superadmin.set_failed'));
            redirect('/superadmin/settings');
        }

        AuditLog::record($userId, 'settings.updated', 'Updated: ' . implode(', ', array_keys($values)));

        // Maintenance mode is worth its own audit line — it takes the whole
        // site down for residents.
        if ($wasMaintenance !== $values['maintenance_mode']) {
            AuditLog::record(
                $userId,
                $values['maintenance_mode'] ? 'maintenance.enabled' : 'maintenance.disabled',
                $values['maintenance_mode'] ? 'Maintenance mode turned ON' : 'Maintenance mode turned OFF'
            );
        }

        flash('success', t('superadmin.set_saved'));
        redirect('/superadmin/settings');
    }

    /** POST /superadmin/settings/logo — replace the system logo. */
    public function uploadLogo(): void
    {
        check_csrf();

        $file = $_FILES['logo'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', t('superadmin.set_logo_none'));
            redirect('/superadmin/settings');
        }
        if ((int) $file['size'] > 2 * 1024 * 1024) {
            flash('error', t('superadmin.set_logo_too_big'));
            redirect('/superadmin/settings');
        }

        // Trust the sniffed MIME type, never the client-supplied name.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);

        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
        if (!isset($allowed[$mime])) {
            flash('error', t('superadmin.set_logo_bad_type'));
            redirect('/superadmin/settings');
        }

        // An SVG can carry script, and this file is rendered on every page for
        // every visitor — refuse rather than try to sanitise it.
        if ($mime === 'image/svg+xml') {
            flash('error', t('superadmin.set_logo_no_svg'));
            redirect('/superadmin/settings');
        }

        $dir = \dirname(__DIR__, 2) . '/public/uploads/branding';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            flash('error', t('superadmin.set_logo_dir'));
            redirect('/superadmin/settings');
        }

        // Randomised name so the browser cannot serve a cached old logo.
        $filename = 'logo_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        $target   = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            flash('error', t('superadmin.set_logo_failed'));
            redirect('/superadmin/settings');
        }
        @chmod($target, 0644);

        // Remove the previous upload so the folder does not accumulate logos.
        $previous = trim((string) Setting::get('system_logo', ''));
        if ($previous !== '' && str_starts_with($previous, 'uploads/branding/')) {
            $previousPath = \dirname(__DIR__, 2) . '/public/' . $previous;
            if (is_file($previousPath)) {
                @unlink($previousPath);
            }
        }

        Setting::set('system_logo', 'uploads/branding/' . $filename, (int) $_SESSION['user_id']);
        AuditLog::record((int) $_SESSION['user_id'], 'settings.logo_updated', 'Uploaded ' . $filename);

        flash('success', t('superadmin.set_logo_saved'));
        redirect('/superadmin/settings');
    }

    /** POST /superadmin/settings/logo/reset — go back to the bundled logo. */
    public function resetLogo(): void
    {
        check_csrf();

        $current = trim((string) Setting::get('system_logo', ''));
        if ($current !== '' && str_starts_with($current, 'uploads/branding/')) {
            $path = \dirname(__DIR__, 2) . '/public/' . $current;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        Setting::set('system_logo', '', (int) $_SESSION['user_id']);
        AuditLog::record((int) $_SESSION['user_id'], 'settings.logo_reset', 'Reverted to the default logo');

        flash('success', t('superadmin.set_logo_reset'));
        redirect('/superadmin/settings');
    }

    /** POST /superadmin/backups — run mysqldump now. */
    public function createBackup(): void
    {
        check_csrf();

        // A dump can outlive the default limit on a large database.
        @set_time_limit(300);

        try {
            $result = (new BackupService())->create();
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::createBackup] ' . $e->getMessage());
            flash('error', t('superadmin.bk_failed', ['error' => $e->getMessage()]));
            redirect('/superadmin/backups');
        }

        $pruned = (new BackupService())->prune();

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'backup.created',
            'Manual backup: ' . $result['filename'] . ' (' . $result['size'] . ' bytes)'
                . ($pruned > 0 ? ', pruned ' . $pruned : '')
        );

        flash('success', t('superadmin.bk_created', [
            'file' => $result['filename'],
            'size' => number_format($result['size'] / 1024, 1),
        ]));
        redirect('/superadmin/backups');
    }

    /**
     * GET /superadmin/backups/download?file=…
     * Streams a backup. The filename is validated by BackupService::pathFor(),
     * which rejects anything not matching the generated-backup pattern, so a
     * crafted ?file= cannot escape the backup folder.
     */
    public function downloadBackup(): void
    {
        $service = new BackupService();
        $path    = $service->pathFor((string) ($_GET['file'] ?? ''));

        if ($path === null || !is_file($path)) {
            http_response_code(404);
            flash('error', t('superadmin.bk_not_found'));
            redirect('/superadmin/backups');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'backup.downloaded', 'Downloaded ' . basename($path));

        // Clear any buffering so the gzip stream is not corrupted by stray output.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** POST /superadmin/backups/delete — remove one backup file. */
    public function deleteBackup(): void
    {
        check_csrf();

        $filename = (string) ($_POST['file'] ?? '');

        if (!(new BackupService())->delete($filename)) {
            flash('error', t('superadmin.bk_not_found'));
            redirect('/superadmin/backups');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'backup.deleted', 'Deleted ' . basename($filename));
        flash('success', t('superadmin.bk_deleted'));
        redirect('/superadmin/backups');
    }

    /** POST /superadmin/sessions/revoke — force-end a session or all of a user's. */
    public function revokeSession(): void
    {
        check_csrf();

        $id     = (int) ($_POST['id']      ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);

        try {
            if ($userId > 0) {
                // Guard: never let a super admin lock themselves out mid-action.
                if ($userId === (int) $_SESSION['user_id']) {
                    flash('error', t('superadmin.sess_cannot_revoke_self'));
                    redirect('/superadmin/sessions');
                }
                $count  = UserSession::revokeAllForUser($userId);
                $detail = 'Revoked ' . $count . ' session(s) for user #' . $userId;
            } elseif ($id > 0) {
                UserSession::revoke($id);
                $detail = 'Revoked session #' . $id;
            } else {
                flash('error', t('superadmin.err_missing_id'));
                redirect('/superadmin/sessions');
            }
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::revokeSession] ' . $e->getMessage());
            flash('error', t('superadmin.err_action_failed'));
            redirect('/superadmin/sessions');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'session.revoked', $detail);
        flash('success', t('superadmin.sess_revoked'));
        redirect('/superadmin/sessions');
    }

    /** POST /superadmin/sessions/unlock — clear failed attempts for an email. */
    public function unlockAccount(): void
    {
        check_csrf();

        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            flash('error', t('superadmin.err_missing_id'));
            redirect('/superadmin/sessions');
        }

        try {
            $cleared = LoginAttempt::clearFor($email);
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::unlockAccount] ' . $e->getMessage());
            flash('error', t('superadmin.err_action_failed'));
            redirect('/superadmin/sessions');
        }

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'account.unlocked',
            'Cleared ' . $cleared . ' failed attempts for ' . $email
        );
        flash('success', t('superadmin.sess_unlocked', ['email' => $email]));
        redirect('/superadmin/sessions');
    }

    /**
     * POST /superadmin/sessions/unlock-ip — lift a per-IP block.
     *
     * Clears the failure history for one address, which unblocks every
     * account behind it — the point of the IP limit is shared connections.
     */
    public function unlockIp(): void
    {
        check_csrf();

        $ip = trim($_POST['ip'] ?? '');
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            flash('error', t('superadmin.lock_bad_ip'));
            redirect('/superadmin/sessions');
        }

        try {
            $cleared = LoginAttempt::clearForIp($ip);
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::unlockIp] ' . $e->getMessage());
            flash('error', t('superadmin.err_action_failed'));
            redirect('/superadmin/sessions');
        }

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'ip.unlocked',
            'Cleared ' . $cleared . ' failed attempts from ' . $ip
        );
        flash('success', t('superadmin.lock_ip_unlocked', ['ip' => $ip]));
        redirect('/superadmin/sessions');
    }

    /** POST /superadmin/errors/resolve — mark one error handled. */
    public function resolveError(): void
    {
        check_csrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', t('superadmin.err_missing_id'));
            redirect('/superadmin/errors');
        }

        try {
            if (!empty($_POST['reopen'])) {
                ErrorLog::unresolve($id);
                $action = 'errorlog.reopened';
            } else {
                ErrorLog::resolve($id);
                $action = 'errorlog.resolved';
            }
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::resolveError] ' . $e->getMessage());
            flash('error', t('superadmin.err_action_failed'));
            redirect('/superadmin/errors');
        }

        AuditLog::record((int) $_SESSION['user_id'], $action, 'Error log #' . $id);
        flash('success', t('superadmin.err_updated'));
        redirect('/superadmin/errors');
    }

    /**
     * POST /superadmin/errors/fix — diagnose one error and repair it if it is
     * one of the few kinds that can be repaired safely.
     *
     * The important rule lives in the response, not the repair: an error is
     * closed here ONLY when ErrorRemedy re-checked the system afterwards and
     * found the condition gone. A repair that ran but could not be confirmed
     * leaves the row open and says why. Closing an error because a button was
     * pressed is how a live bug disappears from the list nobody then re-reads.
     *
     * See ErrorRemedy for what "safe" means — replaying migrations, recreating
     * an upload folder. Nothing here edits code or invents schema changes.
     */
    public function fixError(): void
    {
        check_csrf();

        $id  = (int) ($_POST['id'] ?? 0);
        $log = $id > 0 ? ErrorLog::find($id) : null;

        if ($log === null) {
            flash('error', t('superadmin.err_missing_id'));
            redirect('/superadmin/errors');
        }

        $result = (new ErrorRemedy())->apply($log);

        // Only a verified repair may close the error.
        if ($result['verified']) {
            try {
                ErrorLog::resolve($id);
            } catch (\Throwable $e) {
                error_log('[SuperAdminController::fixError] ' . $e->getMessage());
            }
        }

        AuditLog::record(
            (int) $_SESSION['user_id'],
            'errorlog.fix_attempted',
            \sprintf(
                'Error log #%d (%s): %s%s',
                $id,
                $result['code'],
                $result['attempted'] ? 'remedy ran' : 'no safe remedy',
                $result['verified'] ? ', verified fixed' : ', not verified'
            )
        );

        // The detail lines are where the useful part is when nothing could be
        // repaired, so they ride along in the message rather than being
        // dropped into a flash key nothing renders.
        $message = $result['summary'];
        if ($result['detail'] !== []) {
            $message .= ' — ' . implode(' ', $result['detail']);
        }

        flash($result['verified'] ? 'success' : 'warning', $message);

        // Back to the expanded row, so the diagnosis is on screen beside the
        // outcome rather than a click away.
        redirect('/superadmin/errors?view=' . $id . '#detail');
    }

    /** POST /superadmin/errors/purge — delete old rows, or all of them. */
    public function purgeErrors(): void
    {
        check_csrf();

        $days = (int) ($_POST['days'] ?? 30);

        try {
            if ($days === 0) {
                $removed = ErrorLog::purgeAll();
                $detail  = 'Cleared every error log row';
            } else {
                $removed = ErrorLog::purgeOlderThan($days);
                $detail  = 'Cleared error logs older than ' . $days . ' days';
            }
        } catch (\Throwable $e) {
            error_log('[SuperAdminController::purgeErrors] ' . $e->getMessage());
            flash('error', t('superadmin.err_action_failed'));
            redirect('/superadmin/errors');
        }

        AuditLog::record((int) $_SESSION['user_id'], 'errorlog.purged', $detail . ' (' . $removed . ' rows)');
        flash('success', t('superadmin.err_purged', ['count' => $removed]));
        redirect('/superadmin/errors');
    }

    // ── Internal helpers ──────────────────────────────────────────────────

    /**
     * Whether the nightly backup task is registered with Windows Task
     * Scheduler. Used only to tell the super admin whether scheduling is
     * actually set up; null means "could not tell" (non-Windows, or schtasks
     * unavailable), which the view renders as unknown rather than as "no".
     */
    private function scheduledTaskExists(): ?bool
    {
        if (stripos(PHP_OS_FAMILY, 'win') !== 0) {
            return null;
        }

        try {
            $output = @shell_exec('schtasks /query /tn "BarangGabay Nightly Backup" 2>&1');
            if (!is_string($output) || trim($output) === '') {
                return null;
            }
            return stripos($output, 'BarangGabay Nightly Backup') !== false
                && stripos($output, 'ERROR') === false;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Read-only system health snapshot for the sessions page.
     *
     * Every probe is individually guarded — one unavailable metric (a disk
     * call blocked by the host, say) must not take the whole page down.
     *
     * @return array<string,string|int|null>
     */
    private function systemHealth(): array
    {
        $health = [
            'php_version'  => PHP_VERSION,
            'db_version'   => null,
            'db_size_mb'   => null,
            'disk_free_gb' => null,
            'log_size_mb'  => null,
            'errors_24h'   => null,
            'timezone'     => date_default_timezone_get(),
            'server_time'  => date('Y-m-d H:i:s'),
        ];

        try {
            $health['db_version'] = (string) db()->query('SELECT VERSION()')->fetchColumn();

            $stmt = db()->prepare(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1)
                 FROM information_schema.TABLES WHERE table_schema = ?'
            );
            $stmt->execute([env('DB_NAME', 'baranggabay')]);
            $health['db_size_mb'] = (float) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            // Leave as null; the view renders an em dash.
        }

        try {
            $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
            $dateCond = $isPgsql ? "created_at > NOW() - INTERVAL '24 hours'" : "created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $health['errors_24h'] = (int) db()->query(
                "SELECT COUNT(*) FROM error_logs WHERE {$dateCond}"
            )->fetchColumn();
        } catch (\Throwable $e) {
            // error_logs may not be migrated yet.
        }

        try {
            $free = @disk_free_space(\dirname(__DIR__, 2));
            if ($free !== false) {
                $health['disk_free_gb'] = round($free / 1024 / 1024 / 1024, 1);
            }
        } catch (\Throwable $e) {
            // Some hosts disable disk_free_space().
        }

        try {
            $log = \dirname(__DIR__, 2) . '/storage/logs/error.log';
            if (is_file($log)) {
                $health['log_size_mb'] = round((int) filesize($log) / 1024 / 1024, 2);
            }
        } catch (\Throwable $e) {
            // Ignore.
        }

        return $health;
    }

    /**
     * POST|GET /admin/system/seed-demo
     * Seed or re-seed comprehensive demo/sample data.
     */
    public function seedDemo(): void
    {
        require_once dirname(__DIR__, 2) . '/tools/seed_comprehensive_demo.php';
        $driver = db()->getAttribute(\PDO::ATTR_DRIVER_NAME) ?: 'mysql';
        $results = seed_comprehensive_demo(db(), $driver);
        \App\Models\Setting::set('demo_sample_data_v1', '1');

        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json' || (isset($_GET['format']) && $_GET['format'] === 'json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'seeded' => $results]);
            exit;
        }

        flash('success', 'Matagumpay na naitanim ang sample data sa sistema (Announcements, Events, Ordinances, Residents, Evacuation Centers, Document Requests, Feedback, Notifications)!');
        redirect('/admin');
    }
}

