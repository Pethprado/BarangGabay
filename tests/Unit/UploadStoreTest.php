<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UploadStore;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Uploads must survive Render wiping public/uploads/: a file kept by
 * UploadStore is served from the database once its disk copy is gone, valid
 * IDs only to staff or their owner, and odd paths are never looked up.
 */
class UploadStoreTest extends TestCase
{
    private const PUBLIC_FILE  = '/uploads/zz-test/0123456789abcdef.png';
    private const PRIVATE_FILE = '/uploads/id-photos/zz0123456789abcdef.png';

    private array $session = [];

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        foreach ([self::PUBLIC_FILE, self::PRIVATE_FILE] as $path) {
            UploadStore::forget($path);
            @unlink(\dirname(__DIR__, 2) . '/public' . $path);
        }
        @rmdir(\dirname(__DIR__, 2) . '/public/uploads/zz-test');
        $_SESSION = $this->session;
    }

    private function write(string $path, string $bytes): void
    {
        $disk = \dirname(__DIR__, 2) . '/public' . $path;
        @mkdir(\dirname($disk), 0755, true);
        file_put_contents($disk, $bytes);
    }

    private function serve(string $path): array
    {
        ob_start();
        $served = UploadStore::serve($path);
        return [$served, (string) ob_get_clean()];
    }

    public function testServesAWipedFileFromTheDatabaseAndRestoresIt(): void
    {
        $bytes = "\x89PNG\r\n\x1a\n" . random_bytes(64);
        $this->write(self::PUBLIC_FILE, $bytes);
        UploadStore::keep(self::PUBLIC_FILE);

        $disk = \dirname(__DIR__, 2) . '/public' . self::PUBLIC_FILE;
        unlink($disk);                                   // the deploy wipes the disk
        $this->assertTrue(UploadStore::exists(self::PUBLIC_FILE));

        [$served, $body] = $this->serve(self::PUBLIC_FILE);
        $this->assertTrue($served);
        $this->assertSame($bytes, $body);
        $this->assertFileExists($disk, 'put back on disk for the next request');
    }

    public function testValidIdsAreOnlyServedToStaff(): void
    {
        $this->write(self::PRIVATE_FILE, 'id-bytes');
        UploadStore::keep(self::PRIVATE_FILE);
        unlink(\dirname(__DIR__, 2) . '/public' . self::PRIVATE_FILE);

        [$served, $body] = $this->serve(self::PRIVATE_FILE);
        $this->assertTrue($served);
        $this->assertSame('Forbidden', $body);

        $_SESSION = ['user_id' => 1, 'role' => 'admin'];
        [, $body] = $this->serve(self::PRIVATE_FILE);
        $this->assertSame('id-bytes', $body);
        $this->assertFileDoesNotExist(\dirname(__DIR__, 2) . '/public' . self::PRIVATE_FILE, 'IDs are never re-exposed on disk');
    }

    public function testRejectsPathsOutsideUploads(): void
    {
        $this->assertNull(UploadStore::normalise('/uploads/../config/database.php'));
        $this->assertNull(UploadStore::normalise('/index.php'));
        $this->assertSame('/uploads/avatars/ab12.jpg', UploadStore::normalise('uploads/avatars/ab12.jpg'));
        [$served] = $this->serve('/uploads/zz-test/missing.png');
        $this->assertFalse($served, 'no copy -> normal 404');
    }
}
