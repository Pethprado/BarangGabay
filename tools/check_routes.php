<?php
require __DIR__ . '/../app/bootstrap.php';

$web = require __DIR__ . '/../routes/web.php';
$api = require __DIR__ . '/../routes/api.php';
$all = array_merge($web, $api);

echo "Total routes: " . count($all) . "\n\n";

// Check every handler resolves to an existing class + method
$ok = 0; $fail = 0;
foreach ($all as [$method, $path, $handler]) {
    [$cls, $action] = explode('@', $handler, 2);
    $fqcn = 'App\\Controllers\\' . $cls;
    if (class_exists($fqcn) && method_exists($fqcn, $action)) {
        $ok++;
    } else {
        echo "  MISSING: {$method} {$path} -> {$fqcn}::{$action}\n";
        $fail++;
    }
}
echo "Handler check: {$ok} OK, {$fail} missing\n";