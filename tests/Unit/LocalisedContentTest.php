<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * localised_content() — picking the right language version of a post.
 *
 * This is the whole content side of the FIL / EN / MN switch, so the rules it
 * encodes are worth pinning individually. The one that matters most is the
 * fallback: it must be reported, not hidden, or a resident reading Filipino
 * after tapping EN has no way to tell a missing translation from a broken
 * button.
 */
final class LocalisedContentTest extends TestCase
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

    /** @return array<string,string> */
    private function post(): array
    {
        return [
            'title'        => 'Libreng bakuna sa Barangay Hall',
            'body'         => 'May libreng bakuna bukas.',
            'title_en'     => 'Free vaccination at the Barangay Hall',
            'body_en'      => 'There is free vaccination tomorrow.',
            'title_manobo' => "Libre nga bakuna sa Barangay Hall",
            'body_manobo'  => "Adunay libre nga bakuna ugma.",
        ];
    }

    public function testFilipinoReadsTheOriginalColumns(): void
    {
        set_locale('fil');
        $pick = localised_content($this->post(), 'title');

        $this->assertSame('Libreng bakuna sa Barangay Hall', $pick['text']);
        $this->assertTrue($pick['translated'], 'The original IS the Filipino version');
    }

    public function testEnglishReadsTheEnglishColumn(): void
    {
        set_locale('en');
        $pick = localised_content($this->post(), 'body');

        $this->assertSame('There is free vaccination tomorrow.', $pick['text']);
        $this->assertTrue($pick['translated']);
    }

    public function testManoboReadsTheManoboColumn(): void
    {
        set_locale('msm');
        $pick = localised_content($this->post(), 'title');

        $this->assertSame('Libre nga bakuna sa Barangay Hall', $pick['text']);
        $this->assertTrue($pick['translated']);
    }

    /**
     * The case this whole feature has to get right today: the Anthropic account
     * has no credits, so most posts have no translation yet.
     */
    public function testMissingTranslationFallsBackAndSaysSo(): void
    {
        $post = $this->post();
        unset($post['title_en'], $post['body_en']);

        set_locale('en');
        $pick = localised_content($post, 'title');

        $this->assertSame('Libreng bakuna sa Barangay Hall', $pick['text'], 'Must show the original, never an empty page');
        $this->assertFalse($pick['translated'], 'The caller must be told this is a fallback');
        $this->assertSame('en', $pick['locale']);
    }

    /** A column that exists but holds only whitespace is not a translation. */
    public function testBlankTranslationCountsAsMissing(): void
    {
        $post              = $this->post();
        $post['title_en']  = "   \n\t  ";

        set_locale('en');
        $pick = localised_content($post, 'title');

        $this->assertSame('Libreng bakuna sa Barangay Hall', $pick['text']);
        $this->assertFalse($pick['translated']);
    }

    /** Never invent text: an empty original stays empty rather than borrowing another language. */
    public function testEmptyOriginalWithNoTranslationStaysEmpty(): void
    {
        set_locale('en');
        $pick = localised_content(['title' => ''], 'title');

        $this->assertSame('', $pick['text']);
        $this->assertFalse($pick['translated']);
    }

    /** Works on rows that simply do not carry the field (partial SELECTs). */
    public function testMissingFieldDoesNotError(): void
    {
        set_locale('msm');
        $pick = localised_content(['id' => 5], 'description');

        $this->assertSame('', $pick['text']);
    }

    // ── Posts written in English ─────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    private function englishPost(): array
    {
        return [
            'source_lang'  => 'en',
            'title'        => 'Free wifi for every household',
            'body'         => 'Every house will have free wifi next week.',
            'title_fil'    => 'Libreng wifi para sa bawat sambahayan',
            'body_fil'     => 'Ang bawat bahay ay magkakaroon ng libreng wifi sa susunod na linggo.',
            'title_manobo' => 'Libre wifi para matag household',
            'fil_is_auto'  => 1,
        ];
    }

    /**
     * The bug this fixes: a post typed in English told English readers
     * "this post has no English version yet" while showing them English,
     * because the system assumed every post was authored in Filipino.
     */
    public function testEnglishSourcePostReadsAsEnglishWithNoMissingNotice(): void
    {
        set_locale('en');
        $pick = localised_content($this->englishPost(), 'title');

        $this->assertSame('Free wifi for every household', $pick['text']);
        $this->assertTrue($pick['translated'], 'The source text IS the English version');
        $this->assertFalse($pick['machine'], 'A person wrote it, so no machine label');
    }

    /** Filipino is now the language that needs translating, and it is. */
    public function testEnglishSourcePostServesTheFilipinoTranslation(): void
    {
        set_locale('fil');
        $pick = localised_content($this->englishPost(), 'body');

        $this->assertStringContainsString('libreng wifi', $pick['text']);
        $this->assertTrue($pick['translated']);
        $this->assertTrue($pick['machine'], 'The Filipino here was machine-made');
    }

    /** With no Filipino translation yet, Filipino readers fall back honestly. */
    public function testEnglishSourcePostFallsBackWhenFilipinoIsMissing(): void
    {
        $post = $this->englishPost();
        unset($post['title_fil'], $post['body_fil']);

        set_locale('fil');
        $pick = localised_content($post, 'title');

        $this->assertSame('Free wifi for every household', $pick['text']);
        $this->assertFalse($pick['translated'], 'Filipino readers must be told it is missing');
    }

    /** Manobo is unaffected by which language the post was written in. */
    public function testManoboWorksRegardlessOfSourceLanguage(): void
    {
        set_locale('msm');

        $this->assertSame(
            'Libre wifi para matag household',
            localised_content($this->englishPost(), 'title')['text']
        );
        $this->assertSame(
            'Libre nga bakuna sa Barangay Hall',
            localised_content($this->post(), 'title')['text']
        );
    }

    /** Rows created before migration 018 have no column and stay Filipino-source. */
    public function testMissingSourceLangDefaultsToFilipino(): void
    {
        $post = $this->post();
        unset($post['source_lang']);

        set_locale('fil');
        $this->assertSame('Libreng bakuna sa Barangay Hall', localised_content($post, 'title')['text']);

        set_locale('en');
        $this->assertSame('Free vaccination at the Barangay Hall', localised_content($post, 'title')['text']);
    }

    public function testLocalisedTextReturnsJustTheString(): void
    {
        set_locale('en');
        $this->assertSame(
            'Free vaccination at the Barangay Hall',
            localised_text($this->post(), 'title')
        );
    }

    /**
     * Staff pages are pinned to English (current_locale()), so a post read from
     * the back office resolves to its English copy — not to whatever language
     * the staff member last chose on the resident side.
     */
    public function testBackOfficeResolvesContentAsEnglish(): void
    {
        set_locale('msm');
        $GLOBALS['bg_is_back_office'] = true;

        $pick = localised_content($this->post(), 'title');
        $this->assertSame('Free vaccination at the Barangay Hall', $pick['text']);
    }

    // ── english_gloss_phrase() — the admin's draft button ─────────────────

    /**
     * The dictionary's parenthetical disambiguators are notes to a human
     * reading the dictionary, not words. Pasting "open (not closed)" into a
     * draft sentence produces nonsense, which is exactly what it did before.
     */
    public function testGlossStripsTheDictionarysParentheticalNotes(): void
    {
        $out = english_gloss_phrase('Bukas ang barangay hall.')['text'];

        $this->assertStringNotContainsString('(', $out, "Dictionary notes must not leak: {$out}");
        $this->assertStringContainsString('pen', $out, "Expected 'open' somewhere in: {$out}");
    }

    /**
     * Tagalog attaches a linker to a modifier ("libre" → "libreng"). The
     * dictionary stores the bare root, so without stripping the linker these
     * very common words never matched at all.
     */
    public function testGlossHandlesTheTagalogLinker(): void
    {
        $plain  = english_gloss_phrase('libre')['text'];
        $linked = english_gloss_phrase('libreng')['text'];

        $this->assertSame('free', strtolower($plain));
        $this->assertSame('free', strtolower($linked), 'The linker form must resolve to the same word');
    }

    /** Case is carried over from the source word, not from the dictionary. */
    public function testGlossKeepsTheSourceCase(): void
    {
        $this->assertSame('Free', english_gloss_phrase('Libreng')['text']);
    }

    /** Placeholders, numbers and acronyms pass through and are not counted. */
    public function testGlossLeavesPlaceholdersAndAcronymsAlone(): void
    {
        $result = english_gloss_phrase('SMS :name 2026');

        $this->assertStringContainsString(':name', $result['text']);
        $this->assertStringContainsString('SMS', $result['text']);
        $this->assertStringContainsString('2026', $result['text']);
        $this->assertSame(0, $result['total'], 'None of these count toward coverage');
    }

    /** An unknown word is left exactly as it was — nothing is invented. */
    public function testGlossLeavesUnknownWordsUntouchedAndReportsThem(): void
    {
        $result = english_gloss_phrase('Zzzqqx tubig');

        $this->assertStringContainsString('Zzzqqx', $result['text']);
        $this->assertContains('Zzzqqx', $result['missing']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['matched']);
    }

    /** "ng" is "of" and "sa" is "at" — they are different words. */
    public function testGlossDistinguishesNgFromSa(): void
    {
        $this->assertSame('of', strtolower(english_gloss_phrase('ng')['text']));
        $this->assertSame('at', strtolower(english_gloss_phrase('sa')['text']));
    }

    public function testGlossOnEmptyTextIsSafe(): void
    {
        $result = english_gloss_phrase('');

        $this->assertSame('', $result['text']);
        $this->assertSame(0, $result['total']);
    }
}
