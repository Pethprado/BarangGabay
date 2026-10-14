<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Models\UserSession;

class AuthMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            // Remember the page (e.g. a post link tapped in an SMS) so login
            // can bring the resident straight back to it.
            remember_intended_url();
            header('Location: ' . app_url('/login'));
            exit;
        }

        // Refresh this session's last-seen stamp, and sign the user out if a
        // super admin revoked it from the Sessions panel. Fails open on a DB
        // error so a database blip can never lock everyone out.
        if (UserSession::touchAndCheckRevoked(session_id())) {
            session_unset();
            session_destroy();
            header('Location: ' . app_url('/login'));
            exit;
        }
    }
}
