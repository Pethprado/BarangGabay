<?php
try {
    $dsn = 'pgsql:host=dpg-dasi2860tbcc73fghjeg-a.oregon-postgres.render.com;port=5432;dbname=baranggabay;sslmode=require';
    $pdo = new PDO($dsn, 'baranggabay_user', 'Oik2JK4YbZa96ghL0NsFOyVc5vRtIT2H', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10
    ]);
    echo "Connected to Render PostgreSQL successfully!\n";
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables on Render: " . implode(', ', $tables) . "\n";
    
    // Check users
    $users = $pdo->query("SELECT id, email, role, status FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users count: " . count($users) . "\n";
    foreach ($users as $u) {
        echo "  [#{$u['id']}] {$u['email']} ({$u['role']}, {$u['status']})\n";
    }

    // Check announcements count
    echo "Announcements: " . $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn() . "\n";
    echo "Events: " . $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn() . "\n";
    echo "Ordinances: " . $pdo->query("SELECT COUNT(*) FROM ordinances")->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
