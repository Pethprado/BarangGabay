<?php
declare(strict_types=1);

use App\Services\SpokenText;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Pins the parts of the voice reader that cannot be checked by listening.
 *
 * Every assertion here corresponds to something a resident would actually hear
 * go wrong: a sentence cut in half at "Brgy.", a highlight that never lands
 * because the spoken text and the on-screen text drifted apart, or a Manobo
 * page quietly being handed to a Filipino voice.
 */
final class SpokenTextTest extends TestCase
{
    /** @param list<array{say:string,find:string|null,kind:string}> $chunks */
    private function said(array $chunks): string
    {
        return implode(' ', array_column($chunks, 'say'));
    }

    // ── Reading HTML ─────────────────────────────────────────────────────────

    public function testQuillHtmlIsStrippedButKeepsItsSentenceBoundaries(): void
    {
        $script = SpokenText::script([
            'body' => '<p>Magandang umaga.</p><ul><li>Unang paalala</li><li>Pangalawang paalala</li></ul>',
        ]);

        $says = array_column($script['chunks'], 'say');

        $this->assertSame(
            ['Magandang umaga.', 'Unang paalala', 'Pangalawang paalala'],
            $says,
            'List items must be read as separate pieces, not run together'
        );
        $this->assertStringNotContainsString('<', $this->said($script['chunks']));
    }

    public function testHtmlEntitiesAreDecodedRatherThanSpelledOut(): void
    {
        $script = SpokenText::script(['body' => '<p>Tubig &amp; kuryente ay &quot;libre&quot;.</p>']);

        $this->assertStringNotContainsString('&amp;', $this->said($script['chunks']));
        $this->assertStringNotContainsString('&quot;', $this->said($script['chunks']));
    }

    // ── Sentence splitting ───────────────────────────────────────────────────

    public function testAnAbbreviationDoesNotEndASentence(): void
    {
        $sentences = SpokenText::sentences('Pumunta sa Brgy. Bayogo bukas. Salamat po.');

        $this->assertSame(
            ['Pumunta sa Brgy. Bayogo bukas.', 'Salamat po.'],
            $sentences
        );
    }

    public function testDecimalsAndInitialsDoNotEndASentence(): void
    {
        $this->assertSame(
            ['Ang bayad ay 1.50 piso kay J. Cruz.'],
            SpokenText::sentences('Ang bayad ay 1.50 piso kay J. Cruz.')
        );
    }

    // ── say vs find ──────────────────────────────────────────────────────────

    public function testAbbreviationsAreExpandedForTheVoiceButNotForTheHighlight(): void
    {
        $script = SpokenText::script(['body' => 'Sa Brgy. hall, tanungin si Hon. Reyes.']);
        $chunk  = $script['chunks'][0];

        $this->assertStringContainsString('Barangay', $chunk['say']);
        $this->assertStringNotContainsString('Brgy.', $chunk['say']);

        // The page still says "Brgy." — highlighting has to match what is there.
        $this->assertSame('Sa Brgy. hall, tanungin si Hon. Reyes.', $chunk['find']);
    }

    /**
     * The highlight only works if every spoken piece of the body exists,
     * character for character, in the text the browser lays out. This is the
     * invariant that quietly breaks the moment anything reformats 'find'.
     */
    public function testEveryBodyChunkIsFindableInTheNormalisedBody(): void
    {
        $html = '<p>Magkakaroon ng libreng check-up sa Brgy. hall sa Lunes, '
              . 'simula alas-otso ng umaga hanggang alas-onse, para sa lahat ng '
              . 'residente ng Bayogo na nakarehistro sa BHW listahan.</p>'
              . '<p>Magdala po ng ID at ng inyong barangay clearance.</p>';

        $script = SpokenText::script(['body' => $html]);
        $onPage = SpokenText::plain($html);

        $body = array_filter($script['chunks'], static fn (array $c): bool => $c['kind'] === 'body');
        $this->assertNotEmpty($body);

        foreach ($body as $chunk) {
            $this->assertStringContainsString(
                $chunk['find'],
                $onPage,
                'A spoken piece that is not on the page can never be highlighted'
            );
        }
    }

    /**
     * Quill emits &nbsp; constantly. PCRE's \s does not match U+00A0 but
     * JavaScript's does, so leaving one in makes the sentence the player looks
     * for differ from the sentence it was handed by one invisible character —
     * and the highlight just never appears, with nothing to debug.
     */
    public function testNonBreakingSpacesBecomeOrdinarySpaces(): void
    {
        $chunk = SpokenText::script(['body' => '<p>Alas&nbsp;otso ng umaga.</p>'])['chunks'][0];

        $this->assertSame('Alas otso ng umaga.', $chunk['find']);
        $this->assertStringNotContainsString("\u{00A0}", $chunk['find']);
    }

    public function testEmojiAreSilentButStayOnThePage(): void
    {
        $script = SpokenText::script(['body' => '📢 Mahalagang paalala sa lahat.']);
        $chunk  = $script['chunks'][0];

        $this->assertStringNotContainsString('📢', $chunk['say']);
        $this->assertStringContainsString('📢', (string) $chunk['find']);
    }

    // ── Expansion ────────────────────────────────────────────────────────────

    public function testNumberSignIsSpokenAsANumberNotAsTheWordNo(): void
    {
        $this->assertStringContainsString('Numero 2026', SpokenText::expand('Ordinance No. 2026-014', 'fil'));
        $this->assertStringContainsString('Number 2026', SpokenText::expand('Ordinance No. 2026-014', 'en'));
    }

    public function testOrdinanceNumberIsNotReadAsASubtraction(): void
    {
        $this->assertStringNotContainsString('2026-014', SpokenText::expand('Ordinansa Blg. 2026-014', 'fil'));
    }

    /**
     * "Blg." is how a barangay ordinance is actually numbered, and the full
     * stop in it is part of the word — left unlisted it ended the sentence and
     * the reader stopped dead between "Ordinansa Blg." and its number.
     */
    public function testTheFilipinoNumberAbbreviationNeitherSplitsNorIsSpelledOut(): void
    {
        $this->assertSame(
            ['Ordinansa Blg. 2026-014 tungkol sa basura.'],
            SpokenText::sentences('Ordinansa Blg. 2026-014 tungkol sa basura.')
        );
        $this->assertStringContainsString('Bilang 2026', SpokenText::expand('Ordinansa Blg. 2026-014', 'fil'));
    }

    public function testAcronymsUseTheFormResidentsActuallySay(): void
    {
        $spoken = SpokenText::expand('Ang SK at ang BHW ay kasama.', 'fil');

        $this->assertStringContainsString('Es-Key', $spoken);
        $this->assertStringContainsString('Barangay Health Worker', $spoken);
    }

    public function testWholeHoursAndTimeRangesAreSpokenNaturally(): void
    {
        $this->assertSame('8 AM hanggang 11 AM', SpokenText::expand('8:00 AM - 11:00 AM', 'fil'));
        $this->assertSame('8 AM to 11 AM', SpokenText::expand('8:00 AM - 11:00 AM', 'en'));
        $this->assertStringContainsString('8:30 AM', SpokenText::expand('8:30 AM', 'fil'));
    }

    public function testUrlsAreNotReadCharacterByCharacter(): void
    {
        $spoken = SpokenText::expand('Bisitahin ang https://baranggabay.ph/ordinances para sa detalye.', 'fil');

        $this->assertStringNotContainsString('https', $spoken);
        $this->assertStringContainsString('link', $spoken);
    }

    // ── Chunking and the cap ─────────────────────────────────────────────────

    /**
     * Chrome stops speaking after roughly fifteen seconds of a single
     * utterance, so no chunk may be long enough to reach that.
     */
    public function testNoChunkIsLongEnoughToHitTheChromeCutout(): void
    {
        $sentence = 'Ang lahat ng residente ng Bayogo ay inaanyayahang dumalo sa '
                  . 'pagpupulong tungkol sa basura, tubig, kuryente, kalsada, '
                  . 'kalusugan, kaligtasan, at iba pang usapin ng barangay na '
                  . 'tatalakayin ng konseho sa susunod na linggo ng umaga.';

        $script = SpokenText::script(['body' => $sentence]);

        $this->assertGreaterThan(1, count($script['chunks']), 'A long sentence must be split');
        foreach ($script['chunks'] as $chunk) {
            $this->assertLessThanOrEqual(200, mb_strlen($chunk['say']));
        }
    }

    public function testAnAbsurdlyLongPostIsCappedAndSaysSo(): void
    {
        $script = SpokenText::script([
            'body' => str_repeat('Ito ay isang pangungusap na paulit-ulit. ', 500),
        ]);

        $this->assertTrue($script['truncated']);
        $this->assertLessThanOrEqual(SpokenText::MAX_CHARS, $script['chars']);
    }

    // ── Order and language ───────────────────────────────────────────────────

    public function testUrgencyAndVenueAreSpokenBeforeTheBody(): void
    {
        $script = SpokenText::script([
            'title' => 'Libreng bakuna',
            'lead'  => ['Ito ay isang agarang anunsyo.', 'Lugar: Barangay Hall.'],
            'body'  => 'Dumating na ang mga bakuna.',
            'tail'  => ['Salamat po.'],
        ]);

        $this->assertSame(
            ['title', 'lead', 'lead', 'body', 'tail'],
            array_column($script['chunks'], 'kind')
        );
    }

    /**
     * Manobo is read by the Filipino voice — and must always be flagged as an
     * approximation.
     *
     * No provider anywhere has an Agusan Manobo voice. Borrowing the Filipino
     * one is a deliberate compromise (the orthographies and most of the sound
     * inventory line up, so it is broadly intelligible), but the stress, the
     * schwa and the glottal stops come out Filipino. The compromise is only
     * acceptable while it is labelled, so the flag is pinned right beside the
     * voice: nobody can add the one without the other.
     */
    public function testManoboBorrowsTheFilipinoVoiceAndIsMarkedApproximate(): void
    {
        $this->assertSame('fil-PH', SpokenText::speechLang('msm'));
        $this->assertTrue(SpokenText::isApproximateVoice('msm'));

        $this->assertSame('fil-PH', SpokenText::speechLang('fil'));
        $this->assertSame('en-PH', SpokenText::speechLang('en'));
    }

    /** Only Manobo is an approximation; the other two are their own language. */
    public function testFilipinoAndEnglishAreNotApproximations(): void
    {
        $this->assertFalse(SpokenText::isApproximateVoice('fil'));
        $this->assertFalse(SpokenText::isApproximateVoice('en'));
    }

    public function testVoiceListsPreferThePhilippineTags(): void
    {
        $this->assertSame('fil-PH', SpokenText::voiceCandidates('fil')[0]);
        $this->assertContains('tl-PH', SpokenText::voiceCandidates('fil'), 'iOS labels the same voice tl-PH');
        $this->assertSame('en-PH', SpokenText::voiceCandidates('en')[0]);

        // Manobo tries Cebuano first — the spoken mix is code-switched with
        // Bisaya (see ManoboAutoTranslator) and a ceb voice says that half
        // correctly where fil-PH does not — then falls back through the same
        // Filipino list as before. Still never an English voice.
        $this->assertSame('ceb-PH', SpokenText::voiceCandidates('msm')[0]);
        $this->assertContains('ceb', SpokenText::voiceCandidates('msm'));
        $this->assertSame(
            SpokenText::voiceCandidates('fil'),
            \array_values(\array_diff(SpokenText::voiceCandidates('msm'), ['ceb-PH', 'ceb']))
        );
    }
}
