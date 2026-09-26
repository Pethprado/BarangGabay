<?php
declare(strict_types=1);

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

date_default_timezone_set('Asia/Manila');

define('BASE_URL', rtrim($_ENV['APP_URL'] ?? 'http://localhost/baranggabay', '/'));
define('APP_NAME', $_ENV['APP_NAME'] ?? 'BarangGabay');

return [
    'name' => APP_NAME,
    'url' => BASE_URL,
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila',
];
