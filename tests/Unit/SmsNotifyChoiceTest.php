<?php
declare(strict_types=1);

use App\Controllers\OrdinanceController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The "also send this by SMS" choice.
 *
 * Every text message costs Semaphore credits and a blast is one message per
 * resident, so the two ways this can go wrong are not equally bad:
 *
 *   Sending when not asked   spends real money and texts the whole barangay.
 *   Not sending when asked   means residents miss a notice.
 *
 * Both are pinned here. The subtle one is the HTML checkbox contract: an
 * unchecked box submits NOTHING — the key is absent from $_POST entirely, it
 * is not "0" or "" — so a naive isset() or a truthiness test on a missing key
 * has to be read carefully.
 */
final class SmsNotifyChoiceTest extends TestCase
{
    /** Mirrors the controllers: `!empty($_POST['send_sms'])`. */
    private function wantsSms(array $post): bool
    {
        return !empty($post['send_sms']);
    }

    /** Mirrors ScheduledPublisher: `(int) ($post['notify_sms'] ?? 1) === 1`. */
    private function dispatchSendsSms(array $row): bool
    {
        return (int) ($row['notify_sms'] ?? 1) === 1;
    }

    // ── Reading the form ──────────────────────────────────────────────────

    /** A ticked box posts value="1". */
    public function testTickedBoxRequestsSms(): void
    {
        $this->assertTrue($this->wantsSms(['send_sms' => '1']));
    }

    /**
     * The contract that matters: an unticked checkbox is ABSENT from $_POST.
     * There is no 'send_sms' => '0' to look for.
     */
    public function testUntickedBoxIsAbsentAndMeansNo(): void
    {
        $this->assertFalse($this->wantsSms([]), 'A missing key must mean "do not send"');
        $this->assertFalse($this->wantsSms(['title' => 'Something else']));
    }

    /** Defensive: an explicit falsy value must also mean no. */
    public function testExplicitFalsyValuesMeanNo(): void
    {
        $this->assertFalse($this->wantsSms(['send_sms' => '0']));
        $this->assertFalse($this->wantsSms(['send_sms' => '']));
    }

    // ── Acting on the stored choice ───────────────────────────────────────

    /** A post saved with the box ticked texts residents. */
    public function testStoredYesSends(): void
    {
        $this->assertTrue($this->dispatchSendsSms(['notify_sms' => 1]));
        $this->assertTrue($this->dispatchSendsSms(['notify_sms' => '1']));
    }

    /** A post saved with the box unticked does not. */
    public function testStoredNoDoesNotSend(): void
    {
        $this->assertFalse($this->dispatchSendsSms(['notify_sms' => 0]));
        $this->assertFalse($this->dispatchSendsSms(['notify_sms' => '0']));
    }

    /**
     * Falls back to sending when nothing is stored.
     *
     * Announcements and events blasted SMS unconditionally before this feature
     * existed. A database that has not run migration 020, or any caller that
     * does not set the key, must keep doing what it did rather than silently
     * going quiet — a barangay relying on those texts should not lose them to
     * an unapplied migration.
     */
    public function testMissingChoiceFallsBackToSending(): void
    {
        $this->assertTrue($this->dispatchSendsSms([]), 'Absent column must not silence notifications');
        $this->assertTrue($this->dispatchSendsSms(['notify_sms' => null]));
    }

    // ── The migration ─────────────────────────────────────────────────────

    /**
     * Migration 020 must carry no data statement.
     *
     * The runner replays every migration on every database connection, so an
     * UPDATE there fires forever. The column DEFAULT gives existing rows the
     * right value without one — see migration 019, where an unbounded backfill
     * silently cancelled every scheduled post.
     */
    public function testMigrationUsesColumnDefaultRatherThanABackfill(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/migrations/020_add_sms_notify_choice.sql');

        $this->assertIsString($sql);
        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS', $sql, 'DDL must be replay-safe');
        $this->assertStringContainsString('DEFAULT 1', $sql, 'Existing rows keep the old always-send behaviour');
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*UPDATE\s/mi',
            $sql,
            'No UPDATE in a migration: the runner replays it on every connection'
        );
    }

    // ── The SMS body ──────────────────────────────────────────────────────

    /**
     * One SMS segment is 160 characters and Semaphore bills per segment, so a
     * message that quietly runs to 161 costs double for every resident in the
     * barangay. An earlier version reached 171 with a long number and a long
     * title, which is exactly the combination nobody tries by hand.
     *
     * @dataProvider ordinanceInputs
     */
    public function testOrdinanceMessageAlwaysFitsOneSegment(string $no, string $title, string $case): void
    {
        $message = OrdinanceController::buildOrdinanceSms($no, $title);

        $this->assertLessThanOrEqual(
            160,
            mb_strlen($message),
            "{$case}: message runs to " . mb_strlen($message) . " characters\n{$message}"
        );
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function ordinanceInputs(): array
    {
        return [
            'typical'          => ['Ordinance No. 2026-014', 'Anti-Littering Ordinance', 'typical'],
            'long number'      => ['Ordinance No. 2026-014-A Series of 2026 Amended', 'Waste Ordinance', 'long number'],
            'long title'       => ['Ord. 2026-01', 'Anti-Littering, Waste Segregation and Proper Disposal Ordinance of Barangay Bayogo Madrid', 'long title'],
            'both long'        => [str_repeat('N', 80), str_repeat('T', 200), 'both long'],
            'empty title'      => ['Ordinance No. 2026-014', '', 'empty title'],
            'empty everything' => ['', '', 'empty everything'],
            'unicode title'    => ['Ord. 2026-02', str_repeat('ñ', 200), 'multibyte title'],
        ];
    }

    /** Truncating for length must not throw away what identifies the policy. */
    public function testOrdinanceMessageKeepsTheNumberAndTheSender(): void
    {
        $message = OrdinanceController::buildOrdinanceSms(
            'Ordinance No. 2026-014',
            'Anti-Littering, Waste Segregation and Proper Disposal Ordinance of Barangay Bayogo Madrid'
        );

        $this->assertStringContainsString('BarangGabay', $message, 'Residents must see who texted them');
        $this->assertStringContainsString('2026-014', $message, 'The number is how a resident looks the policy up');
        $this->assertStringContainsString('portal', $message, 'The message must point somewhere for the full text');
    }

    /** A missing title must not leave a dangling separator. */
    public function testEmptyTitleDoesNotLeaveADanglingSeparator(): void
    {
        $message = OrdinanceController::buildOrdinanceSms('Ordinance No. 2026-014', '');

        $this->assertStringNotContainsString('2026-014 - ', $message);
        $this->assertStringContainsString('2026-014', $message);
    }
}
