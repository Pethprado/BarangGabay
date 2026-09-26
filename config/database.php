<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_NAME'] ?? 'baranggabay';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            if (str_contains($exception->getMessage(), 'Unknown database') || $exception->getCode() === '1049') {
                $pdo = initializeDatabase($host, $port, $name, $user, $pass);
            } else {
                throw $exception;
            }
        }

        // Keep an existing database's structure in sync with database/migrations/*.sql.
        // Every migration file is written with IF NOT EXISTS guards, so this is a
        // cheap no-op once a column/table already exists — it exists purely to
        // self-heal installs that were set up before a later migration was added
        // (e.g. Manobo translation columns, AI ID-verification columns, sms_logs).
        runPendingMigrations($pdo);

        seedAdminUser($pdo);
    }
    return $pdo;
}

/**
 * Apply every *.sql file under database/migrations/, in filename order.
 * Each statement is executed independently and failures are logged (never
 * thrown) so one incompatible statement can't take down the whole request —
 * migrations are additive/idempotent by convention (CREATE TABLE IF NOT EXISTS,
 * ADD COLUMN IF NOT EXISTS, CREATE INDEX IF NOT EXISTS).
 */
function runPendingMigrations(PDO $pdo): void
{
    $migrationsDir = __DIR__ . '/../database/migrations';
    if (!is_dir($migrationsDir)) {
        return;
    }

    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            continue;
        }

        foreach (preg_split('/;\s*\n/', $sql) as $statement) {
            // Strip leading comments rather than skipping the whole chunk.
            //
            // Splitting on ";\n" leaves each statement carrying the comment
            // block written above it, so a chunk that opens with "--" is a
            // comment AND the SQL that follows it. Skipping on that prefix
            // silently dropped 26 statements across the migration set —
            // including the CREATE TABLEs for settings, user_sessions,
            // error_logs and ai_predictions — which defeated the whole point
            // of running migrations on boot. Every statement in these files
            // is written with IF NOT EXISTS, so re-applying them is a no-op.
            $statement = preg_replace('#^\s*(?:--[^\n]*(?:\n|$)|/\*.*?\*/\s*)+#s', '', $statement);
            $statement = trim((string) $statement);
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Non-fatal: log and keep applying the remaining migrations/statements.
                error_log(sprintf('Migration warning (%s): %s', basename($file), $e->getMessage()));
            }
        }
    }
}

function seedAdminUser(PDO $pdo): void
{
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM users');
        $count = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return;
    }

    if ($count === 0) {
        $password = password_hash('Admin@1234', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, status, email_verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            'Super Admin',
            'admin@baranggabay.ph',
            $password,
            'superadmin',
            'verified',
            1,
        ]);
    }
}

function initializeDatabase(string $host, string $port, string $name, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $name));
    $pdo->exec(sprintf('USE `%s`', $name));

    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException('Database schema file not found: ' . $schemaFile);
    }

    $schema = file_get_contents($schemaFile);
    $queries = preg_split('/;\s*\n/', $schema);
    foreach ($queries as $query) {
        $query = trim($query);
        if ($query === '' || str_starts_with($query, '--') || str_starts_with($query, '/*')) {
            continue;
        }
        $pdo->exec($query);
    }

    return $pdo;
}
