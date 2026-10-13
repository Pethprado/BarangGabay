<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;

class NotificationController
{
    /**
     * GET /notifications
     * Notification list page — enriches each record with a resolved URL.
     */
    public function index(): void
    {
        $userId = (int) $_SESSION['user_id'];

        // Staff and admins get their own page in the back-office layout:
        // notifications only, in English, linking to back-office screens.
        // The resident page brought the whole resident menu (Home, Events,
        // Announcements, Ordinances, Documents, Feedback) along with it.
        $backOffice = self::isBackOfficeUser();
        if ($backOffice) {
            $GLOBALS['bg_is_back_office'] = true;   // English labels, see back_office_locale()
        }

        // Stored in the language they were written in; shown in the reader's.
        $notifications = localise_notifications(Notification::getForUser($userId));

        foreach ($notifications as &$notif) {
            $notif['url'] = $this->resolveUrl($notif);
        }
        unset($notif);

        if ($backOffice) {
            $pageTitle    = 'Notifications';
            $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
            view('admin/notifications', compact('notifications', 'pageTitle', 'pendingCount'));
            return;
        }

        view('resident/notifications', compact('notifications'));
    }

    /**
     * GET /api/notifications/unread
     * Returns JSON { count: N } for the bell-badge poller.
     */
    public function unreadCount(): void
    {
        header('Content-Type: application/json');
        $count = Notification::unreadCount((int) $_SESSION['user_id']);
        echo json_encode(['count' => $count]);
    }

    /**
     * POST /api/notifications/mark-read  (and POST /notifications/mark-read)
     * Marks ALL notifications for the current user as read.
     *
     * Content-negotiated: the bell dropdown and the dashboard panel call this
     * with fetch() and want JSON, but the dashboard's "mark all read" is also a
     * plain <form> so it still works with JavaScript disabled. A browser form
     * post must land back on a page, not on a screenful of `{"success":true}`.
     */
    public function markRead(): void
    {
        check_csrf();
        Notification::markRead((int) $_SESSION['user_id']);

        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            return;
        }

        flash('success', t('resident_home.notifs_marked'));
        redirect($this->safeReferer());
    }

    /**
     * True when the caller is an AJAX/JSON client rather than a browser
     * submitting a form.
     */
    private function wantsJson(): bool
    {
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return true;
        }

        return str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /**
     * Where to send a non-JS caller back to.
     *
     * Only same-origin referers are honoured — an attacker-supplied Referer
     * must never turn this into an open redirect. Anything else falls back to
     * the notifications page.
     */
    private function safeReferer(): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $base    = rtrim(base_url(), '/');

        if ($referer !== '' && str_starts_with($referer, $base)) {
            return $referer;
        }

        return '/notifications';
    }

    /**
     * POST /api/notifications/{id}/read
     * Marks a single notification as read (owned by current user).
     */
    public function markOneRead(array $params): void
    {
        check_csrf();
        header('Content-Type: application/json');
        $id     = (int) ($params['id'] ?? 0);
        $userId = (int) $_SESSION['user_id'];

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => __('Missing ID')]);
            return;
        }

        Notification::markOneRead($id, $userId);
        echo json_encode(['success' => true]);
    }

    /**
     * GET /api/notifications/recent
     * Returns the 8 most recent notifications with resolved URLs and human time.
     */
    public function recent(): void
    {
        header('Content-Type: application/json');
        $userId = (int) $_SESSION['user_id'];
        $notifs = localise_notifications(\App\Models\Notification::getRecentForUser($userId, 8));

        foreach ($notifs as &$n) {
            $n['url']      = $this->resolveUrl($n);
            $n['time_ago'] = $this->timeAgo((string) $n['created_at']);
        }
        unset($n);

        echo json_encode(array_values($notifs));
    }

    // ── Private ─────────────────────────────────────────────────────

    /** Convert a datetime string to a short human-readable "X ago" label in Filipino. */
    private function timeAgo(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return 'Kamakailan lang';
        if ($diff < 3600)   return floor($diff / 60) . ' min. nakalipas';
        if ($diff < 86400)  return floor($diff / 3600) . ' oras nakalipas';
        if ($diff < 604800) return floor($diff / 86400) . ' araw nakalipas';
        return date('M j', strtotime($datetime));
    }

    /**
     * Resolve the notification's related_type + related_id into a navigable URL.
     * Uses a single DB lookup per notification (acceptable for small lists).
     */
    private static function isBackOfficeUser(): bool
    {
        return \in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true);
    }

    private function resolveUrl(array $notif): string
    {
        // Staff open the matching back-office screen, never a resident page.
        if (self::isBackOfficeUser()) {
            $rid = (int) ($notif['related_id'] ?? 0);
            return match ((string) ($notif['related_type'] ?? '')) {
                'announcement'     => $rid ? route('admin/announcements/' . $rid . '/edit') : route('admin/announcements'),
                'event'            => $rid ? route('admin/events/' . $rid . '/edit') : route('admin/events'),
                'ordinance'        => $rid ? route('admin/ordinances/' . $rid . '/edit') : route('admin/ordinances'),
                'document_request' => route('admin/documents'),
                'document_payment' => route('admin/payments'),
                'feedback'         => route('admin/feedback'),
                'user', 'resident' => route('admin/residents'),
                default            => match ((string) ($notif['type'] ?? '')) {
                    'verification' => route('admin/residents'),
                    default        => route('notifications'),
                },
            };
        }

        if (empty($notif['related_id']) || empty($notif['related_type'])) {
            return route('notifications');
        }

        $rid = (int) $notif['related_id'];

        switch ($notif['related_type']) {
            case 'announcement':
                $stmt = db()->prepare('SELECT slug FROM announcements WHERE id = ? LIMIT 1');
                $stmt->execute([$rid]);
                $slug = $stmt->fetchColumn();
                return $slug ? route('announcements/' . $slug) : route('announcements');

            case 'event':
                $stmt = db()->prepare('SELECT slug FROM events WHERE id = ? LIMIT 1');
                $stmt->execute([$rid]);
                $slug = $stmt->fetchColumn();
                return $slug ? route('events/' . $slug) : route('events');

            case 'ordinance':
                return route('ordinances/' . $rid);

            default:
                return route('notifications');
        }
    }
}
