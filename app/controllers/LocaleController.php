<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Switches the session's active UI language. See app/helpers.php::t().
 */
class LocaleController
{
    /**
     * GET /set-locale/{locale} — redirects back to the referring page.
     *
     * ?scope=admin switches the back-office interface language instead of the
     * resident one (see back_office_locale()); it is only honoured for
     * back-office roles, so a resident cannot set a value nothing reads.
     */
    public function set(array $params): void
    {
        $locale = (string) ($params['locale'] ?? '');
        $isStaff = \in_array($_SESSION['role'] ?? '', ['staff', 'admin', 'superadmin'], true);

        if (($_GET['scope'] ?? '') === 'admin' && $isStaff) {
            set_back_office_locale($locale);
        } else {
            set_locale($locale);
        }

        $back = $_SERVER['HTTP_REFERER'] ?? null;
        if ($back && str_starts_with($back, rtrim(base_url(), '/'))) {
            header('Location: ' . $back);
            exit;
        }
        redirect('/');
    }
}