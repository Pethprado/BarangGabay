<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Backup machine translator: Google Translate's free, keyless web endpoint.
 *
 * MyMemory stays the first choice everywhere. This is only asked when
 * MyMemory refuses or fails — which on Render is mostly its daily quota,
 * shared by every app on the same outbound IP. Without a backup, a post saved
 * after the quota runs out gets no English/Filipino version, and its Manobo
 * keeps Tagalog words where Bisaya should be.
 *
 * Covers en, tl (Filipino) and ceb (Bisaya/Cebuano). Never throws; null on
 * any failure. Off in the test suite (tests mock the HTTP client and count
 * its calls) and whenever GOOGLE_FREE_TRANSLATE=false.
 */
class GoogleFreeTranslate
{
    /** Short: this runs inside a staff member's save request. */
    private const TIMEOUT = 6;

    public static function enabled(): bool
    {
        if ((string) env('APP_ENV', '') === 'testing') {
            return false;
        }
        $flag = strtolower((string) env('GOOGLE_FREE_TRANSLATE', 'true'));

        return !in_array($flag, ['0', 'false', 'off', 'no'], true);
    }

    /**
     * @param string $from 'en' | 'tl' | 'fil' | 'ceb'
     * @param string $to   'en' | 'tl' | 'fil' | 'ceb'
     */
    public static function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '' || !self::enabled()) {
            return null;
        }
        $from = $from === 'fil' ? 'tl' : $from;
        $to   = $to === 'fil' ? 'tl' : $to;

        try {
            $client = new \GuzzleHttp\Client([
                'timeout'         => self::TIMEOUT,
                'connect_timeout' => 3,
                'http_errors'     => false,
            ]);
            $res = $client->post('https://translate.googleapis.com/translate_a/single', [
                'query'       => ['client' => 'gtx', 'sl' => $from, 'tl' => $to, 'dt' => 't'],
                'form_params' => ['q' => $text],
            ]);
            if ($res->getStatusCode() !== 200) {
                error_log('[GoogleFreeTranslate] HTTP ' . $res->getStatusCode() . " {$from}->{$to}");
                return null;
            }

            $data = json_decode((string) $res->getBody(), true);
            if (!is_array($data[0] ?? null)) {
                return null;
            }
            $out = '';
            foreach ($data[0] as $segment) {
                $out .= (string) ($segment[0] ?? '');
            }
            $out = trim($out);

            return $out === '' ? null : $out;
        } catch (\Throwable $e) {
            error_log('[GoogleFreeTranslate] ' . $e->getMessage());
            return null;
        }
    }
}
