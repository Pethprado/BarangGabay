<?php
declare(strict_types=1);

use App\Services\TranslationHealth;
use App\Services\TranslationOutcome;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The page and the script must not be able to disagree.
 *
 * A defence-day check that reports something different from the page being
 * demonstrated is worse than having neither: it turns a five-second
 * question into an argument about which one is lying. So both read the
 * same service, and these tests are mostly about keeping it that way.
 */
final class TranslationHealthTest extends TestCase
{
    private function cli(): string
    {
        return (string) file_get_contents(
            \dirname(__DIR__, 2) . '/tools/check-translations.php'
        );
    }

    private function view(): string
    {
        return (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/admin/translations/health.php'
        );
    }

    public function testBothReadTheSameService(): void
    {
        $this->assertStringContainsString('TranslationHealth', $this->cli(),
            'the console check computes its own answer');

        $controller = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/controllers/TranslationHealthController.php'
        );
        $this->assertStringContainsString('TranslationHealth::', $controller);
    }

    /**
     * Whether a language is MISSING comes from the content tables, not
     * from the attempts table.
     *
     * A post published before any of this existed has no attempt row at
     * all. Indexing on attempts would report exactly those posts as
     * complete — and they are the ones most likely to be broken.
     */
    public function testMissingIsReadFromTheContentNotTheAttempts(): void
    {
        /*
         * Comments stripped first.
         *
         * This file's own docblock explains that it reads through
         * TranslationService::statusFor(), so searching the raw source for
         * that string matched the PROSE and stayed green when the call was
         * replaced by a hardcoded "everything is fine" array. The class
         * describing the rule is not the class following it.
         */
        $src = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/TranslationHealth.php'
        );
        $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);
        $src = (string) preg_replace('#(?<!:)//[^\n]*#', '', $src);

        $this->assertStringContainsString(
            'TranslationService::statusFor(',
            $src,
            'the health view decides what is missing on its own terms, so it can '
            . 'disagree with the badges and the resident notice'
        );
    }

    /**
     * Every provider that is down must name the variable to set.
     *
     * "Manobo needs ANTHROPIC_API_KEY" is a two-minute fix that went
     * unmade for weeks only because nothing said it out loud.
     */
    public function testEveryProviderThatIsDownNamesItsEnvKey(): void
    {
        foreach (TranslationHealth::providers() as $p) {
            $this->assertArrayHasKey('ready', $p);
            $this->assertArrayHasKey('envKey', $p);
            $this->assertArrayHasKey('reason', $p);

            if ($p['ready']) {
                $this->assertNull($p['reason'], "{$p['label']} is ready but carries a reason");
                continue;
            }

            $this->assertNotNull($p['reason'], "{$p['label']} is down with no reason");
            $this->assertTrue(
                TranslationOutcome::isKnown((string) $p['reason']),
                "{$p['label']} uses a reason code nothing can translate"
            );
        }
    }

    /** All three providers are reported, not just the two that translate. */
    public function testAllThreeProvidersAreReported(): void
    {
        $names = array_column(TranslationHealth::providers(), 'name');

        $this->assertContains('free_translator', $names);
        $this->assertContains('anthropic', $names);
        $this->assertContains('tts', $names, 'the voice provider is missing from the health view');
    }

    /**
     * The bulk retry is offered per language only.
     *
     * "Retry everything in every language" would spend the entire daily
     * allowance in one press and is never what anybody means by it.
     */
    public function testBulkRetryRequiresALanguage(): void
    {
        $view = $this->view();

        $this->assertMatchesRegularExpression(
            "~if \(\\\$lang !== ''\):.*?admin/retranslate-all~s",
            $view,
            'the bulk retry button is offered with no language chosen'
        );

        $controller = (string) preg_replace(
            '#/\*.*?\*/#s',
            '',
            (string) file_get_contents(
                \dirname(__DIR__, 2) . '/app/controllers/TranslationHealthController.php'
            )
        );

        $at   = strpos($controller, 'function retryAll()');
        $body = substr($controller, (int) $at, 1200);

        $this->assertStringContainsString('check_csrf()', $body, 'the bulk retry has no CSRF check');
        $this->assertStringContainsString('TranslationAttempt::LANGS', $body,
            'the language is not validated against the known list');
        $this->assertStringContainsString('BULK_LIMIT', $body,
            'the bulk retry is uncapped and would time out the request it runs in');
    }

    public function testTheHealthRoutesAreProtected(): void
    {
        $routes = require \dirname(__DIR__, 2) . '/routes/web.php';

        $seen = [];
        foreach ($routes as $route) {
            $path = $route[1] ?? '';
            if (!\in_array($path, ['/admin/translation-health', '/admin/retranslate-all'], true)) {
                continue;
            }
            $seen[$path] = true;

            $this->assertContains('auth', $route[3] ?? [], "{$path} is not behind auth");
            $this->assertContains('role:admin,staff', $route[3] ?? [], "{$path} is open to residents");
        }

        $this->assertArrayHasKey('/admin/translation-health', $seen, 'the health page is not routed');
        $this->assertArrayHasKey('/admin/retranslate-all', $seen, 'the bulk retry is not routed');

        // The page reads; the retry writes. A GET that retries would be
        // triggered by any crawler that follows a link.
        foreach ($routes as $route) {
            if (($route[1] ?? '') === '/admin/retranslate-all') {
                $this->assertSame('POST', $route[0]);
            }
            if (($route[1] ?? '') === '/admin/translation-health') {
                $this->assertSame('GET', $route[0]);
            }
        }
    }

    /**
     * The console check must be usable in a pre-deploy step, which means
     * its exit codes have to mean something.
     */
    public function testTheConsoleCheckExitsMeaningfully(): void
    {
        $cli = $this->cli();

        $this->assertStringContainsString("PHP_SAPI !== 'cli'", $cli,
            'the check is reachable from a browser');
        $this->assertStringContainsString('exit(0)', $cli, 'there is no success exit');
        $this->assertStringContainsString('exit(1)', $cli, 'a gap does not fail the check');
        $this->assertStringContainsString('exit(2)', $cli, 'a broken check is indistinguishable from a clean one');
    }

    /**
     * And it must speak the same language as the page it mirrors.
     *
     * Anything outside the front controller defaults to the resident side,
     * so this printed Filipino while the admin page printed English —
     * a mirror that has to be translated before it can be compared.
     */
    public function testTheConsoleCheckRendersAsTheBackOfficeDoes(): void
    {
        $this->assertStringContainsString(
            "\$GLOBALS['bg_is_back_office'] = true",
            $this->cli(),
            'the console check renders in the resident language while the page it mirrors does not'
        );
    }

    /**
     * A language with no text is not also reported as missing audio.
     *
     * There is nothing to read, so listing it twice puts one underlying
     * gap on the page as two work items — and fixing the text fixes both.
     */
    public function testALanguageWithNoTextIsNotAlsoCountedAsMissingAudio(): void
    {
        $src = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/TranslationHealth.php'
        );

        $at   = strpos($src, 'function audioGaps(');
        $body = substr($src, (int) $at, 700);

        $this->assertMatchesRegularExpression(
            '~if \(!\$hasText\) \{\s*continue;~',
            $body,
            'a language with no text would be listed as missing audio as well'
        );
    }

    public function testEveryLabelOnThePageIsTranslated(): void
    {
        $en  = require \dirname(__DIR__, 2) . '/lang/en.php';
        $fil = require \dirname(__DIR__, 2) . '/lang/fil.php';

        $keys = [
            'page_title', 'page_sub', 'providers_title', 'provider_ready',
            'count_posts', 'count_text', 'count_audio', 'count_queued',
            'filter_label', 'filter_all', 'retry_all', 'retried_all',
            'all_clear', 'all_clear_sub',
            'col_post', 'col_missing', 'col_why',
            'type_announcement', 'type_event', 'type_ordinance',
            'written_in_short', 'audio_only',
        ];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $en['translation_health'], "en.php has no {$key}");
            $this->assertArrayHasKey($key, $fil['translation_health'], "fil.php has no {$key}");
        }

        foreach ([':done', ':attempted', ':left', ':lang'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $en['translation_health']['retried_all']);
            $this->assertStringContainsString($placeholder, $fil['translation_health']['retried_all']);
        }
    }

    /** The scan is bounded — this renders a page on a shared host. */
    public function testTheScanIsBounded(): void
    {
        $this->assertGreaterThan(0, TranslationHealth::SCAN_LIMIT);
        $this->assertLessThanOrEqual(1000, TranslationHealth::SCAN_LIMIT);

        $src = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/TranslationHealth.php'
        );
        $this->assertStringContainsString('LIMIT {$limit}', $src);
        $this->assertStringContainsString('ORDER BY id DESC', $src,
            'the scan should look at the newest posts, which are the ones anyone is waiting on');
    }
}
