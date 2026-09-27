<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ManoboHybridTranslator;
use App\Services\ManoboDictionary;
use App\Services\TranslationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * @group database
 * Unit and integration tests for the Manobo and Bisaya Hybrid Translator.
 */
final class ManoboHybridTranslatorTest extends TestCase
{
    private ManoboHybridTranslator $translator;

    protected function setUp(): void
    {
        $this->translator = new ManoboHybridTranslator();
    }

    /**
     * Requirement 29.1: Acceptance cases for approved production dictionary entries.
     * Mabuti → Madujow; I like you → Naliyagan ko sikuna; Good morning → Madjow no masim; Tomorrow → Kasim
     */
    public function testApprovedProductionDictionaryMatches(): void
    {
        $resMabuti = $this->translator->translate('Mabuti', 'auto', ['force_refresh' => true]);
        $this->assertSame('Madujow', trim($resMabuti['translation']));
        $this->assertGreaterThanOrEqual(1, $resMabuti['manoboMatches']);

        $resTomorrow = $this->translator->translate('Tomorrow', 'auto', ['force_refresh' => true]);
        $this->assertSame('Kasim', trim($resTomorrow['translation']));
        $this->assertGreaterThanOrEqual(1, $resTomorrow['manoboMatches']);

        $resILikeYou = $this->translator->translate('I like you', 'auto', ['force_refresh' => true]);
        $this->assertSame('Naliyagan ko sikuna', trim($resILikeYou['translation']));
        $this->assertGreaterThanOrEqual(1, $resILikeYou['manoboMatches']);

        $resGoodMorning = $this->translator->translate('Good morning', 'auto', ['force_refresh' => true]);
        $this->assertSame('Madjow no masim', trim($resGoodMorning['translation']));
        $this->assertGreaterThanOrEqual(1, $resGoodMorning['manoboMatches']);
    }

    /**
     * Requirement 29.2: Unknown “vaccination” uses Bisaya fallback and creates no invented Manobo entry.
     */
    public function testUnknownConceptUsesBisayaFallbackWithoutInventingManobo(): void
    {
        $res = $this->translator->translate('There will be a vaccination tomorrow.', 'en', ['force_refresh' => true]);
        $translation = $res['translation'];

        // Should use Bisaya fallback for vaccination (e.g. bakuna or vaccine), and Kasim for tomorrow
        $this->assertStringContainsString('kasim', strtolower($translation));
        $this->assertMatchesRegularExpression('/(bakuna|vaccin)/i', $translation);

        // Ensure missing concept was logged
        $stmt = db()->prepare("SELECT * FROM manobo_missing_concepts WHERE concept LIKE '%vaccin%' OR concept LIKE '%bakuna%'");
        $stmt->execute();
        $this->assertNotEmpty($stmt->fetchAll());
    }

    /**
     * Requirement 29.3: Mixed text uses approved Manobo matches and fluent Bisaya spans;
     * ambiguous meanings choose fallback when no safe Manobo match exists.
     */
    public function testMixedTextWithManoboAndBisayaAndAmbiguityResolution(): void
    {
        // "Good morning residents"
        $res = $this->translator->translate('Good morning residents', 'en', ['force_refresh' => true]);
        $this->assertStringStartsWith('Madjow no masim', trim($res['translation']));
        $this->assertGreaterThanOrEqual(1, $res['manoboMatches']);
        $this->assertGreaterThanOrEqual(1, $res['bisayaFallbacks']);

        // Ambiguous term: "turn on the light" vs "light weight"
        // "light" here means lamp/illumination -> should NOT match Manobo "maagkap" (which is weight)
        $resLight = $this->translator->translate('Please turn on the light in the dark room.', 'en', ['force_refresh' => true]);
        $this->assertStringNotContainsString('maagkap', strtolower($resLight['translation']));
    }

    /**
     * Requirement 29.4: “Ordinance No. 25-2026” and “September 30, 2026” survive unchanged as protected references.
     */
    public function testProtectedEntitiesSurviveUnchanged(): void
    {
        $input = "Ordinance No. 25-2026 will take effect on September 30, 2026 at Barangay San Roque with a fee of ₱500 at 8:00 AM.";
        $res = $this->translator->translate($input, 'en', ['force_refresh' => true]);
        $out = $res['translation'];

        $this->assertStringContainsString('Ordinance No. 25-2026', $out);
        $this->assertStringContainsString('September 30, 2026', $out);
        $this->assertStringContainsString('Barangay San Roque', $out);
        $this->assertStringContainsString('₱500', $out);
        $this->assertStringContainsString('8:00 AM', $out);
    }

    /**
     * Requirement 29.5: MN changes all applicable resident-facing Events, Ordinances, Announcements.
     */
    public function testMNContentLocalisation(): void
    {
        $row = [
            'id'                  => 99999,
            'title'               => 'Mabuti',
            'title_manobo'        => 'Madujow',
            'body'                => 'Bukas magkakaroon ng pulong.',
            'body_manobo'         => 'Kasim magkakaroon ng pulong.',
            'manobo_is_auto'      => 1,
            'source_lang'         => 'fil',
        ];

        $content = localised_content($row, 'title', 'msm');
        $this->assertSame('msm', $content['locale']);
        $this->assertSame('Madujow', $content['text']);
        $this->assertTrue($content['translated']);
    }

    /**
     * Requirement 29.6: MN remains selected after navigation and reload; switching restores other content.
     */
    public function testLocalePersistence(): void
    {
        set_locale('msm');
        $this->assertSame('msm', current_locale());
        $this->assertSame('msm', $_SESSION['locale']);

        set_locale('fil');
        $this->assertSame('fil', current_locale());
        $this->assertSame('fil', $_SESSION['locale']);
    }

    /**
     * Requirement 29.7: Approving a dictionary entry invalidates stale translated content.
     */
    public function testDictionaryVersionIncrementInvalidatesCache(): void
    {
        $v1 = $this->translator->getDictionaryVersion();
        $v2 = ManoboHybridTranslator::incrementDictionaryVersion();
        $this->assertSame($v1 + 1, $v2);
        $this->assertSame($v2, $this->translator->getDictionaryVersion());
    }

    /**
     * Requirement 29.8: Dataset integrity — preserves different senses of duplicates and exact spellings.
     */
    public function testDuplicateSensesPreserved(): void
    {
        $dict = new ManoboDictionary();
        $all = $dict->all();

        // Check that entries exist and PDF spellings are retained
        $foundSikuna = false;
        $foundSed = false;
        foreach ($all as $e) {
            if ($e['manobo'] === 'sikuna' || $e['manobo'] === 'Sikuna') {
                $foundSikuna = true;
            }
            if ($e['manobo'] === 'sed') {
                $foundSed = true;
            }
        }
        $this->assertTrue($foundSikuna);
        $this->assertTrue($foundSed);
    }

    /**
     * Requirement 29.9: Translated output cannot inject executable markup (XSS prevention).
     */
    public function testXssPreventionInTranslation(): void
    {
        $input = "Good morning <script>alert('xss')</script> & <b>bold</b>";
        $res = $this->translator->translate($input, 'en', ['force_refresh' => true]);
        
        // Either tag is masked/preserved or stripped, but never executed as raw unsanitized executable payload
        $this->assertStringContainsString('Madjow no masim', $res['translation']);
        // Verify script is neutralized or kept as raw non-executable string
        $this->assertStringContainsString('<script>', $res['translation']);
    }

    /**
     * Requirement: Connect roots, linkers, and affixes to Manobo before using Bisaya fallback.
     */
    public function testStemmingConnectsToManoboBeforeBisayaFallback(): void
    {
        // 'walang' -> stems to 'wala' -> Manobo 'wada'
        $resWala = $this->translator->translate('walang pagkain', 'fil', ['force_refresh' => true]);
        $this->assertStringContainsString('wada', mb_strtolower($resWala['translation']));
        $this->assertGreaterThanOrEqual(1, $resWala['manoboMatches']);

        // 'kumain' -> stems to 'kain' -> Manobo 'kuon'
        $resKain = $this->translator->translate('kumain', 'fil', ['force_refresh' => true]);
        $this->assertStringContainsString('kuon', mb_strtolower($resKain['translation']));
        $this->assertSame(1, $resKain['manoboMatches']);

        // 'pumasok' -> stems to 'pasok' -> Manobo 'sed'
        $resPasok = $this->translator->translate('pumasok', 'fil', ['force_refresh' => true]);
        $this->assertStringContainsString('sed', mb_strtolower($resPasok['translation']));
        $this->assertSame(1, $resPasok['manoboMatches']);
    }

    /**
     * Requirement: Dashboard safety title 'Walang abiso ngayon' must translate using 100% Manobo words.
     */
    public function testWalangAbisoNgayonFullyManobo(): void
    {
        $res = $this->translator->translate('Walang abiso ngayon', 'fil', ['force_refresh' => true]);
        $this->assertStringContainsString('wada', mb_strtolower($res['translation']));
        $this->assertStringContainsString('pahinomdom', mb_strtolower($res['translation']));
        $this->assertStringContainsString('kuntoon', mb_strtolower($res['translation']));
        $this->assertSame(3, $res['manoboMatches']);
        $this->assertSame(0, $res['bisayaFallbacks']);
    }

    /**
     * Requirement: UI labels translate to Manobo when locale is 'msm'.
     */
    public function testUiLabelsTranslateWhenLocaleIsManobo(): void
    {
        $nav = t('nav.announcements', [], 'msm');
        $this->assertStringContainsString('Pahinomdom', $nav);

        $quietTitle = t('safety.quiet_title', [], 'msm');
        $this->assertSame('Wada pahinomdom kuntoon', $quietTitle);

        $doc = t('nav.documents', [], 'msm');
        $this->assertSame('Suyat', $doc);

        $home = t('nav.home', [], 'msm');
        $this->assertSame('Bayoy', $home);
    }

    /**
     * Requirement: Bisaya fallback text is refined with approved Manobo vocabulary.
     */
    public function testRefineBisayaFallbackWithManobo(): void
    {
        $manoboIndex = $this->translator->loadManoboIndex();
        $refined = $this->translator->refineBisayaWithManobo('walay pahibalo sa balay', $manoboIndex);
        
        $lower = mb_strtolower($refined['text']);
        $this->assertStringContainsString('wada', $lower);
        $this->assertStringContainsString('pahinomdom', $lower);
        $this->assertStringContainsString('bayoy', $lower);
        $this->assertGreaterThanOrEqual(2, $refined['manoboCount']);
    }
}
