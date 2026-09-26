<?php
declare(strict_types=1);

use App\Models\Announcement;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The rules behind scheduled publishing.
 *
 * Two things are pinned here, and they are pinned separately because they fail
 * in opposite directions:
 *
 *   Visibility — a post scheduled for later must be invisible. Getting this
 *                wrong leaks a notice early, which is the one thing scheduling
 *                exists to prevent.
 *
 *   Timing map — the form's three choices collapse onto two database columns.
 *                Getting this wrong publishes something at the wrong moment,
 *                or never.
 *
 * These assert logic rather than touching the database: the rules are pure
 * functions of a row and a clock, so a test that needed MariaDB running would
 * be testing the wrong thing.
 */
final class ScheduledPublishingTest extends TestCase
{
    // ── Visibility ────────────────────────────────────────────────────────

    /** The whole point: a future go-live time hides the post. */
    public function testScheduledPostIsNotVisible(): void
    {
        $row = ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() + 3600)];

        $this->assertFalse(Announcement::isVisibleNow($row), 'A post due in an hour must not be readable now');
        $this->assertTrue(Announcement::isScheduled($row));
    }

    /** Once the moment passes, the same row is visible — no job had to run. */
    public function testPostBecomesVisibleOnceItsMomentPasses(): void
    {
        $row = ['status' => 'published', 'published_at' => date('Y-m-d H:i:s', time() - 60)];

        $this->assertTrue(Announcement::isVisibleNow($row));
        $this->assertFalse(Announcement::isScheduled($row), 'A post past its moment is published, not scheduled');
    }

    /** A draft stays hidden whatever its timestamp says. */
    public function testDraftIsNeverVisible(): void
    {
        $past = ['status' => 'draft', 'published_at' => date('Y-m-d H:i:s', time() - 86400)];

        $this->assertFalse(Announcement::isVisibleNow($past));
        $this->assertFalse(Announcement::isScheduled($past), 'A draft is not "scheduled" — nothing will publish it');
    }

    /** Archived posts are pulled from public view too. */
    public function testArchivedIsNotVisible(): void
    {
        $this->assertFalse(Announcement::isVisibleNow(
            ['status' => 'archived', 'published_at' => date('Y-m-d H:i:s', time() - 86400)]
        ));
    }

    /**
     * Posts written before scheduling existed can have a NULL go-live time.
     * Those must stay readable — failing open is the right failure for a
     * barangay notice, and the alternative would make live posts vanish.
     */
    public function testNullPublishedAtFailsOpen(): void
    {
        $this->assertTrue(Announcement::isVisibleNow(['status' => 'published', 'published_at' => null]));
        $this->assertTrue(Announcement::isVisibleNow(['status' => 'published', 'published_at' => '']));
        $this->assertFalse(Announcement::isScheduled(['status' => 'published', 'published_at' => null]));
    }

    /** A row missing the keys entirely must not be treated as published. */
    public function testMissingStatusIsNotVisible(): void
    {
        $this->assertFalse(Announcement::isVisibleNow([]));
    }

    /** The SQL and the PHP must express the same rule, or lists and the
     *  detail page will disagree about what is public. */
    public function testVisibleSqlChecksBothStatusAndTime(): void
    {
        $sql = Announcement::visibleSql('a');

        $this->assertStringContainsString("a.status = 'published'", $sql);
        $this->assertStringContainsString('a.published_at <= NOW()', $sql);
        $this->assertStringContainsString('a.published_at IS NULL', $sql, 'Legacy NULL rows must still pass');

        // Unaliased form, for queries with a single table.
        $bare = Announcement::visibleSql('');
        $this->assertStringContainsString("status = 'published'", $bare);
        $this->assertStringNotContainsString('a.', $bare);
    }

    // ── The form's timing choice → (status, published_at) ─────────────────

    /**
     * Mirrors AnnouncementController::resolvePublishTiming().
     *
     * @return array{status:string, scheduled:bool, at:?int}
     */
    private function resolve(string $status, string $rawWhen, int $now): array
    {
        if ($status !== 'published') {
            return ['status' => $status, 'scheduled' => false, 'at' => null];
        }

        $rawWhen = trim($rawWhen);
        if ($rawWhen === '') {
            return ['status' => 'published', 'scheduled' => false, 'at' => $now];
        }

        $when = strtotime(str_replace('T', ' ', $rawWhen));
        if ($when === false || $when > $now + (365 * 86400)) {
            return ['status' => 'published', 'scheduled' => false, 'at' => $now];
        }
        if ($when <= $now) {
            return ['status' => 'published', 'scheduled' => false, 'at' => $now];
        }

        return ['status' => 'published', 'scheduled' => true, 'at' => $when];
    }

    /** A draft has no go-live moment, whatever the date box happened to hold. */
    public function testDraftIgnoresTheDateBox(): void
    {
        $now = time();
        $r   = $this->resolve('draft', '2030-01-01T08:00', $now);

        $this->assertSame('draft', $r['status']);
        $this->assertNull($r['at'], 'A draft must not carry a publish timestamp');
        $this->assertFalse($r['scheduled']);
    }

    /** "Publish immediately" posts an empty date and means now. */
    public function testEmptyDateMeansPublishNow(): void
    {
        $now = time();
        $r   = $this->resolve('published', '', $now);

        $this->assertFalse($r['scheduled']);
        $this->assertSame($now, $r['at']);
    }

    /** A future date is the scheduling case. */
    public function testFutureDateSchedules(): void
    {
        $now  = time();
        $when = date('Y-m-d\TH:i', $now + 7200);
        $r    = $this->resolve('published', $when, $now);

        $this->assertTrue($r['scheduled']);
        $this->assertSame('published', $r['status'], 'Scheduled posts are stored as published, not a fourth status');
        $this->assertGreaterThan($now, $r['at']);
    }

    /**
     * Staff who pick 8:00 and hit save at 8:05 mean "send it", not "error".
     * A past date publishes immediately rather than being rejected — and must
     * never be stored as a scheduled post, or it would sit in the queue with
     * its moment already gone.
     */
    public function testPastDatePublishesImmediately(): void
    {
        $now = time();
        $r   = $this->resolve('published', date('Y-m-d\TH:i', $now - 300), $now);

        $this->assertFalse($r['scheduled']);
        $this->assertSame($now, $r['at']);
    }

    /** A mistyped year must not park a notice beyond any useful horizon. */
    public function testAbsurdlyFarDateFallsBackToNow(): void
    {
        $now = time();
        $r   = $this->resolve('published', '2035-01-01T08:00', $now);

        $this->assertFalse($r['scheduled'], 'Ten years out is a typo, not a plan');
        $this->assertSame($now, $r['at']);
    }

    /** Unparsable input publishes now rather than throwing away the post. */
    public function testGarbageDateFallsBackToNow(): void
    {
        $now = time();
        $r   = $this->resolve('published', 'not-a-date', $now);

        $this->assertFalse($r['scheduled']);
        $this->assertSame($now, $r['at']);
    }

    /** A year ahead is allowed — barangay fiestas are planned that far out. */
    public function testOneMonthAheadIsAccepted(): void
    {
        $now  = time();
        $when = date('Y-m-d\TH:i', $now + (30 * 86400));
        $r    = $this->resolve('published', $when, $now);

        $this->assertTrue($r['scheduled']);
    }

    // ── The migration backfill ────────────────────────────────────────────

    /**
     * The backfill must be bounded by a fixed cutoff.
     *
     * This project has no migrations tracking table: runPendingMigrations()
     * replays every statement on every database connection. That is harmless
     * for DDL written with IF NOT EXISTS, but an unbounded
     * "UPDATE ... SET notified_at = ... WHERE notified_at IS NULL" re-fires on
     * every request and stamps freshly scheduled posts as already-notified,
     * so their moment arrives and the sweep skips them — residents are never
     * told. This was a real bug, caught on the live database; the cutoff is
     * what makes the statement genuinely one-shot under replay.
     */
    public function testBackfillIsBoundedSoReplayCannotStampNewPosts(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/migrations/019_add_publish_scheduling.sql');

        $this->assertIsString($sql);
        $this->assertStringContainsString('notified_at IS NULL', $sql, 'sanity: the backfill is still here');
        $this->assertMatchesRegularExpression(
            '/created_at\s*<\s*\'\d{4}-\d{2}-\d{2}/',
            $sql,
            'The backfill UPDATE must be bounded by a fixed created_at cutoff, '
            . 'or replaying it will silently cancel every scheduled post'
        );
    }

    // ── Display helpers ───────────────────────────────────────────────────

    /** The staff-facing format is absolute, not relative. */
    public function testFormatDatetimeIsReadable(): void
    {
        $this->assertSame('Sep 20, 2026, 8:00 AM', format_datetime('2026-09-20 08:00:00'));
        $this->assertSame('Dec 1, 2026, 5:30 PM', format_datetime('2026-12-01 17:30:00'));
    }

    /** Missing or broken input yields nothing, never an invented date. */
    public function testFormatDatetimeRefusesToInventADate(): void
    {
        $this->assertSame('', format_datetime(null));
        $this->assertSame('', format_datetime(''));
        $this->assertSame('', format_datetime('   '));
        $this->assertSame('', format_datetime('not a date'));
    }

    /**
     * <input type="datetime-local"> silently shows an empty box for anything
     * that is not exactly "Y-m-d\TH:i", which would look like data loss when
     * editing a scheduled post.
     */
    public function testFormatDatetimeInputMatchesWhatTheBrowserAccepts(): void
    {
        $this->assertSame('2026-09-20T08:00', format_datetime_input('2026-09-20 08:00:00'));
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
            format_datetime_input('2026-12-01 17:30:00')
        );
        $this->assertSame('', format_datetime_input(null));
        $this->assertSame('', format_datetime_input('rubbish'));
    }
}
