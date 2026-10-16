<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\QrPh;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * The barangay's GCash / InstaPay QR with the fee filled in: same account,
 * the exact amount, a valid CRC, and never another account's QR.
 */
class QrPhTest extends TestCase
{
    public function testBundledQrIsAValidQrPhCode(): void
    {
        $this->assertTrue(QrPh::isValid(QrPh::BUNDLED_PAYLOAD));
        $this->assertSame('089C', QrPh::crc(substr(QrPh::BUNDLED_PAYLOAD, 0, -4)), 'matches the CRC GCash printed');
    }

    public function testAmountIsAddedWithoutChangingTheAccount(): void
    {
        $qr = QrPh::withAmount(QrPh::BUNDLED_PAYLOAD, 30);

        $this->assertNotNull($qr);
        $this->assertTrue(QrPh::isValid($qr), 'new CRC is correct');
        $this->assertStringContainsString('540530.00', $qr, 'tag 54 = 30.00');
        $this->assertStringStartsWith('000201010212', $qr, 'marked as a one-time (dynamic) QR');
        $this->assertStringContainsString('0012com.p2pqrpay0111GXCHPHM2XXX', $qr, 'same GCash InstaPay account block');
        $this->assertStringContainsString('5912MA*Y JA*E P.', $qr);
        $this->assertStringContainsString('5303608', $qr, 'Philippine peso');
    }

    public function testFieldsStayInTagOrder(): void
    {
        $qr = (string) QrPh::withAmount(QrPh::BUNDLED_PAYLOAD, 150.5);
        $this->assertLessThan(strpos($qr, '5802PH'), strpos($qr, '5406150.50'), 'amount (54) sits between currency (53) and country (58)');
    }

    public function testBrokenOrForeignPayloadsAreRefused(): void
    {
        $this->assertFalse(QrPh::isValid('hello'));
        $this->assertFalse(QrPh::isValid(substr(QrPh::BUNDLED_PAYLOAD, 0, -1) . 'D'), 'wrong CRC');
        $this->assertNull(QrPh::withAmount(QrPh::BUNDLED_PAYLOAD, 0));
        $this->assertNull(QrPh::withAmount('0002010102', 30));
    }

    public function testAnUnreadableUploadedQrTurnsTheFeeQrOff(): void
    {
        $previous = Setting::get('gcash_qr_payload', '');
        try {
            Setting::set('gcash_qr_payload', 'none');
            Setting::flush();
            $this->assertNull(QrPh::payload(), 'never fall back to another account');
        } finally {
            Setting::set('gcash_qr_payload', (string) $previous);
            Setting::flush();
        }
    }

    public function testPngIsARealImage(): void
    {
        $png = QrPh::png((string) QrPh::withAmount(QrPh::BUNDLED_PAYLOAD, 30));
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        $this->assertNotFalse(getimagesizefromstring($png));
    }
}
