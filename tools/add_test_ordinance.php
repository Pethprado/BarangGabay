<?php
require __DIR__ . '/../app/bootstrap.php';
$pdo = db();
$stmt = $pdo->prepare('INSERT INTO ordinances (title, ordinance_no, description, category, file_url, enacted_date, uploaded_by, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
$stmt->execute([
    'Test Ordinance',
    'Ordinance No. 2026-001',
    'This is a sample ordinance for testing AI summarize endpoint. It contains several points about community rules and scheduling.',
    'Test',
    '/uploads/test.pdf',
    date('Y-m-d'),
    1,
    'active',
]);
echo "Inserted ordinance id: " . db()->lastInsertId() . "\n";
