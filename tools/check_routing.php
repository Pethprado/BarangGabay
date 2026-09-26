<?php
/**
 * Simulates the basePath stripping logic from public/index.php
 * so we can verify the routing works without Apache.
 */
require __DIR__ . '/../app/bootstrap.php';

$APP_URL = rtrim($_ENV['APP_URL'] ?? '', '/');
$basePath = rtrim((string)(parse_url($APP_URL, PHP_URL_PATH) ?? ''), '/');

$testUris = [
    '/BarangGabay/login'                => '/login',
    '/BarangGabay/register'             => '/register',
    '/BarangGabay/announcements'        => '/announcements',
    '/BarangGabay/admin'                => '/admin',
    '/BarangGabay/admin/residents'      => '/admin/residents',
    '/BarangGabay/ordinances/5'         => '/ordinances/5',
];

echo "APP_URL  : {$APP_URL}\n";
echo "basePath : {$basePath}\n\n";

$ok = 0; $fail = 0;
foreach ($testUris as $incoming => $expected) {
    $uri = $incoming;
    if ($basePath !== '' && stripos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
    }
    $uri = '/' . trim($uri, '/');

    $status = ($uri === $expected) ? 'OK  ' : 'FAIL';
    if ($uri === $expected) { $ok++; } else { $fail++; }
    echo "  [{$status}]  {$incoming}\n        → got '{$uri}'  expected '{$expected}'\n";
}

echo "\nResult: {$ok} OK, {$fail} FAIL\n";