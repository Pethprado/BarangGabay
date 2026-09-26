<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/ai.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/../config/database.php';

require __DIR__ . '/ErrorHandler.php';
ErrorHandler::register();

/*
 * Apply the timezone chosen in Super Admin → Settings, overriding the value
 * config/app.php set from .env. Guarded because settings live in the database
 * and this runs on every request, including before migrations have been run.
 */
try {
    $configuredTimezone = (string) \App\Models\Setting::get('timezone', '');
    if ($configuredTimezone !== '' && in_array($configuredTimezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($configuredTimezone);
    }
} catch (\Throwable $e) {
    // Keep whatever config/app.php set.
}
