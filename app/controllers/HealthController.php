<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Public, unauthenticated diagnostic endpoint.
 *
 * Exists so a deploy problem (most commonly: the database is unreachable from
 * the host, before any page-specific code even runs) can be told apart from a
 * bug in a specific page, without needing shell/log access to the server —
 * useful on Render, where the only database is an external one the app
 * connects to over the network. Never returns raw credentials or a full stack
 * trace in production; APP_DEBUG=true unlocks the underlying exception
 * message for local troubleshooting only.
 */
class HealthController
{
    public function check(): void
    {
        header('Content-Type: application/json');

        $debug = ((string) env('APP_DEBUG', 'false')) === 'true';

        $result = [
            'app'       => 'ok',
            'php'       => PHP_VERSION,
            'timestamp' => date('c'),
        ];

        try {
            $pdo = db();
            $pdo->query('SELECT 1');
            $result['database'] = 'ok';
            http_response_code(200);
        } catch (\Throwable $e) {
            $result['database'] = 'unreachable';

            // Safe to show publicly even outside debug mode: the exception
            // class and SQL error codes identify the failure category (bad
            // credentials vs. unreachable host vs. refused connection)
            // without exposing the host, database name or credentials that
            // the full exception message may contain.
            $result['error_type'] = get_class($e);
            if ($e instanceof \PDOException) {
                $result['sqlstate'] = (string) $e->getCode();
                if (preg_match('/\[(\d+)\]/', $e->getMessage(), $m)) {
                    $result['driver_error_code'] = $m[1];
                }
            }

            if ($debug) {
                $result['database_error'] = get_class($e) . ': ' . $e->getMessage();
            }
            http_response_code(503);
        }

        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
