<?php
declare(strict_types=1);

use App\Services\TranslationOutcome;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The badge is only worth adding if it survives contact with the things
 * that made the old one useless.
 *
 * The chips already existed: FIL / EN / MN with a ✓ or an ✗. What they
 * could not say is WHY, and a red MN meant any of four different problems
 * with four different fixes — no API key, no credits, the dictionary
 * covered too little, or nothing has tried yet — all shown identically.
 * Staff guessed, or re-saved the post hoping it would take.
 *
 * These tests are about the parts of that which can go quietly wrong.
 */
final class LanguageBadgeTest extends TestCase
{
    private function partial(): string
    {
        return (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/shared/_language-badges.php'
        );
    }

    /**
     * The chip is an action, so it must be a real button.
     *
     * A <span> with a click handler is unreachable by keyboard and invisible
     * to a screen reader — on an admin tool a barangay secretary uses all
     * day, that is not a detail.
     */
    public function testAClickableChipIsARealButton(): void
    {
        $src = $this->partial();

        $this->assertStringContainsString('<button type="button"', $src);
        $this->assertStringContainsString('lang-chip--', $src);
    }

    /**
     * Only the two states worth acting on open anything.
     *
     * The source language needs no explanation, and text a person typed
     * needs no fix. Making those clickable would train staff to open panels
     * that say nothing.
     */
    public function testOnlyMissingAndMachineAreClickable(): void
    {
        $this->assertMatchesRegularExpression(
            "~\\\$lbClickable\s*=\s*\\\\in_array\(\\\$lbState,\s*\['missing',\s*'machine'\],\s*true\)~",
            $this->partial()
        );
    }

    /**
     * The JSON payload rides in an HTML attribute, so it must be escaped
     * for one — bare json_encode() there ends the attribute at the first
     * quote in a post title and breaks the page.
     */
    public function testThePayloadIsEscapedForAnAttribute(): void
    {
        $src = $this->partial();

        $this->assertStringContainsString('e(json_encode(', $src);
        $this->assertStringContainsString('JSON_HEX_APOS', $src);
        $this->assertStringContainsString('JSON_HEX_QUOT', $src);
    }

    /**
     * Manobo is 'msm' as a locale and '_manobo' as a column suffix.
     * Reading title_msm finds nothing and reports a filled language as
     * missing, which is the bug this whole feature exists to prevent.
     */
    public function testManoboMapsToItsColumnSuffix(): void
    {
        $this->assertStringContainsString("'msm' => 'manobo'", $this->partial());
    }

    /**
     * A language nothing has ever tried has no attempt row. Saying "no
     * reason recorded" is honest; inventing a provider error is not.
     */
    public function testALanguageNeverTriedGetsItsOwnHonestReason(): void
    {
        $src = $this->partial();

        $this->assertStringContainsString("'never_tried'", $src);

        foreach ([require \dirname(__DIR__, 2) . '/lang/en.php',
                  require \dirname(__DIR__, 2) . '/lang/fil.php'] as $lang) {
            $this->assertArrayHasKey('never_tried', $lang['translation_health']);
            $this->assertArrayHasKey('never_tried_fix', $lang['translation_health']);
        }
    }

    // ── The retry endpoint ───────────────────────────────────────────────

    /**
     * The controller's source with comments removed.
     *
     * Searching the raw file for "check_csrf()" stayed green when the call
     * was commented out — the string was still there, in a comment, doing
     * nothing. For the CSRF check in particular that is the difference
     * between a guard and the appearance of one, so the comments go before
     * anything is asserted.
     */
    private function controller(): string
    {
        $src = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/controllers/TranslationHealthController.php'
        );

        $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);   // block comments
        $src = (string) preg_replace('#(?<!:)//[^\n]*#', '', $src); // line comments

        return $src;
    }

    public function testTheRetryEndpointIsProtected(): void
    {
        $src = $this->controller();

        $this->assertStringContainsString('check_csrf()', $src, 'the retry POST has no CSRF check');

        $routes = require \dirname(__DIR__, 2) . '/routes/web.php';
        $found  = false;
        foreach ($routes as $route) {
            if (($route[1] ?? '') === '/admin/retranslate') {
                $found = true;
                $this->assertSame('POST', $route[0], 'retranslate must not be reachable by GET');
                $this->assertContains('auth', $route[3] ?? []);
                $this->assertContains('role:admin,staff', $route[3] ?? []);
            }
        }
        $this->assertTrue($found, '/admin/retranslate is not routed');
    }

    /**
     * The redirect comes from POST data, so it is an open redirect unless
     * something refuses the targets that point off-site.
     */
    public function testTheReturnAddressCannotLeaveTheAdminPanel(): void
    {
        $src = $this->controller();

        $this->assertStringContainsString('goBack', $src);
        $this->assertMatchesRegularExpression(
            '~preg_match\(.~',
            $src,
            'the redirect target is not validated at all'
        );
        $this->assertStringContainsString('/admin/announcements', $src, 'no safe default to fall back to');
    }

    /**
     * Pressing the button must override the stored backoff.
     *
     * The saved retry time was computed before the staff member added
     * credits or shortened the text. Honouring it would make the button
     * appear to do nothing — and a permanently-failed row, which has
     * retry_after NULL by design, would never run at all.
     */
    public function testPressingTheButtonMakesTheLanguageDueImmediately(): void
    {
        /*
         * Scoped to retry(), not the whole controller.
         *
         * A file-wide search for makeDue stopped meaning anything once
         * retryAll() was added in the health page: removing the call from
         * retry() left the other one in place and the test stayed green,
         * while the single-language button silently went back to honouring
         * a backoff calculated before the staff member fixed anything.
         */
        $src = $this->controller();

        $at = strpos($src, 'public function retry()');
        $this->assertNotFalse($at, 'retry() is gone');

        $next = strpos($src, 'private function goBack', $at);
        $body = substr($src, $at, ($next !== false ? $next : \strlen($src)) - $at);

        $this->assertStringContainsString(
            'makeDue',
            $body,
            'retry() honours the stored backoff, so the button appears to do nothing'
        );

        $model = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/models/TranslationAttempt.php'
        );
        $this->assertStringContainsString('function makeDue', $model);
        $this->assertStringContainsString('DATE_SUB(NOW()', $model, 'makeDue does not actually move the clock back');
    }

    /**
     * When it fails, the flash says what happened AND what to do. A bare
     * "could not translate" is exactly what the system did before.
     */
    public function testAFailedRetryExplainsItself(): void
    {
        $src = $this->controller();

        $this->assertStringContainsString('messageKey', $src);
        $this->assertStringContainsString('fixKey', $src);

        foreach ([require \dirname(__DIR__, 2) . '/lang/en.php',
                  require \dirname(__DIR__, 2) . '/lang/fil.php'] as $lang) {
            $message = $lang['translation_health']['retried_failed'];
            $this->assertStringContainsString(':reason', $message);
            $this->assertStringContainsString(':fix', $message);
        }
    }

    /**
     * Every state and every reason code has a sentence in both languages —
     * a panel that renders a raw key like "translation_reason.no_credits"
     * is worse than no panel.
     */
    public function testEveryStateReadsInBothLanguages(): void
    {
        $en  = require \dirname(__DIR__, 2) . '/lang/en.php';
        $fil = require \dirname(__DIR__, 2) . '/lang/fil.php';

        foreach (['source', 'manual', 'machine', 'missing'] as $state) {
            $this->assertArrayHasKey("state_{$state}", $en['translation_health']);
            $this->assertArrayHasKey("state_{$state}", $fil['translation_health']);
        }

        foreach (TranslationOutcome::all() as $code) {
            $key = \strtolower($code);
            $this->assertArrayHasKey($key, $en['translation_reason']);
            $this->assertArrayHasKey($key, $fil['translation_reason']);
        }
    }

    /**
     * One panel per page, not one per chip.
     *
     * Twenty posts carry sixty chips. Sixty hidden popovers is sixty copies
     * of the same markup, and a popover anchored in a scrolling table clips
     * against its container.
     */
    public function testTheListPageIncludesExactlyOnePanel(): void
    {
        $index = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/admin/announcements/index.php'
        );

        $this->assertSame(
            1,
            substr_count($index, '_language-badge-panel.php'),
            'the detail panel should be included exactly once per page'
        );
        $this->assertStringContainsString('_language-badges.php', $index);
    }

    /**
     * No admin view may nest one <form> inside another.
     *
     * Nested forms are invalid HTML: the browser drops the inner opening
     * tag while parsing, so the inner form's submit button posts the OUTER
     * form. It does not error, it does not look broken — it just does the
     * wrong thing.
     *
     * Two live instances of this were found by rendering the pages:
     *
     *   - the retry forms this feature added, written inside the edit form
     *     because the status card that owns them sits there. "Translate
     *     now" would have saved the post.
     *   - the events edit page's Delete button, which predates this work.
     *     Pressing Delete saved the event instead of deleting it. The
     *     announcements page had already been fixed the same way; events
     *     was missed, and nothing caught it.
     *
     * Static, so it runs without a server: counts <form> and </form> in
     * source order per file, ignoring comments and PHP.
     *
     * @dataProvider adminViewsWithForms
     */
    public function testNoAdminViewNestsForms(string $relative): void
    {
        $src = (string) file_get_contents(\dirname(__DIR__, 2) . '/app/views/' . $relative);

        /* Comments first. One of these files explains the form="" trick in
           prose that contains the word "<form>" three times, and counting
           those reported three nested forms on a page that has none. */
        $src = (string) preg_replace('~<!--.*?-->~s', '', $src);
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);

        $tokens = [];
        if (preg_match_all('~<form\b[^>]*>~i', $src, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$tag, $at]) { $tokens[$at] = 'open'; }
        }
        $pos = 0;
        while (($pos = stripos($src, '</form>', $pos)) !== false) { $tokens[$pos] = 'close'; $pos++; }
        ksort($tokens);

        $depth = 0;
        $worst = 0;
        foreach ($tokens as $kind) {
            if ($kind === 'open') { $depth++; $worst = max($worst, $depth); }
            else { $depth = max(0, $depth - 1); }
        }

        $this->assertLessThanOrEqual(
            1,
            $worst,
            "{$relative} nests a <form> inside another. The inner form's submit button "
            . 'will post the OUTER form — use form="someId" on the button and put the '
            . 'form at the end of the page instead.'
        );
    }

    /** @return array<string, array{0:string}> */
    public static function adminViewsWithForms(): array
    {
        $root  = \dirname(__DIR__, 2) . '/app/views/admin';
        $cases = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) { continue; }

            $src = (string) file_get_contents($file->getPathname());
            if (!preg_match('~<form\b~i', $src)) { continue; }

            $name = str_replace('\\', '/', substr($file->getPathname(), \strlen(\dirname(__DIR__, 2) . '/app/views/')));
            $cases[$name] = [$name];
        }

        return $cases;
    }

    /**
     * The panel is hidden by Alpine, and this stylesheet has no global
     * [x-cloak] rule — so without a scoped one it flashes fully open on
     * every admin page load before Alpine boots.
     */
    public function testThePanelIsCloakedUntilAlpineBoots(): void
    {
        $css = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/assets/css/admin.css'
        );

        $this->assertMatchesRegularExpression(
            '~\.lang-panel\[x-cloak\][^{]*\{[^}]*display:\s*none~s',
            $css,
            'the detail panel would flash open on every page load'
        );
    }
}
