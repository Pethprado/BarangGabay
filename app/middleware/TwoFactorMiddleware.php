<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\TwoFactorService;

/**
 * Forces roles that require 2FA to finish enrolling before they can use the
 * rest of the system.
 *
 * Called from public/index.php after the auth middleware. The 2FA pages
 * themselves, and sign-out, are exempt — otherwise the redirect would loop
 * and the account would be unusable.
 */
class TwoFactorMiddleware
{
    /** Paths reachable while enrolment is outstanding. */
    private const ALLOWED_PREFIXES = ['/two-factor', '/logout', '/set-locale'];

    public static function handle(string $uri): void
    {
        if (empty($_SESSION['user_id'])) {
            return;                       // not signed in — AuthMiddleware's job
        }

        $role = (string) ($_SESSION['role'] ?? '');
        if (!TwoFactorService::isRequiredFor($role)) {
            return;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return;
            }
        }

        try {
            if ((new TwoFactorService())->isEnabled((int) $_SESSION['user_id'])) {
                return;
            }
        } catch (\Throwable $e) {
            return;    // never lock an admin out because of a database problem
        }

        flash('error', t('twofa.setup_required'));
        header('Location: ' . app_url('two-factor'));
        exit;
    }
}
