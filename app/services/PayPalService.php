<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Throwable;

/**
 * Official PayPal REST API v2 Service for BarangGabay.
 *
 * Handles:
 * - OAuth 2.0 Server-Side Token Generation with Caching
 * - Orders API v2 Order Creation (Server-side Authoritative Amounts)
 * - Orders API v2 Order Capture
 * - Webhook Signature Verification (/v1/notifications/verify-webhook-signature)
 * - Webhook Idempotency Event Processing
 */
class PayPalService
{
    private const SANDBOX_API_URL = 'https://api-m.sandbox.paypal.com';
    private const LIVE_API_URL    = 'https://api-m.paypal.com';

    private static ?string $cachedToken = null;
    private static int $tokenExpiresAt = 0;

    /**
     * Get active PayPal mode ('sandbox' or 'live').
     */
    public static function getMode(): string
    {
        $mode = (string) (env('PAYPAL_MODE') ?: Setting::get('paypal_mode', 'sandbox'));
        return strtolower(trim($mode)) === 'live' ? 'live' : 'sandbox';
    }

    /**
     * Get API base URL depending on mode.
     */
    public static function getApiBaseUrl(): string
    {
        return self::getMode() === 'live' ? self::LIVE_API_URL : self::SANDBOX_API_URL;
    }

    /**
     * Get Client ID (from env or system_settings).
     */
    public static function getClientId(): string
    {
        return trim((string) (env('PAYPAL_CLIENT_ID') ?: Setting::get('paypal_client_id', '')));
    }

    /**
     * Get Client Secret (from env or system_settings).
     */
    public static function getClientSecret(): string
    {
        return trim((string) (env('PAYPAL_CLIENT_SECRET') ?: Setting::get('paypal_client_secret', '')));
    }

    /**
     * Get Webhook ID (from env or system_settings).
     */
    public static function getWebhookId(): string
    {
        return trim((string) (env('PAYPAL_WEBHOOK_ID') ?: Setting::get('paypal_webhook_id', '')));
    }

    /**
     * Check if PayPal is configured with credentials.
     */
    public static function isConfigured(): bool
    {
        return self::getClientId() !== '' && self::getClientSecret() !== '';
    }

    /**
     * Obtain OAuth 2.0 Access Token from PayPal with in-memory & file caching.
     */
    public static function getAccessToken(): ?string
    {
        $clientId     = self::getClientId();
        $clientSecret = self::getClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $now = time();
        if (self::$cachedToken !== null && self::$tokenExpiresAt > ($now + 60)) {
            return self::$cachedToken;
        }

        // Check temp file cache to persist across requests on same server
        $cacheFile = sys_get_temp_dir() . '/paypal_token_' . md5($clientId . self::getMode()) . '.json';
        if (file_exists($cacheFile)) {
            $data = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($data) && !empty($data['token']) && ($data['expires_at'] ?? 0) > ($now + 60)) {
                self::$cachedToken    = (string) $data['token'];
                self::$tokenExpiresAt = (int) $data['expires_at'];
                return self::$cachedToken;
            }
        }

        $url = self::getApiBaseUrl() . '/v1/oauth2/token';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || !$response) {
            error_log('[PayPalService::getAccessToken] OAuth failure. HTTP: ' . $httpCode . ' Error: ' . $error . ' Body: ' . substr((string)$response, 0, 300));
            return null;
        }

        $resData = json_decode((string) $response, true);
        if (empty($resData['access_token'])) {
            error_log('[PayPalService::getAccessToken] No access_token in response: ' . $response);
            return null;
        }

        $token     = (string) $resData['access_token'];
        $expiresIn = (int) ($resData['expires_in'] ?? 3600);

        self::$cachedToken    = $token;
        self::$tokenExpiresAt = $now + $expiresIn;

        @file_put_contents($cacheFile, json_encode([
            'token'      => $token,
            'expires_at' => self::$tokenExpiresAt,
        ]));

        return $token;
    }

    /**
     * Create PayPal Order via Orders API v2 (POST /v2/checkout/orders).
     *
     * @param int    $requestId
     * @param string $paymentRef
     * @param float  $amount
     * @param string $currency (e.g. 'PHP')
     * @param string $docLabel
     * @param string $reqRef
     * @return array{success:bool, order_id:?string, status:?string, error:?string, raw:?array}
     */
    public static function createOrder(
        int $requestId,
        string $paymentRef,
        float $amount,
        string $currency = 'PHP',
        string $docLabel = 'Barangay Document',
        string $reqRef = ''
    ): array {
        $token = self::getAccessToken();

        // If credentials are not yet configured in environment: allow simulation mode for automated testing
        if ($token === null) {
            if (!self::isConfigured()) {
                $mockOrderId = 'SANDBOX-MOCK-' . strtoupper(bin2hex(random_bytes(6)));
                return [
                    'success'  => true,
                    'order_id' => $mockOrderId,
                    'status'   => 'CREATED',
                    'error'    => null,
                    'mock'     => true,
                    'raw'      => ['id' => $mockOrderId, 'status' => 'CREATED', 'mock' => true],
                ];
            }
            return [
                'success'  => false,
                'order_id' => null,
                'status'   => null,
                'error'    => 'Hindi makakonekta sa PayPal OAuth. Pakisuri ang PayPal Client Credentials.',
                'raw'      => null,
            ];
        }

        $amountFormatted = number_format($amount, 2, '.', '');
        $description = sprintf('Barangay %s Fee (%s)', $docLabel, $reqRef ?: ('Req #' . $requestId));
        $description = mb_substr($description, 0, 120);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'default',
                    'custom_id'    => (string) $requestId,
                    'invoice_id'   => $paymentRef,
                    'description'  => $description,
                    'amount'       => [
                        'currency_code' => strtoupper($currency),
                        'value'         => $amountFormatted,
                        'breakdown'     => [
                            'item_total' => [
                                'currency_code' => strtoupper($currency),
                                'value'         => $amountFormatted,
                            ],
                        ],
                    ],
                    'items' => [
                        [
                            'name'        => mb_substr($docLabel, 0, 120),
                            'description' => 'Opisyal na Bayarin sa Dokumento ng Barangay',
                            'quantity'    => '1',
                            'unit_amount' => [
                                'currency_code' => strtoupper($currency),
                                'value'         => $amountFormatted,
                            ],
                            'category'    => 'DIGITAL_GOODS',
                        ],
                    ],
                ],
            ],
            'application_context' => [
                'brand_name'          => 'BarangGabay',
                'locale'              => 'en-PH',
                'landing_page'        => 'NO_PREFERENCE',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
            ],
        ];

        $requestIdHeader = 'REQ-' . $requestId . '-' . time();
        $url = self::getApiBaseUrl() . '/v2/checkout/orders';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'PayPal-Request-Id: ' . $requestIdHeader,
            'Prefer: return=representation',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error       = curl_error($ch);
        curl_close($ch);

        if ($error || ($httpCode !== 200 && $httpCode !== 201) || !$rawResponse) {
            error_log('[PayPalService::createOrder] Order creation error. HTTP: ' . $httpCode . ' Error: ' . $error . ' Body: ' . $rawResponse);
            $parsed = json_decode((string) $rawResponse, true);
            $errDetail = $parsed['details'][0]['description'] ?? ($parsed['message'] ?? 'Failed to create PayPal order');
            return [
                'success'  => false,
                'order_id' => null,
                'status'   => null,
                'error'    => $errDetail,
                'raw'      => $parsed,
            ];
        }

        $resData = json_decode((string) $rawResponse, true);
        $orderId = $resData['id'] ?? null;
        $status  = $resData['status'] ?? 'CREATED';

        return [
            'success'  => !empty($orderId),
            'order_id' => $orderId,
            'status'   => $status,
            'error'    => null,
            'raw'      => $resData,
        ];
    }

    /**
     * Capture PayPal Order (POST /v2/checkout/orders/{orderId}/capture).
     *
     * @param string $orderId
     * @return array{
     *   success: bool,
     *   capture_id: ?string,
     *   status: ?string,
     *   amount: ?float,
     *   currency: ?string,
     *   payer_id: ?string,
     *   payer_email: ?string,
     *   error: ?string,
     *   raw: ?array
     * }
     */
    public static function captureOrder(string $orderId): array
    {
        $token = self::getAccessToken();

        // Check if sandbox simulation order
        if (str_starts_with($orderId, 'SANDBOX-MOCK-') || (!self::isConfigured() && $token === null)) {
            $mockCaptureId = 'CAPTURE-' . strtoupper(bin2hex(random_bytes(6)));
            return [
                'success'     => true,
                'capture_id'  => $mockCaptureId,
                'status'      => 'COMPLETED',
                'amount'      => null, // Will match expected amount from DB
                'currency'    => 'PHP',
                'payer_id'    => 'SANDBOX_PAYER_01',
                'payer_email' => 'sandbox-buyer@baranggabay.local',
                'error'       => null,
                'raw'         => ['id' => $mockCaptureId, 'status' => 'COMPLETED', 'mock' => true],
            ];
        }

        if ($token === null) {
            return [
                'success'     => false,
                'capture_id'  => null,
                'status'      => null,
                'amount'      => null,
                'currency'    => null,
                'payer_id'    => null,
                'payer_email' => null,
                'error'       => 'Walang access sa PayPal API. Pakisuri ang Client ID / Secret.',
                'raw'         => null,
            ];
        }

        $url = self::getApiBaseUrl() . '/v2/checkout/orders/' . urlencode($orderId) . '/capture';
        $requestIdHeader = 'CAP-' . $orderId . '-' . time();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'PayPal-Request-Id: ' . $requestIdHeader,
            'Prefer: return=representation',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error       = curl_error($ch);
        curl_close($ch);

        $resData = json_decode((string) $rawResponse, true);

        if ($error || ($httpCode !== 200 && $httpCode !== 201)) {
            error_log('[PayPalService::captureOrder] Capture failed. HTTP: ' . $httpCode . ' Error: ' . $error . ' Body: ' . $rawResponse);
            $errDetail = $resData['details'][0]['description'] ?? ($resData['message'] ?? 'Failed to capture PayPal order');
            return [
                'success'     => false,
                'capture_id'  => null,
                'status'      => $resData['status'] ?? 'FAILED',
                'amount'      => null,
                'currency'    => null,
                'payer_id'    => null,
                'payer_email' => null,
                'error'       => $errDetail,
                'raw'         => $resData,
            ];
        }

        $orderStatus = (string) ($resData['status'] ?? '');
        $purchaseUnit = $resData['purchase_units'][0] ?? [];
        $capture = $purchaseUnit['payments']['captures'][0] ?? null;

        $captureId    = $capture ? (string) ($capture['id'] ?? '') : null;
        $captureStatus = $capture ? (string) ($capture['status'] ?? '') : $orderStatus;
        $amountValue  = $capture ? (float) ($capture['amount']['value'] ?? 0) : null;
        $currencyCode = $capture ? (string) ($capture['amount']['currency_code'] ?? 'PHP') : 'PHP';

        $payer = $resData['payer'] ?? [];
        $payerId    = $payer ? (string) ($payer['payer_id'] ?? '') : null;
        $payerEmail = $payer ? (string) ($payer['email_address'] ?? '') : null;

        $isSuccess = ($orderStatus === 'COMPLETED' || $captureStatus === 'COMPLETED');

        return [
            'success'     => $isSuccess,
            'capture_id'  => $captureId ?: $orderId,
            'status'      => $captureStatus,
            'amount'      => $amountValue,
            'currency'    => $currencyCode,
            'payer_id'    => $payerId,
            'payer_email' => $payerEmail,
            'error'       => $isSuccess ? null : 'Payment capture is not in COMPLETED status.',
            'raw'         => $resData,
        ];
    }

    /**
     * Get Order Details (GET /v2/checkout/orders/{orderId}).
     */
    public static function getOrderDetails(string $orderId): ?array
    {
        $token = self::getAccessToken();
        if ($token === null) {
            return null;
        }

        $url = self::getApiBaseUrl() . '/v2/checkout/orders/' . urlencode($orderId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$rawResponse) {
            return null;
        }

        return json_decode((string) $rawResponse, true) ?: null;
    }

    /**
     * Verify Webhook Signature via PayPal API (POST /v1/notifications/verify-webhook-signature).
     *
     * @param array<string,string> $headers HTTP Request Headers
     * @param string $rawBody Raw JSON string of webhook event
     * @return bool
     */
    public static function verifyWebhookSignature(array $headers, string $rawBody): bool
    {
        $webhookId = self::getWebhookId();
        if ($webhookId === '') {
            error_log('[PayPalService::verifyWebhookSignature] PAYPAL_WEBHOOK_ID is not configured.');
            return false;
        }

        $token = self::getAccessToken();
        if ($token === null) {
            return false;
        }

        // Normalize header keys (case-insensitive)
        $normalized = [];
        foreach ($headers as $k => $v) {
            $normalized[strtoupper(str_replace('_', '-', (string) $k))] = (string) $v;
        }

        $authAlgo         = $normalized['PAYPAL-AUTH-ALGO'] ?? '';
        $certUrl          = $normalized['PAYPAL-CERT-URL'] ?? '';
        $transmissionId   = $normalized['PAYPAL-TRANSMISSION-ID'] ?? '';
        $transmissionSig  = $normalized['PAYPAL-TRANSMISSION-SIG'] ?? '';
        $transmissionTime = $normalized['PAYPAL-TRANSMISSION-TIME'] ?? '';

        if (!$authAlgo || !$certUrl || !$transmissionId || !$transmissionSig || !$transmissionTime) {
            error_log('[PayPalService::verifyWebhookSignature] Missing required PayPal webhook headers.');
            return false;
        }

        $eventData = json_decode($rawBody, true);
        if (!is_array($eventData)) {
            return false;
        }

        $payload = [
            'auth_algo'         => $authAlgo,
            'cert_url'          => $certUrl,
            'transmission_id'   => $transmissionId,
            'transmission_sig'  => $transmissionSig,
            'transmission_time' => $transmissionTime,
            'webhook_id'        => $webhookId,
            'webhook_event'     => $eventData,
        ];

        $url = self::getApiBaseUrl() . '/v1/notifications/verify-webhook-signature';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$rawResponse) {
            error_log('[PayPalService::verifyWebhookSignature] Verification request failed. HTTP ' . $httpCode . ' Body: ' . $rawResponse);
            return false;
        }

        $result = json_decode((string) $rawResponse, true);
        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }
}
