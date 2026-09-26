<?php
require __DIR__ . '/../app/bootstrap.php';
try {
    $pdo = db();
    $db  = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "DB connected: {$db}\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "No tables found — schema needs to be imported.\n";
    } else {
        echo "Tables: " . implode(', ', $tables) . "\n";
    }
    // Quick sanity: count users
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo "Users: {$userCount}\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}