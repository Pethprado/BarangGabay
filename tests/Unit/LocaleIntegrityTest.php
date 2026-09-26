<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Guards the two invariants of the FIL / EN / MN language switch that are
 * easy to break by accident and hard to notice afterwards.
 *
 * Both exist because the Manobo (msm) locale is resolved from datasets rather
 * than a hand-written string file: adding a row to
 * data/bisaya/bisaya_dictionary.csv or data/manobo/manobo_dictionary.csv
 * changes what t() returns, with no code change to review. These tests run
 * over the real lang files and the real datasets, because a fixture would not
 * catch a bad row being committed.
 */
final class LocaleIntegrityTest extends TestCase
{
    /**
     * Reset the back-office flag no matter how a test ends.
     *
     * current_locale() reads this global, so a test that fails part-way and
     * never reaches its own cleanup would leave every later test resolving
     * strings as a back-office request. That is not hypothetical — it turned
     * one stale assertion in here into a second, entirely unrelated failure
     * over in ManoboGlossTest.
     */
    protected function tearDown(): void
    {
        $GLOBALS['bg_is_back_office'] = false;
        set_locale('fil');
        parent::tearDown();
    }

    /**
     * Flatten a nested lang array into dot keys.
     *
     * @param  array<string,mixed> $node
     * @return array<string,string>
     */
    private function flatten(array $node, string $prefix = ''): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $out += $this->flatten($value, $dotted);
            } elseif (is_string($value)) {
                $out[$dotted] = $value;
            }
        }
        return $out;
    }

    /** @return array<string,string> */
    private function lang(string $locale): array
    {
        return $this->flatten(require __DIR__ . '/../../lang/' . $locale . '.php');
    }

    /**
     * English and Filipino must declare the same keys.
     *
     * t() falls back to the key itself when a string is missing, so a label
     * added to one file and forgotten in the other does not error — it
     * renders as "safety.pulse_knock" on the page, in the language half the
     * barangay reads. Nothing in a code review catches that; the key looks
     * present because it is, in the file the reviewer happened to open.
     */
    public function testEnglishAndFilipinoDeclareTheSameKeys(): void
    {
        $en  = $this->lang('en');
        $fil = $this->lang('fil');

        $this->assertNotEmpty($en, 'en.php resolved to nothing — the scan is broken, not the file');

        $missingFromFil = array_values(array_diff(array_keys($en), array_keys($fil)));
        $missingFromEn  = array_values(array_diff(array_keys($fil), array_keys($en)));

        $this->assertSame(
            [], $missingFromFil,
            "in en.php but not fil.php:\n  " . implode("\n  ", $missingFromFil)
        );
        $this->assertSame(
            [], $missingFromEn,
            "in fil.php but not en.php:\n  " . implode("\n  ", $missingFromEn)
        );
    }

    /**
     * A translated string must keep every :placeholder the English has.
     *
     * ":count still to hear from" translated without its :count renders a
     * sentence with the number silently missing — grammatical, plausible,
     * and wrong. Worse in the other direction: a placeholder the caller
     * never passes is printed raw.
     */
    public function testTranslationsKeepTheirPlaceholders(): void
    {
        $en  = $this->lang('en');
        $fil = $this->lang('fil');

        $placeholders = static function (string $text): array {
            preg_match_all('~:([a-z_]+)~', $text, $m);
            $found = array_unique($m[1]);
            sort($found);
            return $found;
        };

        $wrong = [];
        foreach ($en as $key => $english) {
            if (!isset($fil[$key])) { continue; }

            $want = $placeholders($english);
            $got  = $placeholders($fil[$key]);
            if ($want === $got) { continue; }

            $wrong[] = sprintf(
                '%s — en has [%s], fil has [%s]',
                $key,
                implode(', ', $want) ?: 'none',
                implode(', ', $got) ?: 'none'
            );
        }

        $this->assertSame([], $wrong, "placeholders differ:\n  " . implode("\n  ", $wrong));
    }

    /**
     * The dictionaries must never leak into English or Filipino.
     *
     * The Manobo resolution in t() is gated on $locale === 'msm'. If that gate
     * is ever widened, every EN and FIL label in the app would start picking
     * up Bisaya words — a change nobody would have asked for, affecting the
     * two locales almost all residents actually read.
     */
    public function testEnglishAndFilipinoAreUnaffectedByTheDictionaries(): void
    {
        $_SESSION = [];

        foreach (['en', 'fil'] as $locale) {
            $strings = $this->lang($locale);
            set_locale($locale);

            foreach ($strings as $key => $expected) {
                $this->assertSame(
                    $expected,
                    t($key),
                    "t('{$key}') under '{$locale}' no longer matches lang/{$locale}.php"
                );
            }
        }

        set_locale('fil');
    }

    /**
     * Every :placeholder must survive translation into Manobo.
     *
     * A dictionary row is free text, so a whole-label Bisaya entry can easily
     * be written without the ":n" or ":name" the caller then tries to replace.
     * The result is not a translation bug but a visibly broken string —
     * "Welcome, :name" rendering literally — so it is worth pinning.
     */
    public function testManoboKeepsEveryPlaceholder(): void
    {
        $_SESSION = [];

        $english  = $this->lang('en');
        $filipino = $this->lang('fil');

        $placeholders = static function (string $text): array {
            return preg_match_all('/:[a-z_]+/', $text, $matches)
                ? array_unique($matches[0])
                : [];
        };

        set_locale('msm');

        $checked = 0;
        foreach ($english as $key => $englishValue) {
            $expected = array_unique(array_merge(
                $placeholders($englishValue),
                $placeholders($filipino[$key] ?? '')
            ));
            if ($expected === []) {
                continue;
            }

            $checked++;
            $actual = $placeholders(t($key));
            $this->assertSame(
                [],
                array_values(array_diff($expected, $actual)),
                "t('{$key}') under 'msm' dropped a placeholder: " . t($key)
            );
        }

        // Guards the guard: if the lang files ever stop using placeholders,
        // this test would pass while asserting nothing at all.
        $this->assertGreaterThan(50, $checked, 'Expected many placeholder strings to check');

        set_locale('fil');
    }

    /**
     * A back-office route is one gated by a 'role:' middleware.
     *
     * Pinned against the real route tables rather than a fixture, because the
     * risk being guarded is someone adding a staff page that this rule does
     * not recognise — a fixture would never notice.
     */
    public function testEveryAdminAndSuperadminRouteCountsAsBackOffice(): void
    {
        $routes = array_merge(
            require __DIR__ . '/../../routes/web.php',
            require __DIR__ . '/../../routes/api.php'
        );

        $backOffice = 0;
        foreach ($routes as [$method, $path, $handler, $middleware]) {
            $looksStaff = str_starts_with($path, '/admin') || str_starts_with($path, '/superadmin');
            if ($looksStaff) {
                $this->assertTrue(
                    route_is_back_office($middleware),
                    "{$method} {$path} is a staff page but has no 'role:' middleware, so it would render in the resident's chosen language"
                );
                $backOffice++;
            }
        }

        $this->assertGreaterThan(20, $backOffice, 'Expected many back-office routes');
        $this->assertFalse(route_is_back_office(['auth', 'verified']), 'Resident routes must not count as back office');
        $this->assertFalse(route_is_back_office([]), 'Public routes must not count as back office');
    }

    /**
     * The password-reset route must never be reachable by staff.
     *
     * A staff account that could reset passwords could reset an admin's and
     * take that account over, which is a straight privilege escalation. The
     * route's middleware is the outer gate for that, so it is worth pinning
     * here where a careless edit to routes/web.php would be caught.
     */
    public function testPasswordResetRouteExcludesStaff(): void
    {
        $routes = require __DIR__ . '/../../routes/web.php';

        $found = null;
        foreach ($routes as [$method, $path, $handler, $middleware]) {
            if ($path === '/admin/residents/{id}/reset-password') {
                $found = $middleware;
            }
        }

        $this->assertNotNull($found, 'The password reset route is missing');
        $roleRule = '';
        foreach ($found as $entry) {
            if (str_starts_with($entry, 'role:')) {
                $roleRule = $entry;
            }
        }

        $roles = explode(',', substr($roleRule, strlen('role:')));
        $this->assertContains('admin', $roles);
        $this->assertContains('superadmin', $roles);
        $this->assertNotContains('staff', $roles, 'Staff must never be able to reset another account password');
        $this->assertContains('auth', $found);
    }

    /**
     * Back-office pages ignore the session language; resident pages honour it.
     *
     * The stored choice must survive, because the same staff member browsing
     * the resident side should still see the language they picked.
     */
    public function testBackOfficePagesAlwaysRenderInEnglish(): void
    {
        $_SESSION = [];
        set_locale('fil');

        $GLOBALS['bg_is_back_office'] = true;
        $this->assertSame('en', current_locale(), 'Staff pages must ignore the chosen language');

        $GLOBALS['bg_is_back_office'] = false;
        $this->assertSame('fil', current_locale(), 'Resident pages must honour the chosen language');
        $this->assertSame('fil', $_SESSION['locale'], 'The stored preference must not be overwritten');
    }
}
