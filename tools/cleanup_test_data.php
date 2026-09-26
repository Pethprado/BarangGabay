<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pdo = db();
// delete announcements created by smoke tests
$stmt = $pdo->prepare("DELETE FROM announcements WHERE title LIKE ?");
$stmt->execute(['Automated Test Announcement%']);
$deleted = $stmt->rowCount();
echo "Deleted announcements: $deleted\n";
// delete ordinances uploaded by smoke tests
$stmt2 = $pdo->prepare("DELETE FROM ordinances WHERE title = ? OR ordinance_no = ?");
$stmt2->execute(['Automated Upload Ordinance','Ordinance AUTO-001']);
$del2 = $stmt2->rowCount();
echo "Deleted ordinances: $del2\n";
?>