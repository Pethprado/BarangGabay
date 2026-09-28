<?php
declare(strict_types=1);

/**
 * BarangGabay — SQL Dump Importer Tool
 *
 * Imports database dump files directly into MySQL or PostgreSQL.
 *
 * Usage:
 *  php tools/import_dump.php
 *  php tools/import_dump.php --file=database/sample_data.sql
 *  php tools/import_dump.php --file=database/sample_data.pgsql.sql
 */

$root = dirname(__DIR__);
require_once $root . '/app/bootstrap.php';

echo "====================================================\n";
echo " BarangGabay — SQL Dump Importer\n";
echo "====================================================\n\n";

$pdo = db();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?: 'mysql';
echo "Active Database Driver: {$driver}\n";

// Parse CLI options
$options = getopt('', ['file::']);
$dumpFile = $options['file'] ?? null;

if (!$dumpFile) {
    if ($driver === 'pgsql') {
        $dumpFile = $root . '/database/sample_data.pgsql.sql';
        if (!file_exists($dumpFile)) {
            $dumpFile = $root . '/database/schema.pgsql.sql';
        }
    } else {
        $dumpFile = $root . '/database/sample_data.sql';
        if (!file_exists($dumpFile)) {
            $dumpFile = $root . '/database/schema.sql';
        }
    }
} else {
    if (!str_starts_with($dumpFile, '/') && !str_starts_with($dumpFile, 'c:\\') && !str_starts_with($dumpFile, 'C:\\')) {
        $dumpFile = $root . '/' . ltrim($dumpFile, '/');
    }
}

if (!file_exists($dumpFile)) {
    echo "ERROR: Specified SQL dump file not found: {$dumpFile}\n";
    exit(1);
}

echo "Importing Dump File: " . basename($dumpFile) . "\n";
echo "File Size: " . number_format(filesize($dumpFile)) . " bytes\n\n";

$sqlContent = file_get_contents($dumpFile);
if (!$sqlContent) {
    echo "ERROR: Failed to read SQL file.\n";
    exit(1);
}

// Split into statements safely
$statements = preg_split('/;\s*\n/', $sqlContent) ?: [];
$executed = 0;
$failed = 0;

if ($driver === 'mysql') {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
}

foreach ($statements as $stmt) {
    $stmt = preg_replace('#^\s*(?:--[^\n]*(?:\n|$)|/\*.*?\*/\s*)+#s', '', $stmt);
    $stmt = trim((string)$stmt);
    if ($stmt === '') {
        continue;
    }

    try {
        $pdo->exec($stmt);
        $executed++;
    } catch (\PDOException $e) {
        $failed++;
        // Log note for minor non-fatal duplicate errors
        if (!str_contains($e->getMessage(), 'already exists') && !str_contains($e->getMessage(), 'Duplicate')) {
            echo "Notice: Statement error: " . $e->getMessage() . "\n";
        }
    }
}

if ($driver === 'mysql') {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

echo "\n====================================================\n";
echo " Import Finished!\n";
echo " Statements Executed: {$executed}\n";
echo " Statements Skipped/Noted: {$failed}\n";
echo "====================================================\n";
