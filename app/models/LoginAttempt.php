<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Login history — successes and failures — backed by the login_attempts table.
 *
 * This table also powers AuthController's rate limiting, so note the contract
 * in countRecentFailures(): only genuine credential failures count toward a
 * lockout. Successes and "you are already locked out" rows are recorded for
 * the audit trail but must never extend a lockout.
 */
class LoginAttempt
{
    /** Why a login failed. Stored in the `reason` column. */
    public const REASON_BAD_CREDENTIALS = 'bad_credentials';
    public const REASON_SUSPENDED       = 'suspended';
    public const REASON_RATE_LIMITED    = 'rate_limited';
    public const REASON_UNKNOWN_EMAIL   = 'unknown_email';
    public const REASON_IP_RATE_LIMITED = 'ip_rate_limited';
    public const REASON_TWOFA_FAILED    = 'twofa_failed';

    /**
     * A wrong current-password confirmation on /admin/account.
     *
     * Deliberately NOT in LOCKOUT_REASONS: guessing on the account page must
     * throttle the account page (see countRecentByReason) without locking the
     * user out of signing in — otherwise anyone who hijacked a session could
     * lock the real owner out of their own account just by guessing badly.
     */
    public const REASON_ACCOUNT_CONFIRM = 'account_confirm';

    /**
     * A correct password offered at the wrong entry point — a resident on the
     * staff login, or a staff account on the resident login.
     *
     * Deliberately NOT in LOCKOUT_REASONS, for the same reason as
     * REASON_ACCOUNT_CONFIRM above: whoever did this already knows the
     * password, so they are not guessing. They clicked the wrong door. Locking
     * them out of the right one for fifteen minutes over a misread link would
     * punish a mistake the page itself invited.
     *
     * Still recorded, because "someone keeps trying a staff password on the
     * resident page" is worth a Super Admin being able to see.
     */
    public const REASON_WRONG_ENTRY = 'wrong_entry';

    /** Reasons that count toward locking an account out. */
    private const LOCKOUT_REASONS = [
        self::REASON_BAD_CREDENTIALS,
        self::REASON_SUSPENDED,
        self::REASON_UNKNOWN_EMAIL,
    ];

    /**
     * Record one login attempt. Never throws — a logging failure must not
     * interrupt signing in.
     */
    public static function record(
        string  $email,
        bool    $successful,
        ?string $reason = null,
        ?int    $userId = null
    ): void {
        try {
            $stmt = db()->prepare(
                'INSERT INTO login_attempts
                    (email, user_id, ip_address, successful, reason, user_agent, attempted_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                substr($email, 0, 191),
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $successful ? 1 : 0,
                $reason,
                isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            // Non-critical. Swallowed on purpose.
        }
    }

    /**
     * Failed attempts for an email inside the cooldown window.
     *
     * Successes and rate_limited rows are excluded: counting a lockout notice
     * as a new failure would make the lockout renew itself forever.
     */
    public static function countRecentFailures(string $email, int $withinMinutes): int
    {
        $placeholders = implode(',', array_fill(0, count(self::LOCKOUT_REASONS), '?'));

        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email = ?
               AND successful = 0
               AND (reason IS NULL OR reason IN ({$placeholders}))
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute(array_merge([$email], self::LOCKOUT_REASONS, [$withinMinutes]));

        return (int) $stmt->fetchColumn();
    }

    /**
     * Failed attempts from one IP inside the window.
     *
     * Same exclusions as countRecentFailures(): a lockout notice must not
     * count as a fresh failure, or the block would renew itself forever.
     */
    public static function countRecentFailuresByIp(string $ip, int $withinMinutes): int
    {
        if ($ip === '') {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count(self::LOCKOUT_REASONS), '?'));

        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ?
               AND successful = 0
               AND (reason IS NULL OR reason IN ({$placeholders}))
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute(array_merge([$ip], self::LOCKOUT_REASONS, [$withinMinutes]));

        return (int) $stmt->fetchColumn();
    }

    /**
     * Failed attempts for one email with one specific reason inside the
     * window. Used to rate-limit the account page's password/email changes
     * with the same ceiling and cooldown the login flow uses, while keeping
     * those failures out of the sign-in lockout count.
     */
    public static function countRecentByReason(string $email, string $reason, int $withinMinutes): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ?
               AND successful = 0
               AND reason = ?
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$email, $reason, $withinMinutes]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Accounts currently locked out — at or over the threshold inside the
     * window. Includes when the lock lifts, so the panel can show a countdown
     * rather than an unexplained block.
     *
     * @return list<array<string,mixed>>
     */
    public static function lockedAccounts(int $threshold, int $withinMinutes): array
    {
        $placeholders = implode(',', array_fill(0, count(self::LOCKOUT_REASONS), '?'));

        $stmt = db()->prepare(
            "SELECT a.email,
                    COUNT(*)                   AS failures,
                    COUNT(DISTINCT a.ip_address) AS ip_count,
                    MIN(a.attempted_at)        AS first_attempt,
                    MAX(a.attempted_at)        AS last_attempt,
                    DATE_ADD(MAX(a.attempted_at), INTERVAL ? MINUTE) AS unlocks_at,
                    u.id                       AS user_id,
                    u.full_name,
                    u.role
             FROM login_attempts a
             LEFT JOIN users u ON u.email = a.email
             WHERE a.successful = 0
               AND (a.reason IS NULL OR a.reason IN ({$placeholders}))
               AND a.attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             GROUP BY a.email, u.id, u.full_name, u.role
             HAVING failures >= ?
             ORDER BY failures DESC, last_attempt DESC
             LIMIT 50"
        );
        $stmt->execute(array_merge(
            [$withinMinutes],
            self::LOCKOUT_REASONS,
            [$withinMinutes, $threshold]
        ));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * IP addresses currently over the per-IP ceiling.
     *
     * @return list<array<string,mixed>>
     */
    public static function lockedIps(int $threshold, int $withinMinutes): array
    {
        $placeholders = implode(',', array_fill(0, count(self::LOCKOUT_REASONS), '?'));

        $stmt = db()->prepare(
            "SELECT a.ip_address,
                    COUNT(*)                 AS failures,
                    COUNT(DISTINCT a.email)  AS email_count,
                    MAX(a.attempted_at)      AS last_attempt,
                    DATE_ADD(MAX(a.attempted_at), INTERVAL ? MINUTE) AS unlocks_at
             FROM login_attempts a
             WHERE a.successful = 0
               AND a.ip_address IS NOT NULL AND a.ip_address <> ''
               AND (a.reason IS NULL OR a.reason IN ({$placeholders}))
               AND a.attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             GROUP BY a.ip_address
             HAVING failures >= ?
             ORDER BY failures DESC
             LIMIT 50"
        );
        $stmt->execute(array_merge(
            [$withinMinutes],
            self::LOCKOUT_REASONS,
            [$withinMinutes, $threshold]
        ));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Clear the failure history for one IP — unlocks everyone behind it. */
    public static function clearForIp(string $ip): int
    {
        $stmt = db()->prepare('DELETE FROM login_attempts WHERE ip_address = ? AND successful = 0');
        $stmt->execute([$ip]);

        return $stmt->rowCount();
    }

    /**
     * Paginated login history with optional filters.
     *
     * @param array{email?:string, result?:string, from?:string, to?:string} $filters
     * @return array{items: array[], total: int}
     */
    public static function paginate(int $page, int $perPage, array $filters = []): array
    {
        $where  = [];
        $params = [];

        $email = trim((string) ($filters['email'] ?? ''));
        if ($email !== '') {
            $where[]  = '(a.email LIKE ? OR a.ip_address LIKE ?)';
            $like     = '%' . $email . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $result = (string) ($filters['result'] ?? '');
        if ($result === 'success') {
            $where[] = 'a.successful = 1';
        } elseif ($result === 'failed') {
            $where[] = 'a.successful = 0';
        }

        if (!empty($filters['from'])) {
            $where[]  = 'a.attempted_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[]  = 'a.attempted_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = db()->prepare("SELECT COUNT(*) FROM login_attempts a {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt   = db()->prepare(
            "SELECT a.id, a.email, a.user_id, a.ip_address, a.successful, a.reason,
                    a.user_agent, a.attempted_at, u.full_name, u.role
             FROM login_attempts a
             LEFT JOIN users u ON u.id = a.user_id
             {$whereClause}
             ORDER BY a.attempted_at DESC, a.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * Counts for the stat cards.
     *
     * @return array{failed_24h:int, success_24h:int, distinct_ips_24h:int}
     */
    public static function stats(): array
    {
        $pdo = db();

        return [
            'failed_24h' => (int) $pdo->query(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE successful = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            )->fetchColumn(),
            'success_24h' => (int) $pdo->query(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE successful = 1 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            )->fetchColumn(),
            'distinct_ips_24h' => (int) $pdo->query(
                'SELECT COUNT(DISTINCT ip_address) FROM login_attempts
                 WHERE successful = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            )->fetchColumn(),
        ];
    }

    /**
     * Emails with repeated recent failures — the "suspicious activity" list.
     *
     * @return list<array<string,mixed>>
     */
    public static function suspicious(int $minFailures = 3, int $withinMinutes = 60): array
    {
        $stmt = db()->prepare(
            'SELECT email,
                    COUNT(*)                    AS failures,
                    COUNT(DISTINCT ip_address)  AS ip_count,
                    MAX(attempted_at)           AS last_attempt
             FROM login_attempts
             WHERE successful = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
             GROUP BY email
             HAVING failures >= ?
             ORDER BY failures DESC
             LIMIT 20'
        );
        $stmt->execute([$withinMinutes, $minFailures]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Clear the failure history for one email — unlocks a locked-out account. */
    public static function clearFor(string $email): int
    {
        $stmt = db()->prepare('DELETE FROM login_attempts WHERE email = ? AND successful = 0');
        $stmt->execute([$email]);

        return $stmt->rowCount();
    }
}
