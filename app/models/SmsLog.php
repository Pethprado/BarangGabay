<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Active-record style model for the sms_logs table.
 * All queries use PDO prepared statements.
 */
class SmsLog
{
    /**
     * Insert a new SMS log row and return its ID.
     *
     * @param array{phone:string, message:string, type:string, reference_id:int|null, status:string, response:string|null} $data
     */
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO sms_logs (phone, message, type, reference_id, status, response, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['phone'],
            $data['message'],
            $data['type']         ?? 'general',
            $data['reference_id'] ?? null,
            $data['status']       ?? 'pending',
            $data['response']     ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    /**
     * Paginated list of SMS logs with optional type and status filters.
     *
     * @return array{items: array[], total: int}
     */
    public static function paginate(int $page, int $perPage, string $type = '', string $status = ''): array
    {
        $where  = [];
        $params = [];

        if ($type !== '') {
            $where[]  = 'type = ?';
            $params[] = $type;
        }
        if ($status !== '') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total count
        $countStmt = db()->prepare("SELECT COUNT(*) FROM sms_logs {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Paginated rows
        $offset    = ($page - 1) * $perPage;
        $rowParams = array_merge($params, [$perPage, $offset]);
        $rowStmt   = db()->prepare(
            "SELECT id, phone, message, type, reference_id, status,
                    LEFT(response, 200) AS response_excerpt,
                    created_at
             FROM sms_logs
             {$whereClause}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $rowStmt->execute($rowParams);

        return [
            'items' => $rowStmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * Returns aggregate counts used by the admin stats cards.
     *
     * @return array{total:int, sent:int, failed:int, monthly:int}
     */
    /**
     * The last time this exact post was texted, if it ever was.
     *
     * Exists to stop the expensive accident. Auto-send-on-publish already
     * texts a post once; a staff member who does not know that, or who opens
     * the SMS page twice, can spend the whole thing again — and SMS credits
     * are real money that cannot be refunded once the carrier has taken them.
     *
     * Only successful rows are counted. A failed attempt cost nothing and
     * warning about it would train staff to click past the warning, which is
     * the failure mode that matters more than the duplicate itself.
     *
     * @param  string $type 'announcement' | 'event' | 'ordinance'
     * @return array{sent_at:string, recipients:int}|null
     */
    public static function lastForPost(string $type, int $postId): ?array
    {
        if ($postId <= 0 || !\in_array($type, ['announcement', 'event', 'ordinance'], true)) {
            return null;
        }

        try {
            $stmt = db()->prepare(
                "SELECT MAX(created_at) AS sent_at, COUNT(*) AS recipients
                   FROM sms_logs
                  WHERE type = ? AND reference_id = ? AND status = 'sent'"
            );
            $stmt->execute([$type, $postId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[SmsLog::lastForPost] ' . $e->getMessage());
            return null;
        }

        if (!$row || empty($row['sent_at'])) {
            return null;
        }

        return [
            'sent_at'    => (string) $row['sent_at'],
            'recipients' => (int) $row['recipients'],
        ];
    }

    public static function stats(): array
    {
        $pdo = db();

        return [
            'total'   => (int) $pdo->query("SELECT COUNT(*) FROM sms_logs")->fetchColumn(),
            'sent'    => (int) $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'sent'")->fetchColumn(),
            'failed'  => (int) $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'failed'")->fetchColumn(),
            'monthly' => (int) $pdo->query(
                "SELECT COUNT(*) FROM sms_logs WHERE status = 'sent' AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)"
            )->fetchColumn(),
        ];
    }
}
