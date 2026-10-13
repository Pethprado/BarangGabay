<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The word-level Manobo substitution behind the MB language switch.
 *
 * manobo_gloss_phrase() is the fallback used when a UI label is not in the
 * dataset as a whole phrase. Its guards exist because the unguarded version,
 * run against the real dataset, produced actively worse output than leaving
 * the label in English — each test below pins one of those guards.
 *
 * These assertions use the live bundled dataset rather than a fixture: the
 * point is that the shipped word list behaves, and a test double would not
 * catch a bad row being added to the CSV.
 */
final class ManoboGlossTest extends TestCase
{
    /** A term known to be in the bundled dataset, or null if it is unavailable. */
    private function knownTerm(): ?string
    {
        foreach (['eye', 'cow', 'cough', 'six', 'two', 'shoulder', 'lip'] as $word) {
            if (manobo_word($word) !== null) {
                return $word;
            }
        }
        return null;
    }

    protected function setUp(): void
    {
        if ($this->knownTerm() === null) {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }
    }

    // ── The map itself ───────────────────────────────────────────────────

    public function testDatasetIndexesBothEnglishAndTagalog(): void
    {
        $map = manobo_word_map();

        $this->assertNotEmpty($map);
        $this->assertSame('mata', $map['eye'] ?? null, 'English side indexed');
        $this->assertSame('mata', $map['mata'] ?? null, 'Tagalog side indexed');
    }

    public function testWholePhraseLookupReturnsNullForAnUnknownWord(): void
    {
        $this->assertNull(manobo_word('zzzz-not-a-word'));
    }

    // ── Singular / plural ────────────────────────────────────────────────

    public function testAPluralLabelFindsASingularDictionaryEntry(): void
    {
        // The dataset stores "eye"; the UI says "Eyes". A speaker should not
        // have to add both forms for the label to translate.
        $this->assertSame(manobo_word('eye'), manobo_word('eyes'));
        $this->assertNotNull(manobo_word('eyes'));
    }

    public function testPluralHandlingIsCaseInsensitiveLikeTheRestOfLookup(): void
    {
        $this->assertSame(manobo_word('eye'), manobo_word('Eyes'));
    }

    /** @dataProvider notPluralProvider */
    public function testWordsThatMerelyEndInSAreNotStripped(string $word): void
    {
        // "address" must never be reduced to "addres": a wrong translation is
        // worse than falling back to English.
        $this->assertNull(manobo_singular($word), "{$word} is not a plural");
    }

    public static function notPluralProvider(): array
    {
        return [['address'], ['status'], ['analysis'], ['pass'], ['bus']];
    }

    /** @dataProvider pluralProvider */
    public function testRecognisedPluralsReduceCorrectly(string $plural, string $singular): void
    {
        $this->assertSame($singular, manobo_singular($plural));
    }

    public static function pluralProvider(): array
    {
        return [
            ['events', 'event'],
            ['announcements', 'announcement'],
            ['ordinances', 'ordinance'],
            ['policies', 'policy'],
            ['boxes', 'box'],
            ['churches', 'church'],
        ];
    }

    public function testVeryShortWordsAreLeftAlone(): void
    {
        $this->assertNull(manobo_singular('is'));
        $this->assertNull(manobo_singular('as'));
    }

    // ── Guard: placeholders are never substituted ────────────────────────

    public function testPlaceholderTokensAreLeftIntact(): void
    {
        // Regression guard. Unguarded, ":name" became ":'ngadan", which broke
        // t()'s own :placeholder substitution and silently dropped the
        // resident's name out of the greeting.
        foreach ([':name', ':n', ':total', ':zone'] as $placeholder) {
            $result = manobo_gloss_phrase("Hi :name") ?? '';
            $this->assertStringNotContainsString(
                ":'",
                $result,
                'a placeholder must never be rewritten'
            );
        }

        $greeting = manobo_gloss_phrase('Welcome, :name');
        if ($greeting !== null) {
            $this->assertStringContainsString(':name', $greeting);
        }
        $this->assertTrue(true); // null (left English) is equally acceptable here
    }

    // ── Guard: long labels are left alone ────────────────────────────────

    public function testLongSentenceWithOneMatchIsLeftInEnglish(): void
    {
        $term = $this->knownTerm();

        // One hit inside a long sentence reads as a typo, not as Manobo.
        $long = "this is a fairly long sentence that mentions the {$term} exactly once";
        $this->assertNull(manobo_gloss_phrase($long));
    }

    public function testShortLabelWhereEveryWordConvertsIsGlossed(): void
    {
        $term = $this->knownTerm();

        $result = manobo_gloss_phrase($term);
        $this->assertNotNull($result);
        $this->assertSame(manobo_word($term), $result);
    }

    // ── Guard: a minimum share of the label must convert ─────────────────

    public function testLabelBelowTheCoverageThresholdIsRejected(): void
    {
        $term = $this->knownTerm();

        // 1 of 4 words = 25%, under the 50% floor.
        $this->assertNull(manobo_gloss_phrase("alpha beta gamma {$term}"));
    }

    public function testLabelAtOrAboveTheThresholdIsAccepted(): void
    {
        $term = $this->knownTerm();

        // 1 of 2 words = 50%, exactly on the floor.
        $result = manobo_gloss_phrase("alpha {$term}");
        $this->assertNotNull($result);
        $this->assertStringContainsString(manobo_word($term), $result);
        $this->assertStringContainsString('alpha', $result, 'uncovered words survive untouched');
    }

    public function testNothingMatchingReturnsNullSoCallersFallBackToEnglish(): void
    {
        $this->assertNull(manobo_gloss_phrase('alpha beta gamma delta'));
    }

    // ── Formatting is preserved ──────────────────────────────────────────

    public function testLeadingCapitalIsCarriedOntoTheReplacement(): void
    {
        $term = $this->knownTerm();
        $expected = manobo_word($term);

        $result = manobo_gloss_phrase(ucfirst($term));
        $this->assertNotNull($result);
        $this->assertSame(
            mb_strtoupper(mb_substr($expected, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($expected, 1, null, 'UTF-8'),
            $result
        );
    }

    public function testAllCapsIsCarriedOntoTheReplacement(): void
    {
        $term = $this->knownTerm();

        $result = manobo_gloss_phrase(mb_strtoupper($term, 'UTF-8'));
        $this->assertNotNull($result);
        $this->assertSame(mb_strtoupper(manobo_word($term), 'UTF-8'), $result);
    }

    public function testPunctuationAndSpacingSurvive(): void
    {
        $term = $this->knownTerm();

        $result = manobo_gloss_phrase("{$term}, {$term}!");
        $this->assertNotNull($result);
        $this->assertStringContainsString(',', $result);
        $this->assertStringContainsString('!', $result);
    }

    public function testEmptyInputIsRejected(): void
    {
        $this->assertNull(manobo_gloss_phrase(''));
        $this->assertNull(manobo_gloss_phrase('   '));
    }

    // ── t() integration ──────────────────────────────────────────────────

    public function testTranslateNeverReturnsTheRawKeyUnderTheManoboLocale(): void
    {
        $_SESSION = [];
        set_locale('msm');

        foreach (['nav.home', 'nav.announcements', 'common.save', 'res_manobo.title'] as $key) {
            $value = t($key);
            $this->assertNotSame($key, $value, "t('{$key}') leaked its key");
            $this->assertNotSame('', $value);
        }

        set_locale('fil');
    }

    public function testManoboLocaleUsesBisayaRatherThanEnglishOrTagalog(): void
    {
        $_SESSION = [];
        set_locale('en');
        $english = t('res_ordinances.ai_working');
        set_locale('fil');
        $filipino = t('res_ordinances.ai_working');

        set_locale('msm');
        $manobo = t('res_ordinances.ai_working');

        // A long sentence the Manobo dataset cannot cover whole. MN must show
        // the generated Manobo/Bisaya form, never the English or Tagalog one.
        // See manobo_ui_text() in helpers.php.
        $this->assertNotSame($english, $manobo, 'MN must not fall through to English');
        $this->assertNotSame($filipino, $manobo, 'MN must not fall through to Tagalog');
        $this->assertSame((require __DIR__ . '/../../lang/msm_auto.php')['res_ordinances.ai_working'], $manobo);

        set_locale('fil');
    }

    public function testEveryInterfaceStringHasAManoboForm(): void
    {
        $flatten = static function (array $arr, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($arr as $k => $v) {
                $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
                $out += is_array($v) ? $flatten($v, $key) : [$key => $v];
            }
            return $out;
        };

        $keys    = array_keys($flatten(require __DIR__ . '/../../lang/en.php'));
        $auto    = require __DIR__ . '/../../lang/msm_auto.php';
        $phrases = array_keys(require __DIR__ . '/../../lang/phrases/en.php');
        $mnPhr   = require __DIR__ . '/../../lang/phrases/msm.php';

        $this->assertSame([], array_values(array_diff($keys, array_keys($auto))),
            'lang/en.php has keys with no MN form — run php tools/generate-manobo-ui.php');
        $this->assertSame([], array_values(array_diff($phrases, array_keys($mnPhr))),
            'lang/phrases/en.php has phrases with no MN form — run php tools/generate-manobo-ui.php');
    }

    public function testPhrasesFollowTheChosenLanguage(): void
    {
        $_SESSION = [];

        set_locale('en');
        $this->assertSame('Continue', __('Magpatuloy (Continue)'));
        $this->assertSame('Event not found.', __('Event not found.'));

        set_locale('fil');
        $this->assertSame('Magpatuloy', __('Magpatuloy (Continue)'));
        $this->assertSame('Hindi nahanap ang event.', __('Event not found.'));

        set_locale('msm');
        $mn = __('Magpatuloy (Continue)');
        $this->assertNotSame('Continue', $mn);
        $this->assertNotSame('Magpatuloy', $mn);

        set_locale('fil');
    }

    public function testPhrasePlaceholdersAreFilledInEveryLanguage(): void
    {
        $_SESSION = [];
        foreach (['en', 'fil', 'msm'] as $locale) {
            set_locale($locale);
            $text = __('Nalikha na ang inyong kahilingan (:ref). Kumpletuhin ang pagbabayad gamit ang online checkout.', ['ref' => 'DOC-123']);
            $this->assertStringContainsString('DOC-123', $text, "placeholder lost under {$locale}");
            $this->assertStringNotContainsString(':ref', $text);
        }
        set_locale('fil');
    }

    public function testPhraseCatalogsKeepTheirPlaceholders(): void
    {
        foreach (['en', 'fil', 'msm'] as $locale) {
            foreach (require __DIR__ . "/../../lang/phrases/{$locale}.php" as $source => $translated) {
                preg_match_all('/:[a-z_]+/', (string) $source, $a);
                preg_match_all('/:[a-z_]+/', (string) $translated, $b);
                sort($a[0]);
                sort($b[0]);
                $this->assertSame($a[0], $b[0], "{$locale}: placeholders differ for \"{$source}\"");
            }
        }
    }
}
