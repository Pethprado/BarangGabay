<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Pins the length rules on a post: there are almost none, and that is on
 * purpose.
 *
 * A 50-character floor used to sit on announcement bodies. It blocked exactly
 * the notices this system exists to carry in a hurry — "Walang pasok bukas."
 * is nineteen characters — so it is gone. What remains is the minimum a post
 * needs to be a post at all (some words), and one ceiling on the headline,
 * which exists only because that column has to be a finite width.
 */
final class PostLengthTest extends TestCase
{
    /** Reach the controller's private emptiness check. */
    private function hasContent(string $html): bool
    {
        $method = new ReflectionMethod(\App\Controllers\AnnouncementController::class, 'hasContent');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, $html);
    }

    // ── No minimum ───────────────────────────────────────────────────────────

    /**
     * The shortest real announcements a barangay posts. Every one of these was
     * refused by the old rule.
     */
    public function testVeryShortAnnouncementsAreAccepted(): void
    {
        $realNotices = [
            '<p>Walang pasok bukas.</p>',                 // 19 characters
            '<p>Brownout 8AM-12NN.</p>',
            '<p>Libreng bakuna ngayon.</p>',
            '<p>Cancelled ang assembly.</p>',
            '<p>Ok</p>',                                   // as short as it gets
        ];

        foreach ($realNotices as $body) {
            $this->assertTrue($this->hasContent($body), $body);
        }
    }

    /** Length is not the test — presence is. */
    public function testAnEmptyEditorIsStillRefused(): void
    {
        $empty = [
            '',
            '   ',
            '<p></p>',
            '<p><br></p>',             // what Quill posts when untouched
            "<p>\n\n</p>",
            '<p>&nbsp;</p>',           // a space typed then deleted
            '<p>&nbsp;&nbsp;</p>',
            '<div><p><br></p></div>',
        ];

        foreach ($empty as $body) {
            $this->assertFalse(
                $this->hasContent($body),
                json_encode($body) . ' should not count as content'
            );
        }
    }

    /** A body made only of markup with no words is not content either. */
    public function testMarkupWithoutWordsIsNotContent(): void
    {
        $this->assertFalse($this->hasContent('<ul><li></li><li></li></ul>'));
        $this->assertTrue($this->hasContent('<ul><li>Tubig</li></ul>'));
    }

    // ── No maximum on the body ───────────────────────────────────────────────

    /**
     * A very long announcement has to pass. The column is LONGTEXT and nothing
     * in the save path truncates, so the only thing that could refuse it is a
     * rule someone adds later — which this test exists to catch.
     */
    public function testAVeryLongBodyIsAccepted(): void
    {
        $long = '<p>' . str_repeat('Ang barangay ay may programa para sa lahat. ', 2000) . '</p>';

        $this->assertGreaterThan(80000, strlen($long));
        $this->assertTrue($this->hasContent($long));
    }

    // ── The one ceiling, and it is on the headline only ──────────────────────

    /**
     * The title limit and the migration that widened the column must agree. If
     * they drift, the check passes and the database then truncates or throws —
     * which is the failure the check was added to prevent.
     */
    public function testTheTitleLimitMatchesTheColumnTheMigrationCreates(): void
    {
        $this->assertSame(500, post_title_limit());

        $migration = (string) file_get_contents(
            __DIR__ . '/../../database/migrations/023_widen_post_titles.sql'
        );

        foreach (['announcements', 'events', 'ordinances'] as $table) {
            $this->assertStringContainsString(
                "ALTER TABLE {$table} MODIFY COLUMN title VARCHAR(500)",
                $migration,
                "Migration 023 must widen {$table}.title to match post_title_limit()"
            );
        }
    }

    /**
     * Replaying the migration must be free. runPendingMigrations() re-runs
     * every file on every database connection, and an unguarded ALTER TABLE
     * can rebuild the table by copy even when nothing changes — a permanent
     * tax on every page load.
     */
    public function testTheWideningMigrationIsGuardedAgainstReplay(): void
    {
        $migration = (string) file_get_contents(
            __DIR__ . '/../../database/migrations/023_widen_post_titles.sql'
        );

        $this->assertStringContainsString('information_schema.COLUMNS', $migration);
        $this->assertStringContainsString('CHARACTER_MAXIMUM_LENGTH < 500', $migration);
        $this->assertSame(
            3,
            substr_count($migration, 'DO 0'),
            'Each table needs a no-op branch for when it is already wide enough'
        );
    }

    // ── Slugs stay inside their column whatever the title does ───────────────

    /**
     * Titles can now run long; slugs cannot. The slug column is VARCHAR(255)
     * and uniqueSlug() appends "-2", "-3" on collision, so the generated slug
     * has to leave room for that.
     */
    public function testALongTitleStillProducesASlugThatFits(): void
    {
        $title = str_repeat('mahabang pamagat ng anunsyo ', 40);   // ~1120 chars
        $slug  = generate_slug($title);

        $this->assertLessThanOrEqual(180, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
    }

    public function testShortTitlesAreNotAlteredBySlugCapping(): void
    {
        $this->assertSame('walang-pasok-bukas', generate_slug('Walang pasok bukas.'));
        $this->assertSame('libreng-check-up', generate_slug('  Libreng Check-up  '));
    }

    /**
     * The cut lands between words, so the address stays readable — the point
     * is that no segment is a fragment, not which word happens to land last.
     */
    public function testTheSlugIsCutBetweenWordsNotMidWord(): void
    {
        $slug     = generate_slug(str_repeat('barangay anunsyo ', 30));
        $segments = explode('-', $slug);

        $this->assertNotEmpty($segments);
        foreach ($segments as $segment) {
            $this->assertContains(
                $segment,
                ['barangay', 'anunsyo'],
                "'{$segment}' is a fragment — the slug was cut mid-word"
            );
        }
    }
}
