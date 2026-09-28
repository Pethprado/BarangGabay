<?php
declare(strict_types=1);

/**
 * Global error and exception handler.
 *
 * In debug mode  → dumps detailed trace to screen.
 * In production  → logs to storage/logs/error.log and renders a friendly HTML page.
 */
class ErrorHandler
{
    public static function register(): void
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
            ini_set('display_errors', '0');
        }

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $e): void
    {
        $errorId = strtoupper(bin2hex(random_bytes(4)));

        $entry = sprintf(
            "[%s] [%s] %s: %s in %s on line %d\nURL: %s %s\nTrace:\n%s\n",
            date('Y-m-d H:i:s'),
            $errorId,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $_SERVER['REQUEST_URI']    ?? '',
            $e->getTraceAsString()
        );

        self::writeLog($entry);
        self::writeDb([
            'error_id'    => $errorId,
            'severity'    => 'critical',
            'type'        => get_class($e),
            'message'     => $e->getMessage(),
            'file'        => $e->getFile(),
            'line'        => $e->getLine(),
            'stack_trace' => $e->getTraceAsString(),
        ]);

        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true' || php_sapi_name() === 'cli') {
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, $entry);
            } else {
                http_response_code(500);
                echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:2rem;font-size:.85rem;line-height:1.6;">';
                echo htmlspecialchars($entry, ENT_QUOTES, 'UTF-8');
                echo '</pre>';
            }
        } else {
            http_response_code(500);
            self::renderErrorPage($errorId);
        }

        exit(1);
    }

    public static function handleError(
        int    $errno,
        string $errstr,
        string $errfile,
        int    $errline
    ): bool {
        /* Suppress errors when the @ operator was used. */
        if (error_reporting() === 0) {
            return false;
        }

        $entry = sprintf(
            "[%s] PHP Error [E%d]: %s in %s on line %d\n",
            date('Y-m-d H:i:s'),
            $errno,
            $errstr,
            $errfile,
            $errline
        );

        self::writeLog($entry);

        /* Only warnings and worse reach the database. Notices and deprecations
           are high-volume and low-signal — they stay in error.log so the
           viewer does not fill up with noise. */
        $severity = self::severityForErrno($errno);
        if ($severity !== null) {
            self::writeDb([
                'error_id' => strtoupper(bin2hex(random_bytes(4))),
                'severity' => $severity,
                'type'     => self::errnoName($errno),
                'message'  => $errstr,
                'file'     => $errfile,
                'line'     => $errline,
            ]);
        }

        /* Return false to let PHP's internal handler also run (needed for E_WARNING etc.). */
        return false;
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err === null) {
            return;
        }

        $fatals = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatals, true)) {
            return;
        }

        $errorId = strtoupper(bin2hex(random_bytes(4)));
        $entry   = sprintf(
            "[%s] [%s] Fatal shutdown: %s in %s on line %d\n",
            date('Y-m-d H:i:s'),
            $errorId,
            $err['message'],
            $err['file'],
            $err['line']
        );

        self::writeLog($entry);
        self::writeDb([
            'error_id' => $errorId,
            'severity' => 'critical',
            'type'     => self::errnoName($err['type']),
            'message'  => $err['message'],
            'file'     => $err['file'],
            'line'     => $err['line'],
        ]);

        if (($_ENV['APP_DEBUG'] ?? 'false') !== 'true') {
            http_response_code(500);
            self::renderErrorPage($errorId);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Persist an error to the error_logs table for the Super Admin viewer.
     *
     * Deliberately swallows everything: this runs *inside* error handling, so
     * a database problem here must never escalate into a second failure. The
     * file log written by writeLog() remains the source of truth.
     *
     * @param array<string,mixed> $data
     */
    private static function writeDb(array $data): void
    {
        try {
            if (!function_exists('db') || !class_exists(\App\Models\ErrorLog::class)) {
                return;
            }
            \App\Models\ErrorLog::record($data);
        } catch (\Throwable $e) {
            // Intentionally ignored — see the docblock above.
        }
    }

    /**
     * Map a PHP error constant to a stored severity, or null to skip storing
     * it. Notices and deprecations are skipped on purpose.
     */
    private static function severityForErrno(int $errno): ?string
    {
        return match ($errno) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'critical',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING  => 'warning',
            default                                                        => null,
        };
    }

    /** Human-readable name for a PHP error constant. */
    private static function errnoName(int $errno): string
    {
        $names = [
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        ];

        return $names[$errno] ?? ('E_UNKNOWN_' . $errno);
    }

    private static function writeLog(string $message): void
    {
        $dir = __DIR__ . '/../storage/logs';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        error_log($message, 3, $dir . '/error.log');
    }

    /** Render a branded, user-friendly 500 error page. */
    private static function renderErrorPage(string $errorId): void
    {
        $appName = function_exists('system_name') ? system_name() : ($_ENV['APP_NAME'] ?? 'BarangGabay');

        echo <<<HTML
<!DOCTYPE html>
<html lang="fil">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>May Error — {$appName}</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{min-height:100vh;background:linear-gradient(180deg,#ecf3ea,#d8e9db);
         display:flex;align-items:center;justify-content:center;
         font-family:'Segoe UI',system-ui,sans-serif;padding:1rem;}
    .card{background:#fff;border-radius:1.25rem;padding:3rem 2.5rem;max-width:480px;width:100%;
          box-shadow:0 24px 80px rgba(15,64,35,.12);text-align:center;border:1px solid #e4ece6;}
    .icon{font-size:3rem;margin-bottom:1.25rem;}
    h1{color:#1a6b3a;font-size:1.35rem;font-weight:800;margin-bottom:.75rem;}
    p{color:#4a5568;line-height:1.7;font-size:.9rem;margin-bottom:.5rem;}
    .code{display:inline-block;background:#f4f9f5;border:1px solid #c3e6cb;
           border-radius:.375rem;padding:.25rem .75rem;font-family:monospace;
           font-size:.78rem;color:#155724;margin-top:.5rem;}
    .btn{display:inline-block;margin-top:1.5rem;background:#1a6b3a;color:#fff;
          text-decoration:none;padding:.75rem 2rem;border-radius:.625rem;
          font-weight:700;font-size:.875rem;transition:background .15s;}
    .btn:hover{background:#145730;}
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">⚠️</div>
    <h1>Oops! May nangyaring Mali</h1>
    <p>Paumanhin sa abala. May naganap na hindi inaasahang error sa aming sistema.</p>
    <p>Awtomatikong naiulat na ang error sa aming technical team.</p>
    <p>Kung maaari, makipag-ugnayan sa Barangay Hall at ibigay ang sumusunod na code:</p>
    <span class="code">Error ID: {$errorId}</span>
    <br>
    <a href="/" class="btn">Bumalik sa Home</a>
  </div>
</body>
</html>
HTML;
    }
}

