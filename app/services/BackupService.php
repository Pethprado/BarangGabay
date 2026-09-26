<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Database backups via mysqldump.
 *
 * spatie/laravel-backup was the obvious choice but it is Laravel-only — it
 * depends on the framework's filesystem, console and notification layers,
 * none of which exist in this project. This wraps mysqldump directly instead.
 *
 * Backups are gzipped and written to storage/backups/, which sits OUTSIDE the
 * webroot: a dump contains every user row including password hashes, so it
 * must never be reachable by URL. Downloads are streamed by the controller
 * after a role check.
 *
 * Credentials are passed through a temporary --defaults-extra-file rather
 * than on the command line, where they would be visible in the process list.
 */
final class BackupService
{
    /** Backups older than the newest N are pruned automatically. */
    public const KEEP_LATEST = 10;

    /** A backup older than this many hours is reported as overdue. */
    public const STALE_AFTER_HOURS = 24;

    /** Only files matching this can ever be downloaded or deleted. */
    private const FILENAME_PATTERN = '/^baranggabay_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/';

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? \dirname(__DIR__, 2) . '/storage/backups';

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    // ── Public API ───────────────────────────────────────────────────

    /**
     * Run mysqldump and write a gzipped backup.
     *
     * @return array{filename:string, path:string, size:int, duration:float}
     * @throws RuntimeException on any failure, with mysqldump's own stderr.
     */
    public function create(): array
    {
        $started = microtime(true);

        if (!is_writable($this->directory)) {
            throw new RuntimeException('Backup directory is not writable: ' . $this->directory);
        }

        $binary = $this->mysqldumpPath();
        $filename = sprintf('baranggabay_%s.sql.gz', date('Y-m-d_His'));
        $target   = $this->directory . '/' . $filename;
        $rawFile  = $target . '.tmp.sql';

        $configFile = $this->writeCredentialsFile();

        try {
            $command = sprintf(
                '%s --defaults-extra-file=%s --single-transaction --quick --routines '
                . '--default-character-set=utf8mb4 --result-file=%s %s 2>&1',
                escapeshellarg($binary),
                escapeshellarg($configFile),
                escapeshellarg($rawFile),
                escapeshellarg((string) env('DB_NAME', 'baranggabay'))
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                @unlink($rawFile);
                throw new RuntimeException(
                    'mysqldump failed (exit ' . $exitCode . '): ' . trim(implode("\n", $output))
                );
            }
            if (!is_file($rawFile) || filesize($rawFile) === 0) {
                @unlink($rawFile);
                throw new RuntimeException('mysqldump produced an empty file.');
            }

            $this->gzipFile($rawFile, $target);
        } finally {
            @unlink($rawFile);
            @unlink($configFile);
        }

        return [
            'filename' => $filename,
            'path'     => $target,
            'size'     => (int) filesize($target),
            'duration' => round(microtime(true) - $started, 2),
        ];
    }

    /**
     * Existing backups, newest first.
     *
     * @return list<array{filename:string, size:int, created_at:int}>
     */
    public function all(): array
    {
        $backups = [];

        foreach ((array) glob($this->directory . '/*.sql.gz') as $path) {
            $name = basename((string) $path);
            if (!preg_match(self::FILENAME_PATTERN, $name)) {
                continue;   // ignore anything not written by this service
            }
            $backups[] = [
                'filename'   => $name,
                'size'       => (int) filesize((string) $path),
                'created_at' => (int) filemtime((string) $path),
            ];
        }

        usort($backups, static fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /**
     * Absolute path for a backup filename, or null if the name is not a
     * legitimate backup. This is the only place a user-supplied filename is
     * turned into a path — the pattern check blocks traversal outright.
     */
    public function pathFor(string $filename): ?string
    {
        $filename = basename($filename);
        if (!preg_match(self::FILENAME_PATTERN, $filename)) {
            return null;
        }

        $path = $this->directory . '/' . $filename;
        $real = realpath($path);
        $base = realpath($this->directory);

        // Belt and braces: the resolved path must still sit inside the folder.
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            return null;
        }

        return $real;
    }

    /** Delete one backup. Returns false if the name was not a valid backup. */
    public function delete(string $filename): bool
    {
        $path = $this->pathFor($filename);

        return $path !== null && @unlink($path);
    }

    /** Delete all but the newest KEEP_LATEST backups. Returns how many went. */
    public function prune(int $keep = self::KEEP_LATEST): int
    {
        $removed = 0;
        foreach (array_slice($this->all(), max(0, $keep)) as $backup) {
            if ($this->delete($backup['filename'])) {
                $removed++;
            }
        }

        return $removed;
    }

    /** Unix timestamp of the newest backup, or null if there are none. */
    public function lastBackupAt(): ?int
    {
        $all = $this->all();

        return $all === [] ? null : $all[0]['created_at'];
    }

    /** True when there is no backup, or the newest is older than the threshold. */
    public function isOverdue(int $hours = self::STALE_AFTER_HOURS): bool
    {
        $last = $this->lastBackupAt();

        return $last === null || $last < time() - ($hours * 3600);
    }

    /** Total bytes used by all backups. */
    public function totalSize(): int
    {
        return array_sum(array_column($this->all(), 'size'));
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /** Whether mysqldump can actually be found — surfaced in the UI. */
    public function isAvailable(): bool
    {
        try {
            $this->mysqldumpPath();
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Locate mysqldump. MYSQLDUMP_PATH in .env wins; otherwise try the usual
     * XAMPP location and then the system PATH.
     */
    private function mysqldumpPath(): string
    {
        $configured = (string) env('MYSQLDUMP_PATH', '');
        if ($configured !== '') {
            if (!is_file($configured)) {
                throw new RuntimeException('MYSQLDUMP_PATH is set but no file exists there: ' . $configured);
            }
            return $configured;
        }

        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // Last resort: hope it is on PATH.
        $which = stripos(PHP_OS_FAMILY, 'win') === 0 ? 'where mysqldump' : 'command -v mysqldump';
        $found = @shell_exec($which);
        if (is_string($found) && trim($found) !== '') {
            $first = trim(strtok($found, "\r\n") ?: '');
            if ($first !== '' && is_file($first)) {
                return $first;
            }
        }

        throw new RuntimeException(
            'mysqldump was not found. Set MYSQLDUMP_PATH in .env to its full path.'
        );
    }

    /**
     * Write a short-lived my.cnf holding the DB credentials, so they never
     * appear in the process list. Deleted by the caller's finally block.
     */
    private function writeCredentialsFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bgbk');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary credentials file.');
        }

        $ini = "[client]\n"
             . 'host=' . env('DB_HOST', '127.0.0.1') . "\n"
             . 'port=' . env('DB_PORT', '3306') . "\n"
             . 'user=' . env('DB_USER', 'root') . "\n"
             . 'password=' . env('DB_PASS', '') . "\n";

        file_put_contents($path, $ini);
        @chmod($path, 0600);

        return $path;
    }

    /** Stream-compress $source into $target so memory use stays flat. */
    private function gzipFile(string $source, string $target): void
    {
        $in  = fopen($source, 'rb');
        $out = gzopen($target, 'wb9');

        if ($in === false || $out === false) {
            if ($in !== false)  { fclose($in); }
            if ($out !== false) { gzclose($out); }
            throw new RuntimeException('Could not compress the backup file.');
        }

        while (!feof($in)) {
            $chunk = fread($in, 262144);
            if ($chunk === false) {
                break;
            }
            gzwrite($out, $chunk);
        }

        fclose($in);
        gzclose($out);
    }
}
