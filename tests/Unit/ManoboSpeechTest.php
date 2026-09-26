<?php
declare(strict_types=1);

use App\Services\ManoboSpeech;
use App\Services\SpokenText;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Pins how Manobo is handed to a speech engine.
 *
 * Every case here is drawn from the orthography section of
 * data/manobo/README.md and from the real entries in manobo_dictionary.csv,
 * because those are the facts the pronunciation depends on. Two kinds of
 * assertion live side by side on purpose:
 *
 *   what the layer fixes  — marks that would otherwise be read as punctuation,
 *                           and the schwa a Filipino voice gets wrong;
 *   what it cannot fix    — the contrasts Filipino has no way to carry.
 *
 * The second kind is written down rather than left implicit, because the
 * honest claim for this feature is "an approximation, labelled as one", and a
 * future change that silently made the losses worse should have to edit a test
 * that says so.
 */
final class ManoboSpeechTest extends TestCase
{
    protected function setUp(): void
    {
        ManoboSpeech::flushCache();
        parent::setUp();
    }

    // ── Marks that would break synthesis ─────────────────────────────────────

    /**
     * The stress apostrophe is the single most damaging mark to send raw: an
     * engine reads it as an opening quote and can swallow the word.
     */
    public function testStressApostrophesAreRemoved(): void
    {
        $this->assertStringNotContainsString("'", ManoboSpeech::forSpeech("wo'hig"));
        $this->assertStringNotContainsString("'", ManoboSpeech::forSpeech("'hilu"));
        $this->assertStringNotContainsString("'", ManoboSpeech::forSpeech("a'baga"));
    }

    public function testATypographicApostropheIsRemovedToo(): void
    {
        // Word processors and phone keyboards produce U+2019, not U+0027.
        $this->assertStringNotContainsString("\u{2019}", ManoboSpeech::forSpeech("wo\u{2019}hig"));
    }

    /** The grave accent marks a glottal stop, not a letter an engine knows. */
    public function testGraveAccentsAreReducedToTheirBaseVowel(): void
    {
        $this->assertSame('baka', ManoboSpeech::forSpeech('bakà'));
        $this->assertSame('suda', ManoboSpeech::forSpeech("so'dà"));
    }

    public function testADecomposedAccentIsHandledLikeAPrecomposedOne(): void
    {
        // "a" followed by combining grave — what pasting from Word can produce.
        $this->assertSame('baka', ManoboSpeech::forSpeech("baka\u{0300}"));
    }

    /**
     * A medial dash is a glottal stop and Filipino spells it the same way
     * ("mag-asawa"), so it survives — but a trailing one would be read as a
     * pause rather than a consonant.
     */
    public function testAMedialDashSurvivesAndATrailingOneDoesNot(): void
    {
        $this->assertSame('agid-id', ManoboSpeech::forSpeech('agid-id'));

        // respell() owns the trailing-dash rule; word() would not even reach it
        // for "agid-", which carries no evidence of being Manobo at all.
        $this->assertSame('agid', ManoboSpeech::respell('agid-'));
    }

    public function testPunctuationAroundAWordIsKept(): void
    {
        $this->assertSame('wuhig.', ManoboSpeech::forSpeech("wo'hig."));
        $this->assertSame('(wuhig)', ManoboSpeech::forSpeech("('wohig)"));
    }

    // ── Vowels ───────────────────────────────────────────────────────────────

    /**
     * `o` is a schwa in this orthography, not [o]. Left alone, a fil-PH voice
     * mispronounces every occurrence of the language's commonest vowel.
     *
     * The dataset backs the target chosen: wo'hig "water" is Tagalog tubig,
     * so'dà "viand" is ulam.
     */
    public function testTheSchwaIsRespelled(): void
    {
        $this->assertSame('wuhig', ManoboSpeech::forSpeech("wo'hig"));
        $this->assertSame('unum',  ManoboSpeech::forSpeech("o'nom"));
        $this->assertSame('guyangan', ManoboSpeech::forSpeech("gu'yangan"));
    }

    /** `e` is a true `e` here, so it must NOT be swept up with the schwa. */
    public function testTheTrueEIsLeftAlone(): void
    {
        $this->assertStringContainsString('e', ManoboSpeech::respell('bel'));
        $this->assertSame('bel', ManoboSpeech::respell('bel'));
    }

    public function testVowelDigraphsAreTreatedAsSingleVowels(): void
    {
        // 'aehu "pestle" is a-e-h-u: the 'ae' digraph then 'hu', so it becomes
        // "ahu". Read as two vowels it would come out "a-eh-u" instead.
        $this->assertSame('ahu', ManoboSpeech::forSpeech("'aehu"));
        $this->assertSame('a-a', ManoboSpeech::forSpeech('a-ae'));
    }

    public function testLongVowelsDropTheirGlide(): void
    {
        // The dataset notes 'abiy as "a long i sound", not a y-glide.
        $this->assertSame('abi', ManoboSpeech::forSpeech("'abiy"));
    }

    public function testCapitalisationIsPreserved(): void
    {
        $this->assertSame('Wuhig', ManoboSpeech::forSpeech("Wo'hig"));
    }

    // ── Scope: only Manobo is touched ────────────────────────────────────────

    /**
     * The spoken Manobo track carries the venue, the date and proper nouns,
     * and the language itself is code-switched with Surigaonon and Bisaya
     * where `o` really is [o]. Blanket re-spelling would turn "Zone" into
     * "Zune" — so only words with positive evidence of this orthography move.
     */
    public function testNonManoboWordsInTheSameSentenceAreUntouched(): void
    {
        $sentence = 'Dapit: Barangay Hall Bayogo. Kahimtang: Umaabot.';

        $this->assertSame($sentence, ManoboSpeech::forSpeech($sentence));
    }

    public function testDatesAndNumbersSurviveIntact(): void
    {
        $line = 'Petsa ug oras: August 27, 2026, 12 PM - August 29, 2026, 12 PM.';

        $this->assertSame($line, ManoboSpeech::forSpeech($line));
    }

    /** A hyphen alone is not evidence — Filipino writes "mag-aral" too. */
    public function testAPlainFilipinoHyphenatedWordIsNotTreatedAsManobo(): void
    {
        $this->assertFalse(ManoboSpeech::isManobo('mag-aral'));
        $this->assertSame('mag-aral', ManoboSpeech::forSpeech('mag-aral'));
    }

    /**
     * The 39-entry dictionary earns its keep here: it is the second kind of
     * evidence, so a staff member who types a Manobo word without its marks
     * still gets it pronounced correctly.
     */
    public function testAWordKnownToTheDictionaryIsRecognisedWithoutMarks(): void
    {
        $this->assertTrue(ManoboSpeech::isManobo('wohig'), 'wo\'hig is in the dictionary');
        $this->assertSame('wuhig', ManoboSpeech::forSpeech('wohig'));
    }

    public function testAnOrdinaryWordIsNotInventedIntoManobo(): void
    {
        $this->assertFalse(ManoboSpeech::isManobo('announcement'));
        $this->assertFalse(ManoboSpeech::isManobo('ordinansa'));
    }

    // ── The contrasts Filipino cannot carry ──────────────────────────────────

    /**
     * 'hilu "thread" and hi'lu "poison" differ only in stress, and plain-text
     * synthesis has no way to ask a fil-PH voice for lexical stress. They come
     * out identical.
     *
     * Pinned deliberately. This is the clearest single reason a recording by a
     * Manobo speaker outranks anything generated here, and the UI has to keep
     * saying so.
     */
    public function testTheStressMinimalPairCollapsesInSpeech(): void
    {
        $thread = ManoboSpeech::forSpeech("'hilu");
        $poison = ManoboSpeech::forSpeech("hi'lu");

        $this->assertSame($thread, $poison, 'Known limitation: stress is not recoverable in synthesis');
        $this->assertSame('hilu', $thread);
    }

    /**
     * bakà "jaw" and baka "cow" differ only by a final glottal stop, which
     * Filipino orthography has no way to write and no fil-PH voice will
     * produce on request.
     */
    public function testTheGlottalMinimalPairCollapsesInSpeech(): void
    {
        $this->assertSame(
            ManoboSpeech::forSpeech('baka'),
            ManoboSpeech::forSpeech('bakà'),
            'Known limitation: a final glottal stop is not reproducible'
        );
    }

    // ── Integration with the voice reader ────────────────────────────────────

    /**
     * The transform belongs to speech and must never reach the screen: the
     * marks distinguish different words, so stripping them from the displayed
     * text would lose meaning a reader can actually use.
     */
    public function testTheDisplayedTextKeepsItsMarksWhileTheSpokenTextDoesNot(): void
    {
        $script = SpokenText::script([
            'locale' => 'msm',
            'body'   => "Wo'hig kag so'dà.",
        ]);

        $chunk = $script['chunks'][0];

        $this->assertSame("Wo'hig kag so'dà.", $chunk['find'], 'The page must keep the marks');
        $this->assertStringNotContainsString("'", $chunk['say']);
        $this->assertStringContainsString('Wuhig', $chunk['say']);
        $this->assertStringContainsString('suda', $chunk['say']);
    }

    /** Filipino and English must be completely unaffected by any of this. */
    public function testOtherLanguagesAreNotRespelled(): void
    {
        foreach (['fil', 'en'] as $locale) {
            $this->assertSame(
                'Ang tubig sa Bayogo ay libre.',
                SpokenText::expand('Ang tubig sa Bayogo ay libre.', $locale)
            );
        }
    }
}
