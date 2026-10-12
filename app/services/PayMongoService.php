<?php
declare(strict_types=1);

namespace App\Services;

/**
 * PayMongo REST client for BarangGabay document payments.
 *
 * - Hosted Checkout Sessions (v2, falling back to v1 if v2 is unavailable)
 * - Checkout Session retrieval for reconciliation
 * - Webhook signature verification (fails CLOSED when no secret is set)
 *
 * Credentials come ONLY from environment variables (Render → Environment):
 *   PAYMONGO_SECRET_KEY      sk_test_… / sk_live_…   server-side only
 *   PAYMONGO_PUBLIC_KEY      pk_test_… / pk_live_…   not needed by hosted checkout
 *   PAYMONGO_WEBHOOK_SECRET  whsk_…                  signing secret of the webhook
 *   PAYMONGO_PAYMENT_METHODS optional, comma list (default "gcash")
 * Test vs live is decided by the key itself, so switching to live mode is an
 * environment change only. Nothing here ever echoes, logs or returns a key.
 *
 * Amounts are integers in CENTAVOS (₱100.00 = 10000).
 */
class PayMongoService
{
    private const API_BASE_URL = 'https://api.paymongo.com';

    /** PayMongo's minimum charge for e-wallets is ₱20.00. */
    public const MIN_AMOUNT_CENTAVOS = 2000;

    /** Methods that may be enabled through PAYMONGO_PAYMENT_METHODS. */
    private const ALLOWED_METHODS = ['gcash', 'qrph', 'paymaya', 'grab_pay', 'card', 'dob', 'billease'];

    // ── Configuration ────────────────────────────────────────────────────

    private static function envValue(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_string($value) ? trim($value) : '';
    }

    public static function getSecretKey(): string
    {
        return self::envValue('PAYMONGO_SECRET_KEY');
    }

    public static function getPublicKey(): string
    {
        return self::envValue('PAYMONGO_PUBLIC_KEY');
    }

    public static function getWebhookSecret(): string
    {
        return self::envValue('PAYMONGO_WEBHOOK_SECRET');
    }

    public static function isConfigured(): bool
    {
        return str_starts_with(self::getSecretKey(), 'sk_');
    }

    public static function isWebhookConfigured(): bool
    {
        return self::getWebhookSecret() !== '';
    }

    /** 'live' only when the secret key itself is a live key. */
    public static function getMode(): string
    {
        return str_starts_with(self::getSecretKey(), 'sk_live_') ? 'live' : 'test';
    }

    /** @return list<string> */
    public static function paymentMethodTypes(): array
    {
        $raw = self::envValue('PAYMONGO_PAYMENT_METHODS');
        if ($raw === '') {
            return ['gcash'];
        }
        $methods = array_values(array_intersect(
            self::ALLOWED_METHODS,
            array_map('trim', explode(',', strtolower($raw)))
        ));
        return $methods ?: ['gcash'];
    }

    /** Pesos (as stored in DECIMAL columns) → integer centavos, without float drift. */
    public static function toCentavos(float|int|string $pesos): int
    {
        return (int) round(((float) $pesos) * 100);
    }

    // ── Checkout Sessions ────────────────────────────────────────────────

    /**
     * Create a hosted Checkout Session. The amount must already be the
     * official server-side fee, in centavos — never a client value.
     *
     * @param array{
     *   amount_centavos:int, item_name:string, description:string,
     *   reference_number:string, success_url:string, cancel_url:string,
     *   metadata:array<string,string>
     * } $args
     * @return array{success:bool, checkout_id:?string, checkout_url:?string, error:?string, raw:?array}
     */
    public static function createCheckoutSession(array $args): array
    {
        $fail = static fn (string $error, ?array $raw = null): array => [
            'success' => false, 'checkout_id' => null, 'checkout_url' => null, 'error' => $error, 'raw' => $raw,
        ];

        $secretKey = self::getSecretKey();
        if (!self::isConfigured()) {
            return $fail('Online payment is not set up yet (PAYMONGO_SECRET_KEY is missing).');
        }

        $amount = (int) $args['amount_centavos'];
        if ($amount < self::MIN_AMOUNT_CENTAVOS) {
            // Never round a fee UP to the gateway minimum — that would charge
            // the resident more than the official fee.
            return $fail('This fee is below PayMongo\'s ₱20.00 minimum for GCash. Please pay at the Barangay Hall.');
        }

        $payload = ['data' => ['attributes' => [
            'line_items' => [[
                'name'     => mb_substr($args['item_name'], 0, 255),
                'amount'   => $amount,
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            'payment_method_types' => self::paymentMethodTypes(),
            'description'          => mb_substr($args['description'], 0, 255),
            'reference_number'     => $args['reference_number'],
            'success_url'          => $args['success_url'],
            'cancel_url'           => $args['cancel_url'],
            'send_email_receipt'   => true,
            'show_description'     => true,
            'show_line_items'      => true,
            'metadata'             => array_map('strval', $args['metadata']),
        ]]];

        $response = self::apiRequest('POST', '/v2/checkout_sessions', $payload, $secretKey);
        if (!$response['success'] && $response['http'] === 404) {
            $response = self::apiRequest('POST', '/v1/checkout_sessions', $payload, $secretKey);
        }
        if (!$response['success']) {
            return $fail((string) $response['error'], $response['raw']);
        }

        $data        = $response['raw']['data'] ?? [];
        $checkoutId  = (string) ($data['id'] ?? '');
        $checkoutUrl = (string) ($data['attributes']['checkout_url'] ?? '');
        if ($checkoutId === '' || !str_starts_with($checkoutUrl, 'https://')) {
            return $fail('PayMongo returned an incomplete checkout session.', $response['raw']);
        }

        return ['success' => true, 'checkout_id' => $checkoutId, 'checkout_url' => $checkoutUrl, 'error' => null, 'raw' => $response['raw']];
    }

    /**
     * Retrieve a Checkout Session and its payments, normalised.
     *
     * @return array{success:bool, status:?string, checkout_url:?string, payment_intent_id:?string,
     *               payments:list<array>, metadata:array, livemode:?bool, error:?string, raw:?array}
     */
    public static function retrieveCheckoutSession(string $checkoutId): array
    {
        $empty = ['success' => false, 'status' => null, 'checkout_url' => null, 'payment_intent_id' => null,
                  'payments' => [], 'metadata' => [], 'livemode' => null, 'error' => null, 'raw' => null];

        if (!self::isConfigured()) {
            return ['error' => 'PayMongo is not configured.'] + $empty;
        }
        if (!preg_match('/^cs_[A-Za-z0-9]+$/', $checkoutId)) {
            return ['error' => 'Invalid checkout session id.'] + $empty;
        }

        $secretKey = self::getSecretKey();
        $response  = self::apiRequest('GET', '/v2/checkout_sessions/' . $checkoutId, null, $secretKey);
        if (!$response['success'] && $response['http'] === 404) {
            $response = self::apiRequest('GET', '/v1/checkout_sessions/' . $checkoutId, null, $secretKey);
        }
        if (!$response['success']) {
            return ['error' => $response['error'], 'raw' => $response['raw']] + $empty;
        }

        $attrs    = $response['raw']['data']['attributes'] ?? [];
        $intent   = $attrs['payment_intent'] ?? null;
        $payments = array_values(array_filter(array_map(
            [self::class, 'normalizePayment'],
            is_array($attrs['payments'] ?? null) ? $attrs['payments'] : []
        )));

        return [
            'success'           => true,
            'status'            => (string) ($attrs['status'] ?? 'unknown'),
            'checkout_url'      => isset($attrs['checkout_url']) ? (string) $attrs['checkout_url'] : null,
            'payment_intent_id' => is_array($intent) ? (string) ($intent['id'] ?? '') ?: null : null,
            'payments'          => $payments,
            'metadata'          => is_array($attrs['metadata'] ?? null) ? $attrs['metadata'] : [],
            'livemode'          => isset($attrs['livemode']) ? (bool) $attrs['livemode'] : null,
            'error'             => null,
            'raw'               => $response['raw'],
        ];
    }

    /**
     * A PayMongo Payment resource → the fields confirmation needs.
     *
     * @return array{id:string, status:string, amount:int, currency:string, source_type:string, payment_intent_id:?string}|null
     */
    public static function normalizePayment(mixed $resource): ?array
    {
        if (!is_array($resource) || empty($resource['id'])) {
            return null;
        }
        $a = $resource['attributes'] ?? [];
        return [
            'id'                => (string) $resource['id'],
            'status'            => strtolower((string) ($a['status'] ?? '')),
            'amount'            => (int) ($a['amount'] ?? 0),
            'currency'          => strtoupper((string) ($a['currency'] ?? '')),
            'source_type'       => strtolower((string) ($a['source']['type'] ?? ($a['payment_method_type'] ?? ''))),
            'payment_intent_id' => isset($a['payment_intent_id']) ? (string) $a['payment_intent_id'] : null,
        ];
    }

    // ── Webhooks ─────────────────────────────────────────────────────────

    /**
     * Verify the Paymongo-Signature header: "t=<ts>,te=<test sig>,li=<live sig>".
     * The signature is HMAC-SHA256 of "<ts>.<raw body>" keyed with the webhook
     * secret. Test events are checked against `te`, live events against `li`
     * (taken from the event's own livemode flag).
     *
     * Fails CLOSED: with no secret configured nothing is accepted.
     */
    public static function verifyWebhookSignature(array $headers, string $rawPayload, ?string $webhookSecret = null): bool
    {
        $secret = trim($webhookSecret ?? self::getWebhookSecret());
        if ($secret === '') {
            return false;
        }

        $header = '';
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'paymongo-signature') {
                $header = is_array($value) ? (string) reset($value) : (string) $value;
            }
        }
        if ($header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }
        $timestamp = $parts['t'] ?? '';
        if ($timestamp === '' || !ctype_digit($timestamp)) {
            return false;
        }

        $decoded  = json_decode($rawPayload, true);
        $livemode = (bool) ($decoded['data']['attributes']['livemode'] ?? false);
        $received = $livemode ? ($parts['li'] ?? '') : ($parts['te'] ?? '');
        if ($received === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $secret);
        return hash_equals($expected, $received);
    }

    /**
     * Normalise a webhook event.
     *
     * @return array{event_id:string, event_type:string, livemode:bool, checkout_session_id:?string,
     *               payments:list<array>, metadata:array}
     */
    public static function parseWebhookEvent(array $event): array
    {
        $attrs    = $event['data']['attributes'] ?? [];
        $resource = is_array($attrs['data'] ?? null) ? $attrs['data'] : [];
        $type     = (string) ($attrs['type'] ?? '');
        $resId    = (string) ($resource['id'] ?? '');
        $resAttrs = $resource['attributes'] ?? [];

        $payments          = [];
        $checkoutSessionId = null;
        if (str_starts_with($type, 'checkout_session.')) {
            $checkoutSessionId = $resId !== '' ? $resId : null;
            foreach (is_array($resAttrs['payments'] ?? null) ? $resAttrs['payments'] : [] as $p) {
                if ($n = self::normalizePayment($p)) {
                    $payments[] = $n;
                }
            }
        } elseif (str_starts_with($type, 'payment.')) {
            if ($n = self::normalizePayment($resource)) {
                $payments[] = $n;
            }
        }

        return [
            'event_id'            => (string) ($event['data']['id'] ?? ''),
            'event_type'          => $type,
            'livemode'            => (bool) ($attrs['livemode'] ?? false),
            'checkout_session_id' => $checkoutSessionId,
            'payments'            => $payments,
            'metadata'            => is_array($resAttrs['metadata'] ?? null) ? $resAttrs['metadata'] : [],
        ];
    }

    // ── HTTP ─────────────────────────────────────────────────────────────

    /**
     * Authenticated request (HTTP Basic: secret key as username, empty password).
     * Errors are logged by status and PayMongo error code only — never the key.
     *
     * @return array{success:bool, http:int, error:?string, raw:?array}
     */
    private static function apiRequest(string $method, string $path, ?array $payload, string $secretKey): array
    {
        $ch = curl_init(self::API_BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?? []));
        }

        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            error_log('[PayMongo] network error on ' . $method . ' ' . $path . ': ' . $curlErr);
            return ['success' => false, 'http' => 0, 'error' => 'Could not reach PayMongo. Please try again.', 'raw' => null];
        }

        $parsed = json_decode((string) $body, true);
        if ($httpCode >= 200 && $httpCode < 300 && is_array($parsed)) {
            return ['success' => true, 'http' => $httpCode, 'error' => null, 'raw' => $parsed];
        }

        $code   = (string) ($parsed['errors'][0]['code'] ?? '');
        $detail = (string) ($parsed['errors'][0]['detail'] ?? 'PayMongo request failed (HTTP ' . $httpCode . ').');
        if (str_contains($code, 'payment_method_not_allowed') || str_contains($code, 'payment_method_unavailable')) {
            $detail = 'GCash is not yet activated on the PayMongo account (Dashboard → Settings → Payment Methods).';
        } elseif ($httpCode === 401) {
            $detail = 'PayMongo rejected the API key. Check PAYMONGO_SECRET_KEY on the server.';
        }
        error_log(sprintf('[PayMongo] HTTP %d on %s %s (%s)', $httpCode, $method, $path, $code ?: 'no code'));

        return ['success' => false, 'http' => $httpCode, 'error' => $detail, 'raw' => is_array($parsed) ? $parsed : null];
    }
}
