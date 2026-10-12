<?php
declare(strict_types=1);

use App\Models\DocumentRequest;
use App\Services\PaymentConfirmation;
use App\Services\PayMongoService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The rules that decide whether money is considered received. All pure —
 * no network, no database — so they run on every commit.
 */
final class PayMongoPaymentTest extends TestCase
{
    private const SECRET = 'whsk_unit_test_secret';

    private static function event(bool $livemode = false, int $amount = 5000, string $status = 'paid', string $currency = 'PHP'): string
    {
        return json_encode(['data' => [
            'id'         => 'evt_test_1',
            'attributes' => [
                'type'     => 'checkout_session.payment.paid',
                'livemode' => $livemode,
                'data'     => [
                    'id'         => 'cs_test_abc',
                    'attributes' => [
                        'metadata' => ['payment_ref' => 'BG-2026-ABCD1234', 'document_request_id' => '7'],
                        'payments' => [[
                            'id'         => 'pay_test_1',
                            'attributes' => [
                                'status' => $status, 'amount' => $amount, 'currency' => $currency,
                                'source' => ['type' => 'gcash'], 'payment_intent_id' => 'pi_test_1',
                            ],
                        ]],
                    ],
                ],
            ],
        ]]);
    }

    private static function sign(string $body, string $secret = self::SECRET, string $key = 'te', string $t = '1760000000'): array
    {
        return ['Paymongo-Signature' => "t={$t},{$key}=" . hash_hmac('sha256', $t . '.' . $body, $secret)];
    }

    // ── Webhook signature ───────────────────────────────────────────────

    public function testValidTestSignatureIsAccepted(): void
    {
        $body = self::event();
        $this->assertTrue(PayMongoService::verifyWebhookSignature(self::sign($body), $body, self::SECRET));
    }

    public function testLiveEventMustUseLiveSignature(): void
    {
        $body = self::event(true);
        $this->assertFalse(PayMongoService::verifyWebhookSignature(self::sign($body, self::SECRET, 'te'), $body, self::SECRET));
        $this->assertTrue(PayMongoService::verifyWebhookSignature(self::sign($body, self::SECRET, 'li'), $body, self::SECRET));
    }

    public function testWrongSecretIsRejected(): void
    {
        $body = self::event();
        $this->assertFalse(PayMongoService::verifyWebhookSignature(self::sign($body, 'someone_else'), $body, self::SECRET));
    }

    public function testTamperedBodyIsRejected(): void
    {
        $body    = self::event();
        $headers = self::sign($body);
        $this->assertFalse(PayMongoService::verifyWebhookSignature($headers, self::event(false, 100), self::SECRET));
    }

    public function testMissingSecretFailsClosed(): void
    {
        $body = self::event();
        $this->assertFalse(PayMongoService::verifyWebhookSignature(self::sign($body), $body, ''));
    }

    public function testMissingHeaderIsRejected(): void
    {
        $this->assertFalse(PayMongoService::verifyWebhookSignature([], self::event(), self::SECRET));
    }

    // ── Event parsing ───────────────────────────────────────────────────

    public function testCheckoutSessionEventIsNormalised(): void
    {
        $e = PayMongoService::parseWebhookEvent(json_decode(self::event(), true));
        $this->assertSame('evt_test_1', $e['event_id']);
        $this->assertSame('cs_test_abc', $e['checkout_session_id']);
        $this->assertSame('pay_test_1', $e['payments'][0]['id']);
        $this->assertSame(5000, $e['payments'][0]['amount']);
        $this->assertSame('gcash', $e['payments'][0]['source_type']);
        $this->assertSame('BG-2026-ABCD1234', $e['metadata']['payment_ref']);
    }

    // ── Amount / currency / status rule ─────────────────────────────────

    public function testExactFeeInPesosIsConfirmed(): void
    {
        $this->assertTrue(PaymentConfirmation::evaluate(50.00, ['status' => 'paid', 'amount' => 5000, 'currency' => 'PHP'])['ok']);
    }

    /** @dataProvider rejectedPayments */
    public function testAnythingElseIsNotConfirmed(array $pm, bool $paid): void
    {
        $verdict = PaymentConfirmation::evaluate(50.00, $pm);
        $this->assertFalse($verdict['ok']);
        $this->assertSame($paid, $verdict['paid'], 'paid-but-wrong goes to manual review, unpaid is ignored');
    }

    public static function rejectedPayments(): array
    {
        return [
            'tampered to ₱1'   => [['status' => 'paid', 'amount' => 100, 'currency' => 'PHP'], true],
            'one centavo less' => [['status' => 'paid', 'amount' => 4999, 'currency' => 'PHP'], true],
            'overpaid'         => [['status' => 'paid', 'amount' => 5100, 'currency' => 'PHP'], true],
            'wrong currency'   => [['status' => 'paid', 'amount' => 5000, 'currency' => 'USD'], true],
            'still pending'    => [['status' => 'pending', 'amount' => 5000, 'currency' => 'PHP'], false],
            'failed'           => [['status' => 'failed', 'amount' => 5000, 'currency' => 'PHP'], false],
        ];
    }

    public function testCentavoConversionHasNoFloatDrift(): void
    {
        $this->assertSame(10000, PayMongoService::toCentavos('100.00'));
        $this->assertSame(2999, PayMongoService::toCentavos(29.99));
        $this->assertSame(1, PayMongoService::toCentavos(0.01));
    }

    // ── Release guard ───────────────────────────────────────────────────

    public function testUnpaidRequestCannotBeReleasedOrApproved(): void
    {
        $req = ['fee_amount' => 50.00, 'payment_status' => 'PENDING'];
        foreach (['released', 'completed', 'approved', 'ready', 'pending_review'] as $to) {
            $this->assertNotNull(DocumentRequest::paymentBlocksMove($req, $to), $to);
        }
        $this->assertNull(DocumentRequest::paymentBlocksMove($req, 'cancelled'));
        $this->assertNull(DocumentRequest::paymentBlocksMove($req, 'rejected'));
    }

    public function testPaidAndFreeRequestsMoveFreely(): void
    {
        $this->assertNull(DocumentRequest::paymentBlocksMove(['fee_amount' => 50.00, 'payment_status' => 'PAID'], 'released'));
        $this->assertNull(DocumentRequest::paymentBlocksMove(['fee_amount' => 0.00, 'payment_status' => 'NOT_REQUIRED'], 'released'));
    }

    public function testPayAtHallCanBePreparedButNotReleasedUnpaid(): void
    {
        $req = ['fee_amount' => 50.00, 'payment_status' => 'PAY_AT_PICKUP'];
        $this->assertNull(DocumentRequest::paymentBlocksMove($req, 'ready_for_pickup'));
        $this->assertNotNull(DocumentRequest::paymentBlocksMove($req, 'released'));
    }

    public function testManualReviewBlocksRelease(): void
    {
        $this->assertNotNull(DocumentRequest::paymentBlocksMove(['fee_amount' => 50.00, 'payment_status' => 'UNDER_REVIEW'], 'released'));
    }
}
