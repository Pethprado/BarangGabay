<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = (string) env('DB_HOST', '127.0.0.1');
        $port = (string) env('DB_PORT', '3306');
        $name = (string) env('DB_NAME', 'baranggabay');
        $user = (string) env('DB_USER', 'root');
        $pass = (string) env('DB_PASS', '');

        // Support full connection URLs like DATABASE_URL or MYSQL_URL
        $dbUrl = (string) (env('DATABASE_URL') ?: env('MYSQL_URL', ''));
        if ($dbUrl !== '') {
            $parsed = parse_url($dbUrl);
            if (is_array($parsed)) {
                $host = $parsed['host'] ?? $host;
                $port = isset($parsed['port']) ? (string) $parsed['port'] : $port;
                $user = isset($parsed['user']) ? urldecode($parsed['user']) : $user;
                $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : $pass;
                if (!empty($parsed['path'])) {
                    $name = ltrim($parsed['path'], '/');
                }
            }
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Support cloud databases with SSL
        $ssl = env('DB_SSL', false);
        if ($ssl === true || $ssl === 'true' || $ssl === '1' || (!empty($parsed['query']) && str_contains($parsed['query'], 'ssl'))) {
            if (defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
            } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $exception) {
            if (str_contains($exception->getMessage(), 'Unknown database') || $exception->getCode() === '1049') {
                $pdo = initializeDatabase($host, $port, $name, $user, $pass, $options);
            } else {
                throw $exception;
            }
        }

        // If the database is connected but empty (common on cloud MySQL provisions), load the base schema.
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt && $stmt->rowCount() === 0) {
                loadDatabaseSchema($pdo);
            }
        } catch (\Throwable $e) {
            error_log('Database schema check warning: ' . $e->getMessage());
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

function initializeDatabase(string $host, string $port, string $name, string $user, string $pass, array $options = []): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $defaultOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, !empty($options) ? $options : $defaultOptions);

    $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $name));
    $pdo->exec(sprintf('USE `%s`', $name));

    loadDatabaseSchema($pdo);

    return $pdo;
}

function loadDatabaseSchema(PDO $pdo): void
{
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException('Database schema file not found: ' . $schemaFile);
    }

    $schema = file_get_contents($schemaFile);
    if ($schema === false || trim($schema) === '') {
        return;
    }

    $queries = preg_split('/;\s*\n/', $schema);
    foreach ($queries as $query) {
        $query = preg_replace('#^\s*(?:--[^\n]*(?:\n|$)|/\*.*?\*/\s*)+#s', '', (string) $query);
        $query = trim((string) $query);
        if ($query === '') {
            continue;
        }
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            error_log('Schema load statement warning: ' . $e->getMessage());
        }
    }
}
