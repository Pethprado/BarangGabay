<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SmsLog;
use GuzzleHttp\Client;

/**
 * MoceanAPI SMS gateway (https://moceanapi.com).
 *
 *   POST https://rest.moceanapi.com/rest/2/sms
 *   Authorization: Bearer <MOCEAN_API_TOKEN>        (current Mocean accounts)
 *   — or —  mocean-api-key / mocean-api-secret form fields (older accounts)
 *   form: mocean-from, mocean-to (up to 500, comma separated), mocean-text,
 *         mocean-resp-format=JSON
 *   reply: {"messages":[{"status":0,"receiver":"639…","msgid":"…"}]}
 *
 * status 0 = accepted; any other status is an error (codes below). Every send
 * is written to sms_logs, the same table the admin SMS page reads. Credentials
 * come only from the server environment and never reach the browser. Never
 * throws to callers: a gateway problem must not stop a post being published.
 */
class MoceanSmsService implements SmsSender
{
    public const API_BASE = 'https://rest.moceanapi.com/rest/2';

    /** Mocean status codes worth explaining to a barangay admin. */
    private const ERRORS = [
        1  => 'Authorization failed: check MOCEAN_API_TOKEN (or MOCEAN_API_KEY / MOCEAN_API_SECRET).',
        2  => 'Not enough Mocean credit. Top up or claim the free trial credit in your Mocean dashboard.',
        4  => 'This number is whitelisted-only on your account (trial accounts can only text verified numbers).',
        5  => 'This number is blacklisted on your Mocean account.',
        6  => 'No destination number was given.',
        8  => 'Sender ID not found or not approved on your Mocean account.',
        23 => 'The message text is empty.',
        27 => 'Too many receivers in one request.',
        28 => 'Invalid destination number.',
        29 => 'The message is too long.',
        32 => 'Mocean is throttling messages; try again shortly.',
        45 => 'Mocean system error; try again.',
    ];

    private Client $client;
    private string $token;
    private string $apiKey;
    private string $apiSecret;
    private string $senderId;
    private bool   $testMode;

    public function __construct(?Client $client = null, ?array $config = null)
    {
        $env = static fn (string $k, string $d = ''): string => trim((string) ($config[$k] ?? $_ENV[$k] ?? (getenv($k) ?: $d)));

        $this->token     = $env('MOCEAN_API_TOKEN');
        $this->apiKey    = $env('MOCEAN_API_KEY');
        $this->apiSecret = $env('MOCEAN_API_SECRET');
        $this->senderId  = $env('MOCEAN_SENDER_ID', '');

        // No token in the server environment: use the one an admin saved on
        // the SMS page (encrypted in the settings table, like PayMongo keys),
        // so the barangay never has to edit Render's environment by hand.
        if ($config === null) {
            if ($this->token === '' && $this->apiKey === '') {
                $this->token = self::storedToken();
            }
            if ($this->senderId === '') {
                $this->senderId = self::storedSetting(self::SETTING_SENDER);
            }
        }
        if ($this->senderId === '') {
            $this->senderId = 'BarangGabay';
        }
        $this->testMode  = filter_var($env('MOCEAN_TEST_MODE', 'false'), FILTER_VALIDATE_BOOLEAN);
        $this->client    = $client ?? new Client(['timeout' => 20, 'http_errors' => false]);
    }

    public const SETTING_TOKEN  = 'mocean_api_token';
    public const SETTING_SENDER = 'mocean_sender_id';

    public function providerName(): string
    {
        return 'MoceanAPI';
    }

    /** 'env' (server environment), 'stored' (saved on the SMS page) or ''. */
    public static function tokenSource(): string
    {
        foreach (['MOCEAN_API_TOKEN', 'MOCEAN_API_KEY'] as $k) {
            $v = $_ENV[$k] ?? getenv($k);
            if (is_string($v) && trim($v) !== '') {
                return 'env';
            }
        }
        return self::storedToken() !== '' ? 'stored' : '';
    }

    private static function storedSetting(string $key): string
    {
        try {
            return trim((string) \App\Models\Setting::get($key, ''));
        } catch (\Throwable $e) {
            return '';   // no database (CLI without .env, tests)
        }
    }

    private static function storedToken(): string
    {
        $stored = self::storedSetting(self::SETTING_TOKEN);
        return $stored === '' ? '' : SecretBox::decrypt($stored);
    }

    /**
     * Save a token entered on the SMS page — only after Mocean accepts it.
     *
     * @return string|null an error to show, or null when saved
     */
    public static function saveToken(string $token, string $senderId, int $updatedBy, ?Client $client = null): ?string
    {
        $token    = trim($token);
        $senderId = trim($senderId);

        if (!preg_match('/^apit-[A-Za-z0-9_\-]{10,}$/', $token)) {
            return 'That does not look like a Mocean API token (it starts with "apit-").';
        }
        if ($senderId !== '' && !preg_match('/^[A-Za-z0-9 ]{1,11}$/', $senderId)) {
            return 'Sender name: up to 11 letters or numbers.';
        }
        if (!SecretBox::available()) {
            return 'The server has no secret (JWT_SECRET) to encrypt the token with.';
        }

        // Check it with Mocean before saving: a typo should fail here, not
        // silently on the next announcement.
        $probe   = new self($client, ['MOCEAN_API_TOKEN' => $token]);
        $balance = $probe->getBalance();
        if ($balance === null || ($balance['status'] ?? '') !== 'ok') {
            return 'Mocean did not accept this token' . (isset($balance['error']) ? ': ' . $balance['error'] : '. Check it and try again.');
        }

        $updatedBy = $updatedBy > 0 ? $updatedBy : null;   // settings.updated_by references users
        \App\Models\Setting::set(self::SETTING_TOKEN, SecretBox::encrypt($token), $updatedBy);
        if ($senderId !== '') {
            \App\Models\Setting::set(self::SETTING_SENDER, $senderId, $updatedBy);
        }

        return null;
    }

    /** Forget the token saved on the SMS page. */
    public static function clearStoredToken(int $updatedBy): void
    {
        \App\Models\Setting::set(self::SETTING_TOKEN, '', $updatedBy > 0 ? $updatedBy : null);
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' || ($this->apiKey !== '' && $this->apiSecret !== '');
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
     * 09XXXXXXXXX / 9XXXXXXXXX / +639XXXXXXXXX / 639XXXXXXXXX → 639XXXXXXXXX
     * (Mocean wants the number with its country code and no "+").
     */
    public function cleanPhone(string $number): ?string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return match (true) {
            strlen($digits) === 11 && str_starts_with($digits, '09')   => '63' . substr($digits, 1),
            strlen($digits) === 10 && str_starts_with($digits, '9')    => '63' . $digits,
            strlen($digits) === 12 && str_starts_with($digits, '639')  => $digits,
            strlen($digits) === 13 && str_starts_with($digits, '6309') => '639' . substr($digits, 4),
            default                                                     => null,
        };
    }

    public function send(string $number, string $message, string $type = 'general', ?int $refId = null): array
    {
        $res = $this->sendBulk([$number], $message, $type, $refId);

        return [
            'success'   => $res['sent'] > 0,
            'response'  => $res['response'] ?? null,
            'error'     => $res['sent'] > 0 ? null : ($res['error'] ?? 'SMS was not sent.'),
            'test_mode' => $this->testMode,
        ];
    }

    /**
     * One request per 500 numbers (Mocean's limit); each receiver is logged
     * with its own result.
     */
    public function sendBulk(array $numbers, string $message, string $type = 'general', ?int $refId = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'MoceanAPI is not configured (set MOCEAN_API_TOKEN).'];
        }

        $clean = array_values(array_unique(array_filter(array_map([$this, 'cleanPhone'], $numbers))));
        if ($clean === []) {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'No valid Philippine mobile number.'];
        }

        // Long messages go out as one joined SMS (Mocean splits them; the
        // phone shows one message). The SMS screens show the credit cost
        // first; PostSms::MAX_LENGTH is the ceiling.
        $message = mb_substr(trim($message), 0, PostSms::MAX_LENGTH);
        if ($message === '') {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'error' => 'The message is empty.'];
        }

        if ($this->testMode) {
            foreach ($clean as $num) {
                SmsLog::create([
                    'phone' => $num, 'message' => $message, 'type' => $type, 'reference_id' => $refId,
                    'status' => 'sent', 'response' => '[TEST MODE — MoceanAPI simulated send]',
                ]);
            }
            return ['success' => true, 'sent' => count($clean), 'failed' => 0, 'test_mode' => true];
        }

        $sent = 0;
        $failed = 0;
        $lastError = null;
        $lastBody = null;

        foreach (array_chunk($clean, 500) as $chunk) {
            $form = [
                'mocean-from'        => $this->senderId,
                'mocean-to'          => implode(',', $chunk),
                'mocean-text'        => $message,
                'mocean-resp-format' => 'JSON',
            ];
            if (!PostSms::isGsm($message)) {
                $form['mocean-coding'] = '3';   // UCS-2, so emoji and dashes arrive intact
            }
            $options = ['form_params' => $form];
            if ($this->token !== '') {
                $options['headers'] = ['Authorization' => 'Bearer ' . $this->token];
            } else {
                $options['form_params'] += ['mocean-api-key' => $this->apiKey, 'mocean-api-secret' => $this->apiSecret];
            }

            try {
                $response = $this->client->post(self::API_BASE . '/sms', $options);
                $body     = (string) $response->getBody();
                $lastBody = $body;
                $decoded  = json_decode($body, true);
                $results  = is_array($decoded['messages'] ?? null) ? $decoded['messages'] : [];
            } catch (\Throwable $e) {
                $body    = 'HTTP exception: ' . $e->getMessage();
                $results = [];
                error_log('[MoceanSms] ' . $body);
            }

            // Map each receiver to its result. A request-level failure (e.g.
            // status 1, authorization) comes back as one entry with no
            // receiver: it applies to every number in the chunk.
            $byReceiver = [];
            $global     = null;
            foreach ($results as $r) {
                if (!empty($r['receiver'])) {
                    $byReceiver[(string) $r['receiver']] = $r;
                } else {
                    $global = $r;
                }
            }

            foreach ($chunk as $num) {
                $r      = $byReceiver[$num] ?? $global;
                $status = isset($r['status']) ? (int) $r['status'] : -1;
                $ok     = $r !== null && $status === 0;
                $error  = $ok ? null : $this->explain($status, (string) ($r['err_msg'] ?? ''), $body);

                SmsLog::create([
                    'phone' => $num, 'message' => $message, 'type' => $type, 'reference_id' => $refId,
                    'status'   => $ok ? 'sent' : 'failed',
                    'response' => mb_substr($ok ? ('MoceanAPI msgid ' . ($r['msgid'] ?? '?')) : ('MoceanAPI: ' . $error), 0, 1000),
                ]);

                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                    $lastError = $error;
                }
            }
        }

        return ['success' => $sent > 0, 'sent' => $sent, 'failed' => $failed, 'error' => $lastError, 'response' => $lastBody];
    }

    /** Remaining Mocean credit, or null when it cannot be read. */
    public function getBalance(): ?array
    {
        if ($this->testMode) {
            return ['status' => 'test_mode', 'credits' => 'N/A (Test Mode)'];
        }
        if (!$this->isConfigured()) {
            return null;
        }

        $query   = ['mocean-resp-format' => 'JSON'];
        $options = [];
        if ($this->token !== '') {
            $options['headers'] = ['Authorization' => 'Bearer ' . $this->token];
        } else {
            $query += ['mocean-api-key' => $this->apiKey, 'mocean-api-secret' => $this->apiSecret];
        }
        $options['query'] = $query;

        try {
            $response = $this->client->get(self::API_BASE . '/account/balance', $options);
            $decoded  = json_decode((string) $response->getBody(), true);
            if (!is_array($decoded)) {
                return null;
            }
            if ((int) ($decoded['status'] ?? 0) !== 0) {
                return ['status' => 'error', 'error' => $this->explain((int) $decoded['status'], (string) ($decoded['err_msg'] ?? ''), '')];
            }
            return ['status' => 'ok', 'credits' => $decoded['value'] ?? $decoded['balance'] ?? $decoded, 'provider' => 'MoceanAPI'];
        } catch (\Throwable $e) {
            error_log('[MoceanSms] balance: ' . $e->getMessage());
            return null;
        }
    }

    private function explain(int $status, string $errMsg, string $body): string
    {
        if (isset(self::ERRORS[$status])) {
            return self::ERRORS[$status] . ($errMsg !== '' ? " ({$errMsg})" : '');
        }
        if ($errMsg !== '') {
            return "Mocean error {$status}: {$errMsg}";
        }

        return 'Unexpected reply from MoceanAPI: ' . mb_substr($body, 0, 200);
    }
}
