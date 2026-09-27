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
        // Two-factor authentication is disabled system-wide.
        return;
    }
}
