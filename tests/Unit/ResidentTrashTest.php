<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ResidentTrash;
use App\Models\User;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Deleting a resident moves them to the Trash (hidden from lists, cannot log
 * in), Restore brings back their previous status, the Trash groups by purok,
 * and Delete forever removes only accounts already in the Trash.
 */
class ResidentTrashTest extends TestCase
{
    private const EMAIL = 'zz.residenttrash@test.local';
    private int $id = 0;

    protected function setUp(): void
    {
        $this->tearDown();
        db()->prepare(
            "INSERT INTO users (full_name, email, password_hash, role, status, zone) VALUES ('Zz Trash Unit', ?, 'x', 'resident', 'verified', 'Purok 6')"
        )->execute([self::EMAIL]);
        $this->id = (int) db()->query("SELECT id FROM users WHERE email = '" . self::EMAIL . "'")->fetchColumn();
    }

    protected function tearDown(): void
    {
        db()->prepare('DELETE FROM users WHERE email = ?')->execute([self::EMAIL]);
    }

    private function inList(): bool
    {
        return in_array(self::EMAIL, array_column(User::allResidents('', 'zz.residenttrash'), 'email'), true);
    }

    public function testTrashHidesThenRestoreBringsBackTheSameStatus(): void
    {
        $this->assertTrue($this->inList());
        $this->assertTrue(ResidentTrash::trash($this->id, 1));

        $this->assertFalse($this->inList(), 'trashed residents are left out of the list');
        $this->assertSame('suspended', User::find($this->id)['status'], 'cannot log in while trashed');
        $names = array_column(ResidentTrash::byPurok()['Purok 6'] ?? [], 'email');
        $this->assertContains(self::EMAIL, $names, 'grouped under their purok');

        $this->assertTrue(ResidentTrash::restore($this->id));
        $this->assertTrue($this->inList());
        $this->assertSame('verified', User::find($this->id)['status']);
    }

    public function testDeleteForeverOnlyWorksFromTheTrash(): void
    {
        $this->assertFalse(ResidentTrash::purge($this->id), 'a live account is never purged');
        $this->assertNotNull(User::find($this->id));

        ResidentTrash::trash($this->id, 1);
        $this->assertTrue(ResidentTrash::purge($this->id));
        $this->assertNull(User::find($this->id));
    }
}
