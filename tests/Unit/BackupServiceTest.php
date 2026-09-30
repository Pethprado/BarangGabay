<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BackupService;
use PHPUnit\Framework\TestCase;

final class BackupServiceTest extends TestCase
{
    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackupService();
    }

    public function testDriverDetection(): void
    {
        $driver = $this->service->getDriver();
        $this->assertContains($driver, ['mysql', 'pgsql', 'sqlite']);
    }

    public function testPathTraversalProtection(): void
    {
        $this->assertNull($this->service->pathFor('../../etc/passwd'));
        $this->assertNull($this->service->pathFor('malicious.php'));
        $this->assertNull($this->service->pathFor('baranggabay_2026-09-30_120000.sql'));
    }

    public function testDirectoryExists(): void
    {
        $dir = $this->service->directory();
        $this->assertDirectoryExists($dir);
    }
}
