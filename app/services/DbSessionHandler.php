<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * PHP sessions kept in the database (table php_sessions) instead of files.
 *
 * On Render the container's disk is wiped on every deploy and whenever the
 * free instance sleeps and wakes, so file sessions signed every user out:
 * staff clicking "Preview PDF" after a deploy landed on the login page. The
 * database survives both, so a login now lasts until it is idle for
 * IDLE_SECONDS or the user signs out.
 *
 * Data is stored base64-encoded so PostgreSQL TEXT never sees the NUL bytes
 * PHP's session serializer can produce. Any database error fails open to an
 * empty session (the user signs in again) rather than a crash.
 */
final class DbSessionHandler implements \SessionHandlerInterface
{
    /** A login lasts this long without any activity. */
    public const IDLE_SECONDS = 12 * 3600;

    /** What read() returned, so an unchanged session only has its timestamp touched. */
    private string $loaded = '';

    public static function register(): void
    {
        ini_set('session.gc_maxlifetime', (string) self::IDLE_SECONDS);
        session_set_save_handler(new self(), true);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $this->loaded = '';
        try {
            $stmt = db()->prepare('SELECT data, last_activity FROM php_sessions WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false || (int) $row['last_activity'] < time() - self::IDLE_SECONDS) {
                return '';
            }
            $data = base64_decode((string) $row['data'], true);
            $this->loaded = $data === false ? '' : $data;

            return $this->loaded;
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::read] ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $pdo = db();
            if ($data === $this->loaded && $data !== '') {
                // Nothing changed: just keep the login alive.
                $pdo->prepare('UPDATE php_sessions SET last_activity = ? WHERE id = ?')->execute([time(), $id]);
                return true;
            }
            $encoded = base64_encode($data);
            $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                ? 'INSERT INTO php_sessions (id, data, last_activity) VALUES (?, ?, ?)
                   ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_activity = EXCLUDED.last_activity'
                : 'INSERT INTO php_sessions (id, data, last_activity) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)';
            $pdo->prepare($sql)->execute([$id, $encoded, time()]);

            return true;
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::write] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            db()->prepare('DELETE FROM php_sessions WHERE id = ?')->execute([$id]);
        } catch (\Throwable $e) {
            error_log('[DbSessionHandler::destroy] ' . $e->getMessage());
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = db()->prepare('DELETE FROM php_sessions WHERE last_activity < ?');
            $stmt->execute([time() - self::IDLE_SECONDS]);

            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
