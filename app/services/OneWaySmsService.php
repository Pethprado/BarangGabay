<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SmsLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * OneWaySMS Philippines API Wrapper (https://www.onewaysms.ph).
 *
 * All API calls are executed server-side.
 * Credentials are read exclusively from server environment variables.
 * Fails gracefully without throwing uncaught exceptions to callers.
 */
class OneWaySmsService
{
    private Client $client;
    private string $apiUsername;
    private string $apiPassword;
    private string $senderId;
    private string $apiUrl;
    private bool   $testMode;

    public function __construct()
    {
        $this->apiUsername = $_ENV['ONEWAYSMS_USERNAME']     ?? $_ENV['ONEWAYSMS_API_USERNAME'] ?? $_ENV['SMS_USERNAME'] ?? '';
        $this->apiPassword = $_ENV['ONEWAYSMS_PASSWORD']     ?? $_ENV['ONEWAYSMS_API_PASSWORD'] ?? $_ENV['SMS_PASSWORD'] ?? $_ENV['ONEWAYSMS_API_KEY'] ?? '';
        $this->senderId    = $_ENV['ONEWAYSMS_SENDER_ID']    ?? $_ENV['SEMAPHORE_SENDER_NAME']  ?? 'BARANGGABAY';
        $this->apiUrl      = rtrim($_ENV['ONEWAYSMS_API_URL'] ?? 'https://www.onewaysms.ph/api.aspx', '/');
        $this->testMode    = filter_var($_ENV['ONEWAYSMS_TEST_MODE'] ?? $_ENV['SEMAPHORE_TEST_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        $this->client = new Client(['timeout' => 15]);
    }

    public function isConfigured(): bool
    {
        return $this->apiUsername !== '' && $this->apiPassword !== '';
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    /**
     * Normalizes Philippine mobile numbers to the 639XXXXXXXXX (12-digit) format required by OneWaySMS PH.
     *
     * Accepted input formats:
     *   09XXXXXXXXX  -> 639XXXXXXXXX
     *   9XXXXXXXXX   -> 639XXXXXXXXX
     *   +639XXXXXXXX -> 639XXXXXXXXX
     *   639XXXXXXXXX -> 639XXXXXXXXX
     */
    public function cleanPhone(string $number): ?string
    {
        $digits = preg_replace('/\D/', '', $number);

        // 09XXXXXXXXX (11 digits) -> 639XXXXXXXXX
        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return '63' . substr($digits, 1);
        }

        // 9XXXXXXXXX (10 digits) -> 639XXXXXXXXX
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '63' . $digits;
        }

        // 639XXXXXXXXX (12 digits) -> 639XXXXXXXXX
        if (strlen($digits) === 12 && str_starts_with($digits, '639')) {
            return $digits;
        }

        // 6309XXXXXXXXX (13 digits malformed) -> 639XXXXXXXXX
        if (strlen($digits) === 13 && str_starts_with($digits, '6309')) {
            return '639' . substr($digits, 4);
        }

        error_log('OneWaySMS: unrecognized PH number format — ' . $number);
        return null;
    }

    /**
     * Send an SMS to a single recipient.
     *
     * @param  string   $number  Raw phone number in any PH format.
     * @param  string   $message Message body (trimmed to 160 chars).
     * @param  string   $type    Log category ('document_request', 'announcement', 'event', 'verification', 'manual', …).
     * @param  int|null $refId   Optional foreign key to related row.
     * @return array{success:bool, response?:mixed, error?:string, test_mode?:bool}
     */
    public function send(string $number, string $message, string $type = 'general', ?int $refId = null): array
    {
        if (!$this->isConfigured()) {
            error_log('OneWaySMS: API credentials not configured.');
            return ['success' => false, 'error' => 'OneWaySMS credentials not configured in environment.'];
        }

        $clean = $this->cleanPhone($number);
        if ($clean === null) {
            error_log('OneWaySMS: invalid phone number — ' . $number);
            return ['success' => false, 'error' => 'Invalid Philippine mobile number: ' . $number];
        }

        $message = substr(trim($message), 0, 160);

        if ($this->testMode) {
            SmsLog::create([
                'phone'        => $clean,
                'message'      => $message,
                'type'         => $type,
                'reference_id' => $refId,
                'status'       => 'sent',
                'response'     => '[TEST MODE — OneWaySMS simulated send]',
            ]);
            error_log('OneWaySMS test-mode send → ' . $clean);
            return ['success' => true, 'test_mode' => true, 'response' => []];
        }

        try {
            $response = $this->client->post($this->apiUrl, [
                'form_params' => [
                    'apiusername' => $this->apiUsername,
                    'apipassword' => $this->apiPassword,
                    'senderid'    => $this->senderId,
                    'mobileno'    => $clean,
                    'message'     => $message,
                    'ltype'       => '1',
                ],
            ]);

            $body    = trim((string) $response->getBody());
            $success = false;
            $error   = null;

            // OneWaySMS PH Response Parsing:
            // Positive integer (>0) represents transaction ID -> success
            // Negative integers represent error codes:
            // -10: Invalid API Username / Password
            // -20: Invalid Sender ID
            // -30: Invalid Mobile Number
            // -40: Insufficient Balance / Credits
            // -50: Empty or Invalid Message
            // -60: Gateway Error
            if (is_numeric($body)) {
                $code = (int) $body;
                if ($code > 0) {
                    $success = true;
                } else {
                    $success = false;
                    $error   = match ($code) {
                        -10 => 'Invalid OneWaySMS Username or Password (-10)',
                        -20 => 'Invalid or Unregistered Sender ID (-20)',
                        -30 => 'Invalid Mobile Number (-30)',
                        -40 => 'Insufficient OneWaySMS Credits (-40)',
                        -50 => 'Empty or Invalid Message Content (-50)',
                        -60 => 'OneWaySMS Gateway Service Error (-60)',
                        default => 'OneWaySMS API Error Code: ' . $code,
                    };
                }
            } else {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    if ((isset($decoded['status']) && (string) $decoded['status'] === '0') || isset($decoded['mt_id'])) {
                        $success = true;
                    } else {
                        $error = $decoded['error'] ?? $decoded['message'] ?? $body;
                    }
                } else {
                    if (stripos($body, 'OK') !== false || stripos($body, 'SUCCESS') !== false || str_starts_with($body, '639')) {
                        $success = true;
                    } else {
                        $error = $body;
                    }
                }
            }

            SmsLog::create([
                'phone'        => $clean,
                'message'      => $message,
                'type'         => $type,
                'reference_id' => $refId,
                'status'       => $success ? 'sent' : 'failed',
                'response'     => $body . ($error ? ' (' . $error . ')' : ''),
            ]);

            error_log('OneWaySMS: ' . ($success ? 'sent' : 'failed') . ' → ' . $clean . ' Response: ' . $body);
            return ['success' => $success, 'response' => $body, 'error' => $error];

        } catch (GuzzleException $e) {
            $errorMsg = $e->getMessage();
            error_log('OneWaySMS exception: ' . $errorMsg);
            SmsLog::create([
                'phone'        => $clean,
                'message'      => $message,
                'type'         => $type,
                'reference_id' => $refId,
                'status'       => 'failed',
                'response'     => 'HTTP Exception: ' . $errorMsg,
            ]);
            return ['success' => false, 'error' => 'Connection failed: ' . $errorMsg];
        }
    }

    /**
     * Send bulk SMS to multiple recipients.
     *
     * @param  string[] $numbers Raw phone numbers.
     * @param  string   $message Message body.
     * @param  string   $type    Log type.
     * @param  int|null $refId   Optional reference ID.
     * @return array{success:bool, sent:int, failed:int, error?:string}
     */
    public function sendBulk(array $numbers, string $message, string $type = 'general', ?int $refId = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'OneWaySMS credentials not configured.'];
        }

        $cleaned = array_values(array_unique(array_filter(
            array_map([$this, 'cleanPhone'], $numbers)
        )));

        if (empty($cleaned)) {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'No valid PH mobile numbers.'];
        }

        $sent   = 0;
        $failed = 0;

        foreach ($cleaned as $num) {
            $res = $this->send($num, $message, $type, $refId);
            if ($res['success']) {
                $sent++;
            } else {
                $failed++;
            }
            usleep(100000); // 100ms pause between SMS requests
        }

        return ['success' => true, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Check OneWaySMS account balance.
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
            $url = str_contains($this->apiUrl, 'api.aspx')
                ? str_replace('api.aspx', 'api_balance.aspx', $this->apiUrl)
                : $this->apiUrl . '/balance';

            $response = $this->client->get($url, [
                'query' => [
                    'apiusername' => $this->apiUsername,
                    'apipassword' => $this->apiPassword,
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $body = trim((string) $response->getBody());
                if (is_numeric($body) && (float) $body >= 0) {
                    return ['credits' => (float) $body, 'status' => 'ok'];
                }
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                return ['credits' => $body, 'status' => 'ok'];
            }
        } catch (\Throwable $e) {
            error_log('OneWaySMS balance check failed: ' . $e->getMessage());
        }

        return null;
    }
}
