<?php
declare(strict_types=1);

use App\Models\Announcement;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The review gate on machine-translated URGENT announcements.
 *
 * Free machine translation can invert meaning. The case that prompted this:
 *
 *   Filipino : "May bagyo, lumikas na sa evacuation center."
 *   Free MT  : "A hurricane has evacuated the evacuation center."
 *
 * On a routine notice that is an annoyance; on a storm warning it is the
 * difference between a family leaving and a family staying put. These tests
 * pin the four cases the gate has to get right, at the level where the
 * decision is actually made — what localised_content() serves a reader.
 *
 * No database: the rule is a function of the row's columns, so the rows are
 * built here directly. That keeps the test honest about what the gate reads.
 */
final class UrgentTranslationGateTest extends TestCase
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

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'title'           => 'May bagyo, lumikas na',
            'body'            => 'May bagyo, lumikas na sa evacuation center.',
            'title_en'        => 'A hurricane has evacuated',
            'body_en'         => 'A hurricane has evacuated the evacuation center.',
            'en_is_auto'      => 1,
            'en_review_state' => Announcement::REVIEW_PENDING,
            'urgency'         => 'urgent',
        ], $overrides);
    }

    // ── 1. Urgent + free MT → not publicly visible until confirmed ────────

    public function testUrgentMachineTranslationIsHiddenUntilConfirmed(): void
    {
        set_locale('en');
        $pick = localised_content($this->row(), 'body');

        $this->assertSame(
            'May bagyo, lumikas na sa evacuation center.',
            $pick['text'],
            'A pending machine translation must not reach a reader'
        );
        $this->assertFalse($pick['translated'], 'It must read as "no translation yet"');
        $this->assertStringNotContainsString('has evacuated', $pick['text']);
    }

    public function testConfirmedTranslationBecomesVisible(): void
    {
        set_locale('en');
        $pick = localised_content(
            $this->row(['en_review_state' => Announcement::REVIEW_APPROVED]),
            'body'
        );

        $this->assertSame('A hurricane has evacuated the evacuation center.', $pick['text']);
        $this->assertTrue($pick['translated']);
        $this->assertTrue($pick['machine'], 'Confirmed-as-is text is still the machine\'s wording');
    }

    /** The gate hides the English only. Filipino readers are never affected. */
    public function testPendingGateDoesNotAffectTheOriginalLanguage(): void
    {
        set_locale('fil');
        $pick = localised_content($this->row(), 'body');

        $this->assertSame('May bagyo, lumikas na sa evacuation center.', $pick['text']);
        $this->assertTrue($pick['translated']);
    }

    // ── 2. Urgent + manually typed → no gate ─────────────────────────────

    public function testUrgentManualTranslationPublishesImmediately(): void
    {
        set_locale('en');
        $pick = localised_content($this->row([
            'en_is_auto'      => 0,
            'en_review_state' => Announcement::REVIEW_NONE,
            'title_en'        => 'There is a typhoon — evacuate now',
            'body_en'         => 'There is a typhoon. Go to the evacuation center now.',
        ]), 'body');

        $this->assertSame('There is a typhoon. Go to the evacuation center now.', $pick['text']);
        $this->assertTrue($pick['translated'], 'Text a person typed is never gated');
        $this->assertFalse($pick['machine'], 'And it carries no machine notice');
    }

    // ── 3. Non-urgent + free MT → unchanged behaviour ────────────────────

    public function testNonUrgentMachineTranslationPublishesImmediately(): void
    {
        set_locale('en');
        $pick = localised_content($this->row([
            'urgency'         => 'normal',
            'en_review_state' => Announcement::REVIEW_NONE,
            'title_en'        => 'Free vaccination',
            'body_en'         => 'There is free vaccination tomorrow.',
        ]), 'body');

        $this->assertSame('There is free vaccination tomorrow.', $pick['text']);
        $this->assertTrue($pick['translated'], 'Non-urgent posts must not be gated');
        $this->assertTrue($pick['machine'], 'They still carry the machine notice, as before');
    }

    // ── 4. Manobo is untouched by the gate ───────────────────────────────

    public function testManoboIsNeverGated(): void
    {
        set_locale('msm');
        $pick = localised_content($this->row([
            'body_manobo'    => 'Adunay bagyo, bakwit na sa pagbakwit sentro.',
            'manobo_is_auto' => 1,
        ]), 'body');

        $this->assertSame(
            'Adunay bagyo, bakwit na sa pagbakwit sentro.',
            $pick['text'],
            'Manobo has no alternative source, so it is never held back'
        );
        $this->assertTrue($pick['translated']);
        $this->assertTrue($pick['machine']);
    }

    // ── State handling ───────────────────────────────────────────────────

    /** A row from before migration 017 has no state column and must not break. */
    public function testRowWithoutTheStateColumnIsTreatedAsUngated(): void
    {
        set_locale('en');
        $row = $this->row();
        unset($row['en_review_state']);

        $pick = localised_content($row, 'body');

        $this->assertTrue($pick['translated'], 'A missing state column must default to ungated');
    }

    /** Only the three known states exist; anything else is ignored, not stored. */
    public function testReviewStateConstantsAreDistinct(): void
    {
        $states = [Announcement::REVIEW_NONE, Announcement::REVIEW_PENDING, Announcement::REVIEW_APPROVED];

        $this->assertSame($states, array_unique($states));
        $this->assertSame('pending', Announcement::REVIEW_PENDING);
    }

    /** The gate keys off the state column, not off urgency, at read time. */
    public function testTitleIsGatedAlongsideTheBody(): void
    {
        set_locale('en');
        $pick = localised_content($this->row(), 'title');

        $this->assertSame('May bagyo, lumikas na', $pick['text']);
        $this->assertFalse($pick['translated']);
    }
}
