<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use PDO;

/**
 * Notification broadcast helpers used by controllers after creating content.
 */
class NotificationService
{
    /**
     * Broadcast to every verified resident.
     * Returns the number of notifications inserted.
     */
    public function broadcast(
        string $type,
        string $title,
        string $message,
        int    $relatedId   = 0,
        string $relatedType = ''
    ): int {
        $stmt  = db()->query("SELECT id FROM users WHERE status = 'verified' AND role = 'resident'");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;

        foreach ($users as $userId) {
            Notification::create([
                'user_id'      => (int) $userId,
                'title'        => $title,
                'message'      => $message,
                'type'         => $type,
                'related_id'   => $relatedId   ?: null,
                'related_type' => $relatedType ?: null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Notify a single user by ID.
     * Returns the new notification ID.
     */
    public function notifyUser(
        int    $userId,
        string $type,
        string $title,
        string $message,
        int    $relatedId   = 0,
        string $relatedType = ''
    ): int {
        return Notification::create([
            'user_id'      => $userId,
            'title'        => $title,
            'message'      => $message,
            'type'         => $type,
            'related_id'   => $relatedId   ?: null,
            'related_type' => $relatedType ?: null,
        ]);
    }

    /**
     * Notify back-office users (staff / admin / superadmin) about work waiting
     * for them — a new feedback message, a resident awaiting verification, a
     * newly published post.
     *
     * Each recipient is filtered on their own notify_* preference from
     * /admin/account, so turning a toggle off here actually stops the alert
     * rather than only hiding it. The person who triggered the event is
     * skipped: nobody needs a notification about their own action.
     *
     * Never throws — an alert failing must not roll back the action that
     * caused it.
     *
     * @param  string $preference One of notify_feedback|notify_registrations|notify_content
     * @return int                Notifications inserted
     */
    public function notifyBackOffice(
        string $preference,
        string $type,
        string $title,
        string $message,
        int    $relatedId   = 0,
        string $relatedType = '',
        int    $exceptUserId = 0
    ): int {
        $count = 0;

        try {
            foreach (User::backOfficeWanting($preference, $exceptUserId) as $userId) {
                Notification::create([
                    'user_id'      => $userId,
                    'title'        => $title,
                    'message'      => $message,
                    'type'         => $type,
                    'related_id'   => $relatedId   ?: null,
                    'related_type' => $relatedType ?: null,
                ]);
                $count++;
            }
        } catch (\Throwable $e) {
            error_log('[NotificationService::notifyBackOffice] ' . $e->getMessage());
        }

        return $count;
    }

    /**
     * Notify all verified residents in a specific zone.
     * Returns the number of notifications inserted.
     */
    public function notifyZone(
        string $zone,
        string $type,
        string $title,
        string $message,
        int    $relatedId   = 0,
        string $relatedType = ''
    ): int {
        $stmt = db()->prepare(
            "SELECT id FROM users
             WHERE status = 'verified' AND role = 'resident' AND zone = ?"
        );
        $stmt->execute([$zone]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;

        foreach ($users as $userId) {
            Notification::create([
                'user_id'      => (int) $userId,
                'title'        => $title,
                'message'      => $message,
                'type'         => $type,
                'related_id'   => $relatedId   ?: null,
                'related_type' => $relatedType ?: null,
            ]);
            $count++;
        }

        return $count;
    }
}
