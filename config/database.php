<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host   = (string) env('DB_HOST', '127.0.0.1');
        $port   = (string) env('DB_PORT', '3306');
        $name   = (string) env('DB_NAME', 'baranggabay');
        $user   = (string) env('DB_USER', 'root');
        $pass   = (string) env('DB_PASS', '');
        $driver = 'mysql'; // default; overridden when DATABASE_URL says pgsql

        // Support full connection URLs: DATABASE_URL (Render PostgreSQL/MySQL)
        // or MYSQL_URL (PlanetScale, Railway, etc.)
        $dbUrl  = (string) (env('DATABASE_URL') ?: env('MYSQL_URL', ''));
        $parsed = [];
        if ($dbUrl !== '') {
            $parsed = parse_url($dbUrl) ?: [];
            if (!empty($parsed)) {
                $scheme = strtolower($parsed['scheme'] ?? 'mysql');
                // postgresql:// or postgres:// -> use pgsql PDO driver
                if (in_array($scheme, ['postgresql', 'postgres'], true)) {
                    $driver = 'pgsql';
                    $port   = '5432'; // PostgreSQL default
                }
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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($driver === 'mysql') {
            // MySQL SSL support (Render MySQL, PlanetScale, etc.)
            $ssl = env('DB_SSL', false);
            if ($ssl === true || $ssl === 'true' || $ssl === '1'
                || (!empty($parsed['query']) && str_contains((string)($parsed['query'] ?? ''), 'ssl'))
            ) {
                if (defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
                } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        } else {
            // PostgreSQL DSN - sslmode=require for Render
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', $host, $port, $name);
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $exception) {
            // MySQL-only: handle "unknown database" by auto-creating it
            if ($driver === 'mysql'
                && (str_contains($exception->getMessage(), 'Unknown database') || $exception->getCode() === '1049')
            ) {
                $pdo = initializeDatabase($host, $port, $name, $user, $pass, $options);
            } else {
                // Connection refused or host unreachable - show clear setup page.
                renderDbConnectionError($host, $exception);
            }
        }

        // Check if schema needs to be loaded (empty database)
        try {
            if ($driver === 'pgsql') {
                $stmt   = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'");
                $exists = (int) ($stmt ? $stmt->fetchColumn() : 0);
                if ($exists === 0) {
                    loadDatabaseSchema($pdo, $driver);
                }
            } else {
                $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
                if ($stmt && $stmt->rowCount() === 0) {
                    loadDatabaseSchema($pdo, $driver);
                }
            }
        } catch (\Throwable $e) {
            error_log('Database schema check warning: ' . $e->getMessage());
        }

        if ($driver === 'pgsql') {
            syncPostgresSchema($pdo);
        } else {
            // Keep an existing database structure in sync with migrations.
            // All migration files use IF NOT EXISTS guards, so re-running is safe.
            runPendingMigrations($pdo, $driver);
        }

        seedAdminUser($pdo, $driver);
    }
    return $pdo;
}

/**
 * Synchronize PostgreSQL schema with all required columns, indexes, and tables.
 */
function syncPostgresSchema(PDO $pdo): void
{
    $statements = [
        // 1. Users table columns for 2FA, staff settings, etc.
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(512) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_confirmed_at TIMESTAMP NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMP NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS designation VARCHAR(100) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_feedback SMALLINT NOT NULL DEFAULT 1",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_registrations SMALLINT NOT NULL DEFAULT 1",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_content SMALLINT NOT NULL DEFAULT 1",

        // 2. Two-Factor Backup Codes table
        "CREATE TABLE IF NOT EXISTS two_factor_backup_codes (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_user_unused ON two_factor_backup_codes (user_id, used_at)",

        // 3. Document Requests table
        "CREATE TABLE IF NOT EXISTS document_requests (
            id SERIAL PRIMARY KEY,
            reference_no VARCHAR(30) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            document_type VARCHAR(60) NOT NULL,
            purpose VARCHAR(255) NOT NULL,
            notes TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            staff_note TEXT NULL,
            handled_by INT NULL,
            requested_at TIMESTAMP NOT NULL DEFAULT NOW(),
            ready_at TIMESTAMP NULL,
            released_at TIMESTAMP NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_dr_status ON document_requests(status)",
        "CREATE INDEX IF NOT EXISTS idx_dr_user ON document_requests(user_id)",

        // 4. Evacuation Centers table
        "CREATE TABLE IF NOT EXISTS evacuation_centers (
            id SERIAL PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            purok VARCHAR(50) NULL,
            address VARCHAR(255) NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            capacity INT NULL,
            contact_person VARCHAR(120) NULL,
            contact_phone VARCHAR(30) NULL,
            is_active SMALLINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMP NOT NULL DEFAULT NOW()
        )",
        "CREATE INDEX IF NOT EXISTS idx_ec_purok ON evacuation_centers(purok)",
        "CREATE INDEX IF NOT EXISTS idx_ec_active ON evacuation_centers(is_active)",

        // 5. Safety Check-ins table
        "CREATE TABLE IF NOT EXISTS safety_checkins (
            id SERIAL PRIMARY KEY,
            advisory_id INT NOT NULL,
            user_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'safe',
            purok VARCHAR(50) NULL,
            note VARCHAR(255) NULL,
            checked_in_at TIMESTAMP NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
            UNIQUE (advisory_id, user_id),
            FOREIGN KEY (advisory_id) REFERENCES announcements(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE INDEX IF NOT EXISTS idx_sc_status ON safety_checkins(status)",

        // 6. Announcements columns
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_purok VARCHAR(50) NULL",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS asks_safety_checkin SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS source_lang VARCHAR(10) NOT NULL DEFAULT 'fil'",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS title_fil VARCHAR(500) NULL",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS body_fil TEXT NULL",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS fil_is_auto SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS en_is_auto SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS manobo_is_auto SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS en_review_state VARCHAR(20) NOT NULL DEFAULT 'none'",

        // 7. Events columns
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS target_purok VARCHAR(50) NULL",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS source_lang VARCHAR(10) NOT NULL DEFAULT 'fil'",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS title_fil VARCHAR(500) NULL",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS description_fil TEXT NULL",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS fil_is_auto SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS en_is_auto SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE events ADD COLUMN IF NOT EXISTS manobo_is_auto SMALLINT NOT NULL DEFAULT 0",

        // 8. Ordinances columns
        "ALTER TABLE ordinances ADD COLUMN IF NOT EXISTS source_lang VARCHAR(10) NOT NULL DEFAULT 'fil'",
        "ALTER TABLE ordinances ADD COLUMN IF NOT EXISTS title_fil VARCHAR(500) NULL",
        "ALTER TABLE ordinances ADD COLUMN IF NOT EXISTS description_fil TEXT NULL",
        "ALTER TABLE ordinances ADD COLUMN IF NOT EXISTS fil_is_auto SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE ordinances ADD COLUMN IF NOT EXISTS en_is_auto SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE ordinances ADD COLUMN IF NOT EXISTS manobo_is_auto SMALLINT NOT NULL DEFAULT 0",

        // 9. Disable 2FA system-wide
        "UPDATE users SET totp_enabled = 0, totp_secret = NULL, totp_confirmed_at = NULL",
        "INSERT INTO settings (setting_key, setting_value, value_type) VALUES
            ('twofa_required_roles', '', 'string'),
            ('twofa_enabled', '0', 'bool')
         ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value",

        // 10. Error logs columns
        "ALTER TABLE error_logs ADD COLUMN IF NOT EXISTS method VARCHAR(10) NULL",
        "ALTER TABLE error_logs ADD COLUMN IF NOT EXISTS route VARCHAR(500) NULL",
        "ALTER TABLE error_logs ADD COLUMN IF NOT EXISTS user_id INT NULL",
        "ALTER TABLE error_logs ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL",
        "ALTER TABLE error_logs ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) NULL",
        "ALTER TABLE error_logs ADD COLUMN IF NOT EXISTS resolved_at TIMESTAMP NULL",

        // 11. User Sessions columns and unique index
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS session_hash CHAR(64) NULL",
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS login_at TIMESTAMP NOT NULL DEFAULT NOW()",
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMP NOT NULL DEFAULT NOW()",
        "ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS logout_at TIMESTAMP NULL",
        "CREATE UNIQUE INDEX IF NOT EXISTS uniq_session_hash ON user_sessions(session_hash)",

        // 12. Login Attempts columns
        "ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS successful SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS reason VARCHAR(50) NULL",
        "ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) NULL",
        "UPDATE login_attempts SET successful = success WHERE successful = 0 AND success = 1",

        // 13. MySQL Compatibility Functions for PostgreSQL
        "CREATE OR REPLACE FUNCTION date_sub(ts timestamp with time zone, iv interval)
         RETURNS timestamp with time zone AS $$ SELECT ts - iv; $$ LANGUAGE sql IMMUTABLE",
        "CREATE OR REPLACE FUNCTION date_sub(ts timestamp without time zone, iv interval)
         RETURNS timestamp without time zone AS $$ SELECT ts - iv; $$ LANGUAGE sql IMMUTABLE",
        "CREATE OR REPLACE FUNCTION date_add(ts timestamp with time zone, iv interval)
         RETURNS timestamp with time zone AS $$ SELECT ts + iv; $$ LANGUAGE sql IMMUTABLE",
        "CREATE OR REPLACE FUNCTION date_add(ts timestamp without time zone, iv interval)
         RETURNS timestamp without time zone AS $$ SELECT ts + iv; $$ LANGUAGE sql IMMUTABLE",
        "CREATE OR REPLACE FUNCTION curdate()
         RETURNS date AS $$ SELECT CURRENT_DATE; $$ LANGUAGE sql STABLE",
        "CREATE OR REPLACE FUNCTION timestampdiff(unit text, ts1 timestamp, ts2 timestamp)
         RETURNS integer AS $$
         BEGIN
             unit := lower(unit);
             IF unit = 'second' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)));
             ELSIF unit = 'minute' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 60);
             ELSIF unit = 'hour' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 3600);
             ELSIF unit = 'day' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 86400);
             ELSE RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 60);
             END IF;
         END;
         $$ LANGUAGE plpgsql IMMUTABLE",
        "CREATE OR REPLACE FUNCTION timestampdiff(unit text, ts1 timestamp with time zone, ts2 timestamp with time zone)
         RETURNS integer AS $$
         BEGIN
             unit := lower(unit);
             IF unit = 'second' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)));
             ELSIF unit = 'minute' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 60);
             ELSIF unit = 'hour' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 3600);
             ELSIF unit = 'day' THEN RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 86400);
             ELSE RETURN FLOOR(EXTRACT(EPOCH FROM (ts2 - ts1)) / 60);
             END IF;
         END;
         $$ LANGUAGE plpgsql IMMUTABLE",
        "CREATE OR REPLACE FUNCTION date_format(ts timestamp without time zone, fmt text)
         RETURNS text AS $$
         DECLARE pg_fmt text;
         BEGIN
             pg_fmt := replace(fmt, '%Y', 'YYYY');
             pg_fmt := replace(pg_fmt, '%y', 'YY');
             pg_fmt := replace(pg_fmt, '%m', 'MM');
             pg_fmt := replace(pg_fmt, '%d', 'DD');
             pg_fmt := replace(pg_fmt, '%H', 'HH24');
             pg_fmt := replace(pg_fmt, '%i', 'MI');
             pg_fmt := replace(pg_fmt, '%s', 'SS');
             RETURN to_char(ts, pg_fmt);
         END;
         $$ LANGUAGE plpgsql IMMUTABLE",
        "CREATE OR REPLACE FUNCTION date_format(ts timestamp with time zone, fmt text)
         RETURNS text AS $$
         DECLARE pg_fmt text;
         BEGIN
             pg_fmt := replace(fmt, '%Y', 'YYYY');
             pg_fmt := replace(pg_fmt, '%y', 'YY');
             pg_fmt := replace(pg_fmt, '%m', 'MM');
             pg_fmt := replace(pg_fmt, '%d', 'DD');
             pg_fmt := replace(pg_fmt, '%H', 'HH24');
             pg_fmt := replace(pg_fmt, '%i', 'MI');
             pg_fmt := replace(pg_fmt, '%s', 'SS');
             RETURN to_char(ts, pg_fmt);
         END;
         $$ LANGUAGE plpgsql IMMUTABLE",

        // 14. Manobo Dictionary columns for Hybrid Translator
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS bisaya VARCHAR(255) NULL",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS normalized_tagalog VARCHAR(255) NULL",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS normalized_english VARCHAR(255) NULL",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS normalized_bisaya VARCHAR(255) NULL",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS type VARCHAR(30) NOT NULL DEFAULT 'word'",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS priority INT NOT NULL DEFAULT 0",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS source_page INT NULL",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS review_status VARCHAR(30) NOT NULL DEFAULT 'approved'",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS needs_review SMALLINT NOT NULL DEFAULT 0",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS aliases TEXT NULL",
        "ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL",

        // 15. Translation Cache table
        "CREATE TABLE IF NOT EXISTS translation_cache (
            id SERIAL PRIMARY KEY,
            source_text_hash CHAR(64) NOT NULL,
            source_lang VARCHAR(10) NOT NULL DEFAULT 'fil',
            target_lang VARCHAR(10) NOT NULL DEFAULT 'msm',
            dictionary_version INT NOT NULL DEFAULT 1,
            translated_text TEXT NOT NULL,
            provenance_json TEXT NULL,
            manobo_matches INT NOT NULL DEFAULT 0,
            bisaya_fallbacks INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
            UNIQUE (source_text_hash, source_lang, target_lang, dictionary_version)
        )",
        "CREATE INDEX IF NOT EXISTS idx_tc_hash ON translation_cache(source_text_hash)",

        // 16. Manobo Missing Concepts table
        "CREATE TABLE IF NOT EXISTS manobo_missing_concepts (
            id SERIAL PRIMARY KEY,
            concept VARCHAR(255) NOT NULL,
            source_lang VARCHAR(10) NOT NULL DEFAULT 'tl',
            bisaya_fallback VARCHAR(255) NULL,
            usage_count INT NOT NULL DEFAULT 1,
            first_seen_at TIMESTAMP NOT NULL DEFAULT NOW(),
            last_seen_at TIMESTAMP NOT NULL DEFAULT NOW(),
            review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
            notes TEXT NULL,
            UNIQUE (concept, source_lang)
        )",
        "CREATE INDEX IF NOT EXISTS idx_mc_status ON manobo_missing_concepts(review_status)",
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            error_log('PgSQL schema sync note: ' . $e->getMessage());
        }
    }

    try {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM manobo_dictionary WHERE source = 'Manobo Words.pdf'")->fetchColumn();
        if ($count < 50) {
            require_once __DIR__ . '/../tools/seed_manobo_pdf_dataset.php';
            seed_manobo_pdf_dataset($pdo);
        }
    } catch (\Throwable $e) {
        error_log('PgSQL seed_manobo_pdf_dataset note: ' . $e->getMessage());
    }
}

/**
 * Apply every *.sql file under database/migrations/, in filename order.
 * Failures are logged and non-fatal so one bad statement cannot block others.
 */
function runPendingMigrations(PDO $pdo, string $driver = 'mysql'): void
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
            $statement = preg_replace('#^\s*(?:--[^\n]*(?:\n|$)|/\*.*?\*/\s*)+#s', '', $statement);
            $statement = trim((string) $statement);
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                error_log(sprintf('Migration warning (%s): %s', basename($file), $e->getMessage()));
            }
        }
    }
}

function seedAdminUser(PDO $pdo, string $driver = 'mysql'): void
{
    try {
        $stmt  = $pdo->query('SELECT COUNT(*) FROM users');
        $count = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return;
    }

    if ($count === 0) {
        $password = password_hash('Admin@1234', PASSWORD_BCRYPT);
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, status, email_verified, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, status, email_verified, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
        }
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
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, !empty($options) ? $options : $defaultOptions);

    $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $name));
    $pdo->exec(sprintf('USE `%s`', $name));

    loadDatabaseSchema($pdo, 'mysql');

    return $pdo;
}

function loadDatabaseSchema(PDO $pdo, string $driver = 'mysql'): void
{
    // Choose the right schema file for the driver
    $pgsqlSchema = __DIR__ . '/../database/schema.pgsql.sql';
    $mysqlSchema = __DIR__ . '/../database/schema.sql';

    if ($driver === 'pgsql' && file_exists($pgsqlSchema)) {
        $schemaFile = $pgsqlSchema;
    } else {
        $schemaFile = $mysqlSchema;
    }

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

/**
 * Show a clear, actionable page when the database cannot be reached.
 * Does NOT throw in production - shows a branded setup-guide page instead.
 *
 * @never-returns (calls exit)
 */
function renderDbConnectionError(string $host, \PDOException $e): never
{
    $debug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');

    error_log('[DB] Connection failed (host=' . $host . '): ' . $e->getMessage());

    if ($debug) {
        throw $e;
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 60');

    echo <<<HTML
<!DOCTYPE html>
<html lang="fil">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>BarangGabay — Database Not Configured</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{min-height:100vh;background:linear-gradient(180deg,#ecf3ea,#d8e9db);
         display:flex;align-items:center;justify-content:center;
         font-family:'Segoe UI',system-ui,sans-serif;padding:1rem}
    .card{background:#fff;border-radius:1.25rem;padding:3rem 2.5rem;max-width:540px;width:100%;
          box-shadow:0 24px 80px rgba(15,64,35,.12);text-align:center;border:1px solid #e4ece6}
    .icon{font-size:3rem;margin-bottom:1.25rem}
    h1{color:#1a6b3a;font-size:1.35rem;font-weight:800;margin-bottom:.75rem}
    p{color:#4a5568;line-height:1.7;font-size:.9rem;margin-bottom:.5rem}
    .steps{text-align:left;background:#f4f9f5;border-radius:.75rem;padding:1.25rem 1.5rem;margin:1.25rem 0}
    .steps li{color:#374151;font-size:.85rem;line-height:1.8;margin-left:1.25rem}
    code{background:#e8f5e9;padding:.1rem .35rem;border-radius:.25rem;font-size:.8rem;color:#1a6b3a;font-family:monospace}
    .btn{display:inline-block;margin-top:1.5rem;background:#1a6b3a;color:#fff;
         text-decoration:none;padding:.75rem 2rem;border-radius:.625rem;
         font-weight:700;font-size:.875rem;transition:background .15s}
    .btn:hover{background:#145730}
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">&#128374;</div>
    <h1>Database Hindi Naka-configure</h1>
    <p>Hindi makakonekta ang sistema sa database. Kailangan itakda ang database credentials sa Render dashboard.</p>
    <div class="steps">
      <p style="font-weight:700;margin-bottom:.5rem;color:#1a6b3a;">Para sa Render &mdash; Itakda ang mga env var na ito:</p>
      <ol>
        <li>Pumunta sa <strong>Render Dashboard &rarr; baranggabay &rarr; Environment</strong></li>
        <li>I-add o i-update ang mga sumusunod na variable:</li>
      </ol>
      <ul style="list-style:none;margin:.75rem 0 0 0">
        <li>&bull; <code>DATABASE_URL</code> &mdash; Full connection string ng iyong Render PostgreSQL</li>
        <li>&bull; <code>APP_URL</code> &mdash; <code>https://baranggabay.onrender.com</code></li>
      </ul>
    </div>
    <p style="font-size:.8rem;color:#94a3b8;">Pagkatapos i-save ang mga env var, i-redeploy ang service sa Render.</p>
    <a href="https://dashboard.render.com" class="btn">Buksan ang Render Dashboard</a>
  </div>
</body>
</html>
HTML;
    exit(1);
}
