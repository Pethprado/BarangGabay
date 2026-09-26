<?php
declare(strict_types=1);

use App\Services\FreeTranslationService;
use App\Services\LanguageGuess;
use App\Services\SocialText;
use App\Services\TranslationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Which language a post was written in, and what follows from getting it wrong.
 *
 * This is the highest-consequence value in the translation pipeline. A post is
 * translated INTO the languages it was not written in, so a wrong source does
 * not produce a bad translation — it produces none, silently. The reported bug
 * was exactly that: an English Facebook caption filed as Filipino, "translated"
 * English→English, never given a Filipino version, and shown to residents
 * reading in FIL as English under a Filipino badge with nothing to explain it.
 *
 * Nothing here touches the network.
 */
final class SourceLanguageTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $GLOBALS['bg_is_back_office'] = false;
    }

    // ── Detection ────────────────────────────────────────────────────────────

    public function testAFilipinoNoticeIsRecognisedAsFilipino(): void
    {
        $guess = LanguageGuess::detect(
            'May libreng bakuna bukas sa Barangay Hall mula alas otso ng umaga para sa lahat ng residente.'
        );

        $this->assertSame('fil', $guess['lang']);
        $this->assertGreaterThanOrEqual(LanguageGuess::MIN_CONFIDENCE, $guess['confidence']);
        $this->assertSame('fil', LanguageGuess::detectOrNull('Ang mga residente ng barangay ay dapat magdala ng ID.'));
    }

    public function testAnEnglishNoticeIsRecognisedAsEnglish(): void
    {
        $guess = LanguageGuess::detect(
            'There will be a free vaccination tomorrow at the Barangay Hall from eight in the morning for all residents.'
        );

        $this->assertSame('en', $guess['lang']);
        $this->assertGreaterThanOrEqual(LanguageGuess::MIN_CONFIDENCE, $guess['confidence']);
    }

    /**
     * The exact shape of the post that caused the bug: an English caption in
     * Facebook's mathematical-bold Unicode. Without folding those characters
     * to ASCII every marker goes uncounted and the post reads as having no
     * language at all — which is how it ended up filed as Filipino.
     */
    public function testAnEnglishCaptionInStyledUnicodeIsStillEnglish(): void
    {
        $styled = '𝐎𝐮𝐫 𝐞𝐯𝐞𝐧𝐭 𝐰𝐚𝐬 𝐚 𝐛𝐞𝐚𝐮𝐭𝐢𝐟𝐮𝐥 𝐜𝐞𝐥𝐞𝐛𝐫𝐚𝐭𝐢𝐨𝐧 𝐨𝐟 𝐭𝐡𝐞 𝐜𝐮𝐥𝐭𝐮𝐫𝐞 𝐭𝐡𝐚𝐭 𝐰𝐞 𝐬𝐡𝐚𝐫𝐞 𝐰𝐢𝐭𝐡 𝐚𝐥𝐥 𝐨𝐟 𝐲𝐨𝐮.';

        $this->assertSame('en', LanguageGuess::detectOrNull($styled));
    }

    /** HTML is markup, not evidence of a language. */
    public function testQuillMarkupIsIgnoredWhenDetecting(): void
    {
        $this->assertSame(
            'fil',
            LanguageGuess::detectOrNull('<p><strong>Ang</strong> mga residente ay dapat magdala ng ID sa Barangay Hall.</p>')
        );
    }

    /**
     * A genuinely bilingual notice must not be forced either way. Abstaining
     * leaves the staff member's own choice standing, which is the right
     * outcome — they read the post and a word counter did not.
     */
    public function testAMixedLanguageNoticeAbstainsRatherThanGuessing(): void
    {
        $this->assertNull(LanguageGuess::detectOrNull(
            'Libreng vaccination bukas sa Barangay Hall. Please bring your ID and come early, salamat po.'
        ));
    }

    public function testTooLittleTextAbstains(): void
    {
        foreach (['', '   ', 'Barangay Bayogo', '2026', '#PuloySaKultura'] as $text) {
            $this->assertNull(LanguageGuess::detectOrNull($text), $text);
        }
    }

    // ── What the save path does with it ──────────────────────────────────────

    /** A staff member's explicit choice is never second-guessed. */
    public function testAnExplicitChoiceBeatsTheDetector(): void
    {
        $english = 'There will be a free vaccination tomorrow at the Barangay Hall for all residents.';

        $this->assertSame('fil', TranslationService::resolveSourceLang('fil', 'Paunawa', $english));
        $this->assertSame('en', TranslationService::resolveSourceLang('en', 'Paunawa', 'Ang mga residente ay dapat dumalo.'));
    }

    /** 'auto' — and a missing field — hand the decision to the text. */
    public function testAutoReadsTheTextInstead(): void
    {
        $english = 'There will be a free vaccination tomorrow at the Barangay Hall for all residents.';
        $filipino = 'May libreng bakuna bukas sa Barangay Hall para sa lahat ng mga residente.';

        $this->assertSame('en', TranslationService::resolveSourceLang('auto', '', $english));
        $this->assertSame('en', TranslationService::resolveSourceLang(null, '', $english));
        $this->assertSame('fil', TranslationService::resolveSourceLang('auto', '', $filipino));
    }

    /**
     * When the text does not say clearly, an edit must not silently re-file
     * the post's language as a side effect of an unrelated change.
     */
    public function testAnUnclearPostKeepsTheLanguageItAlreadyHad(): void
    {
        $this->assertSame('en', TranslationService::resolveSourceLang('auto', 'Barangay Bayogo', '', 'en'));
        $this->assertSame('fil', TranslationService::resolveSourceLang('auto', 'Barangay Bayogo', '', 'fil'));
    }

    // ── What the admin list reports ──────────────────────────────────────────

    /**
     * The status of the post that caused the bug, as it was stored: English
     * original, no Filipino, no Manobo. FIL and MN must both read as missing —
     * that is what tells staff residents are seeing another language.
     */
    public function testStatusReportsMissingLanguagesForAnEnglishPost(): void
    {
        $status = TranslationService::statusFor([
            'source_lang'    => 'en',
            'title'          => 'Celebrating Culture',
            'title_fil'      => null,
            'body_fil'       => null,
            'title_manobo'   => '',
            'body_manobo'    => '',
            'en_is_auto'     => 0,
            'fil_is_auto'    => 0,
            'manobo_is_auto' => 0,
        ]);

        $this->assertSame('en', $status['source']);
        $this->assertTrue($status['en']['has'], 'the language it was written in is always readable');
        $this->assertFalse($status['en']['machine'], 'the original was written by a person');
        $this->assertFalse($status['fil']['has']);
        $this->assertFalse($status['manobo']['has']);
    }

    public function testStatusMarksMachineOutputAsMachine(): void
    {
        $status = TranslationService::statusFor([
            'source_lang' => 'en',
            'title_fil'   => 'Pagdiriwang ng Kultura',
            'body_fil'    => 'Ang aming kaganapan...',
            'fil_is_auto' => 1,
        ]);

        $this->assertTrue($status['fil']['has']);
        $this->assertTrue($status['fil']['machine']);
    }

    /** Text a person typed is never regenerated over. */
    public function testAHandTypedTranslationIsNotRegenerable(): void
    {
        $row = [
            'source_lang' => 'fil',
            'title_en'    => 'Free vaccination tomorrow',
            'body_en'     => 'Come to the Barangay Hall.',
            'en_is_auto'  => 0,
        ];

        $this->assertFalse(TranslationService::regenerable($row)['other']);

        $row['en_is_auto'] = 1;
        $this->assertTrue(
            TranslationService::regenerable($row)['other'],
            'machine output may be replaced by a better attempt'
        );
    }

    // ── Guards on what may be stored ─────────────────────────────────────────

    /**
     * Asked to put an English title into Filipino, MyMemory returned an
     * unrendered template from its own web front end, with a 200 status. It
     * was stored and shown to residents as the headline of a barangay notice.
     */
    public function testTheApisOwnPageTemplateIsNotAcceptedAsATranslation(): void
    {
        foreach ([
            "{{app['fromLang']['value']}} -> {{app['toLang']['value']}}",
            '<div class="translation">Pagdiriwang</div>',
            'Pagdiriwang ng <?= $kultura ?>',
        ] as $junk) {
            $this->assertTrue(FreeTranslationService::looksLikeMarkup($junk), $junk);
        }
    }

    /** A real translation must not be mistaken for markup. */
    public function testOrdinaryTranslationsAreNotTreatedAsMarkup(): void
    {
        foreach ([
            'Pagdiriwang ng Kultura: Paggalang sa katutubong tradisyon',
            'There is a free vaccine tomorrow at Barangay Hall.',
            'Mag-ingat po — 5 < 10 at ang temperatura ay 30 > 25 degrees.',
        ] as $good) {
            $this->assertFalse(FreeTranslationService::looksLikeMarkup($good), $good);
        }
    }

    // ── Folding styled Unicode ───────────────────────────────────────────────

    /**
     * The same fold the slug, the search, the voice reader and the translation
     * service all need. Facebook has no bold button, so captions arrive in the
     * mathematical alphanumerics and every one of those breaks on it.
     */
    public function testStyledUnicodeIsFoldedToOrdinaryLetters(): void
    {
        $this->assertSame(
            'Celebrating Culture: Honoring Indigenous Tradition Together',
            SocialText::unstyleUnicode('𝐂𝐞𝐥𝐞𝐛𝐫𝐚𝐭𝐢𝐧𝐠 𝐂𝐮𝐥𝐭𝐮𝐫𝐞: 𝐇𝐨𝐧𝐨𝐫𝐢𝐧𝐠 𝐈𝐧𝐝𝐢𝐠𝐞𝐧𝐨𝐮𝐬 𝐓𝐫𝐚𝐝𝐢𝐭𝐢𝐨𝐧 𝐓𝐨𝐠𝐞𝐭𝐡𝐞𝐫')
        );
    }

    /** Ordinary text — accents, curly quotes, emoji — must pass through whole. */
    public function testOrdinaryTextIsLeftExactlyAsWritten(): void
    {
        foreach ([
            'Malumanay na paalala sa mga residente.',
            'Our event, “Celebrating Culture,” was a success.',
            'Salamat po! 🎉 — Barangay Bayogo, Madrid',
            'Niño, mañana, café',
        ] as $text) {
            $this->assertSame($text, SocialText::unstyleUnicode($text), $text);
        }
    }

    /** A pasted caption is folded on the way in, and the panel says it did. */
    public function testPastedCaptionsAreFoldedAndReported(): void
    {
        $clean = SocialText::clean('𝐀𝐏𝐏𝐑𝐄𝐂𝐈𝐀𝐓𝐈𝐎𝐍 𝐏𝐎𝐒𝐓');

        $this->assertSame('APPRECIATION POST', $clean['text']);
        $this->assertContains('styled_unicode', $clean['removed']);
    }

    // ── Partial translations ─────────────────────────────────────────────────

    /**
     * Found by seeding a realistic English announcement: the Manobo gloss
     * cleared its coverage threshold on the short title and fell below it on
     * the long body, and the pair was stored as a success. A resident reading
     * in Manobo got a Manobo headline over an English article — and because an
     * empty string is not the same as no translation, the page could not even
     * say that no Manobo version existed.
     *
     * The guard lives in autoTranslatePostToManobo(); this pins the rule it
     * enforces, which is the same one the free service follows: a post is
     * stored in a language whole, or not at all.
     */
    public function testAManoboGlossIsNotStoredWhenOnlyTheTitleCouldBeGlossed(): void
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/TranslationService.php'
        );

        $this->assertSame(
            1,
            preg_match('/\$needTitle\s*&&\s*\$titleGloss === null.*?\$needBody\s*&&\s*\$bodyGloss === null/s', $source),
            'autoTranslatePostToManobo() must refuse a gloss that covers only one of title and body'
        );
    }

    /**
     * The same rule on the free-translation path, stated as behaviour: a
     * translated title with an empty body is not a translation.
     */
    public function testTheFreeServicePathRefusesAHalfTranslation(): void
    {
        $method = new \ReflectionMethod(TranslationService::class, 'isCompleteTranslation');
        $method->setAccessible(true);

        // title translated, body not — refused
        $this->assertFalse($method->invoke(null, 'Paunawa', '<p>May bakuna bukas.</p>', 'Notice', ''));
        // body translated, title not — refused
        $this->assertFalse($method->invoke(null, 'Paunawa', '<p>May bakuna bukas.</p>', '', 'There is a vaccine.'));
        // both translated — accepted
        $this->assertTrue($method->invoke(null, 'Paunawa', '<p>May bakuna bukas.</p>', 'Notice', 'There is a vaccine.'));
        // a source field that was empty to begin with may stay empty
        $this->assertTrue($method->invoke(null, 'Paunawa', '', 'Notice', ''));
    }
}
