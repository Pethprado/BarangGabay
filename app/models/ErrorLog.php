<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Active-record style model for the error_logs table.
 *
 * Written to by ErrorHandler and read by the Super Admin error viewer.
 * Every write is best-effort: logging an error must never itself throw, or a
 * single bad request turns into an error-handling loop.
 */
class ErrorLog
{
    /** Severities in increasing order of urgency — drives filters and badges. */
    public const SEVERITIES = ['notice', 'warning', 'error', 'critical'];

    /**
     * Record one error. Returns the row id, or null if the write failed
     * (a missing table or dead connection must not break the error page).
     *
     * @param array{
     *     error_id:string, severity?:string, type:string, message:string,
     *     file?:string|null, line?:int|null, stack_trace?:string|null
     * } $data
     */
    public static function record(array $data): ?int
    {
        $severity = in_array($data['severity'] ?? '', self::SEVERITIES, true)
            ? $data['severity']
            : 'error';

        $params = [
            substr($data['error_id'], 0, 16),
            $severity,
            substr($data['type'], 0, 191),
            $data['message'],
            isset($data['file']) ? substr((string) $data['file'], 0, 500) : null,
            $data['line'] ?? null,
            isset($_SERVER['REQUEST_METHOD']) ? substr((string) $_SERVER['REQUEST_METHOD'], 0, 10) : null,
            isset($_SERVER['REQUEST_URI'])    ? substr((string) $_SERVER['REQUEST_URI'], 0, 500)   : null,
            isset($_SESSION['user_id'])       ? (int) $_SESSION['user_id'] : null,
            $_SERVER['REMOTE_ADDR']           ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            $data['stack_trace'] ?? null,
        ];

        $sql = 'INSERT INTO error_logs
                (error_id, severity, type, message, file, line,
                 method, route, user_id, ip_address, user_agent, stack_trace, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';

        try {
            db()->prepare($sql)->execute($params);
            return (int) db()->lastInsertId();
        } catch (\PDOException $e) {
            // Stale session pointing at a deleted user — retry anonymously so
            // the error is still captured (same guard as AuditLog::record).
            if ($params[8] !== null && str_contains($e->getMessage(), 'foreign key constraint')) {
                try {
                    $params[8] = null;
                    db()->prepare($sql)->execute($params);
                    return (int) db()->lastInsertId();
                } catch (\Throwable $inner) {
                    return null;
                }
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Paginated list with optional severity, date-range and keyword filters.
     *
     * @param array{severity?:string, from?:string, to?:string, q?:string, unresolved?:bool} $filters
     * @return array{items: array[], total: int}
     */
    public static function paginate(int $page, int $perPage, array $filters = []): array
    {
        [$whereClause, $params] = self::buildWhere($filters);

        // The alias must match buildWhere(), which qualifies every column as e.*
        $countStmt = db()->prepare("SELECT COUNT(*) FROM error_logs e {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt   = db()->prepare(
            "SELECT e.id, e.error_id, e.severity, e.type, e.message, e.file, e.line,
                    e.method, e.route, e.user_id, e.ip_address, e.resolved_at, e.created_at,
                    u.full_name AS user_name
             FROM error_logs e
             LEFT JOIN users u ON u.id = e.user_id
             {$whereClause}
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** One full row including the stack trace. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT e.*, u.full_name AS user_name, u.email AS user_email
             FROM error_logs e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Counts for the stat cards.
     *
     * @return array{total:int, today:int, week:int, unresolved:int, critical:int}
     */
    public static function stats(): array
    {
        $pdo = db();

        return [
            'total'      => (int) $pdo->query('SELECT COUNT(*) FROM error_logs')->fetchColumn(),
            'today'      => (int) $pdo->query('SELECT COUNT(*) FROM error_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
            'week'       => (int) $pdo->query('SELECT COUNT(*) FROM error_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn(),
            'unresolved' => (int) $pdo->query('SELECT COUNT(*) FROM error_logs WHERE resolved_at IS NULL')->fetchColumn(),
            'critical'   => (int) $pdo->query("SELECT COUNT(*) FROM error_logs WHERE severity = 'critical' AND resolved_at IS NULL")->fetchColumn(),
        ];
    }

    /** Mark one error as handled. */
    public static function resolve(int $id): void
    {
        db()->prepare('UPDATE error_logs SET resolved_at = NOW() WHERE id = ? AND resolved_at IS NULL')
            ->execute([$id]);
    }

    /** Re-open a resolved error. */
    public static function unresolve(int $id): void
    {
        db()->prepare('UPDATE error_logs SET resolved_at = NULL WHERE id = ?')->execute([$id]);
    }

    /** Delete rows older than $days. Returns how many were removed. */
    public static function purgeOlderThan(int $days): int
    {
        $stmt = db()->prepare('DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $stmt->execute([max(1, $days)]);

        return $stmt->rowCount();
    }

    /** Delete every row. Used by the "clear all" action. */
    public static function purgeAll(): int
    {
        $stmt = db()->query('DELETE FROM error_logs');

        return $stmt->rowCount();
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Build the shared WHERE clause for paginate().
     *
     * @param  array<string,mixed> $filters
     * @return array{0:string, 1:list<mixed>}
     */
    private static function buildWhere(array $filters): array
    {
        $where  = [];
        $params = [];

        $severity = (string) ($filters['severity'] ?? '');
        if (in_array($severity, self::SEVERITIES, true)) {
            $where[]  = 'e.severity = ?';
            $params[] = $severity;
        }

        // Dates arrive as YYYY-MM-DD from <input type="date">.
        if (!empty($filters['from'])) {
            $where[]  = 'e.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[]  = 'e.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        if (!empty($filters['unresolved'])) {
            $where[] = 'e.resolved_at IS NULL';
        }

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(e.message LIKE ? OR e.type LIKE ? OR e.route LIKE ? OR e.error_id LIKE ? OR e.file LIKE ?)';
            $like    = '%' . $keyword . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }
}
