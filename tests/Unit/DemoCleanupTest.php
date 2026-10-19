<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DemoCleanup;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * The go-live cleanup must catch demo accounts and never a real one — and a
 * preview must leave the database exactly as it was. Test rows are removed.
 */
class DemoCleanupTest extends TestCase
{
    private const REAL = 'zz.cleanup.real@gmail.com';
    private const DEMO = 'zz.cleanup@test.local';

    protected function setUp(): void
    {
        $this->tearDown();
        $insert = db()->prepare(
            "INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, 'x', ?, 'verified')"
        );
        $insert->execute(['zz Real Resident', self::REAL, 'resident']);
        $insert->execute(['zz Demo Resident', self::DEMO, 'resident']);
    }

    protected function tearDown(): void
    {
        db()->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([self::REAL, self::DEMO]);
    }

    public function testMatchesDemoAccountsOnly(): void
    {
        $emails = array_column((new DemoCleanup(db()))->demoAccounts(), 'email');

        $this->assertContains(self::DEMO, $emails);
        $this->assertNotContains(self::REAL, $emails);
        $this->assertNotContains('admin@baranggabay.ph', $emails, 'the owner superadmin is never removed');
    }

    public function testPreviewChangesNothing(): void
    {
        $count  = static fn (): int => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $before = $count();

        $result = (new DemoCleanup(db()))->preview();

        $this->assertGreaterThanOrEqual(1, $result['counts']['users'] ?? 0);
        $this->assertSame($before, $count());
    }
}
