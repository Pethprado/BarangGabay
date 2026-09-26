<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Notification
{
    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function getForUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function markRead(int $userId): void
    {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, related_id, related_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['message'],
            $data['type'] ?? 'system',
            $data['related_id'] ?? null,
            $data['related_type'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    /**
     * Mark a single notification as read, scoped to the owning user.
     */
    public static function markOneRead(int $notificationId, int $userId): void
    {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
    }

    /**
     * The N most recent UNREAD notifications for one user — the dashboard
     * panel, which exists so a resident does not have to go hunting for the
     * bell. Always scoped to a single user_id.
     *
     * @return list<array<string,mixed>>
     */
    public static function unreadForUser(int $userId, int $limit = 5): array
    {
        $stmt = db()->prepare(
            'SELECT id, title, message, type, related_id, related_type, created_at
             FROM notifications
             WHERE user_id = ? AND is_read = 0
             ORDER BY created_at DESC, id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the N most recent notifications for a user (for the bell dropdown).
     */
    public static function getRecentForUser(int $userId, int $limit = 8): array
    {
        $stmt = db()->prepare(
            'SELECT id, title, message, type, related_id, related_type, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
