<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\Setting;

/**
 * Maintenance mode.
 *
 * There is no `artisan down` here, so this is a front-controller gate: called
 * from public/index.php before routing, it serves a 503 page to everyone
 * except super admins.
 *
 * Two things are deliberately still reachable while maintenance is on:
 *   - /login, so a super admin can sign in and turn it back off. Locking that
 *     would make maintenance mode a one-way door needing direct DB access.
 *   - /logout and /set-locale, so an already-signed-in user is not trapped.
 */
class MaintenanceMiddleware
{
    /** Paths that stay reachable while maintenance mode is on. */
    private const ALLOWED_PREFIXES = ['/login', '/logout', '/set-locale'];

    /**
     * Render the maintenance page and exit when the request should be blocked.
     * Returns normally when the request may proceed.
     */
    public static function handle(string $uri): void
    {
        try {
            if (Setting::get('maintenance_mode', false) !== true) {
                return;
            }
        } catch (\Throwable $e) {
            return;     // never let a settings failure take the site down
        }

        // Super admins work as normal — they are the ones fixing things.
        if (($_SESSION['role'] ?? '') === 'superadmin') {
            return;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return;
            }
        }

        self::render();
    }

    /** Send a 503 with Retry-After and a branded holding page, then exit. */
    private static function render(): never
    {
        $name    = system_name();
        $message = (string) Setting::get('maintenance_message', '');
        $login   = app_url('login');

        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=UTF-8');

        $nameEsc    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $messageEsc = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $loginEsc   = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="fil">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$nameEsc} — Maintenance</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{min-height:100vh;background:linear-gradient(180deg,#ecf3ea,#d8e9db);
         display:flex;align-items:center;justify-content:center;
         font-family:'Segoe UI',system-ui,sans-serif;padding:1rem}
    .card{background:#fff;border-radius:1.25rem;padding:3rem 2.5rem;max-width:480px;width:100%;
          box-shadow:0 24px 80px rgba(15,64,35,.12);text-align:center;border:1px solid #e4ece6}
    .icon{font-size:3rem;margin-bottom:1.25rem}
    h1{color:#1a6b3a;font-size:1.35rem;font-weight:800;margin-bottom:.75rem}
    p{color:#4a5568;line-height:1.7;font-size:.92rem;margin-bottom:1.5rem}
    a{display:inline-block;background:#1a6b3a;color:#fff;text-decoration:none;
      padding:.7rem 1.75rem;border-radius:.625rem;font-weight:700;font-size:.85rem}
    .foot{margin-top:1.5rem;font-size:.75rem;color:#94a3b8}
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🔧</div>
    <h1>{$nameEsc}</h1>
    <p>{$messageEsc}</p>
    <a href="{$loginEsc}">Staff sign in</a>
    <p class="foot">HTTP 503 &middot; Service temporarily unavailable</p>
  </div>
</body>
</html>
HTML;
        exit;
    }
}
