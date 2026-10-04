<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Turns text into a playback plan built from approved dataset recordings.
 *
 * The same plan drives the Resident Voice Reader and the admin Interactive
 * Voice Tester, so what staff hear in the preview is what residents hear.
 *
 * Matching is longest-first: if "madjow no masim" is recorded as a phrase it
 * plays as one clip, not as the three single-word clips. Words with no
 * approved recording come back as `missing` segments; the player reads those
 * with the device voice and labels them as an approximation, and — for
 * Manobo — they are counted so staff know what to record next.
 */
final class VoiceResolver
{
    /** @var array<string, array{map: array<string,array<string,mixed>>, max: int}> */
    private static array $cache = [];

    /**
     * Resolve text for one language.
     *
     * @return list<array{type: string, text: string, audio_url?: string, sample_id?: int, speaker?: string}>
     */
    public static function resolve(string $language, string $text): array
    {
        $index = self::index($language);
        return self::plan(VoiceText::tokens($text), $index['map'], $index['max']);
    }

    /**
     * Pure longest-match planner (no database), so it can be unit tested.
     *
     * @param list<string>                      $tokens  Normalised tokens in reading order.
     * @param array<string,array<string,mixed>> $map     normalized_text => ['audio_url'=>…, 'sample_id'=>…, 'speaker'=>…]
     * @param int                               $maxLen  Longest phrase in $map, in tokens.
     * @return list<array<string,mixed>>
     */
    public static function plan(array $tokens, array $map, int $maxLen): array
    {
        $segments = [];
        $missing  = [];
        $count    = count($tokens);
        $i        = 0;

        while ($i < $count) {
            $matched = null;
            for ($len = min($maxLen, $count - $i); $len >= 1; $len--) {
                $key = implode(' ', array_slice($tokens, $i, $len));
                if (isset($map[$key])) {
                    $matched = [$key, $len];
                    break;
                }
            }

            if ($matched === null) {
                $missing[] = $tokens[$i];
                $i++;
                continue;
            }

            if ($missing) {
                $segments[] = ['type' => 'missing', 'text' => implode(' ', $missing)];
                $missing    = [];
            }
            [$key, $len] = $matched;
            $segments[]  = ['type' => 'recorded', 'text' => $key] + $map[$key];
            $i += $len;
        }

        if ($missing) {
            $segments[] = ['type' => 'missing', 'text' => implode(' ', $missing)];
        }
        return $segments;
    }

    /**
     * Whether a plan contains at least one recorded segment.
     *
     * @param list<array<string,mixed>> $segments
     */
    public static function hasRecording(array $segments): bool
    {
        foreach ($segments as $segment) {
            if ($segment['type'] === 'recorded') {
                return true;
            }
        }
        return false;
    }

    /**
     * Unrecorded vocabulary words in a plan (missing segments split back into words).
     *
     * @param  list<array<string,mixed>> $segments
     * @return list<string>
     */
    public static function missingWords(array $segments): array
    {
        $words = [];
        foreach ($segments as $segment) {
            if ($segment['type'] !== 'missing') {
                continue;
            }
            foreach (explode(' ', (string) $segment['text']) as $word) {
                if (VoiceText::isWord($word)) {
                    $words[] = $word;
                }
            }
        }
        return $words;
    }

    /**
     * Approved, playable recordings for a language keyed by normalised text.
     * Newest approved recording wins when the same text was recorded twice.
     *
     * @return array{map: array<string,array<string,mixed>>, max: int}
     */
    public static function index(string $language): array
    {
        if (isset(self::$cache[$language])) {
            return self::$cache[$language];
        }

        $map = [];
        $max = 1;
        try {
            $stmt = db()->prepare(
                "SELECT vs.id, vs.normalized_text, vs.audio_url, vs.speaker_label,
                        CASE WHEN vsa.sample_id IS NULL THEN 0 ELSE 1 END AS has_blob
                   FROM voice_samples vs
                   LEFT JOIN voice_sample_audio vsa ON vsa.sample_id = vs.id
                  WHERE vs.language = ? AND LOWER(vs.status) = 'approved'
                  ORDER BY vs.id ASC"
            );
            $stmt->execute([$language]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $url = self::playableUrl($row);
                $key = VoiceText::normalize((string) $row['normalized_text']);
                if ($url === null || $key === '') {
                    continue;   // broken audio never counts as a recording
                }
                $map[$key] = [
                    'audio_url' => $url,
                    'sample_id' => (int) $row['id'],
                    'speaker'   => (string) ($row['speaker_label'] ?? ''),
                ];
                $max = max($max, substr_count($key, ' ') + 1);
            }
        } catch (\Throwable $e) {
            error_log('[VoiceResolver::index] ' . $e->getMessage());
        }

        return self::$cache[$language] = ['map' => $map, 'max' => $max];
    }

    /** Forget cached indexes (after a sample is approved, deleted, …). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Absolute URL for a sample row's audio, or null when the audio is gone.
     *
     * New samples are stored in the database (has_blob). Samples saved before
     * that change point at public/uploads/…; those only count while the file
     * still exists on this server's disk.
     *
     * @param array<string,mixed> $row  Needs id, audio_url, has_blob.
     */
    public static function playableUrl(array $row): ?string
    {
        if (!empty($row['has_blob'])) {
            return asset(VoiceAudioStore::urlPath((int) $row['id']));
        }

        $url = (string) ($row['audio_url'] ?? '');
        if ($url === '' || str_starts_with($url, 'voice/audio/')) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = preg_replace('#^/?(?:BarangGabay/)?(?:public/)?#i', '', $path) ?? '';
        $file = dirname(__DIR__, 2) . '/public/' . $path;
        return ($path !== '' && is_file($file)) ? asset($path) : null;
    }
}
