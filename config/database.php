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
                // Connection refused or host unreachable — likely missing DB env vars on Render.
                // Show a clear setup page instead of the generic crash page.
                renderDbConnectionError($host, $exception);
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

/**
 * Show a clear, actionable page when the database cannot be reached.
 *
 * This is almost always "DB credentials are not set in the Render
 * dashboard". Rather than the generic 500 error page (which says nothing
 * useful), we tell the operator exactly what to do and then stop — we do
 * NOT throw, so the global ErrorHandler never fires and no error ID is
 * generated for something that is purely a configuration problem.
 *
 * @never-returns (calls exit)
 */
function renderDbConnectionError(string $host, \PDOException $e): never
{
    $isLocalhost = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    $isRender    = !empty($_SERVER['RENDER']) || !empty($_ENV['RENDER']);
    $debug       = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');

    // Always log the real error for operators checking server logs.
    error_log('[DB] Connection failed (host=' . $host . '): ' . $e->getMessage());

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 60');

    $errorDetail = $debug ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : '';

    // Show a friendly page only in production; in debug mode just rethrow.
    if ($debug) {
        throw $e;
    }

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
    p{color:#4a5568;line-height:1.7;font-size:.9rem;margin-bottom.5rem}
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
    <div class="icon">🗄️</div>
    <h1>Database Hindi Naka-configure</h1>
    <p>Hindi makakonekta ang sistema sa database. Kailangan itakda ang database credentials sa Render dashboard.</p>
    <div class="steps">
      <p style="font-weight:700;margin-bottom:.5rem;color:#1a6b3a;">Para sa Render — Itakda ang mga env var na ito:</p>
      <ol>
        <li>Pumunta sa <strong>Render Dashboard → baranggabay → Environment</strong></li>
        <li>I-add o i-update ang mga sumusunod na variable:</li>
      </ol>
      <ul style="list-style:none;margin:.75rem 0 0 0">
        <li>• <code>DB_HOST</code> — Hostname ng iyong Render MySQL</li>
        <li>• <code>DB_USER</code> — Username</li>
        <li>• <code>DB_PASS</code> — Password</li>
        <li>• <code>DB_NAME</code> — <code>baranggabay</code></li>
        <li>• <code>DB_SSL</code> — <code>true</code></li>
        <li>• <code>APP_URL</code> — <code>https://baranggabay.onrender.com</code></li>
      </ul>
      <p style="margin-top:.75rem;font-size:.8rem;color:#6b7280;">💡 O kaya, itakda ang <code>DATABASE_URL</code> sa full connection string mula sa Render MySQL dashboard.</p>
    </div>
    <p style="font-size:.8rem;color:#94a3b8;">Pagkatapos i-save ang mga env var, i-redeploy ang service sa Render.</p>
    <a href="https://dashboard.render.com" class="btn">Buksan ang Render Dashboard</a>
  </div>
</body>
</html>
HTML;
    exit(1);
}
