<?php
declare(strict_types=1);

use App\Services\ManoboAutoTranslator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * @group database
 * Run explicitly with: vendor\bin\phpunit --group database
 * (needs a real connection — see ManoboDictionaryTest for why that is
 * excluded from the default run).
 */
final class ManoboAutoTranslatorTest extends TestCase
{
    private ManoboAutoTranslator $translator;

    protected function setUp(): void
    {
        $this->translator = new ManoboAutoTranslator();
    }

    private function render(string $text): string
    {
        return $this->translator->render($this->translator->translateBlock($text));
    }

    public function testLongestPhraseWinsOverTheSingleWordsInsideIt(): void
    {
        // "Magandang umaga" -> "madjow no masim" as ONE match. If word-by-word
        // ran instead, "umaga" alone would hit the SEPARATE "masim" entry
        // (time: morning) and "Magandang" would be left unmatched.
        $result = $this->translator->translateBlock('Magandang umaga');

        $this->assertSame(1, $result['matched_manobo']);
        $this->assertSame(0, $result['unmatched']);
        $this->assertSame('Madjow no masim', $this->render('Magandang umaga'));
    }

    public function testSingleWordFallbackWhenNoPhraseMatches(): void
    {
        // "Umaga" alone (not part of the "Magandang umaga" phrase context)
        // still resolves via the single-word time entry.
        $this->assertSame('masim', $this->render('umaga'));
    }

    public function testSlashSeparatedAlternativesEachMatchOnTheirOwn(): void
    {
        // tagad's tagalog gloss is "Sandali / Maghintay" — either word alone
        // must resolve to "tagad".
        // Case-matched to the input, per the "keep original capitalisation"
        // rule — both inputs are capitalised, so the output is too.
        $this->assertSame('Tagad', $this->render('Sandali'));
        $this->assertSame('Tagad', $this->render('Maghintay'));
    }

    public function testBisayaFallbackWhenNotInManobo(): void
    {
        // "ordinansa" is a Bisaya-dictionary word with no Manobo entry.
        $result = $this->translator->translateBlock('ordinansa');

        $this->assertSame(0, $result['matched_manobo']);
        $this->assertSame(1, $result['matched_bisaya']);
        $this->assertSame('bisaya', $result['segments'][0]['source']);
    }

    public function testUnknownWordIsKeptUnchangedAndFlagged(): void
    {
        $result = $this->translator->translateBlock('zzqqnonexistentword');

        $this->assertSame(1, $result['unmatched']);
        $this->assertSame('none', $result['segments'][0]['source']);
        $this->assertSame('zzqqnonexistentword', $result['segments'][0]['display']);
    }

    public function testCaseInsensitiveMatchingWithCasePreservedInOutput(): void
    {
        $this->assertSame('Masim', $this->render('Umaga'));
        $this->assertSame('MASIM', $this->render('UMAGA'));
        $this->assertSame('masim', $this->render('umaga'));
    }

    public function testPunctuationAndSpacingArePreservedAroundMatches(): void
    {
        $out = $this->render('Umaga, kumusta?');

        $this->assertStringContainsString(',', $out);
        $this->assertStringContainsString('?', $out);
        $this->assertStringStartsWith('Masim', $out);
    }

    public function testMixedKnownAndUnknownWordsInOneSentence(): void
    {
        $result = $this->translator->translateBlock('umaga zzqqfoobar');

        $this->assertSame(1, $result['matched_manobo']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertCount(3, $result['segments']); // word, space, word
        $this->assertSame('manobo', $result['segments'][0]['source']);
        $this->assertSame('text', $result['segments'][1]['source']);
        $this->assertSame('none', $result['segments'][2]['source']);
    }

    public function testEmptyInputFailsGracefully(): void
    {
        $result = $this->translator->translateBlock('   ');

        $this->assertFalse($result['success']);
        $this->assertSame([], $result['segments']);
    }
}
