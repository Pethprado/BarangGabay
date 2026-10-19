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

        // Render deployment diagnostic check
        $result = [
            'app'         => 'ok',
            'php'         => PHP_VERSION,
            'timestamp'   => date('c'),
            'version'     => '2.8.0-paymongo-gcash',
            // Which git commit Render is serving (Render sets RENDER_GIT_COMMIT),
            // so "did my push go live?" is answered by one request.
            'commit'      => substr((string) (getenv('RENDER_GIT_COMMIT') ?: ($_ENV['RENDER_GIT_COMMIT'] ?? 'local')), 0, 7),
            // Yes/no only — never a key, never part of one.
            'paymongo'    => [
                'configured'     => \App\Services\PayMongoService::isConfigured(),
                'mode'           => \App\Services\PayMongoService::getMode(),
                'webhook_secret' => \App\Services\PayMongoService::isWebhookConfigured(),
                'methods'        => \App\Services\PayMongoService::paymentMethodTypes(),
            ],
        ];

        // Table lists, payment rows and recent errors are for the superadmin
        // only: error messages and stack traces must never be public.
        $isSuperadmin = ($_SESSION['role'] ?? '') === 'superadmin';

        try {
            $pdo = db();
            $pdo->query('SELECT 1');
            $result['database'] = 'ok';
            // The database clock, which must match 'timestamp' (Philippine
            // time). A gap of hours means the session time zone is wrong and
            // every "x ago" would be off by that much.
            $result['db_time'] = (string) $pdo->query('SELECT CAST(NOW() AS CHAR(19))')->fetchColumn();

            if ($isSuperadmin && isset($_GET['tables'])) {
                $tblStmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name");
                $result['tables'] = $tblStmt->fetchAll(\PDO::FETCH_COLUMN);

                $colStmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='users' ORDER BY column_name");
                $result['user_columns'] = $colStmt->fetchAll(\PDO::FETCH_COLUMN);
            }

            if ($isSuperadmin && isset($_GET['payments'])) {
                $pTblStmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name IN ('document_fees', 'gcash_accounts', 'document_payments', 'payment_audit_logs', 'payment_webhook_events')");
                $result['payment_tables'] = $pTblStmt->fetchAll(\PDO::FETCH_COLUMN);

                $dpColStmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='document_payments' AND column_name IN ('paymongo_checkout_id', 'paymongo_payment_id', 'paid_at', 'verification_type', 'checkout_url')");
                $result['paymongo_payment_cols'] = $dpColStmt->fetchAll(\PDO::FETCH_COLUMN);

                $feesStmt = $pdo->query("SELECT document_type, amount, is_free FROM document_fees");
                $result['seeded_fees'] = $feesStmt->fetchAll(\PDO::FETCH_ASSOC);

            }

            if ($isSuperadmin) try {
                $errStmt = $pdo->query("SELECT error_id, severity, type, message, file, line, stack_trace, created_at FROM error_logs ORDER BY id DESC LIMIT 5");
                $result['latest_errors'] = $errStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $errEx) {
                $result['latest_errors_error'] = $errEx->getMessage();
            }

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
