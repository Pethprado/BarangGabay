<?php
declare(strict_types=1);

use App\Services\TranslationOutcome;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * A resident must never be shown one language under another's label.
 *
 * The failure is quiet and complete: a reader taps MN, is served Filipino,
 * and has no way to tell whether the switch is broken, whether Manobo looks
 * like that, or whether the translation simply does not exist. Nothing
 * errors. Nothing looks wrong. They just read the wrong language and
 * believe it is theirs.
 *
 * localised_content() is where that is decided, so it is what is tested.
 */
final class ResidentLanguageHonestyTest extends TestCase
{
    /** A Filipino post with a machine English version and no Manobo. */
    private function post(): array
    {
        return [
            'title'          => 'Libreng bakuna bukas',
            'body'           => 'May libreng bakuna bukas sa Barangay Hall.',
            'source_lang'    => 'fil',
            'title_en'       => 'Free vaccine tomorrow',
            'body_en'        => 'There is a free vaccine tomorrow at the Barangay Hall.',
            'en_is_auto'     => 1,
            'title_manobo'   => '',
            'body_manobo'    => '',
            'manobo_is_auto' => 0,
        ];
    }

    /**
     * The one thing the return value could not say before.
     *
     * 'locale' is what the reader asked for. Serving the source text under
     * that key made the fallback indistinguishable from a real translation
     * to every caller — so no caller COULD name what it had fallen back to,
     * and the notice could only report an absence.
     */
    public function testTheShownLanguageIsReportedSeparatelyFromTheRequestedOne(): void
    {
        $row = $this->post();

        $msm = localised_content($row, 'body', 'msm');

        $this->assertSame('msm', $msm['locale'], 'the request should still be recorded');
        $this->assertSame(
            'fil',
            $msm['shown_locale'],
            'the reader is looking at Filipino and nothing said so'
        );
        $this->assertFalse($msm['translated']);
    }

    /** @dataProvider servings */
    public function testWhatIsServedIsNamedCorrectly(
        string $asked,
        string $shown,
        bool   $translated,
        string $because
    ): void {
        $got = localised_content($this->post(), 'body', $asked);

        $this->assertSame($shown, $got['shown_locale'], $because);
        $this->assertSame($translated, $got['translated'], $because);
    }

    /** @return array<string, array{0:string,1:string,2:bool,3:string}> */
    public static function servings(): array
    {
        return [
            'the source language is itself' => [
                'fil', 'fil', true, 'a Filipino post asked for in Filipino is the original',
            ],
            'a real translation is what it says' => [
                'en', 'en', true, 'the English version exists and is English',
            ],
            'a missing one falls back and admits it' => [
                'msm', 'fil', false, 'there is no Manobo, so Filipino is served and named',
            ],
        ];
    }

    /**
     * A post written in English must behave symmetrically. No language is
     * special — the bug this replaced assumed Filipino always was.
     */
    public function testAnEnglishPostIsTreatedTheSameWay(): void
    {
        $row = [
            'title'       => 'Free vaccine tomorrow',
            'body'        => 'There is a free vaccine tomorrow.',
            'source_lang' => 'en',
            'title_fil'   => '',
            'body_fil'    => '',
        ];

        $en = localised_content($row, 'body', 'en');
        $this->assertSame('en', $en['shown_locale']);
        $this->assertTrue($en['translated']);

        $fil = localised_content($row, 'body', 'fil');
        $this->assertSame('en', $fil['shown_locale'], 'Filipino is missing, so English is served');
        $this->assertFalse($fil['translated']);
    }

    /**
     * The notice must say BOTH things: what is missing and what is on the
     * screen instead. Half of it leaves the reader to guess the other half.
     */
    public function testTheNoticeNamesTheMissingAndTheShownLanguage(): void
    {
        $partial = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/shared/_translation-notice.php'
        );

        $this->assertStringContainsString('shown_locale', $partial,
            'the notice cannot name what it is showing');
        $this->assertStringContainsString('content_lang.showing_instead', $partial);

        foreach ([require \dirname(__DIR__, 2) . '/lang/en.php',
                  require \dirname(__DIR__, 2) . '/lang/fil.php'] as $lang) {
            $this->assertArrayHasKey('showing_instead', $lang['content_lang']);
            $this->assertStringContainsString(':language', $lang['content_lang']['showing_instead']);
            $this->assertStringContainsString(':language', $lang['content_lang']['no_translation']);
        }
    }

    /**
     * And it must not name a language when there is nothing to name — a
     * reader whose language IS the source should see no second sentence.
     */
    public function testNoSecondSentenceWhenTheLanguageMatches(): void
    {
        $partial = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/shared/_translation-notice.php'
        );

        $this->assertMatchesRegularExpression(
            "~\\\$__tnShown\s*!==\s*\\\$__tnPick\['locale'\]~",
            $partial,
            'the notice would tell a Filipino reader they are reading Filipino'
        );
    }

    // ── Voice ────────────────────────────────────────────────────────────

    /**
     * Audio for a language can only be made after the TEXT for it exists.
     *
     * Generated first, there is nothing to read; generated anyway, the
     * player reads one language under another's label — the same lie as
     * the text side, in a medium where it is harder to notice.
     */
    public function testAudioIsNotAttemptedForALanguageWithNoText(): void
    {
        $src = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/PostAudioService.php'
        );

        /*
         * Scoped to ensure(), and to a short window.
         *
         * A first version searched the whole file with /s, so `.*?` could
         * leap from one method to another: the guard was replaced by
         * `if (false)` and the pattern still matched, pairing an unrelated
         * $script['available'] in tracks() with the NO_SOURCE_TEXT constant
         * hundreds of lines later. A regex that can match across the whole
         * file is not checking the thing it names.
         */
        $at = strpos($src, 'public function ensure(');
        $this->assertNotFalse($at, 'ensure() is gone');

        $next = strpos($src, "\n    private function", $at);
        $body = substr($src, $at, ($next !== false ? $next : \strlen($src)) - $at);

        $this->assertMatchesRegularExpression(
            "~if \(!\\\$script\['available'\]\) \{~",
            $body,
            'ensure() does not check that the language has text before making audio for it'
        );

        // The reason must be recorded in the same branch, not somewhere else.
        $guard = strpos($body, "if (!\$script['available']) {");
        $this->assertNotFalse($guard);

        $branch = substr($body, $guard, 400);
        $this->assertStringContainsString(
            'NO_SOURCE_TEXT',
            $branch,
            'a language skipped for having no text records no reason, so nobody can find out why'
        );
    }

    /**
     * Audio failures use the translation vocabulary, not a second one.
     *
     * A barangay secretary looking at a post with no Manobo audio should
     * not have to learn a different set of words to find out why.
     */
    public function testAudioOutcomesUseTheSharedReasonCodes(): void
    {
        $src = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/PostAudioService.php'
        );

        $this->assertStringContainsString('TranslationAttempt::record', $src);
        $this->assertStringContainsString("'audio'", $src);

        foreach ([TranslationOutcome::NO_SOURCE_TEXT,
                  TranslationOutcome::NO_TTS_PROVIDER,
                  TranslationOutcome::TEXT_TOO_LONG,
                  TranslationOutcome::PROVIDER_ERROR] as $code) {
            $this->assertStringContainsString($code, $src, "audio never records {$code}");
        }
    }

    /**
     * The player must say which voice is speaking.
     *
     * Manobo is read by a Filipino voice, and a Manobo speaker has a right
     * to know that before they press play rather than after. When no
     * provider is configured at all it is the browser's own voice, and
     * calling that "the AI voice" would be its own small lie.
     */
    public function testThePlayerNamesTheVoiceItIsUsing(): void
    {
        $js = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/public/assets/js/voice-reader.js'
        );

        $this->assertStringContainsString('sourceLabel', $js);
        $this->assertStringContainsString('source_approx', $js, 'the Manobo approximation is not labelled');
        $this->assertStringContainsString('source_device', $js, 'the browser fallback is not labelled');

        $partial = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/shared/_voice-reader.php'
        );
        $this->assertStringContainsString('x-text="sourceLabel"', $partial,
            'the label is computed and never rendered');

        // And both sentences exist, in both languages.
        foreach ([require \dirname(__DIR__, 2) . '/lang/en.php',
                  require \dirname(__DIR__, 2) . '/lang/fil.php'] as $lang) {
            $this->assertArrayHasKey('source_approx', $lang['voice_reader']);
            $this->assertArrayHasKey('source_device', $lang['voice_reader']);
        }
    }

    /**
     * The Manobo label must actually say it is a Filipino voice. "AI voice"
     * alone would be true and useless.
     */
    public function testTheManoboLabelNamesTheVoiceItBorrows(): void
    {
        $en = require \dirname(__DIR__, 2) . '/lang/en.php';

        $this->assertStringContainsStringIgnoringCase(
            'Filipino',
            $en['voice_reader']['source_approx'],
            'the approximation label does not say whose voice it is'
        );
        $this->assertStringContainsStringIgnoringCase(
            'Manobo',
            $en['voice_reader']['source_approx']
        );
    }
}
