<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Mirrors PHP's file-based sessions into the database so a super admin can
 * see who is signed in and end a session remotely.
 *
 * Only a hash of the session id is stored — the row can identify the current
 * request's session but cannot be used to forge one.
 *
 * Every write is best-effort: a logging failure must never block a login.
 */
class UserSession
{
    /** A session with no activity for this many minutes is treated as idle. */
    public const IDLE_MINUTES = 15;

    /** Record a new signed-in session. Called right after session_regenerate_id(). */
    public static function start(int $userId, string $sessionId): void
    {
        try {
            // ON DUPLICATE guards against a regenerated id colliding with an
            // old row, which would otherwise throw on the unique index.
            $stmt = db()->prepare(
                'INSERT INTO user_sessions
                    (user_id, session_hash, ip_address, user_agent, login_at, last_seen_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id), login_at = NOW(), last_seen_at = NOW(),
                    logout_at = NULL, revoked_at = NULL'
            );
            $stmt->execute([
                $userId,
                self::hash($sessionId),
                $_SERVER['REMOTE_ADDR'] ?? null,
                isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[UserSession::start] ' . $e->getMessage());
        }
    }

    /**
     * Refresh last_seen_at for the current session and report whether it has
     * been revoked. Called on every authenticated request by AuthMiddleware.
     *
     * @return bool True if the session was force-ended and should be destroyed.
     */
    public static function touchAndCheckRevoked(string $sessionId): bool
    {
        try {
            $hash = self::hash($sessionId);

            $stmt = db()->prepare('SELECT revoked_at FROM user_sessions WHERE session_hash = ? LIMIT 1');
            $stmt->execute([$hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                return false;               // not tracked (e.g. logged in before this feature)
            }
            if ($row['revoked_at'] !== null) {
                return true;
            }

            db()->prepare('UPDATE user_sessions SET last_seen_at = NOW() WHERE session_hash = ?')
                ->execute([$hash]);

            return false;
        } catch (\Throwable $e) {
            return false;                   // fail open — never lock people out on a DB blip
        }
    }

    /** Mark a session as ended by the user signing out. */
    public static function end(string $sessionId): void
    {
        try {
            db()->prepare('UPDATE user_sessions SET logout_at = NOW() WHERE session_hash = ? AND logout_at IS NULL')
                ->execute([self::hash($sessionId)]);
        } catch (\Throwable $e) {
            error_log('[UserSession::end] ' . $e->getMessage());
        }
    }

    /** Force-end one session by row id. The user is signed out on their next request. */
    public static function revoke(int $id): void
    {
        db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL')
            ->execute([$id]);
    }

    /** Force-end every session belonging to one user. Returns rows affected. */
    public static function revokeAllForUser(int $userId): int
    {
        $stmt = db()->prepare(
            'UPDATE user_sessions SET revoked_at = NOW()
             WHERE user_id = ? AND revoked_at IS NULL AND logout_at IS NULL'
        );
        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }

    /**
     * Sessions that have not been signed out or revoked, newest activity first.
     *
     * @return list<array<string,mixed>>
     */
    public static function active(): array
    {
        $stmt = db()->prepare(
            'SELECT s.id, s.user_id, s.ip_address, s.user_agent,
                    s.login_at, s.last_seen_at,
                    TIMESTAMPDIFF(MINUTE, s.last_seen_at, NOW()) AS idle_minutes,
                    u.full_name, u.email, u.role
             FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.logout_at IS NULL AND s.revoked_at IS NULL
             ORDER BY s.last_seen_at DESC
             LIMIT 200'
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * One user's own live sessions, newest activity first — the "where am I
     * signed in" list on /admin/account. Returns the session_hash so the view
     * can mark the row belonging to the current request.
     *
     * @return list<array<string,mixed>>
     */
    public static function activeForUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT id, session_hash, ip_address, user_agent, login_at, last_seen_at,
                    TIMESTAMPDIFF(MINUTE, last_seen_at, NOW()) AS idle_minutes
             FROM user_sessions
             WHERE user_id = ? AND logout_at IS NULL AND revoked_at IS NULL
             ORDER BY last_seen_at DESC
             LIMIT 50'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Force-end every session of one user EXCEPT the one making the request.
     *
     * This is what makes a password change meaningful: without it a stolen
     * session cookie keeps working after the password it was obtained with has
     * been replaced. Returns rows affected.
     */
    public static function revokeOthersForUser(int $userId, string $keepSessionId): int
    {
        $stmt = db()->prepare(
            'UPDATE user_sessions SET revoked_at = NOW()
             WHERE user_id = ? AND session_hash <> ?
               AND revoked_at IS NULL AND logout_at IS NULL'
        );
        $stmt->execute([$userId, self::hash($keepSessionId)]);

        return $stmt->rowCount();
    }

    /**
     * Counts for the health cards.
     *
     * @return array{active:int, online:int, today:int}
     */
    public static function stats(): array
    {
        $pdo = db();

        return [
            'active' => (int) $pdo->query(
                'SELECT COUNT(*) FROM user_sessions WHERE logout_at IS NULL AND revoked_at IS NULL'
            )->fetchColumn(),
            'online' => (int) $pdo->query(
                'SELECT COUNT(*) FROM user_sessions
                 WHERE logout_at IS NULL AND revoked_at IS NULL
                   AND last_seen_at > DATE_SUB(NOW(), INTERVAL ' . self::IDLE_MINUTES . ' MINUTE)'
            )->fetchColumn(),
            'today'  => (int) $pdo->query(
                'SELECT COUNT(*) FROM user_sessions WHERE DATE(login_at) = CURDATE()'
            )->fetchColumn(),
        ];
    }

    /** Remove tracking rows for sessions that ended long ago. */
    public static function purgeOlderThan(int $days): int
    {
        $stmt = db()->prepare(
            'DELETE FROM user_sessions
             WHERE (logout_at IS NOT NULL OR revoked_at IS NOT NULL)
               AND last_seen_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([max(1, $days)]);

        return $stmt->rowCount();
    }

    /** SHA-256 of the session id — what actually goes in the table. */
    private static function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }
}
