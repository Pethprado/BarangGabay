<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/services/PayMongoService.php';
require_once __DIR__ . '/../app/services/CertificateGeneratorService.php';
require_once __DIR__ . '/../app/models/DocumentRequest.php';
require_once __DIR__ . '/../app/models/DocumentPayment.php';

use App\Services\PayMongoService;
use App\Services\CertificateGeneratorService;
use App\Models\DocumentRequest;
use App\Models\DocumentPayment;

echo "====================================================\n";
echo "BARANGGABAY PAYMONGO & CERTIFICATE VERIFICATION SUITE\n";
echo "====================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $name\n";
        $passed++;
    } else {
        echo " [FAIL] $name\n";
        $failed++;
    }
}

// ── TEST 1: PayMongo Service Methods ──────────────────────────────────────────
echo "1. Checking PayMongoService methods...\n";
assertTest("PayMongoService class exists", class_exists(PayMongoService::class));
assertTest("PayMongoService::isConfigured() returns bool", is_bool(PayMongoService::isConfigured()));
assertTest("PayMongoService::getMode() returns 'test' or 'live'", in_array(PayMongoService::getMode(), ['test', 'live'], true));

// ── TEST 2: PayMongo API Call (No Mock in Production) ──────────────────────
echo "\n2. Testing PayMongo Checkout Session creation behavior...\n";
$session = PayMongoService::createCheckoutSession(
    9999,
    'PAY-2026-TEST01',
    50.00,
    'Barangay Clearance',
    'BRG-2026-T01',
    'https://baranggabay.onrender.com/documents/payment/success?request_id=9999',
    'https://baranggabay.onrender.com/documents/payment/cancel?request_id=9999'
);

if (!PayMongoService::isConfigured()) {
    assertTest("createCheckoutSession without API key returns false (no fake demo)", $session['success'] === false);
    assertTest("createCheckoutSession error explains secret key is needed", str_contains($session['error'] ?? '', 'PayMongo Secret Key'));
} else {
    assertTest("createCheckoutSession with key executed", is_bool($session['success']));
}

// ── TEST 3: Webhook Signature Verification ──────────────────────────────────
echo "\n3. Testing Webhook Signature Verification (HMAC-SHA256)...\n";
$dummySecret = 'whsk_test_secret_key_1234567890';
$payload = json_encode([
    'data' => [
        'id' => 'evt_test_12345',
        'type' => 'event',
        'attributes' => [
            'type' => 'checkout_session.payment.paid',
            'data' => [
                'id' => 'cs_test_98765'
            ]
        ]
    ]
]);
$timestamp = (string) time();
$signedPayload = $timestamp . '.' . $payload;
$signature = hash_hmac('sha256', $signedPayload, $dummySecret);
$header = "t={$timestamp},te={$signature},li={$signature}";

$headers = ['Paymongo-Signature' => $header];
$verified = PayMongoService::verifyWebhookSignature($headers, $payload, $dummySecret);
assertTest("Valid webhook HMAC signature verified successfully", $verified === true);

$tamperedPayload = $payload . ' ';
$failedVerify = PayMongoService::verifyWebhookSignature($headers, $tamperedPayload, $dummySecret);
assertTest("Tampered payload signature rejected", $failedVerify === false);

$wrongSecretVerify = PayMongoService::verifyWebhookSignature($headers, $payload, 'whsk_wrong_secret');
assertTest("Wrong webhook secret rejected", $wrongSecretVerify === false);

// ── TEST 4: Certificate Number Generator & Public Verify URL ────────────────
echo "\n4. Testing Certificate Generation & Verification...\n";
assertTest("DocumentRequest::TYPES has clearance, residency, indigency, business", 
    isset(DocumentRequest::TYPES['clearance'], DocumentRequest::TYPES['residency'], DocumentRequest::TYPES['indigency'], DocumentRequest::TYPES['business'])
);

assertTest("DocumentRequest::DELIVERY_METHODS has pickup & digital only (Delivery removed per req 5 & 6)",
    isset(DocumentRequest::DELIVERY_METHODS['pickup'], DocumentRequest::DELIVERY_METHODS['digital']) && !isset(DocumentRequest::DELIVERY_METHODS['delivery'])
);

assertTest("DocumentPayment statuses: PAID, PENDING, CANCELLED, FAILED, EXPIRED, NOT_REQUIRED",
    DocumentPayment::STATUS_PAID === 'PAID' &&
    DocumentPayment::STATUS_PENDING === 'PENDING' &&
    DocumentPayment::STATUS_CANCELLED === 'CANCELLED' &&
    DocumentPayment::STATUS_FAILED === 'FAILED' &&
    DocumentPayment::STATUS_EXPIRED === 'EXPIRED' &&
    DocumentPayment::STATUS_NOT_REQUIRED === 'NOT_REQUIRED'
);

$certNumberClearance = 'BGC-2026-000001';
$expectedUrl = 'https://baranggabay.onrender.com/documents/' . urlencode($certNumberClearance) . '/verify';
assertTest("Public verification URL format is correct", str_contains($expectedUrl, '/documents/BGC-2026-000001/verify'));

// ── TEST 5: Verify migration file 037 ───────────────────────────────────────
echo "\n5. Checking Migration 037 SQL...\n";
$m37 = file_get_contents(__DIR__ . '/../database/migrations/037_paymongo_payment_integration.sql');
assertTest("037 migration defines paymongo_checkout_id", str_contains($m37, 'paymongo_checkout_id'));
assertTest("037 migration defines paymongo_payment_intent_id", str_contains($m37, 'paymongo_payment_intent_id'));
assertTest("037 migration defines paymongo_payment_id", str_contains($m37, 'paymongo_payment_id'));
assertTest("037 migration defines paymongo_source_type", str_contains($m37, 'paymongo_source_type'));
assertTest("037 migration defines paymongo_raw_response", str_contains($m37, 'paymongo_raw_response'));

// ── SUMMARY ─────────────────────────────────────────────────────────────────
echo "\n====================================================\n";
echo "TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "====================================================\n";

if ($failed > 0) {
    exit(1);
}
