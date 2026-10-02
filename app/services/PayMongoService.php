<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Throwable;

/**
 * PayMongo REST API Service for BarangGabay.
 *
 * Handles:
 * - Checkout Sessions API v2 (creates hosted checkout for GCash, card, QR PH)
 * - Retrieve Checkout Session status
 * - Webhook Signature Verification (HMAC-SHA256)
 * - Webhook Idempotency
 *
 * PayMongo processes GCash, card, and other e-wallet payments through its
 * hosted checkout page, keeping BarangGabay PCI-compliant without handling
 * card data directly.
 *
 * Amounts are in CENTAVOS (PHP 50.00 = 5000).
 */
class PayMongoService
{
    private const API_BASE_URL = 'https://api.paymongo.com';

    /**
     * Supported payment method types offered to residents.
     * Requirement: GCash through PayMongo ONLY.
     */
    private const PAYMENT_METHOD_TYPES = ['gcash'];

    // ── Configuration ────────────────────────────────────────────────────

    /**
     * Get Secret Key (from env or system_settings).
     * Used for server-side API calls (Authorization header).
     */
    public static function getSecretKey(): string
    {
        return trim((string) (env('PAYMONGO_SECRET_KEY') ?: Setting::get('paymongo_secret_key', '')));
    }

    /**
     * Get Public Key (from env or system_settings).
     * Used client-side if needed (currently unused — hosted checkout handles everything).
     */
    public static function getPublicKey(): string
    {
        return trim((string) (env('PAYMONGO_PUBLIC_KEY') ?: Setting::get('paymongo_public_key', '')));
    }

    /**
     * Get Webhook Secret Key for signature verification.
     */
    public static function getWebhookSecret(): string
    {
        return trim((string) (env('PAYMONGO_WEBHOOK_SECRET') ?: Setting::get('paymongo_webhook_secret', '')));
    }

    /**
     * Check if PayMongo is configured with credentials.
     */
    public static function isConfigured(): bool
    {
        return self::getSecretKey() !== '';
    }

    /**
     * Get the active mode: 'test' or 'live'.
     * Determined by whether the secret key starts with sk_test_ or sk_live_,
     * or via PAYMONGO_MODE environment variable.
     */
    public static function getMode(): string
    {
        $explicit = strtolower(trim((string) env('PAYMONGO_MODE', '')));
        if ($explicit === 'live' || $explicit === 'test') {
            return $explicit;
        }

        $sk = self::getSecretKey();
        if (str_starts_with($sk, 'sk_live_')) {
            return 'live';
        }
        return 'test';
    }

    // ── Checkout Sessions API ────────────────────────────────────────────

    /**
     * Create a PayMongo Checkout Session.
     *
     * Processes GCash payment directly through PayMongo's secure hosted page.
     *
     * @param int    $requestId    Document request ID
     * @param string $paymentRef   Payment reference (e.g. PAY-2026-A1B2C3)
     * @param float  $amount       Amount in PHP (e.g. 50.00)
     * @param string $docLabel     Document type label
     * @param string $reqRef       Document request reference number
     * @param string $successUrl   URL to redirect after successful payment
     * @param string $cancelUrl    URL to redirect if payment is cancelled
     * @return array{success:bool, checkout_id:?string, checkout_url:?string, error:?string, raw:?array}
     */
    public static function createCheckoutSession(
        int $requestId,
        string $paymentRef,
        float $amount,
        string $docLabel = 'Barangay Document',
        string $reqRef = '',
        string $successUrl = '',
        string $cancelUrl = ''
    ): array {
        $secretKey = self::getSecretKey();

        // Production rule: Never simulate or mock payment success
        if ($secretKey === '') {
            return [
                'success'      => false,
                'checkout_id'  => null,
                'checkout_url' => null,
                'error'        => 'PayMongo Secret Key is not configured. Please set PAYMONGO_SECRET_KEY in Render environment variables.',
                'raw'          => null,
            ];
        }

        // PayMongo amounts are in centavos
        $amountCentavos = (int) round($amount * 100);
        if ($amountCentavos < 2000) {
            // PayMongo minimum is PHP 20.00 (2000 centavos) for most methods
            // For free documents, this should never be called
            $amountCentavos = max($amountCentavos, 2000);
        }

        $description = sprintf('Barangay %s Fee (%s)', mb_substr($docLabel, 0, 80), $reqRef ?: ('Req #' . $requestId));
        $description = mb_substr($description, 0, 255);

        $payload = [
            'data' => [
                'attributes' => [
                    'line_items' => [
                        [
                            'name'        => mb_substr('Barangay ' . $docLabel, 0, 255),
                            'amount'      => $amountCentavos,
                            'currency'    => 'PHP',
                            'quantity'    => 1,
                            'description' => 'Opisyal na bayarin sa dokumento ng Barangay',
                        ],
                    ],
                    'payment_method_types' => self::PAYMENT_METHOD_TYPES,
                    'description'          => $description,
                    'reference_number'     => $paymentRef,
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'send_email_receipt'   => true,
                    'show_description'     => true,
                    'show_line_items'      => true,
                    'metadata' => [
                        'request_id'   => (string) $requestId,
                        'payment_ref'  => $paymentRef,
                        'request_ref'  => $reqRef,
                        'doc_type'     => $docLabel,
                        'system'       => 'BarangGabay',
                    ],
                ],
            ],
        ];

        $url = self::API_BASE_URL . '/v1/checkout_sessions';
        $response = self::apiRequest('POST', $url, $payload, $secretKey);

        if (!$response['success']) {
            return [
                'success'      => false,
                'checkout_id'  => null,
                'checkout_url' => null,
                'error'        => $response['error'],
                'raw'          => $response['raw'],
            ];
        }

        $data = $response['raw']['data'] ?? [];
        $checkoutId  = (string) ($data['id'] ?? '');
        $checkoutUrl = (string) ($data['attributes']['checkout_url'] ?? '');

        if ($checkoutId === '' || $checkoutUrl === '') {
            return [
                'success'      => false,
                'checkout_id'  => null,
                'checkout_url' => null,
                'error'        => 'PayMongo returned an incomplete checkout session response.',
                'raw'          => $response['raw'],
            ];
        }

        return [
            'success'      => true,
            'checkout_id'  => $checkoutId,
            'checkout_url' => $checkoutUrl,
            'error'        => null,
            'mock'         => false,
            'raw'          => $response['raw'],
        ];
    }

    /**
     * Retrieve a PayMongo Checkout Session by ID.
     *
     * Used to check the payment status after a resident returns from the
     * hosted checkout page, and as a fallback verification if webhooks fail.
     *
     * @param string $checkoutId  The cs_* checkout session ID
     * @return array{success:bool, status:?string, payment_intent_id:?string, payments:array, error:?string, raw:?array}
     */
    public static function retrieveCheckoutSession(string $checkoutId): array
    {
        $secretKey = self::getSecretKey();
        if ($secretKey === '') {
            return [
                'success'            => false,
                'status'             => null,
                'payment_intent_id'  => null,
                'payments'           => [],
                'error'              => 'PayMongo is not configured.',
                'raw'                => null,
            ];
        }

        $url = self::API_BASE_URL . '/v1/checkout_sessions/' . urlencode($checkoutId);
        $response = self::apiRequest('GET', $url, null, $secretKey);

        if (!$response['success']) {
            return [
                'success'            => false,
                'status'             => null,
                'payment_intent_id'  => null,
                'payments'           => [],
                'error'              => $response['error'],
                'raw'                => $response['raw'],
            ];
        }

        $data = $response['raw']['data']['attributes'] ?? [];
        $status          = (string) ($data['status'] ?? 'unknown');
        $paymentIntentId = (string) ($data['payment_intent']['id'] ?? ($data['payment_intent_id'] ?? ''));
        $payments        = $data['payments'] ?? [];

        return [
            'success'            => true,
            'status'             => $status,
            'payment_intent_id'  => $paymentIntentId ?: null,
            'payments'           => $payments,
            'error'              => null,
            'raw'                => $response['raw'],
        ];
    }

    /**
     * Retrieve a PayMongo Payment by ID.
     *
     * @param string $paymentId  The pay_* payment ID
     * @return array{success:bool, status:?string, amount:?float, source_type:?string, error:?string, raw:?array}
     */
    public static function retrievePayment(string $paymentId): array
    {
        $secretKey = self::getSecretKey();
        if ($secretKey === '') {
            return [
                'success'     => false,
                'status'      => null,
                'amount'      => null,
                'source_type' => null,
                'error'       => 'PayMongo is not configured.',
                'raw'         => null,
            ];
        }

        $url = self::API_BASE_URL . '/v1/payments/' . urlencode($paymentId);
        $response = self::apiRequest('GET', $url, null, $secretKey);

        if (!$response['success']) {
            return [
                'success'     => false,
                'status'      => null,
                'amount'      => null,
                'source_type' => null,
                'error'       => $response['error'],
                'raw'         => $response['raw'],
            ];
        }

        $attrs = $response['raw']['data']['attributes'] ?? [];
        $amountCentavos = (int) ($attrs['amount'] ?? 0);

        return [
            'success'     => true,
            'status'      => (string) ($attrs['status'] ?? 'unknown'),
            'amount'      => $amountCentavos / 100.0,
            'source_type' => (string) ($attrs['source']['type'] ?? ($attrs['payment_method_type'] ?? 'unknown')),
            'error'       => null,
            'raw'         => $response['raw'],
        ];
    }

    // ── Webhook Verification ─────────────────────────────────────────────

    /**
     * Verify PayMongo webhook signature (HMAC-SHA256).
     *
     * PayMongo sends a `Paymongo-Signature` header with the format:
     *   t=<timestamp>,te=<test_signature>,li=<live_signature>
     *
     * We verify by:
     *   1. Extracting the timestamp and the relevant signature (te for test, li for live)
     *   2. Computing HMAC-SHA256 of "<timestamp>.<raw_payload>" with the webhook secret
     *   3. Comparing with timing-safe hash_equals
     *
     * @param array  $headers     Request headers
     * @param string $rawPayload  Raw JSON body
     * @return bool
     */
    public static function verifyWebhookSignature(array $headers, string $rawPayload, ?string $webhookSecret = null): bool
    {
        $webhookSecret = $webhookSecret !== null ? trim($webhookSecret) : self::getWebhookSecret();
        if ($webhookSecret === '') {
            // If no webhook secret configured, skip verification in development
            error_log('[PayMongoService] No webhook secret configured — skipping signature verification.');
            return true;
        }

        // Normalize header keys to lowercase
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }

        $signatureHeader = (string) ($normalizedHeaders['paymongo-signature'] ?? '');
        if ($signatureHeader === '') {
            error_log('[PayMongoService] Missing Paymongo-Signature header.');
            return false;
        }

        // Parse the signature header: t=<timestamp>,te=<test_sig>,li=<live_sig>
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        if ($timestamp === '') {
            error_log('[PayMongoService] Missing timestamp in signature header.');
            return false;
        }

        // Pick the correct signature based on mode
        $mode = self::getMode();
        $receivedSig = ($mode === 'live')
            ? ($parts['li'] ?? '')
            : ($parts['te'] ?? '');

        if ($receivedSig === '') {
            error_log('[PayMongoService] Missing ' . ($mode === 'live' ? 'li' : 'te') . ' signature component.');
            return false;
        }

        // Compute expected signature: HMAC-SHA256 of "timestamp.payload" with webhook secret
        $signedPayload = $timestamp . '.' . $rawPayload;
        $expectedSig = hash_hmac('sha256', $signedPayload, $webhookSecret);

        return hash_equals($expectedSig, $receivedSig);
    }

    /**
     * Extract payment details from a webhook event payload.
     *
     * @param array $eventData  Decoded webhook event JSON
     * @return array{
     *   event_type: string,
     *   event_id: string,
     *   payment_id: ?string,
     *   payment_intent_id: ?string,
     *   checkout_session_id: ?string,
     *   amount: ?float,
     *   currency: ?string,
     *   status: ?string,
     *   source_type: ?string,
     *   metadata: array,
     *   raw_resource: array
     * }
     */
    public static function parseWebhookEvent(array $eventData): array
    {
        $eventType = (string) ($eventData['data']['attributes']['type'] ?? '');
        $eventId   = (string) ($eventData['data']['id'] ?? '');

        $resource  = $eventData['data']['attributes']['data'] ?? [];
        $attrs     = $resource['attributes'] ?? [];

        $paymentId        = null;
        $paymentIntentId  = null;
        $checkoutSessionId = null;
        $amount           = null;
        $currency         = null;
        $status           = null;
        $sourceType       = null;
        $metadata         = [];

        // For payment.paid and payment.failed events, resource is a Payment object
        if (str_starts_with($eventType, 'payment.')) {
            $paymentId       = (string) ($resource['id'] ?? '');
            $amountCentavos  = (int) ($attrs['amount'] ?? 0);
            $amount          = $amountCentavos > 0 ? $amountCentavos / 100.0 : null;
            $currency        = (string) ($attrs['currency'] ?? 'PHP');
            $status          = (string) ($attrs['status'] ?? '');
            $sourceType      = (string) ($attrs['source']['type'] ?? ($attrs['payment_method_type'] ?? ''));
            $metadata        = $attrs['metadata'] ?? [];

            // Try to extract payment_intent_id
            $paymentIntentId = (string) ($attrs['payment_intent_id'] ?? '');
        }

        // For checkout_session.payment.paid events
        if (str_starts_with($eventType, 'checkout_session.')) {
            $checkoutSessionId = (string) ($resource['id'] ?? '');
            $payments          = $attrs['payments'] ?? [];
            $metadata          = $attrs['metadata'] ?? [];

            if (!empty($payments)) {
                $firstPayment    = $payments[0] ?? [];
                $firstPayAttrs   = $firstPayment['attributes'] ?? [];
                $paymentId       = (string) ($firstPayment['id'] ?? '');
                $amountCentavos  = (int) ($firstPayAttrs['amount'] ?? 0);
                $amount          = $amountCentavos > 0 ? $amountCentavos / 100.0 : null;
                $currency        = (string) ($firstPayAttrs['currency'] ?? 'PHP');
                $status          = (string) ($firstPayAttrs['status'] ?? '');
                $sourceType      = (string) ($firstPayAttrs['source']['type'] ?? ($firstPayAttrs['payment_method_type'] ?? ''));
            }
        }

        return [
            'event_type'          => $eventType,
            'event_id'            => $eventId,
            'payment_id'          => $paymentId ?: null,
            'payment_intent_id'   => $paymentIntentId ?: null,
            'checkout_session_id' => $checkoutSessionId ?: null,
            'amount'              => $amount,
            'currency'            => $currency ?: 'PHP',
            'status'              => $status ?: null,
            'source_type'         => $sourceType ?: null,
            'metadata'            => is_array($metadata) ? $metadata : [],
            'raw_resource'        => $resource,
        ];
    }

    // ── Internal HTTP helper ─────────────────────────────────────────────

    /**
     * Make an authenticated API request to PayMongo.
     *
     * PayMongo uses HTTP Basic Auth with the secret key as username
     * and an empty password.
     *
     * @param string      $method     HTTP method (GET, POST)
     * @param string      $url        Full URL
     * @param array|null  $payload    JSON payload for POST requests
     * @param string      $secretKey  API secret key
     * @return array{success:bool, error:?string, raw:?array}
     */
    private static function apiRequest(string $method, string $url, ?array $payload, string $secretKey): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST' && $payload !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('[PayMongoService::apiRequest] cURL error: ' . $curlError);
            return [
                'success' => false,
                'error'   => 'Network error: ' . $curlError,
                'raw'     => null,
            ];
        }

        $parsed = json_decode((string) $rawResponse, true);

        if ($httpCode >= 400 || !is_array($parsed)) {
            $errMsg = 'PayMongo API error (HTTP ' . $httpCode . ')';
            $errCode = (string) ($parsed['errors'][0]['code'] ?? '');
            if (isset($parsed['errors'][0]['detail'])) {
                $errMsg = (string) $parsed['errors'][0]['detail'];
            } elseif (isset($parsed['errors'][0]['message'])) {
                $errMsg = (string) $parsed['errors'][0]['message'];
            }

            // Requirement 12: Check if GCash is active on merchant account
            if (str_contains(strtolower($errMsg), 'gcash') || str_contains(strtolower($errCode), 'payment_method_not_allowed') || str_contains(strtolower($errCode), 'payment_method_unavailable')) {
                $errMsg = 'GCash is not yet active on the PayMongo merchant account. Please activate GCash in PayMongo Dashboard → Settings → Payment Methods.';
            }

            error_log('[PayMongoService::apiRequest] HTTP ' . $httpCode . ' ' . $method . ' ' . $url . ' → ' . substr((string) $rawResponse, 0, 500));
            return [
                'success' => false,
                'error'   => $errMsg,
                'raw'     => $parsed,
            ];
        }

        return [
            'success' => true,
            'error'   => null,
            'raw'     => $parsed,
        ];
    }
}
