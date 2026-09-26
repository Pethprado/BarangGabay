<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\AuditLog;

class RoleMiddleware
{
    public function handle(array $allowedRoles): void
    {
        $userRole = $_SESSION['role'] ?? 'guest';

        // superadmin has unrestricted access to every protected route.
        if ($userRole === 'superadmin') {
            return;
        }
        if (in_array($userRole, $allowedRoles, true)) {
            return;
        }

        // Denied. Log it — repeated hits on admin-only URLs from a staff
        // account are worth seeing in the audit trail.
        try {
            AuditLog::record(
                isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'access.denied',
                sprintf(
                    '%s tried %s %s (needs: %s)',
                    $userRole,
                    $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '/',
                    implode(', ', $allowedRoles)
                )
            );
        } catch (\Throwable $e) {
            // Never let audit logging turn a 403 into a 500.
        }

        http_response_code(403);

        // The route was a back-office one, which is why current_locale() is
        // currently pinned to Filipino — but whoever is reading this page was
        // just told they are not staff. Hand them back their own language so
        // the one message explaining the refusal is in a language they chose.
        $GLOBALS['bg_is_back_office'] = false;

        // A branded page with a way out, instead of a bare heading on a blank
        // screen. Falls back to plain text only if the view is missing.
        $view = \dirname(__DIR__) . '/views/errors/403.php';
        if (is_file($view)) {
            require $view;
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "403 Forbidden";
        }

        exit;
    }
}
