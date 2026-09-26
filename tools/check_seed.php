<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();

echo "=== USERS ===\n";
$users = $pdo->query("SELECT id, full_name, email, role, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "  [{$u['id']}] {$u['full_name']} <{$u['email']}> role={$u['role']} status={$u['status']}\n";
}

echo "\n=== ANNOUNCEMENTS ===\n";
echo "  Count: " . $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn() . "\n";

echo "\n=== EVENTS ===\n";
echo "  Count: " . $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn() . "\n";

echo "\n=== ORDINANCES ===\n";
echo "  Count: " . $pdo->query("SELECT COUNT(*) FROM ordinances")->fetchColumn() . "\n";