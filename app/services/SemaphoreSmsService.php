<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SmsLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Semaphore SMS API wrapper (https://api.semaphore.co/api/v4).
 *
 * All API calls are server-side only — the API key never reaches the browser.
 * Fails gracefully: every exception is caught and logged; callers receive a
 * structured ['success' => bool, ...] array and can continue without crashing.
 */
class SemaphoreSmsService
{
    private Client $client;
    private string $apiKey;
    private string $senderName;
    private string $apiUrl;
    private bool   $testMode;

    public function __construct()
    {
        $this->apiKey     = $_ENV['SEMAPHORE_API_KEY']     ?? '';
        $this->senderName = $_ENV['SEMAPHORE_SENDER_NAME'] ?? 'BarangGabay';
        $this->apiUrl     = rtrim($_ENV['SEMAPHORE_API_URL'] ?? 'https://api.semaphore.co/api/v4', '/');
        // Test mode: simulate SMS delivery without calling Semaphore.
        // Enable when the Semaphore account is not yet approved / topped up.
        $this->testMode   = filter_var($_ENV['SEMAPHORE_TEST_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        $this->client = new Client(['timeout' => 30]);
    }

    /** Returns true when test mode is active (SMS is simulated, not sent). */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Send an SMS to a single Philippine mobile number.
     *
     * @param  string   $number  Raw phone number in any PH format.
     * @param  string   $message Message body (trimmed to 160 chars).
     * @param  string   $type    Log category ('announcement', 'event', 'verification', 'manual', …).
     * @param  int|null $refId   Optional foreign key to the related content row.
     * @return array{success:bool, response?:array, error?:string}
     */
    public function send(string $number, string $message, string $type = 'general', ?int $refId = null): array
    {
        if (!$this->isConfigured()) {
            error_log('SemaphoreSMS: API key not configured.');
            return ['success' => false, 'error' => 'SMS API key not configured.'];
        }

        $clean = $this->cleanPhone($number);
        if ($clean === null) {
            error_log('SemaphoreSMS: invalid phone number — ' . $number);
            return ['success' => false, 'error' => 'Invalid phone number: ' . $number];
        }

        $message = substr(trim($message), 0, 160);

        // ── Test mode: simulate delivery without hitting Semaphore ──────────
        if ($this->testMode) {
            SmsLog::create([
                'phone'        => $clean,
                'message'      => $message,
                'type'         => $type,
                'reference_id' => $refId,
                'status'       => 'sent',
                'response'     => '[TEST MODE — hindi talaga naipadala sa Semaphore]',
            ]);
            error_log('SemaphoreSMS test-mode send → ' . $clean);
            return ['success' => true, 'test_mode' => true, 'response' => []];
        }

        try {
            $response = $this->client->post($this->apiUrl . '/messages', [
                'form_params' => [
                    'apikey'     => $this->apiKey,
                    'number'     => $clean,
                    'message'    => $message,
                    'sendername' => $this->senderName,
                ],
            ]);

            $body    = (string) $response->getBody();
            $decoded = json_decode($body, true);
            $success = $response->getStatusCode() >= 200
                    && $response->getStatusCode() < 300
                    && !isset($decoded['error'])
                    && !isset($decoded['message']); // Semaphore returns "message" key on errors

            SmsLog::create([
                'phone'        => $clean,
                'message'      => $message,
                'type'         => $type,
                'reference_id' => $refId,
                'status'       => $success ? 'sent' : 'failed',
                'response'     => $body,
            ]);

            error_log('SemaphoreSMS: ' . ($success ? 'sent' : 'failed') . ' → ' . $clean);
            return ['success' => $success, 'response' => $decoded];

        } catch (GuzzleException $e) {
            // Extract the clean error message from Semaphore's JSON response body
            // instead of showing the full Guzzle exception string to the admin.
            $cleanError = $this->extractSemaphoreError($e);
            error_log('SemaphoreSMS exception (send): ' . $e->getMessage());
            SmsLog::create([
                'phone'        => $clean,
                'message'      => $message,
                'type'         => $type,
                'reference_id' => $refId,
                'status'       => 'failed',
                'response'     => $cleanError,
            ]);
            return ['success' => false, 'error' => $cleanError];
        }
    }

    /**
     * Send the same message to multiple PH mobile numbers.
     *
     * Sends up to 1 000 numbers per API request (Semaphore accepts
     * comma-separated numbers). Returns counts of sent vs. failed.
     *
     * @param  string[]  $numbers Raw phone numbers (any PH format).
     * @param  string    $message Message body (trimmed to 160 chars).
     * @param  string    $type    Log category.
     * @param  int|null  $refId   Optional FK to related content.
     * @return array{success:bool, sent:int, failed:int, error?:string}
     */
    public function sendBulk(array $numbers, string $message, string $type = 'general', ?int $refId = null): array
    {
        if (!$this->isConfigured()) {
            error_log('SemaphoreSMS: API key not configured (bulk).');
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'SMS API key not configured.'];
        }

        // Clean and deduplicate numbers, drop invalids
        $cleaned = array_values(array_unique(array_filter(
            array_map([$this, 'cleanPhone'], $numbers)
        )));

        if (empty($cleaned)) {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'No valid PH phone numbers.'];
        }

        $message = substr(trim($message), 0, 160);
        $sent    = 0;
        $failed  = 0;

        // ── Test mode: simulate delivery for all numbers ─────────────────
        if ($this->testMode) {
            foreach ($cleaned as $num) {
                SmsLog::create([
                    'phone'        => $num,
                    'message'      => $message,
                    'type'         => $type,
                    'reference_id' => $refId,
                    'status'       => 'sent',
                    'response'     => '[TEST MODE — hindi talaga naipadala sa Semaphore]',
                ]);
                $sent++;
            }
            error_log('SemaphoreSMS test-mode bulk → ' . $sent . ' numbers');
            return ['success' => true, 'sent' => $sent, 'failed' => 0];
        }

        foreach (array_chunk($cleaned, 1000) as $chunk) {
            try {
                $response = $this->client->post($this->apiUrl . '/messages', [
                    'form_params' => [
                        'apikey'     => $this->apiKey,
                        'number'     => implode(',', $chunk),
                        'message'    => $message,
                        'sendername' => $this->senderName,
                    ],
                ]);

                $body    = (string) $response->getBody();
                $ok      = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
                $decoded = json_decode($body, true);
                $ok      = $ok && !isset($decoded['error']) && !isset($decoded['message']);

                // Log each number individually so the SMS panel shows per-recipient status
                foreach ($chunk as $num) {
                    SmsLog::create([
                        'phone'        => $num,
                        'message'      => $message,
                        'type'         => $type,
                        'reference_id' => $refId,
                        'status'       => $ok ? 'sent' : 'failed',
                        'response'     => $body,
                    ]);
                }

                if ($ok) {
                    $sent += count($chunk);
                } else {
                    $failed += count($chunk);
                    error_log('SemaphoreSMS bulk chunk failed: ' . $body);
                }

                // Brief pause between chunks to avoid hammering the API
                if (count($cleaned) > 1000) {
                    sleep(1);
                }

            } catch (GuzzleException $e) {
                $failed += count($chunk);
                $cleanError = $this->extractSemaphoreError($e);
                error_log('SemaphoreSMS exception (bulk): ' . $e->getMessage());
                foreach ($chunk as $num) {
                    SmsLog::create([
                        'phone'        => $num,
                        'message'      => $message,
                        'type'         => $type,
                        'reference_id' => $refId,
                        'status'       => 'failed',
                        'response'     => $cleanError,
                    ]);
                }
            }
        }

        error_log("SemaphoreSMS bulk complete — sent:{$sent}, failed:{$failed}");
        return ['success' => true, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Check the Semaphore account balance / remaining SMS credits.
     * In test mode returns a simulated balance so the UI does not error out.
     *
     * @return array|null  Associative array from the API, or null on failure.
     */
    public function getBalance(): ?array
    {
        if ($this->testMode) {
            return ['status' => 'test_mode', 'credits' => 'N/A (Test Mode)'];
        }

        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client->get($this->apiUrl . '/account', [
                'query' => ['apikey' => $this->apiKey],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode((string) $response->getBody(), true);
            }
        } catch (GuzzleException $e) {
            error_log('SemaphoreSMS balance check failed: ' . $e->getMessage());
        }

        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Extract a short, human-readable error string from a Guzzle exception.
     * Semaphore returns JSON bodies like {"message":"..."} on 4xx responses;
     * we surface that directly instead of the full Guzzle exception string.
     */
    private function extractSemaphoreError(GuzzleException $e): string
    {
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            try {
                $body    = (string) $e->getResponse()->getBody();
                $decoded = json_decode($body, true);
                if (!empty($decoded['message'])) {
                    return $decoded['message'];
                }
                if (!empty($decoded['error'])) {
                    return $decoded['error'];
                }
            } catch (\Throwable) {
                // Fall through to the raw message
            }
        }
        // Strip the verbose Guzzle prefix and return just the response body excerpt.
        $msg = $e->getMessage();
        if (preg_match('/response:\s*(.+)$/s', $msg, $m)) {
            return trim($m[1]);
        }
        return $msg;
    }

    /** Returns true when an API key has been set in the environment. */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Normalise a Philippine mobile number to the 09XXXXXXXXX (11-digit) format
     * that Semaphore expects. Returns null for numbers that cannot be parsed.
     *
     * Accepted input formats:
     *   09XXXXXXXXX  (already correct)
     *   9XXXXXXXXX   (missing leading 0)
     *   639XXXXXXXXX (PH country code without +)
     *   +639XXXXXXXX (PH country code with +)
     */
    public function cleanPhone(string $number): ?string
    {
        $digits = preg_replace('/\D/', '', $number);

        // Already 09XXXXXXXXX
        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return $digits;
        }

        // 9XXXXXXXXX → 09XXXXXXXXX
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0' . $digits;
        }

        // 639XXXXXXXXX → 09XXXXXXXXX
        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            return '0' . substr($digits, 2);
        }

        // Edge: 630XXXXXXXXX (malformed country code)
        if (strlen($digits) === 13 && str_starts_with($digits, '630')) {
            return substr($digits, 2);
        }

        error_log('SemaphoreSMS: unrecognised PH number format — ' . $number);
        return null;
    }
}
