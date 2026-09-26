<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\TranslationAttempt;
use PDO;

/**
 * One answer to "is anything missing?", for the screen and the console.
 *
 * Both the admin health page and tools/check-translations.php read from
 * here. That is the point: a defence-day check that disagrees with the
 * page the panel is looking at is worse than having neither, and two
 * queries written separately will eventually disagree.
 *
 * ── Where the truth lives ───────────────────────────────────────────────
 *
 * Whether a language is MISSING is read from the content tables, through
 * TranslationService::statusFor() — the same function the admin badges and
 * the resident notice use. Not from translation_attempts: a post published
 * before any of this existed has no attempt row at all, and taking the
 * attempts table as the index of what exists would report those posts as
 * complete when they are the ones most likely to be broken.
 *
 * The attempts table answers the second question — WHY — and only that.
 */
class TranslationHealth
{
    /** Content types, and the column their body lives in. */
    private const TYPES = [
        'announcement' => ['table' => 'announcements', 'body' => 'body'],
        'event'        => ['table' => 'events',        'body' => 'description'],
        'ordinance'    => ['table' => 'ordinances',    'body' => 'description'],
    ];

    /**
     * How many posts to examine per type.
     *
     * Bounded because this renders a page and runs on a shared host. Newest
     * first: a notice from this week is the one somebody is waiting on, and
     * a gap in a 2019 ordinance is not what anyone opens this page for.
     */
    public const SCAN_LIMIT = 200;

    /**
     * Which providers can be used right now, and what to set if not.
     *
     * At the top of the page because every other line on it may trace back
     * to one of these three being absent — and because "Manobo needs
     * ANTHROPIC_API_KEY" is a two-minute fix that nobody made for weeks
     * only because nothing said it.
     *
     * @return list<array{name:string, label:string, ready:bool,
     *                    envKey:?string, reason:?string}>
     */
    public static function providers(): array
    {
        $keySet    = !empty(env('ANTHROPIC_API_KEY', ''));
        $outOfCash = TranslationPlanner::looksOutOfCredits($keySet);

        $ttsConfig   = require \dirname(__DIR__, 2) . '/config/tts.php';
        $ttsProvider = (string) ($ttsConfig['provider'] ?? '');
        $ttsReady    = $ttsProvider !== '' && trim((string) ($ttsConfig['api_key'] ?? '')) !== '';

        $freeOn = (int) setting('free_translation_enabled', 1) === 1;

        return [
            [
                'name'   => 'free_translator',
                'label'  => 'MyMemory',
                'ready'  => $freeOn,
                'envKey' => null,
                'reason' => $freeOn ? null : TranslationOutcome::PROVIDER_DISABLED,
            ],
            [
                'name'   => 'anthropic',
                'label'  => 'Anthropic',
                'ready'  => $keySet && !$outOfCash,
                'envKey' => 'ANTHROPIC_API_KEY',
                'reason' => !$keySet
                    ? TranslationOutcome::NO_API_KEY
                    : ($outOfCash ? TranslationOutcome::NO_CREDITS : null),
            ],
            [
                'name'   => 'tts',
                'label'  => $ttsProvider !== '' ? $ttsProvider : 'Text to speech',
                'ready'  => $ttsReady,
                'envKey' => 'TTS_PROVIDER',
                'reason' => $ttsReady ? null : TranslationOutcome::NO_TTS_PROVIDER,
            ],
        ];
    }

    /**
     * Posts with a language missing, and why.
     *
     * @param string|null $onlyLang Restrict to one language, for the page's filter.
     * @return list<array{
     *   type:string, id:int, title:string, source:string,
     *   missing:list<string>, machine:list<string>,
     *   reasons:array<string,array{code:string, message:string, fix:string, envKey:?string}>,
     *   audioMissing:list<string>
     * }>
     */
    public static function incomplete(?string $onlyLang = null, int $limit = self::SCAN_LIMIT): array
    {
        $out = [];

        foreach (self::TYPES as $type => $meta) {
            foreach (self::scan($type, $meta, $limit) as $row) {
                $status = TranslationService::statusFor($row, $meta['body']);

                $missing = [];
                $machine = [];

                foreach (['fil' => 'fil', 'en' => 'en', 'msm' => 'manobo'] as $lang => $key) {
                    if ($status['source'] === $key) {
                        continue;                       // the original is never missing
                    }
                    if (!$status[$key]['has']) {
                        $missing[] = $lang;
                    } elseif ($status[$key]['machine']) {
                        $machine[] = $lang;
                    }
                }

                $attempts     = TranslationAttempt::forContent($type, (int) $row['id']);
                $audioMissing = self::audioGaps($status, $attempts);

                if ($onlyLang !== null) {
                    $missing      = \in_array($onlyLang, $missing, true) ? [$onlyLang] : [];
                    $machine      = \in_array($onlyLang, $machine, true) ? [$onlyLang] : [];
                    $audioMissing = \in_array($onlyLang, $audioMissing, true) ? [$onlyLang] : [];
                }

                if ($missing === [] && $audioMissing === []) {
                    continue;                           // nothing to report
                }

                $out[] = [
                    'type'         => $type,
                    'id'           => (int) $row['id'],
                    'title'        => (string) ($row['title'] ?? ''),
                    'source'       => $status['source'],
                    'missing'      => $missing,
                    'machine'      => $machine,
                    'audioMissing' => $audioMissing,
                    'reasons'      => self::reasonsFor($attempts, array_merge($missing, $audioMissing)),
                ];
            }
        }

        return $out;
    }

    /**
     * Languages whose TEXT exists but whose audio does not.
     *
     * A language with no text is not reported as missing audio — there is
     * nothing to read, and listing it would put the same post on the page
     * twice for one underlying gap. Fixing the text is what fixes both.
     *
     * @param array<string,mixed> $status
     * @param array<string,mixed> $attempts
     * @return list<string>
     */
    private static function audioGaps(array $status, array $attempts): array
    {
        $gaps = [];

        foreach (['fil' => 'fil', 'en' => 'en', 'msm' => 'manobo'] as $lang => $key) {
            $hasText = $status['source'] === $key || $status[$key]['has'];
            if (!$hasText) {
                continue;
            }

            $audio = $attempts[$lang . '.audio'] ?? null;
            if ($audio === null || (int) $audio['ok'] !== 1) {
                $gaps[] = $lang;
            }
        }

        return $gaps;
    }

    /**
     * The recorded reason per language, ready to print.
     *
     * @param array<string,mixed> $attempts
     * @param list<string>        $langs
     * @return array<string,array{code:string, message:string, fix:string, envKey:?string}>
     */
    private static function reasonsFor(array $attempts, array $langs): array
    {
        $out = [];

        foreach (array_unique($langs) as $lang) {
            // Text first: a language with no text explains its missing audio
            // too, and reporting the audio reason would bury the real cause.
            $row = $attempts[$lang . '.text'] ?? $attempts[$lang . '.audio'] ?? null;

            if ($row === null || (int) $row['ok'] === 1) {
                continue;
            }

            $code = (string) $row['reason_code'];
            $out[$lang] = [
                'code'    => $code,
                'message' => t(TranslationOutcome::messageKey($code)),
                'fix'     => t(TranslationOutcome::fixKey($code)),
                'envKey'  => TranslationOutcome::envKey($code),
            ];
        }

        return $out;
    }

    /**
     * @param array{table:string, body:string} $meta
     * @return list<array<string,mixed>>
     */
    private static function scan(string $type, array $meta, int $limit): array
    {
        $limit = \max(1, \min(1000, $limit));
        $body  = $meta['body'];

        try {
            return db()->query(
                "SELECT id, title, source_lang,
                        title_en,     {$body}_en,
                        title_fil,    {$body}_fil,
                        title_manobo, {$body}_manobo,
                        en_is_auto, fil_is_auto, manobo_is_auto
                   FROM {$meta['table']}
                  ORDER BY id DESC
                  LIMIT {$limit}"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            \error_log("[TranslationHealth::scan] {$type}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Headline counts, for the top of the page and the console summary.
     *
     * @return array{posts:int, missingText:int, missingAudio:int,
     *               byLang:array<string,int>, retryable:int}
     */
    public static function overview(?int $limit = null): array
    {
        $rows = self::incomplete(null, $limit ?? self::SCAN_LIMIT);

        $byLang       = ['fil' => 0, 'en' => 0, 'msm' => 0];
        $missingText  = 0;
        $missingAudio = 0;

        foreach ($rows as $row) {
            $missingText  += \count($row['missing']);
            $missingAudio += \count($row['audioMissing']);

            foreach ($row['missing'] as $lang) {
                $byLang[$lang] = ($byLang[$lang] ?? 0) + 1;
            }
        }

        $retryable = 0;
        try {
            $retryable = (int) db()->query(
                'SELECT COUNT(*) FROM translation_attempts
                  WHERE ok = 0 AND orphaned_at IS NULL AND retry_after IS NOT NULL'
            )->fetchColumn();
        } catch (\Throwable $e) {
            \error_log('[TranslationHealth::overview] ' . $e->getMessage());
        }

        return [
            'posts'        => \count($rows),
            'missingText'  => $missingText,
            'missingAudio' => $missingAudio,
            'byLang'       => $byLang,
            'retryable'    => $retryable,
        ];
    }
}
