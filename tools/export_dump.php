<?php
declare(strict_types=1);

/**
 * BarangGabay — SQL Dump Exporter Tool
 *
 * Generates ready-to-import database dump files with complete sample data
 * for both MySQL and PostgreSQL.
 *
 * Output Files:
 *  - database/sample_data.sql        (MySQL complete dump)
 *  - database/sample_data.pgsql.sql  (PostgreSQL complete dump)
 *
 * Usage:
 *  php tools/export_dump.php
 */

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';
require_once $root . '/config/database.php';

echo "====================================================\n";
echo " BarangGabay — SQL Dump Exporter\n";
echo "====================================================\n\n";

$pdo = null;
$driver = 'sqlite';

// Try connecting to active database first, else fallback to SQLite memory
try {
    $host = (string) env('DB_HOST', '127.0.0.1');
    $port = (string) env('DB_PORT', '3306');
    $name = (string) env('DB_NAME', 'baranggabay');
    $user = (string) env('DB_USER', 'root');
    $pass = (string) env('DB_PASS', '');
    $dbUrl = (string) (env('DATABASE_URL') ?: env('MYSQL_URL', ''));

    if ($dbUrl !== '') {
        $parsed = parse_url($dbUrl) ?: [];
        if (!empty($parsed)) {
            $scheme = strtolower($parsed['scheme'] ?? 'mysql');
            if (in_array($scheme, ['postgresql', 'postgres'], true)) {
                $driver = 'pgsql';
                $port = '5432';
            } else {
                $driver = 'mysql';
            }
            $host = $parsed['host'] ?? $host;
            $port = isset($parsed['port']) ? (string)$parsed['port'] : $port;
            $user = isset($parsed['user']) ? urldecode($parsed['user']) : $user;
            $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : $pass;
            if (!empty($parsed['path'])) {
                $name = ltrim($parsed['path'], '/');
            }
        }
    }

    if ($driver === 'pgsql') {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', $host, $port, $name);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    db($pdo);
    echo "Connected to active database ({$driver})\n";
} catch (\Throwable $e) {
    echo "Active database not reachable directly. Using in-memory database for export engine...\n";
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $driver = 'sqlite';
    db($pdo);
    $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
    $pdo->sqliteCreateFunction('date_sub', fn($date, $expr) => date('Y-m-d H:i:s', strtotime('-1 day')));

    // Prepare SQLite schema matching MySQL tables
    $sqliteSchema = <<<SQL
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    phone TEXT NULL,
    address TEXT NULL,
    zone TEXT NULL,
    role TEXT NOT NULL DEFAULT 'resident',
    status TEXT NOT NULL DEFAULT 'pending',
    id_photo_url TEXT NULL,
    avatar_url TEXT NULL,
    designation TEXT NULL,
    email_verified INTEGER NOT NULL DEFAULT 0,
    totp_enabled INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    title_fil TEXT NULL,
    title_en TEXT NULL,
    title_manobo TEXT NULL,
    slug TEXT NOT NULL UNIQUE,
    body TEXT NOT NULL,
    body_fil TEXT NULL,
    body_manobo TEXT NULL,
    category TEXT NOT NULL DEFAULT 'general',
    urgency TEXT NOT NULL DEFAULT 'normal',
    author_id INTEGER NOT NULL,
    cover_image_url TEXT NULL,
    status TEXT NOT NULL DEFAULT 'published',
    published_at DATETIME NULL,
    is_sample INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    title_fil TEXT NULL,
    title_en TEXT NULL,
    title_manobo TEXT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL,
    description_fil TEXT NULL,
    description_manobo TEXT NULL,
    venue TEXT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    event_date DATETIME NOT NULL,
    end_date DATETIME NULL,
    cover_image_url TEXT NULL,
    created_by INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'upcoming',
    is_sample INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ordinances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    title_fil TEXT NULL,
    title_en TEXT NULL,
    title_manobo TEXT NULL,
    ordinance_no TEXT NOT NULL UNIQUE,
    description TEXT NULL,
    description_fil TEXT NULL,
    description_manobo TEXT NULL,
    category TEXT NULL,
    file_url TEXT NOT NULL,
    enacted_date DATE NULL,
    ai_summary TEXT NULL,
    ai_summary_at DATETIME NULL,
    uploaded_by INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    is_sample INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE evacuation_centers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    purok TEXT NULL,
    address TEXT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    capacity INTEGER NULL,
    contact_person TEXT NULL,
    contact_phone TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE document_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference_no TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    document_type TEXT NOT NULL,
    purpose TEXT NOT NULL,
    notes TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    staff_note TEXT NULL,
    handled_by INTEGER NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ready_at DATETIME NULL,
    released_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE feedbacks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT NULL,
    replied_at DATETIME NULL,
    replied_by INTEGER NULL,
    is_read_admin INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE feedback_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    feedback_id INTEGER NOT NULL,
    sender_id INTEGER NOT NULL,
    sender_role TEXT NOT NULL DEFAULT 'resident',
    message TEXT NOT NULL,
    read_by_resident INTEGER NOT NULL DEFAULT 0,
    read_by_staff INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'system',
    related_type TEXT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT NULL,
    value_type TEXT NOT NULL DEFAULT 'string',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE manobo_dictionary (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    manobo TEXT NOT NULL,
    tagalog TEXT NOT NULL,
    english TEXT NOT NULL,
    bisaya TEXT NULL,
    part_of_speech TEXT NULL,
    sample_sentence TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE manobo_missing_concepts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phrase TEXT NOT NULL,
    context TEXT NULL,
    frequency INTEGER DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE translation_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_hash TEXT NOT NULL UNIQUE,
    source_text TEXT NOT NULL,
    target_lang TEXT NOT NULL,
    translated_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL;
    $pdo->exec($sqliteSchema);
}

// Now require seed_comprehensive_demo which relies on db()
require_once $root . '/tools/seed_comprehensive_demo.php';

// Populate sample dataset into the connection
echo "Populating comprehensive sample dataset...\n";
seed_comprehensive_demo($pdo, $driver);

$tables = [
    'users',
    'announcements',
    'events',
    'ordinances',
    'evacuation_centers',
    'document_requests',
    'feedbacks',
    'feedback_messages',
    'notifications',
    'settings',
];

// 1. Generate MySQL Dump File
$mysqlDumpHeader = "-- ========================================================\n";
$mysqlDumpHeader .= "-- BarangGabay - Sample Data Dump (MySQL 8.0+)\n";
$mysqlDumpHeader .= "-- Generated: " . date('Y-m-d H:i:s T') . "\n";
$mysqlDumpHeader .= "-- Target Barangay: Barangay Bayogo, Madrid, Surigao del Sur\n";
$mysqlDumpHeader .= "-- ========================================================\n\n";
$mysqlDumpHeader .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$mysqlSchema = file_get_contents($root . '/database/schema.sql') ?: '';
$mysqlBody = $mysqlDumpHeader . $mysqlSchema . "\n\n-- ================= DATA INSERTS =================\n\n";

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT * FROM {$table}");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (empty($rows)) {
            continue;
        }

        $mysqlBody .= "-- Data for table `{$table}` (" . count($rows) . " rows)\n";
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
            $valList = implode(', ', array_map(function ($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote((string)$val);
            }, array_values($row)));

            $mysqlBody .= "INSERT INTO `{$table}` ({$colList}) VALUES ({$valList});\n";
        }
        $mysqlBody .= "\n";
    } catch (\Throwable $e) {
        echo "Warning: Table `{$table}` skip for MySQL dump: " . $e->getMessage() . "\n";
    }
}
$mysqlBody .= "SET FOREIGN_KEY_CHECKS = 1;\n";

$mysqlDumpFile = $root . '/database/sample_data.sql';
file_put_contents($mysqlDumpFile, $mysqlBody);
echo "✔ Saved MySQL dump: database/sample_data.sql (" . number_format(filesize($mysqlDumpFile)) . " bytes)\n";

// 2. Generate PostgreSQL Dump File
$pgsqlDumpHeader = "-- ========================================================\n";
$pgsqlDumpHeader .= "-- BarangGabay - Sample Data Dump (PostgreSQL 14+)\n";
$pgsqlDumpHeader .= "-- Generated: " . date('Y-m-d H:i:s T') . "\n";
$pgsqlDumpHeader .= "-- Target Barangay: Barangay Bayogo, Madrid, Surigao del Sur\n";
$pgsqlDumpHeader .= "-- ========================================================\n\n";

$pgsqlSchema = file_get_contents($root . '/database/schema.pgsql.sql') ?: '';
$pgsqlBody = $pgsqlDumpHeader . $pgsqlSchema . "\n\n-- ================= DATA INSERTS =================\n\n";

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT * FROM {$table}");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (empty($rows)) {
            continue;
        }

        $pgsqlBody .= "-- Data for table \"{$table}\" (" . count($rows) . " rows)\n";
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $colList = implode(', ', array_map(fn($c) => "\"{$c}\"", $cols));
            $valList = implode(', ', array_map(function ($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote((string)$val);
            }, array_values($row)));

            $pgsqlBody .= "INSERT INTO \"{$table}\" ({$colList}) VALUES ({$valList}) ON CONFLICT DO NOTHING;\n";
        }
        $pgsqlBody .= "\n";
    } catch (\Throwable $e) {
        echo "Warning: Table `{$table}` skip for PgSQL dump: " . $e->getMessage() . "\n";
    }
}

$pgsqlDumpFile = $root . '/database/sample_data.pgsql.sql';
file_put_contents($pgsqlDumpFile, $pgsqlBody);
echo "✔ Saved PostgreSQL dump: database/sample_data.pgsql.sql (" . number_format(filesize($pgsqlDumpFile)) . " bytes)\n";

echo "\n====================================================\n";
echo " Dump Export Finished Successfully!\n";
echo "====================================================\n";
