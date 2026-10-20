<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DictionarySync;
use App\Services\ManoboHybridTranslator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * A dictionary change reaches residents: the translator's version moves on,
 * and the posts that contain the word (whole word only, never a person's
 * Manobo) are the ones re-translated.
 */
class DictionarySyncTest extends TestCase
{
    private const SLUG = 'zz-dictsync-test';

    protected function tearDown(): void
    {
        db()->prepare('DELETE FROM announcements WHERE slug LIKE ?')->execute([self::SLUG . '%']);
    }

    private function post(string $slug, string $body, int $manoboIsAuto): int
    {
        $author = (int) db()->query("SELECT id FROM users WHERE role = 'superadmin' ORDER BY id LIMIT 1")->fetchColumn();
        db()->prepare(
            "INSERT INTO announcements (title, slug, body, body_manobo, manobo_is_auto, author_id, status)
             VALUES ('zz', ?, ?, 'Manobo text', ?, ?, 'draft')"
        )->execute([$slug, $body, $manoboIsAuto, $author]);
        return (int) db()->query("SELECT id FROM announcements WHERE slug = '{$slug}'")->fetchColumn();
    }

    public function testFindsPostsByWholeWordAndSkipsHandWrittenManobo(): void
    {
        $auto   = $this->post(self::SLUG . '-a', '<p>Isara ang zzbintanax tuwing gabi.</p>', 1);
        $part   = $this->post(self::SLUG . '-b', '<p>Ang zzbintanaxes ay hindi tugma.</p>', 1);
        $person = $this->post(self::SLUG . '-c', '<p>Isara ang zzbintanax.</p>', 0);

        $found = DictionarySync::affectedPosts(['window, zzbintanax']);

        $this->assertContains(['announcement', $auto], $found);
        $this->assertNotContains(['announcement', $part], $found, 'part of a longer word is not a match');
        $this->assertNotContains(['announcement', $person], $found, 'Manobo a person wrote is never replaced');
    }

    public function testVersionMovesOnSoCachedTranslationsAreNotReused(): void
    {
        $before = (new ManoboHybridTranslator())->getDictionaryVersion();
        DictionarySync::changed([], 0.0);
        $this->assertGreaterThan($before, (new ManoboHybridTranslator())->getDictionaryVersion());
    }
}
