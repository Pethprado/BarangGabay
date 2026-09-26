<?php
declare(strict_types=1);

use App\Services\FreeTranslationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The two automatic translation paths that work without paid API credits.
 *
 * Nothing here touches the network: the chunking is pure string work, and the
 * Manobo gloss reads the bundled dictionaries. What is being pinned is the
 * judgement each path makes about when NOT to produce a translation, which is
 * the part that protects residents from being shown machine output dressed up
 * as a person's words.
 */
final class AutoTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $GLOBALS['bg_is_back_office'] = false;
    }

    protected function tearDown(): void
    {
        $GLOBALS['bg_is_back_office'] = false;
        set_locale('fil');
        parent::tearDown();
    }

    // ── FreeTranslationService::chunk() ──────────────────────────────────

    /**
     * The provider rejects anything over 500 characters, and returns the
     * refusal as if it were the translation. Every chunk must stay under.
     */
    public function testEveryChunkFitsTheProvidersLimit(): void
    {
        $long = str_repeat('Ipinapaalam po sa lahat ng residente ng Barangay Bayogo na may pulong bukas. ', 12);

        foreach ((new FreeTranslationService())->chunk($long) as $chunk) {
            $this->assertLessThanOrEqual(450, strlen($chunk), "Chunk too long: {$chunk}");
            $this->assertNotSame('', trim($chunk));
        }
    }

    /** Short text is one chunk, not split for no reason. */
    public function testShortTextIsASingleChunk(): void
    {
        $chunks = (new FreeTranslationService())->chunk('May pulong bukas sa Barangay Hall.');

        $this->assertCount(1, $chunks);
        $this->assertSame('May pulong bukas sa Barangay Hall.', $chunks[0]);
    }

    /** Splitting must not lose or duplicate words. */
    public function testChunkingPreservesEveryWord(): void
    {
        $text   = 'Una. Pangalawa. Pangatlo. ' . str_repeat('salita ', 120) . 'wakas.';
        $chunks = (new FreeTranslationService())->chunk($text);

        $rejoined = preg_replace('/\s+/u', ' ', implode(' ', $chunks));
        $original = preg_replace('/\s+/u', ' ', trim($text));

        $this->assertSame($original, $rejoined);
    }

    /** A sentence longer than the limit is split on words, never mid-word. */
    public function testOverlongSentenceIsSplitOnWordBoundaries(): void
    {
        $sentence = str_repeat('mahaba ', 150) . 'dulo.';
        $chunks   = (new FreeTranslationService())->chunk($sentence);

        $this->assertGreaterThan(1, count($chunks), 'This sentence must be split');

        // Every token in every chunk has to be a whole word from the source.
        // A mid-word split would leave a fragment like "maha" behind.
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(450, strlen($chunk));
            foreach (preg_split('/\s+/', trim($chunk)) as $token) {
                $this->assertContains($token, ['mahaba', 'dulo.'], "Fragment left by a mid-word split: {$token}");
            }
        }
    }

    public function testEmptyTextProducesNoChunks(): void
    {
        $this->assertSame([], (new FreeTranslationService())->chunk('   '));
    }

    // ── manobo_gloss_for() ───────────────────────────────────────────────

    /**
     * The guard that matters most. Dictionary "coverage" counts a word as
     * found even when it maps to itself ("sa" → "sa"), so a passage can clear
     * the ratio while almost nothing on screen changed. Publishing that under
     * a Manobo heading would show residents Filipino text labelled Manobo.
     */
    public function testGlossIsRejectedWhenBarelyAnythingChanged(): void
    {
        $this->assertNull(
            manobo_gloss_for('Libreng medical check-up sa Barangay Hall'),
            'A passage that stays almost entirely Filipino is not a translation'
        );
    }

    /** A passage the dictionary really does convert is accepted. */
    public function testGlossIsAcceptedWhenTheTextGenuinelyConverts(): void
    {
        $out = manobo_gloss_for('Magkakaroon ng pulong ang mga opisyal sa Lunes ng hapon.');

        $this->assertNotNull($out);
        $this->assertNotSame('Magkakaroon ng pulong ang mga opisyal sa Lunes ng hapon.', $out);
        $this->assertStringContainsString('tigom', $out, "Expected the Bisaya word for meeting: {$out}");
    }

    public function testGlossOnEmptyTextIsNull(): void
    {
        $this->assertNull(manobo_gloss_for(''));
        $this->assertNull(manobo_gloss_for('   '));
    }

    // ── localised_content() machine flag ─────────────────────────────────

    /** Machine output must be reported as such so the view can label it. */
    public function testMachineFlagIsReportedToTheView(): void
    {
        set_locale('en');

        $auto = localised_content(
            ['title' => 'Orihinal', 'title_en' => 'Original', 'en_is_auto' => 1],
            'title'
        );
        $this->assertTrue($auto['machine'], 'An auto translation must be labelled');

        $human = localised_content(
            ['title' => 'Orihinal', 'title_en' => 'Original', 'en_is_auto' => 0],
            'title'
        );
        $this->assertFalse($human['machine'], 'Text a person wrote must not be labelled machine');
    }

    /** A fallback to the original is never "machine" — it is the real text. */
    public function testFallbackIsNotLabelledMachine(): void
    {
        set_locale('en');

        $pick = localised_content(['title' => 'Orihinal', 'en_is_auto' => 1], 'title');

        $this->assertFalse($pick['translated']);
        $this->assertFalse($pick['machine'], 'The Filipino original is not machine output');
        $this->assertSame('Orihinal', $pick['text']);
    }

    /** Filipino readers see the original, never a machine label. */
    public function testFilipinoIsNeverLabelledMachine(): void
    {
        set_locale('fil');

        $pick = localised_content(
            ['title' => 'Orihinal', 'title_en' => 'Original', 'en_is_auto' => 1],
            'title'
        );

        $this->assertSame('Orihinal', $pick['text']);
        $this->assertFalse($pick['machine']);
    }
}
