<?php
declare(strict_types=1);

use App\Services\PostScript;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Pins what each language actually speaks.
 *
 * Every assertion here corresponds to a failure a resident would hear: an
 * "English" button that reads Filipino, a Manobo track that turns out to be
 * the Filipino text, an edited post whose cached audio still says the old
 * thing, or an ordinance reading twenty pages of legal PDF aloud.
 *
 * Built on the real FREE WIFI event this feature was developed against, which
 * is the useful shape: written in Filipino, translated by hand into English
 * and Manobo, with a community recording attached.
 */
final class PostScriptTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['bg_is_back_office'] = false;
        set_locale('fil');
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function event(array $overrides = []): array
    {
        return $overrides + [
            'id'                 => 2,
            'source_lang'        => 'fil',
            'title'              => 'FREE WIFI',
            'description'        => 'every house have a free wifi',
            'title_en'           => 'FREE WIFI',
            'description_en'     => 'every house has a free wifi',
            'title_manobo'       => 'LIBRE LANG ANG WIFI',
            'description_manobo' => 'wada sika pudot',
            'venue'              => 'Barangay Hall Bayogo',
            'event_date'         => '2026-08-27 12:00:00',
            'end_date'           => '2026-08-29 12:00:00',
            'status'             => 'upcoming',
            'audio_manobo_path'  => '/uploads/manobo-audio/abc.webm',
        ];
    }

    // ── Which text each language speaks ──────────────────────────────────────

    public function testEachLanguageSpeaksItsOwnText(): void
    {
        $row = $this->event();

        $this->assertStringContainsString('every house has a free wifi', PostScript::build('event', $row, 'en')['text']);
        $this->assertStringContainsString('every house have a free wifi', PostScript::build('event', $row, 'fil')['text']);
        // CHANGED: "pudot" is now a recognised headword (migration 030's
        // dictionary import added it — "kumuha/get"), so ManoboSpeech's
        // documented schwa rule (written `o` -> spoken `u`) now correctly
        // applies to it, same as it already does for every other recognised
        // Manobo word. See ManoboSpeech::respell()/isManobo().
        $this->assertStringContainsString('wada sika pudut', PostScript::build('event', $row, 'msm')['text']);
    }

    /**
     * The Manobo track must never turn out to be the Filipino text.
     *
     * This is the failure the whole language-per-track design exists to
     * prevent, and it is invisible to anyone who does not speak Manobo — the
     * audio plays, it sounds like Filipino, and nobody knows it is wrong.
     */
    public function testManoboSpeaksManoboTextNotFilipino(): void
    {
        $manobo = PostScript::build('event', $this->event(), 'msm')['text'];

        $this->assertStringContainsString('LIBRE LANG ANG WIFI', $manobo);
        $this->assertStringNotContainsString('every house have a free wifi', $manobo);
    }

    /**
     * A missing translation is unavailable, never quietly filled in with the
     * original. An English button that speaks Filipino is a bug, not a
     * fallback — so the UI greys the language out instead.
     */
    public function testAMissingTranslationIsUnavailableRatherThanSubstituted(): void
    {
        $row = $this->event(['title_en' => '', 'description_en' => '']);

        $script = PostScript::build('event', $row, 'en');

        $this->assertFalse($script['available']);
        $this->assertSame([], $script['chunks']);
        $this->assertSame('', $script['text']);
    }

    /** The language a post was written in always reads the original columns. */
    public function testTheSourceLanguageReadsTheOriginal(): void
    {
        $row = $this->event(['source_lang' => 'en', 'title_en' => '', 'description_en' => '']);

        $script = PostScript::build('event', $row, 'en');

        $this->assertTrue($script['available'], 'An English-authored post is its own English version');
        $this->assertStringContainsString('every house have a free wifi', $script['text']);
    }

    /**
     * Machine English held back from the screen must be held back from the
     * speaker too. An urgent notice whose translation might have inverted its
     * meaning is exactly the one nobody should hear unreviewed.
     */
    public function testTheUrgentReviewGateAlsoSilencesTheVoice(): void
    {
        $row = $this->event(['en_review_state' => 'pending']);

        $this->assertFalse(PostScript::build('event', $row, 'en')['available']);
        $this->assertTrue(PostScript::build('event', $row, 'fil')['available'], 'The original is never gated');
    }

    // ── Order and framing ────────────────────────────────────────────────────

    public function testAnEventSaysWhenAndWhereBeforeTheDescription(): void
    {
        $chunks = PostScript::build('event', $this->event(), 'fil')['chunks'];

        $said     = array_column($chunks, 'say');
        $whenAt   = $this->indexOfMatch($said, '/August 27/');
        $venueAt  = $this->indexOfMatch($said, '/Barangay Hall Bayogo/');
        // Matched on the description, not "free wifi" — that is also the title,
        // which is spoken first by design.
        $bodyAt   = $this->indexOfMatch($said, '/every house have/i');

        $this->assertGreaterThan(-1, $whenAt,  'The date must be spoken');
        $this->assertGreaterThan(-1, $venueAt, 'The venue must be spoken');
        $this->assertLessThan($bodyAt, $whenAt,  'Date before the description');
        $this->assertLessThan($bodyAt, $venueAt, 'Venue before the description');
    }

    public function testAnUrgentAnnouncementSaysSoFirst(): void
    {
        $row = [
            'id'          => 5,
            'source_lang' => 'fil',
            'title'       => 'Bagyo',
            'body'        => '<p>May paparating na bagyo.</p>',
            'urgency'     => 'urgent',
            'category'    => 'safety',
            'published_at' => '2026-09-01 08:00:00',
        ];

        $chunks = PostScript::build('announcement', $row, 'fil')['chunks'];

        $this->assertSame('title', $chunks[0]['kind']);
        $this->assertStringContainsString('agarang', $chunks[1]['say'], 'Urgency is the first thing after the title');
    }

    /**
     * An ordinance speaks its plain-language summary, never the PDF. Reading a
     * twenty-page document aloud helps nobody and would be metered per
     * character by the provider.
     */
    public function testAnOrdinanceSpeaksTheSummaryAndSaysThatIsWhatItWas(): void
    {
        $row = [
            'id'           => 9,
            'source_lang'  => 'fil',
            'title'        => 'Ordinansa sa basura',
            'description'  => 'Maikling paglalarawan.',
            'ordinance_no' => '2026-014',
            'ai_summary'   => 'Bawal magtapon ng basura sa kanal.',
            'status'       => 'active',
        ];

        $script = PostScript::build('ordinance', $row, 'fil');

        $this->assertStringContainsString('Bawal magtapon', $script['text']);
        $this->assertStringContainsString('Buod', $script['text'], 'It must say it was only a summary');

        $kinds = array_column($script['chunks'], 'kind');
        $this->assertSame('tail', end($kinds));
    }

    /**
     * The summary is Filipino, so only the Filipino track may speak it. An
     * English listener gets the translated description instead of a Filipino
     * summary announced under an English label.
     */
    public function testTheFilipinoSummaryIsNotSpokenByTheEnglishTrack(): void
    {
        $row = [
            'id'             => 9,
            'source_lang'    => 'fil',
            'title'          => 'Ordinansa sa basura',
            'description'    => 'Maikling paglalarawan.',
            'description_en' => 'A short description.',
            'title_en'       => 'Waste ordinance',
            'ordinance_no'   => '2026-014',
            'ai_summary'     => 'Bawal magtapon ng basura sa kanal.',
            'status'         => 'active',
        ];

        $english = PostScript::build('ordinance', $row, 'en')['text'];

        $this->assertStringNotContainsString('Bawal magtapon', $english);
        $this->assertStringContainsString('A short description', $english);
    }

    // ── Staleness ────────────────────────────────────────────────────────────

    /**
     * Editing a post has to change its hash, or cached audio would keep
     * serving the old wording — and the difference between the old and new
     * wording is the entire reason staff edited it.
     */
    public function testEditingThePostChangesTheHash(): void
    {
        $before = PostScript::build('event', $this->event(), 'fil')['hash'];
        $after  = PostScript::build('event', $this->event(['description' => 'no free wifi today']), 'fil')['hash'];

        $this->assertNotSame($before, $after);
        $this->assertSame(64, strlen($before), 'sha256, hex');
    }

    public function testAnUnchangedPostKeepsItsHash(): void
    {
        $this->assertSame(
            PostScript::build('event', $this->event(), 'fil')['hash'],
            PostScript::build('event', $this->event(), 'fil')['hash']
        );
    }

    /** Editing one language must not invalidate the others' audio. */
    public function testEachLanguageHashesIndependently(): void
    {
        $row     = $this->event();
        $edited  = $this->event(['description_en' => 'every household has free wifi']);

        $this->assertSame(
            PostScript::build('event', $row, 'fil')['hash'],
            PostScript::build('event', $edited, 'fil')['hash'],
            'An English edit must not make the Filipino audio stale'
        );
        $this->assertNotSame(
            PostScript::build('event', $row, 'en')['hash'],
            PostScript::build('event', $edited, 'en')['hash']
        );
    }

    // ── Rendering language ───────────────────────────────────────────────────

    /**
     * The spoken date and urgency lines follow the track, not the page.
     *
     * Audio is generated from a staff request, and back-office pages always
     * render in English — so without an explicit locale every track would be
     * framed in English regardless of the language it then reads.
     */
    public function testSpokenFramingFollowsTheTrackNotTheRequest(): void
    {
        $GLOBALS['bg_is_back_office'] = true;       // as a save request would be
        set_locale('fil');

        $filipino = PostScript::build('event', $this->event(), 'fil')['text'];
        $english  = PostScript::build('event', $this->event(), 'en')['text'];

        $this->assertStringContainsString('Lugar:', $filipino);
        $this->assertStringContainsString('Venue:', $english);
    }

    public function testBuildAllReturnsEveryLanguage(): void
    {
        $all = PostScript::buildAll('event', $this->event());

        $this->assertSame(['en', 'fil', 'msm'], array_keys($all));
        foreach ($all as $script) {
            $this->assertTrue($script['available']);
        }
    }

    /** @param list<string> $items */
    private function indexOfMatch(array $items, string $pattern): int
    {
        foreach ($items as $i => $item) {
            if (preg_match($pattern, $item)) {
                return $i;
            }
        }
        return -1;
    }
}
