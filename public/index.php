<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Derive the base path from APP_URL so Apache internal rewrites never corrupt it.
// Fallback to SCRIPT_NAME dirname when APP_URL is not set (e.g. bare CLI tests).
$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
if ($appUrl !== '') {
    $basePath = rtrim((string)(parse_url($appUrl, PHP_URL_PATH) ?? ''), '/');
} else {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath  = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
}

if ($basePath !== '' && stripos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}
$uri = '/' . trim($uri, '/');

// Maintenance mode gate — runs before routing so it covers every page.
// Super admins, /login, /logout and /set-locale are exempt so the switch can
// always be turned back off from the UI. See MaintenanceMiddleware.
App\Middleware\MaintenanceMiddleware::handle($uri);

$webRoutes = require __DIR__ . '/../routes/web.php';
$apiRoutes = require __DIR__ . '/../routes/api.php';
$routes = array_merge($webRoutes, $apiRoutes);

$route = null;
$params = [];
foreach ($routes as $entry) {
    [$routeMethod, $routePath, $handler, $middleware] = $entry;
    if ($routeMethod !== $method) {
        continue;
    }

    $pattern = preg_replace('#\\{([^/}]+)\\}#', '(?P<$1>[^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $uri, $matches)) {
        $route = $entry;
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }
        break;
    }
}

if (!$route) {
    http_response_code(404);
    $appName = system_name();
    $homeUrl = app_url('');
    echo <<<HTML
<!DOCTYPE html><html lang="fil"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 — {$appName}</title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{min-height:100vh;background:linear-gradient(180deg,#ecf3ea,#d8e9db);display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',system-ui,sans-serif;padding:1rem}.card{background:#fff;border-radius:1.25rem;padding:3rem 2.5rem;max-width:440px;width:100%;text-align:center;border:1px solid #e4ece6}.icon{font-size:3rem;margin-bottom:1rem}h1{color:#1a6b3a;font-size:1.25rem;font-weight:800;margin-bottom:.75rem}p{color:#4a5568;font-size:.9rem;line-height:1.7;margin-bottom:1.5rem}a{display:inline-block;background:#1a6b3a;color:#fff;text-decoration:none;padding:.75rem 2rem;border-radius:.625rem;font-weight:700;font-size:.875rem}</style>
</head><body>
<div class="card"><div class="icon">🔍</div>
<h1>Hindi Nahanap ang Pahina</h1>
<p>Paumanhin! Ang pahinang iyong hinahanap ay wala o inilipat na.</p>
<a href="{$homeUrl}">Bumalik sa Home</a></div>
</body></html>
HTML;
    exit;
}

[$routeMethod, $routePath, $handler, $middleware] = $route;

// Back-office pages render in Filipino regardless of the session's language
// choice — see current_locale(). Recorded here, where the matched route's
// middleware is known, because that is what distinguishes staff pages from
// resident ones (/superadmin/* is back office but is not under /admin).
$GLOBALS['bg_is_back_office'] = route_is_back_office($middleware);

[$controllerName, $action] = explode('@', $handler, 2);
$controllerClass = 'App\\Controllers\\' . $controllerName;
if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
    http_response_code(500);
    echo '<h1>Route handler not found</h1>';
    exit;
}

$auth = new \App\Middleware\AuthMiddleware();
$role = new \App\Middleware\RoleMiddleware();
$rateLimit = new \App\Middleware\RateLimitMiddleware();

foreach ($middleware as $item) {
    if ($item === 'auth') {
        $auth->handle();
    } elseif ($item === 'verified') {
        $auth->handle();
        // If the session status is pending, the admin may have since verified
        // this account — do a single DB lookup to catch that transition.
        if (($_SESSION['status'] ?? '') === 'pending') {
            $fresh = \App\Models\User::find((int) ($_SESSION['user_id'] ?? 0));
            if ($fresh) {
                $_SESSION['status'] = $fresh['status'];
            }
        }
        if (($_SESSION['status'] ?? '') !== 'verified') {
            header('Location: ' . app_url('/pending'));
            exit;
        }
    } elseif (str_starts_with($item, 'role:')) {
        $allowed = explode(',', substr($item, 5));
        $role->handle($allowed);
    } elseif ($item === 'rate-limit') {
        $rateLimit->handle((int) ($_SESSION['user_id'] ?? 0));
    }
}

// Roles that require 2FA must finish enrolling before anything else. Runs
// after the route middleware so $_SESSION reflects a fully-authenticated user.
App\Middleware\TwoFactorMiddleware::handle($uri);

// ── Scheduled announcements: send the notifications that have come due ──
//
// A scheduled post BECOMES VISIBLE on its own, with no help from this block —
// visibility is a WHERE clause evaluated on every read. What this block does
// is the part that cannot be recomputed: the one-time in-app notification,
// SMS and email batch.
//
// Piggy-backing on ordinary traffic rather than requiring cron is a deliberate
// fit to where this runs. The barangay deploys on XAMPP and on shared cPanel
// hosting, where a real scheduler is often unavailable and always one more
// thing to forget to set up; a portal that residents and staff open through
// the day supplies the heartbeat for free. `bin/publish-scheduled.php` is
// there for deployments that do have cron and want dispatch to be punctual
// rather than traffic-dependent.
//
// Guarded three ways so it cannot become a tax on page loads: GET requests
// only (never during a form POST the user is waiting on), at most once every
// few minutes per session, and wrapped so a failure here can never take down
// the page that triggered it.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sweepEvery = 180;   // seconds
    $lastSweep  = (int) ($_SESSION['bg_publish_sweep_at'] ?? 0);

    if (time() - $lastSweep >= $sweepEvery) {
        $_SESSION['bg_publish_sweep_at'] = time();
        try {
            (new App\Services\ScheduledPublisher())->run();
        } catch (\Throwable $e) {
            error_log('[scheduled-publish sweep] ' . $e->getMessage());
        }
    }

    // ── Translations that failed for a reason that has since gone away ──
    //
    // Same shape as the sweep above, and for the same reason: a barangay on
    // XAMPP or shared hosting often has no scheduler, and the portal's own
    // traffic is the only reliable heartbeat.
    //
    // The case this exists for is the free translator's daily allowance.
    // Staff publishing several notices on a busy afternoon can exhaust it
    // partway through; those posts save correctly with no English version,
    // and the allowance resets overnight when nobody is in the office. This
    // is what makes them fill themselves in — the first page load after the
    // reset finishes the job with nobody watching.
    //
    // A LONGER interval than the publish sweep on purpose. Dispatching a
    // notification is local work; translating is an HTTP call to a third
    // party that can take seconds, and it happens inside somebody's page
    // load. Fifteen minutes still clears a backlog the same day while
    // keeping that cost off all but one page view in a session.
    //
    // tools/retry-translations.php does the same thing on a schedule for
    // deployments that have one. Running both is safe: each attempt is
    // claimed with a conditional UPDATE before any API call.
    $retryEvery = 900;   // seconds
    $lastRetry  = (int) ($_SESSION['bg_translation_retry_at'] ?? 0);

    if (time() - $lastRetry >= $retryEvery) {
        $_SESSION['bg_translation_retry_at'] = time();
        try {
            (new App\Services\TranslationRetryRunner())->run();
        } catch (\Throwable $e) {
            error_log('[translation retry sweep] ' . $e->getMessage());
        }
    }
}

try {
    $controller = new $controllerClass();
    $controller->{$action}($params);
} catch (\Throwable $e) {
    /* Re-throw so the global handler registered in ErrorHandler::register() fires.
       This ensures all exceptions are logged to storage/logs/error.log and the
       user sees the branded error page instead of a raw PHP error. */
    throw $e;
}

