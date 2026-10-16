<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentRequest;
use App\Services\DocumentQr;
use App\Services\GcashQr;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Document QR: each request's link is signed (cannot be guessed or walked),
 * the barangay GCash / InstaPay QR (Mary Jane P.) is always available to pay
 * with, and documents stay locked until the fee is approved.
 */
class DocumentQrTest extends TestCase
{
    private const REQUEST = ['id' => 42, 'reference_no' => 'BRG-2026-ABC123'];

    public function testTokenIsSignedAndPointsToTheRequest(): void
    {
        $token = DocumentQr::token(self::REQUEST);

        $this->assertMatchesRegularExpression('/^42-[a-f0-9]{16}$/', $token);
        $this->assertSame(42, DocumentQr::idFrom($token));
        $this->assertTrue(DocumentQr::matches($token, self::REQUEST));
        $this->assertSame(app_url('d/' . $token), DocumentQr::url(self::REQUEST));
    }

    public function testForgedOrWalkedTokensAreRefused(): void
    {
        $this->assertFalse(DocumentQr::matches('42-0000000000000000', self::REQUEST), 'made-up signature');
        $other = DocumentQr::token(['id' => 43, 'reference_no' => 'BRG-2026-ABC124']);
        $this->assertFalse(DocumentQr::matches($other, self::REQUEST), 'another request\'s token');
        $this->assertNull(DocumentQr::idFrom('42'));
        $this->assertNull(DocumentQr::idFrom('../42-abcdef0123456789'));
    }

    public function testQrImageEncodesTheLink(): void
    {
        $svg = DocumentQr::svg(self::REQUEST, 120);
        $this->assertStringStartsWith('<?xml', $svg);
        $this->assertStringContainsString('<svg', $svg);
    }

    public function testBarangayPaymentQrIsAlwaysAvailable(): void
    {
        $this->assertFileExists(GcashQr::BUNDLED, 'the Mary Jane P. QR ships with the app');
        $qr = GcashQr::current();
        $this->assertNotNull($qr);
        $this->assertContains($qr['mime'], ['image/jpeg', 'image/png', 'image/webp']);
        $this->assertGreaterThan(10000, strlen($qr['data']));
    }

    public function testDocumentStaysLockedUntilTheFeeIsApproved(): void
    {
        $base = ['fee_amount' => 30.00];
        foreach (['PENDING', 'PAYMENT_PROOF_SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_REJECTED', 'PAY_AT_PICKUP'] as $waiting) {
            $this->assertFalse(DocumentRequest::isPaymentVerified($base + ['payment_status' => $waiting]), $waiting);
        }
        $this->assertTrue(DocumentRequest::isPaymentVerified($base + ['payment_status' => 'PAID_VERIFIED']));
        $this->assertTrue(DocumentRequest::isPaymentVerified(['fee_amount' => 0, 'payment_status' => '']), 'free documents are never locked');
    }
}
