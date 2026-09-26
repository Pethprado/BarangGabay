<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Switches the session's active UI language. See app/helpers.php::t().
 */
class LocaleController
{
    /** GET /set-locale/{locale} — redirects back to the referring page. */
    public function set(array $params): void
    {
        set_locale((string) ($params['locale'] ?? ''));

        $back = $_SERVER['HTTP_REFERER'] ?? null;
        if ($back && str_starts_with($back, rtrim(base_url(), '/'))) {
            header('Location: ' . $back);
            exit;
        }
        redirect('/');
    }
}