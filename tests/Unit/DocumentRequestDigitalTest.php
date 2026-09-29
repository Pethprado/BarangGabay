<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\DocumentRequest;
use App\Services\DocumentStorageService;

require_once __DIR__ . '/../bootstrap.php';

final class DocumentRequestDigitalTest extends TestCase
{
    public function testDeliveryMethodsAreDefined(): void
    {
        $this->assertArrayHasKey('pickup', DocumentRequest::DELIVERY_METHODS);
        $this->assertArrayHasKey('digital', DocumentRequest::DELIVERY_METHODS);
        $this->assertSame('Personal Pickup', DocumentRequest::deliveryLabel('pickup'));
        $this->assertSame('Digital Soft Copy', DocumentRequest::deliveryLabel('digital'));
    }

    public function testStatusTransitions(): void
    {
        $this->assertTrue(DocumentRequest::canMove('pending', 'processing'));
        $this->assertTrue(DocumentRequest::canMove('pending', 'ready'));
        $this->assertTrue(DocumentRequest::canMove('pending', 'rejected'));
        $this->assertTrue(DocumentRequest::canMove('processing', 'ready'));
        $this->assertTrue(DocumentRequest::canMove('ready', 'released'));
        $this->assertFalse(DocumentRequest::canMove('released', 'pending'));
        $this->assertFalse(DocumentRequest::canMove('rejected', 'processing'));
    }

    public function testRoutesRegistered(): void
    {
        $routes = require __DIR__ . '/../../routes/web.php';
        $routesByPath = [];
        foreach ($routes as $r) {
            $routesByPath[$r[0] . ' ' . $r[1]] = $r[2];
        }

        $this->assertArrayHasKey('GET /documents', $routesByPath);
        $this->assertArrayHasKey('POST /documents', $routesByPath);
        $this->assertArrayHasKey('GET /documents/{id}/download', $routesByPath);
        $this->assertArrayHasKey('GET /documents/{id}/preview', $routesByPath);
        $this->assertArrayHasKey('GET /admin/documents', $routesByPath);
        $this->assertArrayHasKey('POST /admin/documents/{id}/status', $routesByPath);
        $this->assertArrayHasKey('POST /admin/documents/{id}/upload', $routesByPath);
        $this->assertArrayHasKey('POST /admin/documents/{id}/replace', $routesByPath);
        $this->assertArrayHasKey('POST /admin/documents/{id}/remove-file', $routesByPath);
        $this->assertArrayHasKey('GET /admin/documents/{id}/download', $routesByPath);
        $this->assertArrayHasKey('GET /admin/documents/{id}/preview', $routesByPath);
        $this->assertArrayHasKey('GET /admin/documents/{id}/details', $routesByPath);
    }

    public function testStorageServiceMimeValidation(): void
    {
        $storage = new DocumentStorageService();

        // 1. Missing file
        $res = $storage->validateUpload([]);
        $this->assertFalse($res['valid']);

        // 2. Oversized file (>10MB)
        $oversized = [
            'name'     => 'test.pdf',
            'type'     => 'application/pdf',
            'tmp_name' => '',
            'error'    => UPLOAD_ERR_OK,
            'size'     => 15 * 1024 * 1024,
        ];
        $res = $storage->validateUpload($oversized);
        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('10MB', (string) $res['error']);
    }

    public function testFormatFileSize(): void
    {
        $this->assertSame('0 B', DocumentRequest::formatFileSize(0));
        $this->assertSame('500 B', DocumentRequest::formatFileSize(500));
        $this->assertSame('1.5 KB', DocumentRequest::formatFileSize(1536));
        $this->assertSame('2 MB', DocumentRequest::formatFileSize(2 * 1024 * 1024));
    }

    public function testTranslationsExist(): void
    {
        $fil = require __DIR__ . '/../../lang/fil.php';
        $en  = require __DIR__ . '/../../lang/en.php';

        $this->assertArrayHasKey('delivery_label', $fil['documents']);
        $this->assertArrayHasKey('delivery_label', $en['documents']);
        $this->assertArrayHasKey('delivery_pickup', $fil['documents']);
        $this->assertArrayHasKey('delivery_digital', $fil['documents']);
        $this->assertArrayHasKey('status_ready_digital', $fil['documents']);
        $this->assertArrayHasKey('status_ready_digital', $en['documents']);
        $this->assertArrayHasKey('upload_modal_title', $fil['admin_documents']);
        $this->assertArrayHasKey('upload_modal_title', $en['admin_documents']);
    }
}
