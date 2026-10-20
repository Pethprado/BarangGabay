<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * The recording script for the Voice Training hub: every post residents can
 * see, sentence by sentence and word by word (numbers included), exactly as
 * the Voice Reader will speak it, with what is already recorded.
 *
 * Sentences come from PostScript (the reader's own chunks), so recording a
 * sentence as one clip makes the reader play it whole — the clearest reading.
 * Words cover every sentence nobody has recorded whole. In Manobo text a word
 * that is a Bisaya-only headword is listed (and recorded) as Bisaya, the same
 * split the reader uses.
 */
final class VoiceScript
{
    public const LOCALES = ['msm', 'ceb', 'fil', 'en'];

    private const TYPES = [
        'announcement' => "SELECT * FROM announcements WHERE status = 'published' AND (published_at IS NULL OR published_at <= NOW()) ORDER BY published_at DESC, id DESC",
        'event'        => "SELECT * FROM events WHERE status <> 'cancelled' ORDER BY event_date DESC, id DESC",
        'ordinance'    => "SELECT * FROM ordinances WHERE status IN ('active','repealed') ORDER BY id DESC",
    ];

    /**
     * @return array{
     *   locale: string,
     *   posts: list<array{type:string,id:int,title:string,sentences:list<array<string,mixed>>,words:list<array<string,mixed>>,missing:int}>,
     *   words: list<array{word:string,language:string,count:int,recorded:bool,is_number:bool}>,
     *   totals: array<string,int>
     * }
     */
    public static function build(string $locale): array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'msm';
        if ($locale === 'ceb') {
            return self::bisaya(self::build('msm'));
        }
        $maps   = ['msm' => [], 'ceb' => [], 'fil' => [], 'en' => []];
        foreach (array_keys($maps) as $lang) {
            $maps[$lang] = VoiceResolver::index($lang)['map'];
        }

        $posts = [];
        $all   = [];
        foreach (self::TYPES as $type => $sql) {
            try {
                $rows = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                error_log('[VoiceScript] ' . $type . ': ' . $e->getMessage());
                continue;
            }
            foreach ($rows as $row) {
                $entry = self::postEntry($type, $row, $locale, $maps, $all);
                if ($entry !== null) {
                    $posts[] = $entry;
                }
            }
        }

        $all = array_values($all);
        usort($all, static fn (array $a, array $b): int =>
            [$a['recorded'], -$a['count'], $a['word']] <=> [$b['recorded'], -$b['count'], $b['word']]);

        $sentenceList = array_merge(...array_map(static fn (array $p): array => $p['sentences'], $posts ?: [['sentences' => []]]));
        return [
            'locale' => $locale,
            'posts'  => $posts,
            'words'  => $all,
            'totals' => [
                'posts'              => count($posts),
                'words'              => count($all),
                'words_recorded'     => count(array_filter($all, static fn (array $w): bool => $w['recorded'])),
                'numbers'            => count(array_filter($all, static fn (array $w): bool => $w['is_number'])),
                'sentences'          => count($sentenceList),
                'sentences_whole'    => count(array_filter($sentenceList, static fn (array $s): bool => $s['whole'])),
                'sentences_covered'  => count(array_filter($sentenceList, static fn (array $s): bool => $s['covered'])),
            ],
        ];
    }

    /**
     * Voice coverage of ONE post, for the note shown right after it is saved.
     * Null when the post has no text in that language yet (the Manobo
     * translation is still being prepared).
     *
     * @return array{type:string,id:int,title:string,words:int,recorded:int,missing:int,sentences:int,sentences_ready:int}|null
     */
    public static function forPost(string $type, int $id, string $locale = 'msm'): ?array
    {
        $tables = ['announcement' => 'announcements', 'event' => 'events', 'ordinance' => 'ordinances'];
        if (!isset($tables[$type])) {
            return null;
        }
        $stmt = db()->prepare("SELECT * FROM {$tables[$type]} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $maps = [];
        foreach (['msm', 'ceb', 'fil', 'en'] as $lang) {
            $maps[$lang] = VoiceResolver::index($lang)['map'];
        }
        $all   = [];
        $entry = self::postEntry($type, $row, $locale, $maps, $all);
        if ($entry === null) {
            return null;
        }
        return [
            'type'            => $type,
            'id'              => $id,
            'title'           => $entry['title'],
            'words'           => count($entry['words']),
            'recorded'        => count($entry['words']) - $entry['missing'],
            'missing'         => $entry['missing'],
            'sentences'       => count($entry['sentences']),
            'sentences_ready' => count(array_filter($entry['sentences'], static fn (array $x): bool => $x['covered'])),
        ];
    }

    /**
     * One post's sentences and words with what is recorded; adds its words
     * to $all (the cross-post word list). Null when it has no text in $locale.
     *
     * @param array<string,array<string,array<string,mixed>>> $maps
     * @param array<string,array<string,mixed>>               $all
     * @return array<string,mixed>|null
     */
    private static function postEntry(string $type, array $row, string $locale, array $maps, array &$all): ?array
    {
        try {
            $script = PostScript::build($type, $row, $locale);
        } catch (\Throwable $e) {
            return null;
        }
        if (empty($script['available'])) {
            return null;
        }

        $sentences = [];
        $words     = [];
        foreach ($script['chunks'] as $chunk) {
            $say    = trim((string) ($chunk['say'] ?? ''));
            $tokens = array_values(array_filter(VoiceText::tokens($say), [self::class, 'speakable']));
            if ($tokens === []) {
                continue;
            }
            $key     = implode(' ', $tokens);
            $whole   = isset($maps[$locale][$key]);
            $have    = 0;
            foreach ($tokens as $token) {
                $lang     = self::wordLanguage($locale, $token);
                $recorded = isset($maps[$lang][$token]) || ($locale === 'msm' && isset($maps['msm'][$token]));
                $have    += $recorded ? 1 : 0;
                $words[$lang . '|' . $token] ??= ['word' => $token, 'language' => $lang, 'recorded' => $recorded, 'is_number' => ctype_digit($token)];
                $all[$lang . '|' . $token] ??= ['word' => $token, 'language' => $lang, 'count' => 0, 'recorded' => $recorded, 'is_number' => ctype_digit($token)];
                $all[$lang . '|' . $token]['count']++;
            }
            // The plan the reader would make: covered when every word
            // (or a longer recorded phrase) has a recording.
            $covered = $whole || self::covered($locale, $tokens);
            $sentences[] = [
                'text'     => $say,
                'words'    => count($tokens),
                'recorded' => $have,
                'whole'    => $whole,
                'covered'  => $covered,
            ];
        }
        if ($sentences === []) {
            return null;
        }
        $words = array_values($words);
        return [
            'type'      => $type,
            'id'        => (int) $row['id'],
            'title'     => (string) ($script['title'] ?: ($row['title'] ?? '')),
            'sentences' => $sentences,
            'words'     => $words,
            'missing'   => count(array_filter($words, static fn (array $w): bool => !$w['recorded'])),
        ];
    }

    /**
     * Bisaya view. Posts have no Bisaya version: Bisaya words live inside the
     * Manobo reader text where the translator used Bisaya, and the reader
     * plays them from Bisaya recordings. So this lists those words (each
     * recorded as Bisaya), with the Manobo sentences they appear in for
     * context. Whole sentences are recorded in the Manobo tab.
     *
     * @param array<string,mixed> $msm
     * @return array<string,mixed>
     */
    private static function bisaya(array $msm): array
    {
        $isCeb = static fn (array $w): bool => $w['language'] === 'ceb';
        $posts = [];
        foreach ($msm['posts'] as $post) {
            $words = array_values(array_filter($post['words'], $isCeb));
            if ($words === []) {
                continue;
            }
            $post['words']   = $words;
            $post['missing'] = count(array_filter($words, static fn (array $w): bool => !$w['recorded']));
            $posts[] = $post;
        }
        $all = array_values(array_filter($msm['words'], $isCeb));
        return [
            'locale'      => 'ceb',
            'words_only'  => true,
            'posts'       => $posts,
            'words'       => $all,
            'totals'      => [
                'posts'             => count($posts),
                'words'             => count($all),
                'words_recorded'    => count(array_filter($all, static fn (array $w): bool => $w['recorded'])),
                'numbers'           => 0,
                'sentences'         => array_sum(array_map(static fn (array $p): int => count($p['sentences']), $posts)),
                'sentences_whole'   => 0,
                'sentences_covered' => 0,
            ],
        ];
    }

    /** A token the reader can say: a word or a number (not a URL piece). */
    public static function speakable(string $token): bool
    {
        if (ctype_digit($token)) {
            return strlen($token) <= 12;
        }
        return VoiceUsageIndex::isTrackable($token);
    }

    private static function wordLanguage(string $locale, string $token): string
    {
        if ($locale !== 'msm' || ctype_digit($token)) {
            return $locale;
        }
        return VoiceUsageIndex::classifyManoboToken($token);
    }

    /** Covered when the reader's own plan for the sentence has no missing part. */
    private static function covered(string $locale, array $tokens): bool
    {
        foreach (VoiceResolver::resolve($locale, implode(' ', $tokens)) as $segment) {
            if ($segment['type'] !== 'recorded') {
                return false;
            }
        }
        return true;
    }
}
