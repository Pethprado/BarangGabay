<?php
declare(strict_types=1);

// Redirect root project access into the public web entrypoint.
$uri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$path = parse_url($uri, PHP_URL_PATH);

if (!str_contains($path, '/public')) {
    $redirect = rtrim($path, '/') . '/public/';
    if ($redirect === '//') {
        $redirect = '/public/';
    }
    header('Location: ' . $redirect);
    exit;
}

require __DIR__ . '/public/index.php';
