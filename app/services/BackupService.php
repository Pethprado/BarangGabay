<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Database Backup Service supporting both PostgreSQL (pg_dump) and MySQL/MariaDB (mysqldump).
 *
 * Production-safe for Render Linux deployments as well as local XAMPP/Windows environments.
 * Backups are gzipped and saved outside the webroot in storage/backups/ (or persistent disk).
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
        // Detect persistent disk or fallback to project storage directory
        if ($directory !== null) {
            $this->directory = $directory;
        } else {
            $renderDisk = (string) env('RENDER_DISK_PATH', '');
            if ($renderDisk !== '' && is_dir($renderDisk)) {
                $this->directory = rtrim($renderDisk, '/') . '/backups';
            } else {
                $this->directory = \dirname(__DIR__, 2) . '/storage/backups';
            }
        }

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    /** Detect active PDO database driver ('pgsql' or 'mysql'). */
    public function getDriver(): string
    {
        try {
            $driver = db()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                return 'pgsql';
            }
        } catch (\Throwable) {}

        $dbUrl = (string) (env('DATABASE_URL') ?: env('MYSQL_URL', ''));
        if ($dbUrl !== '') {
            $scheme = strtolower((string) parse_url($dbUrl, PHP_URL_SCHEME));
            if (in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true)) {
                return 'pgsql';
            }
        }

        return 'mysql';
    }

    /**
     * Locate the appropriate dump binary (pg_dump or mysqldump).
     */
    public function getBinaryPath(): string
    {
        $driver = $this->getDriver();

        if ($driver === 'pgsql') {
            $configured = (string) env('PG_DUMP_PATH', '');
            if ($configured !== '') {
                if (!is_file($configured)) {
                    throw new RuntimeException('PG_DUMP_PATH is set but file does not exist: ' . $configured);
                }
                return $configured;
            }

            $candidates = [
                '/usr/bin/pg_dump',
                '/usr/local/bin/pg_dump',
                '/usr/lib/postgresql/16/bin/pg_dump',
                '/usr/lib/postgresql/15/bin/pg_dump',
                '/usr/lib/postgresql/14/bin/pg_dump',
                '/opt/homebrew/bin/pg_dump',
                'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe',
                'C:\\Program Files\\PostgreSQL\\15\\bin\\pg_dump.exe',
            ];
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            $which = stripos(PHP_OS_FAMILY, 'win') === 0 ? 'where pg_dump' : 'command -v pg_dump';
            $found = @shell_exec($which);
            if (is_string($found) && trim($found) !== '') {
                $first = trim(strtok($found, "\r\n") ?: '');
                if ($first !== '' && (is_file($first) || stripos(PHP_OS_FAMILY, 'win') !== 0)) {
                    return $first;
                }
            }

            throw new RuntimeException('pg_dump was not found. Install postgresql-client or configure PG_DUMP_PATH in environment.');
        } else {
            $configured = (string) env('MYSQLDUMP_PATH', '');
            if ($configured !== '') {
                if (!is_file($configured)) {
                    throw new RuntimeException('MYSQLDUMP_PATH is set but file does not exist: ' . $configured);
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

            $which = stripos(PHP_OS_FAMILY, 'win') === 0 ? 'where mysqldump' : 'command -v mysqldump';
            $found = @shell_exec($which);
            if (is_string($found) && trim($found) !== '') {
                $first = trim(strtok($found, "\r\n") ?: '');
                if ($first !== '' && (is_file($first) || stripos(PHP_OS_FAMILY, 'win') !== 0)) {
                    return $first;
                }
            }

            throw new RuntimeException('mysqldump was not found. Install default-mysql-client or configure MYSQLDUMP_PATH in environment.');
        }
    }

    public function isAvailable(): bool
    {
        try {
            $this->getBinaryPath();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Creates a gzipped database dump.
     *
     * @return array{filename:string, path:string, size:int, duration:float, driver:string}
     */
    public function create(): array
    {
        $started = microtime(true);

        if (!is_writable($this->directory)) {
            throw new RuntimeException('Backup storage directory is not writable: ' . $this->directory);
        }

        $driver   = $this->getDriver();
        $binary   = $this->getBinaryPath();
        $filename = sprintf('baranggabay_%s.sql.gz', date('Y-m-d_His'));
        $target   = $this->directory . '/' . $filename;
        $rawFile  = $target . '.tmp.sql';

        $dbParams = $this->getDatabaseCredentials();

        try {
            if ($driver === 'pgsql') {
                $command = sprintf(
                    '%s -h %s -p %s -U %s -d %s --clean --if-exists --no-owner --no-privileges --file=%s 2>&1',
                    escapeshellcmd($binary),
                    escapeshellarg($dbParams['host']),
                    escapeshellarg($dbParams['port']),
                    escapeshellarg($dbParams['user']),
                    escapeshellarg($dbParams['name']),
                    escapeshellarg($rawFile)
                );

                $processEnv = array_merge($_ENV, ['PGPASSWORD' => $dbParams['pass']]);
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];

                $process = proc_open($command, $descriptors, $pipes, null, $processEnv);
                if (!is_resource($process)) {
                    throw new RuntimeException('Failed to spawn pg_dump process.');
                }

                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);

                $exitCode = proc_close($process);

                if ($exitCode !== 0) {
                    @unlink($rawFile);
                    throw new RuntimeException('pg_dump failed (exit ' . $exitCode . '): ' . trim($stderr . ' ' . $stdout));
                }
            } else {
                $configFile = $this->writeMysqlCredentialsFile($dbParams);
                try {
                    $command = sprintf(
                        '%s --defaults-extra-file=%s --single-transaction --quick --routines --default-character-set=utf8mb4 --result-file=%s %s 2>&1',
                        escapeshellcmd($binary),
                        escapeshellarg($configFile),
                        escapeshellarg($rawFile),
                        escapeshellarg($dbParams['name'])
                    );

                    exec($command, $output, $exitCode);

                    if ($exitCode !== 0) {
                        @unlink($rawFile);
                        throw new RuntimeException('mysqldump failed (exit ' . $exitCode . '): ' . trim(implode("\n", $output)));
                    }
                } finally {
                    @unlink($configFile);
                }
            }

            if (!is_file($rawFile) || filesize($rawFile) === 0) {
                @unlink($rawFile);
                throw new RuntimeException('Dump tool produced an empty backup file.');
            }

            $this->gzipFile($rawFile, $target);
        } finally {
            @unlink($rawFile);
        }

        return [
            'filename' => $filename,
            'path'     => $target,
            'size'     => (int) filesize($target),
            'duration' => round(microtime(true) - $started, 2),
            'driver'   => $driver,
        ];
    }

    public function all(): array
    {
        $backups = [];
        foreach ((array) glob($this->directory . '/*.sql.gz') as $path) {
            $name = basename((string) $path);
            if (!preg_match(self::FILENAME_PATTERN, $name)) {
                continue;
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

    public function pathFor(string $filename): ?string
    {
        $filename = basename($filename);
        if (!preg_match(self::FILENAME_PATTERN, $filename)) {
            return null;
        }

        $path = $this->directory . '/' . $filename;
        $real = realpath($path);
        $base = realpath($this->directory);

        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            return null;
        }

        return $real;
    }

    public function delete(string $filename): bool
    {
        $path = $this->pathFor($filename);
        return $path !== null && @unlink($path);
    }

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

    public function lastBackupAt(): ?int
    {
        $all = $this->all();
        return $all === [] ? null : $all[0]['created_at'];
    }

    public function isOverdue(int $hours = self::STALE_AFTER_HOURS): bool
    {
        $last = $this->lastBackupAt();
        return $last === null || $last < time() - ($hours * 3600);
    }

    public function totalSize(): int
    {
        return array_sum(array_column($this->all(), 'size'));
    }

    public function directory(): string
    {
        return $this->directory;
    }

    private function getDatabaseCredentials(): array
    {
        $driver = $this->getDriver();
        $host   = (string) env('DB_HOST', '127.0.0.1');
        $port   = (string) env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
        $name   = (string) env('DB_NAME', 'baranggabay');
        $user   = (string) env('DB_USER', 'root');
        $pass   = (string) env('DB_PASS', '');

        $dbUrl  = (string) (env('DATABASE_URL') ?: env('MYSQL_URL', ''));
        if ($dbUrl !== '') {
            $parsed = parse_url($dbUrl) ?: [];
            if (!empty($parsed)) {
                $host = $parsed['host'] ?? $host;
                $port = isset($parsed['port']) ? (string) $parsed['port'] : $port;
                $user = isset($parsed['user']) ? urldecode($parsed['user']) : $user;
                $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : $pass;
                if (!empty($parsed['path'])) {
                    $name = ltrim($parsed['path'], '/');
                }
            }
        }

        return compact('driver', 'host', 'port', 'name', 'user', 'pass');
    }

    private function writeMysqlCredentialsFile(array $dbParams): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bgbk');
        if ($path === false) {
            throw new RuntimeException('Could not create temporary credentials file.');
        }

        $ini = "[client]\n"
             . 'host=' . $dbParams['host'] . "\n"
             . 'port=' . $dbParams['port'] . "\n"
             . 'user=' . $dbParams['user'] . "\n"
             . 'password=' . $dbParams['pass'] . "\n";

        file_put_contents($path, $ini);
        @chmod($path, 0600);
        return $path;
    }

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
