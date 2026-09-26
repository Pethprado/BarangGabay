<?php
declare(strict_types=1);

use App\Controllers\ResidentController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Boundary rules behind the resident dashboard's "Happening today / This week"
 * strip, and the "new since your last visit" counter.
 *
 * Both are pinned to a FIXED reference timestamp rather than the real clock, so
 * the suite gives the same answer whatever day (or hour) it runs on — an
 * important property here, since every assertion is about a date boundary.
 */
final class ResidentDashboardTest extends TestCase
{
    /** Wed 2026-09-16, 10:30 — a deliberately mid-day, mid-week reference. */
    private const NOW = 1789561800;

    /** @return array<string,mixed> */
    private function event(string $date, string $title = 'Event'): array
    {
        return ['id' => 1, 'title' => $title, 'event_date' => $date, 'slug' => 'e'];
    }

    /** @param array{today:array,week:array,later:array,past:array} $b */
    private function titles(array $b, string $bucket): array
    {
        return array_column($b[$bucket], 'title');
    }

    public function testReferenceTimestampIsTheDayWeThinkItIs(): void
    {
        // Guards every other assertion in this file: if the constant drifts,
        // fail here with a clear message rather than in a confusing boundary test.
        $this->assertSame('2026-09-16', date('Y-m-d', self::NOW));
    }

    // ── today ────────────────────────────────────────────────────────────

    public function testEventExactlyAtMidnightTodayIsBucketedAsToday(): void
    {
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2026-09-16 00:00:00', 'midnight')],
            self::NOW
        );

        $this->assertSame(['midnight'], $this->titles($buckets, 'today'));
        $this->assertSame([], $buckets['week']);
        $this->assertSame([], $buckets['later']);
        $this->assertSame([], $buckets['past']);
    }

    public function testEventLateTonightIsStillToday(): void
    {
        // The boundary is the calendar day, not "within the next 24 hours".
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2026-09-16 23:59:00', 'tonight')],
            self::NOW
        );

        $this->assertSame(['tonight'], $this->titles($buckets, 'today'));
    }

    public function testEventEarlierTodayIsStillTodayNotPast(): void
    {
        // 08:00 has already passed at the 10:30 reference time, but a fiesta
        // running today must not drop off the dashboard mid-morning.
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2026-09-16 08:00:00', 'this-morning')],
            self::NOW
        );

        $this->assertSame(['this-morning'], $this->titles($buckets, 'today'));
        $this->assertSame([], $buckets['past']);
    }

    // ── the week / later boundary ────────────────────────────────────────

    public function testEventSevenDaysOutIsTheLastDayOfThisWeek(): void
    {
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2026-09-23 09:00:00', 'day-seven')],
            self::NOW
        );

        $this->assertSame(['day-seven'], $this->titles($buckets, 'week'), 'day 7 is inclusive');
        $this->assertSame([], $buckets['later']);
    }

    public function testEventEightDaysOutTipsOverIntoLater(): void
    {
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2026-09-24 09:00:00', 'day-eight')],
            self::NOW
        );

        $this->assertSame(['day-eight'], $this->titles($buckets, 'later'));
        $this->assertSame([], $buckets['week']);
    }

    public function testTomorrowIsThisWeekNotToday(): void
    {
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2026-09-17 00:00:00', 'tomorrow')],
            self::NOW
        );

        $this->assertSame(['tomorrow'], $this->titles($buckets, 'week'));
        $this->assertSame([], $buckets['today']);
    }

    // ── past ─────────────────────────────────────────────────────────────

    public function testPastEventIsNotShownAsThisWeek(): void
    {
        // Regression guard. A plain `dayKey <= weekEnd` test puts yesterday in
        // "This week", because yesterday is also <= seven days from now.
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2026-09-15 09:00:00', 'yesterday')],
            self::NOW
        );

        $this->assertSame(['yesterday'], $this->titles($buckets, 'past'));
        $this->assertSame([], $buckets['week'], 'a finished event must never surface as upcoming');
        $this->assertSame([], $buckets['today']);
        $this->assertSame([], $buckets['later']);
    }

    public function testLongPastEventIsAlsoBucketedAsPast(): void
    {
        $buckets = ResidentController::bucketEventsByProximity(
            [$this->event('2025-01-05 09:00:00', 'last-year')],
            self::NOW
        );

        $this->assertSame(['last-year'], $this->titles($buckets, 'past'));
    }

    // ── mixed input ──────────────────────────────────────────────────────

    public function testMixedEventsLandInTheirOwnBucketsAndKeepOrder(): void
    {
        $buckets = ResidentController::bucketEventsByProximity([
            $this->event('2026-09-15 09:00:00', 'past'),
            $this->event('2026-09-16 00:00:00', 'today-a'),
            $this->event('2026-09-16 18:00:00', 'today-b'),
            $this->event('2026-09-23 09:00:00', 'week-edge'),
            $this->event('2026-09-24 09:00:00', 'later'),
        ], self::NOW);

        $this->assertSame(['today-a', 'today-b'], $this->titles($buckets, 'today'));
        $this->assertSame(['week-edge'], $this->titles($buckets, 'week'));
        $this->assertSame(['later'], $this->titles($buckets, 'later'));
        $this->assertSame(['past'], $this->titles($buckets, 'past'));
    }

    public function testEmptyInputReturnsAllFourBucketsEmpty(): void
    {
        $buckets = ResidentController::bucketEventsByProximity([], self::NOW);

        $this->assertSame(['today', 'week', 'later', 'past'], array_keys($buckets));
        foreach ($buckets as $name => $rows) {
            $this->assertSame([], $rows, "bucket {$name} should be empty");
        }
    }

    public function testUnparseableEventDateIsDroppedRatherThanMisbucketed(): void
    {
        $buckets = ResidentController::bucketEventsByProximity([
            $this->event('', 'blank'),
            $this->event('not a date', 'garbage'),
            $this->event('2026-09-16 09:00:00', 'good'),
        ], self::NOW);

        $this->assertSame(['good'], $this->titles($buckets, 'today'));
        $this->assertSame([], $buckets['week']);
        $this->assertSame([], $buckets['later']);
        $this->assertSame([], $buckets['past']);
    }

    // ── "new since your last visit" ──────────────────────────────────────

    public function testNewSinceCountIsZeroWhenMarkerIsNull(): void
    {
        // Must not reach the database: with no marker there is nothing to
        // compare against, so the guard returns before querying.
        $this->assertSame(0, ResidentController::newSinceCount(null));
    }

    public function testNewSinceCountIsZeroWhenMarkerIsEmptyString(): void
    {
        $this->assertSame(0, ResidentController::newSinceCount(''));
    }

    public function testNewSinceCountIsZeroWhenMarkerIsOnlyWhitespace(): void
    {
        $this->assertSame(0, ResidentController::newSinceCount("   \t\n "));
    }
}
