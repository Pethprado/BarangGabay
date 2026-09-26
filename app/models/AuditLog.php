<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class AuditLog
{
    /**
     * One user's own recent actions, newest first — the read-only activity
     * feed on /admin/account. Always scoped to a single user_id: this is never
     * a window into anyone else's history.
     *
     * @return list<array<string,mixed>>
     */
    public static function recentForUser(int $userId, int $limit = 20): array
    {
        $stmt = db()->prepare(
            'SELECT id, action, description, ip_address, created_at
             FROM audit_logs
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function record(?int $userId, string $action, ?string $description = null): void
    {
        $sql  = 'INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
        $args = [
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR']     ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        try {
            db()->prepare($sql)->execute($args);
        } catch (\PDOException $e) {
            // Stale session: user_id no longer exists → retry anonymously so the action is still logged
            if ($userId !== null && \str_contains($e->getMessage(), 'foreign key constraint')) {
                $args[0] = null;
                db()->prepare($sql)->execute($args);
            } else {
                throw $e;
            }
        }
    }
}
