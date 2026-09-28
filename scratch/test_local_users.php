<?php
require 'app/bootstrap.php';
$users = db()->query('SELECT id, full_name, email, role, status FROM users')->fetchAll();
foreach ($users as $u) {
    echo "#{$u['id']} - {$u['full_name']} <{$u['email']}> [{$u['role']}, {$u['status']}]\n";
}
