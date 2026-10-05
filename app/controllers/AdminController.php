<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\Ordinance;
use App\Models\Setting;
use App\Models\TranslationLog;
use App\Models\User;
use PDO;

class AdminController
{
    /**
     * Severity levels a hazard advisory can carry. Anything else is coerced to
     * 'warning' — the advisory must still render if a bad value reaches the
     * database, because the one time it matters is a typhoon.
     */
    public const ADVISORY_LEVELS = ['info', 'warning', 'danger'];

    /** Advisories are a headline, not an article. */
    private const ADVISORY_MAX = 300;

    /**
     * GET /admin
     * Dashboard: stat cards, pending residents list, recent audit activity.
     * Chart data is fetched via AJAX from separate API endpoints.
     */
    public function dashboard(): void
    {
        $pdo = db();

        $verifiedCount  = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE status = 'verified' AND role = 'resident'"
        )->fetchColumn();

        $pendingCount   = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE status = 'pending'"
        )->fetchColumn();

        // "Published" means residents can read it right now, which is not the
        // same as status = 'published': a scheduled post carries that status
        // while it waits for its go-live moment. Counting those here would make
        // the card claim an audience the post does not have yet — the same
        // distinction the announcements list already draws, and the card now
        // links to that list filtered the same way, so the two agree.
        $publishedCount = (int) $pdo->query(
            'SELECT COUNT(*) FROM announcements WHERE ' . Announcement::visibleSql('')
        )->fetchColumn();

        $upcomingCount  = (int) $pdo->query(
            "SELECT COUNT(*) FROM events WHERE status = 'upcoming'"
        )->fetchColumn();

        $pendingResidents = User::allPending();
        $recentActivity   = $this->getRecentActivity($pdo);

        /*
         * The worklist: open items the person looking at this can personally
         * clear, each one a link to the page that clears it.
         *
         * The stat cards above are a scoreboard — they say how many things
         * exist. None of them say what to do next, so a staff member starting
         * their day had to go hunting through Feedback, Residents and
         * Translations to find their own work. Two of these counts already
         * existed and only fed sidebar badges.
         *
         * Every count is wrapped: a dashboard must still render if one
         * feature's table is missing on an older install.
         */
        $viewerRole = (string) ($_SESSION['role'] ?? '');

        $attention = [];

        /**
         * @param list<string> $roles Who should be PROMPTED about this work.
         */
        $add = static function (
            string $key,
            int    $count,
            string $url,
            string $icon,
            array  $roles
        ) use (&$attention, $viewerRole): void {
            if ($count > 0 && \in_array($viewerRole, $roles, true)) {
                $attention[] = ['key' => $key, 'count' => $count, 'url' => $url, 'icon' => $icon];
            }
        };

        /*
         * Who gets prompted about what.
         *
         * This controls VISIBILITY ON THIS CARD only — it is not a permission
         * check and must never be mistaken for one. The routes decide who may
         * act; RoleMiddleware enforces that. Staff are in fact allowed to
         * verify residents (/admin/residents/{id}/verify is role:admin,staff),
         * so leaving 'verify' off their list means staff simply are not
         * prompted about the queue, not that they are blocked from it.
         *
         * That is a barangay policy decision rather than a technical one, so
         * it lives here as one readable list: add 'staff' to a row to start
         * prompting them, remove it to stop.
         */
        $everyone   = ['staff', 'admin', 'superadmin'];
        $adminsOnly = ['admin', 'superadmin'];

        $rows = [
            // key,            count callback,                          url,                     icon,                    who is prompted
            ['verify',       static fn (): int => $pendingCount,        '/admin/residents',    'bi-person-check',       $adminsOnly],
            ['feedback',     static fn (): int => Feedback::unreadCountAdmin(),                '/admin/feedback',     'bi-chat-dots',          $everyone],
            ['translations', static fn (): int => Announcement::countAwaitingTranslationReview(), '/admin/translations', 'bi-translate',     $everyone],
            ['drafts',       static fn (): int => Announcement::countDrafts(),                  '/admin/announcements', 'bi-file-earmark-text', $everyone],
            ['no_manobo',    static fn (): int => Announcement::countWithoutManobo()
                                                + Event::countWithoutManobo()
                                                + Ordinance::countWithoutManobo(),             '/admin/announcements', 'bi-translate',      $everyone],
            ['documents',    static fn (): int => \App\Models\DocumentRequest::countOpen(),  '/admin/documents',    'bi-file-earmark-check', $everyone],
            ['payments',     static fn (): int => (int) db()->query("SELECT COUNT(*) FROM document_payments WHERE payment_status = 'PAYMENT_PROOF_SUBMITTED'")->fetchColumn(),
                                                                                                '/admin/payments',     'bi-wallet2',            $everyone],
            ['voice',        static fn (): int => (int) db()->query("SELECT COUNT(*) FROM voice_samples WHERE LOWER(status) = 'pending'")->fetchColumn(),
                                                                                                '/admin/voice-training?status=pending#samples', 'bi-mic', $adminsOnly],
        ];

        foreach ($rows as [$key, $countFn, $url, $icon, $roles]) {
            try {
                // Each count is isolated: one feature's table missing on an
                // older install must not take the whole dashboard down.
                $add($key, $countFn(), $url, $icon, $roles);
            } catch (\Throwable $e) {
                error_log('[AdminController] worklist count "' . $key . '" failed: ' . $e->getMessage());
            }
        }

        /*
         * What is on this week. Staff are the people who have to be ready for
         * it, and "upcoming: 3" on a stat card does not say whether that means
         * tonight or next March. Reuses the same pure bucketing function the
         * resident dashboard uses, so the two cannot disagree about what
         * "today" means.
         */
        $weekBuckets = ResidentController::bucketEventsByProximity(Event::upcomingFrom(8));
        $todayEvents = $weekBuckets['today'];
        $weekEvents  = $weekBuckets['week'];

        $pageTitle = t('dashboard.title');

        // Current hazard advisory, so the form shows what is live right now.
        $advisoryText  = (string) setting('hazard_advisory_text', '');
        $advisoryLevel = (string) setting('hazard_advisory_level', 'warning');

        // Language dataset coverage for the dashboard card: real figures —
        // share of the words residents meet in visible posts that have an
        // approved recording, per language, plus Manobo dictionary coverage.
        $datasetCoverage = [];
        $manoboDictionary = ['total_dictionary_words' => 0, 'words_with_audio' => 0, 'coverage_percentage' => 0.0];
        $activeStaffCount = 0;
        try {
            foreach (\App\Services\VoiceUsageIndex::reports() as $lang => $report) {
                $datasetCoverage[$lang] = $report;
            }
            $manoboDictionary = \App\Models\VoiceSample::getManoboCoverageStats();
            $activeStaffCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('staff','admin') AND status = 'verified'")->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[AdminController::dashboard] dataset coverage: ' . $e->getMessage());
        }

        view('admin/dashboard', compact(
            'datasetCoverage',
            'manoboDictionary',
            'activeStaffCount',
            'verifiedCount',
            'pendingCount',
            'publishedCount',
            'upcomingCount',
            'pendingResidents',
            'recentActivity',
            'advisoryText',
            'advisoryLevel',
            'attention',
            'todayEvents',
            'weekEvents',
            'pageTitle'
        ));
    }

    /**
     * POST /admin/advisory
     *
     * Publish or clear the standing hazard advisory shown at the top of every
     * resident dashboard.
     *
     * Deliberately reachable by staff, not just admins: the person at the
     * barangay hall when a signal is raised is whoever is on duty, and making
     * them find a superadmin first would defeat the point. Every change is
     * written to audit_logs with the old and new text, because "who put that
     * on the dashboard, and when" is exactly the question asked afterwards.
     */
    public function updateAdvisory(): void
    {
        check_csrf();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $text   = trim($_POST['advisory_text'] ?? '');
        $level  = (string) ($_POST['advisory_level'] ?? 'warning');
        $clear  = !empty($_POST['clear']);

        if (!in_array($level, self::ADVISORY_LEVELS, true)) {
            $level = 'warning';
        }

        if (!$clear && mb_strlen($text) > self::ADVISORY_MAX) {
            flash('error', t('admin_advisory.too_long'));
            redirect('/admin');
        }

        $previous = trim((string) setting('hazard_advisory_text', ''));
        if ($clear) {
            $text = '';
        }

        try {
            Setting::set('hazard_advisory_text', $text, $userId ?: null);
            Setting::set('hazard_advisory_level', $level, $userId ?: null);
        } catch (\Throwable $e) {
            error_log('[AdminController::updateAdvisory] ' . $e->getMessage());
            flash('error', t('superadmin.err_action_failed'));
            redirect('/admin');
        }

        AuditLog::record(
            $userId ?: null,
            $text === '' ? 'advisory.cleared' : 'advisory.published',
            $text === ''
                ? 'Hazard advisory cleared (was: "' . mb_strimwidth($previous, 0, 120, '…', 'UTF-8') . '")'
                : 'Hazard advisory [' . $level . '] set to: "' . mb_strimwidth($text, 0, 120, '…', 'UTF-8') . '"'
        );

        flash('success', $text === '' ? t('admin_advisory.cleared') : t('admin_advisory.saved'));
        redirect('/admin');
    }

    /**
     * GET /api/admin/charts/registrations
     * Returns 30-day resident registration counts as JSON for the line chart.
     */
    public function chartRegistrations(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->buildRegistrationChartData(db()));
    }

    /**
     * GET /api/admin/charts/announcement-categories
     * Returns announcement counts per category as JSON for the doughnut chart.
     */
    public function chartAnnouncementCategories(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->buildAnnouncementCategoryChartData(db()));
    }

    /** GET /admin/reports */
    public function reports(): void
    {
        $pdo = db();

        $residentStats = [
            'verified'  => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND status = 'verified'")->fetchColumn(),
            'pending'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND status = 'pending'")->fetchColumn(),
            'suspended' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND status = 'suspended'")->fetchColumn(),
        ];
        $residentStats['total'] = array_sum($residentStats);

        $announcementStats = [
            'total'     => (int) $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn(),
            'published' => (int) $pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'published'")->fetchColumn(),
            'draft'     => (int) $pdo->query("SELECT COUNT(*) FROM announcements WHERE status = 'draft'")->fetchColumn(),
        ];

        $eventStats = [
            'upcoming'  => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming'")->fetchColumn(),
            'ongoing'   => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'ongoing'")->fetchColumn(),
            'completed' => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'completed'")->fetchColumn(),
            'cancelled' => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'cancelled'")->fetchColumn(),
        ];
        $eventStats['total'] = array_sum($eventStats);

        $ordinanceStats = [
            'active'   => (int) $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status = 'active'")->fetchColumn(),
            'draft'    => (int) $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status = 'draft'")->fetchColumn(),
            'repealed' => (int) $pdo->query("SELECT COUNT(*) FROM ordinances WHERE status = 'repealed'")->fetchColumn(),
        ];
        $ordinanceStats['total'] = array_sum($ordinanceStats);

        // AI usage — count from ai_logs; fall back to audit_logs if table doesn't exist yet
        try {
            $aiUsage = (int) $pdo->query("SELECT COUNT(*) FROM ai_logs")->fetchColumn();
        } catch (\Throwable) {
            $aiUsage = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'ai.summarize'")->fetchColumn();
        }

        // Feedback stats
        try {
            $feedbackStats = [
                'total'      => (int) $pdo->query("SELECT COUNT(*) FROM feedbacks")->fetchColumn(),
                // "Unanswered" = threads with no staff/admin message yet (feedback_messages is the
                // source of truth — admin_reply is no longer written to, see Feedback model docblock).
                'unanswered' => (int) $pdo->query(
                    "SELECT COUNT(*) FROM feedbacks f
                     WHERE NOT EXISTS (
                         SELECT 1 FROM feedback_messages fm
                         WHERE fm.feedback_id = f.id AND fm.sender_role != 'resident'
                     )"
                )->fetchColumn(),
            ];
        } catch (\Throwable) {
            $feedbackStats = ['total' => 0, 'unanswered' => 0];
        }

        // Manobo translation stats
        try {
            $translationStats = [
                'total'      => (int) $pdo->query("SELECT COUNT(*) FROM translation_logs")->fetchColumn(),
                'this_month' => TranslationLog::totalThisMonth(),
            ];
            $translationLogs     = TranslationLog::recent(10);
            $translationByType   = TranslationLog::monthlyByType();
        } catch (\Throwable) {
            $translationStats  = ['total' => 0, 'this_month' => 0];
            $translationLogs   = [];
            $translationByType = [];
        }

        $regData      = $this->buildRegistrationChartData($pdo);
        $catData      = $this->buildAnnouncementCategoryChartData($pdo);
        $pendingCount = $residentStats['pending'];
        $pageTitle    = t('admin_reports.page_title');

        view('admin/reports/index', compact(
            'residentStats', 'announcementStats', 'eventStats',
            'ordinanceStats', 'aiUsage', 'feedbackStats',
            'regData', 'catData', 'pendingCount', 'pageTitle',
            'translationStats', 'translationLogs', 'translationByType'
        ));
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function getRecentActivity(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT al.id, al.action, al.description, al.ip_address, al.created_at,
                    u.full_name AS user_name, u.role AS user_role
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT 10'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildRegistrationChartData(PDO $pdo): array
    {
        $start = new \DateTimeImmutable('30 days ago');
        $stmt  = $pdo->prepare(
            'SELECT DATE(created_at) AS label, COUNT(*) AS total
             FROM users
             WHERE created_at >= ?
             GROUP BY label ORDER BY label ASC'
        );
        $stmt->execute([$start->format('Y-m-d')]);

        $counts = array_fill_keys(
            array_map(
                static fn (int $i) => $start->modify("+{$i} days")->format('Y-m-d'),
                range(0, 29)
            ),
            0
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($counts[$row['label']])) {
                $counts[$row['label']] = (int) $row['total'];
            }
        }

        return [
            'labels' => array_map(
                static fn (string $d) => (new \DateTimeImmutable($d))->format('M j'),
                array_keys($counts)
            ),
            'values' => array_values($counts),
        ];
    }

    private function buildAnnouncementCategoryChartData(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT category AS label, COUNT(*) AS total
             FROM announcements
             GROUP BY category ORDER BY total DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return [
            'labels' => array_column($rows, 'label'),
            'values' => array_map(static fn (array $r) => (int) $r['total'], $rows),
        ];
    }
}