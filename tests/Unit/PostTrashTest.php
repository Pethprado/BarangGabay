<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PostTrash;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Trash round trip on the real database, with a throwaway announcement that is
 * always removed again (these tests share the database with the running app).
 */
class PostTrashTest extends TestCase
{
    private ?int $postId = null;
    private array $trashIds = [];

    protected function setUp(): void
    {
        try {
            db()->query('SELECT 1 FROM post_trash LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('No database with the post_trash table.');
        }
        $author = (int) db()->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();
        $slug   = 'zz-trash-test-' . bin2hex(random_bytes(4));
        db()->prepare(
            "INSERT INTO announcements (title, slug, body, author_id, status, published_at, is_sample, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'published', NOW(), 1, NOW(), NOW())"
        )->execute(['Trash test post', $slug, '<p>Body</p>', $author]);
        $this->postId = (int) db()->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->postId !== null) {
            db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$this->postId]);
            db()->prepare("DELETE FROM post_trash WHERE content_type = 'announcement' AND content_id = ?")->execute([$this->postId]);
        }
        foreach ($this->trashIds as $id) {
            db()->prepare('DELETE FROM post_trash WHERE id = ?')->execute([$id]);
        }
    }

    private function exists(): bool
    {
        $stmt = db()->prepare('SELECT slug FROM announcements WHERE id = ?');
        $stmt->execute([$this->postId]);
        return $stmt->fetchColumn() !== false;
    }

    private function trashIdFor(): int
    {
        $stmt = db()->prepare("SELECT id FROM post_trash WHERE content_type = 'announcement' AND content_id = ?");
        $stmt->execute([$this->postId]);
        return (int) $stmt->fetchColumn();
    }

    public function testMoveToTrashTakesThePostOffTheSiteAndRestoreBringsItBackAsItWas(): void
    {
        $stmt = db()->prepare('SELECT slug, status FROM announcements WHERE id = ?');
        $stmt->execute([$this->postId]);
        $before = $stmt->fetch();

        PostTrash::moveToTrash('announcement', $this->postId, 0);
        $this->assertFalse($this->exists(), 'A trashed post must leave its table, so no page can show it');
        $trashId = $this->trashIdFor();
        $this->assertGreaterThan(0, $trashId);

        $done = PostTrash::restore($trashId);
        $this->assertSame($this->postId, $done['id'], 'Restored with its original id');
        $this->assertFalse($done['renamed']);

        $stmt->execute([$this->postId]);
        $this->assertSame($before, $stmt->fetch(), 'Same link and status: posted again exactly as it was');
        $this->assertNull(PostTrash::find($trashId), 'And it is no longer in the Trash');
    }

    public function testRestoreRenamesTheLinkWhenAnotherPostTookIt(): void
    {
        $stmt = db()->prepare('SELECT slug FROM announcements WHERE id = ?');
        $stmt->execute([$this->postId]);
        $slug = (string) $stmt->fetchColumn();

        PostTrash::moveToTrash('announcement', $this->postId, 0);
        $trashId = $this->trashIdFor();

        // A new post now uses the old link.
        $author = (int) db()->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();
        db()->prepare(
            "INSERT INTO announcements (title, slug, body, author_id, status, is_sample, created_at, updated_at)
             VALUES ('Clash', ?, '<p>x</p>', ?, 'draft', 1, NOW(), NOW())"
        )->execute([$slug, $author]);
        $clashId = (int) db()->lastInsertId();

        try {
            $done = PostTrash::restore($trashId);
            $this->assertTrue($done['renamed']);
            $stmt->execute([$this->postId]);
            $this->assertSame($slug . '-restored', $stmt->fetchColumn());
        } finally {
            db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$clashId]);
        }
    }

    public function testPurgeDeletesForGood(): void
    {
        PostTrash::moveToTrash('announcement', $this->postId, 0);
        $trashId = $this->trashIdFor();

        $this->assertNotNull(PostTrash::purge($trashId));
        $this->assertNull(PostTrash::find($trashId));
        $this->assertFalse($this->exists());
    }

    public function testStaffSeeOnlyTheirOwnTrash(): void
    {
        $author = (int) db()->query("SELECT author_id FROM announcements WHERE id = {$this->postId}")->fetchColumn();
        PostTrash::moveToTrash('announcement', $this->postId, 0);

        $mine   = array_column(PostTrash::all($author), 'content_id');
        $others = array_column(PostTrash::all($author + 100000), 'content_id');
        $this->assertContains($this->postId, array_map('intval', $mine));
        $this->assertNotContains($this->postId, array_map('intval', $others));
    }
}
