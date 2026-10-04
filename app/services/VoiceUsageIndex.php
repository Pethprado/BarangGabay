<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Which words residents actually hear, per language, so recording effort goes
 * to the words they meet first.
 *
 * - Whenever a post (or one of its translations) is saved, indexPost()
 *   replaces that post's word counts. It reads the exact text the Voice
 *   Reader speaks for each language (PostScript), so the queue and the
 *   reader never disagree.
 * - Bisaya: posts have no Bisaya version; Bisaya words appear inside the
 *   Manobo text where the translator fell back to Bisaya. A token in the
 *   Manobo text that is a Bisaya dictionary headword and NOT a Manobo one is
 *   indexed — and recorded — as Bisaya ('ceb').
 * - When the reader meets a word with no recording, recordReaderMisses()
 *   counts it (content_type 'reader').
 * - rebuild() re-indexes every post: "Rescan Published Content".
 *
 * Drafts are indexed too; queries only count posts residents can see, so a
 * draft's words show up the moment it is published.
 */
final class VoiceUsageIndex
{
    /** Kept for callers that mean "the dictionary language". */
    public const LANGUAGE = 'msm';

    /** Every language the dataset records, in display order. */
    public const LANGUAGES = ['msm', 'en', 'fil', 'ceb'];

    /** Reader locales that have their own text on a post. */
    private const POST_LOCALES = ['msm', 'en', 'fil'];

    private const TABLES = [
        'announcement' => 'announcements',
        'event'        => 'events',
        'ordinance'    => 'ordinances',
    ];

    /** @var array<string,true>|null */
    private static ?array $manoboWords = null;
    /** @var array<string,true>|null */
    private static ?array $bisayaWords = null;

    /**
     * Re-index one post in every language (or just $onlyLanguage).
     * Never throws: indexing must not break saving a post.
     */
    public static function indexPost(string $contentType, int $contentId, ?string $onlyLanguage = null): void
    {
        if (!isset(self::TABLES[$contentType]) || $contentId <= 0) {
            return;
        }

        $pdo  = db();
        // Only own the transaction when the caller is not already inside one;
        // rolling back a caller's transaction would undo their post save.
        $owns = !$pdo->inTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM ' . self::TABLES[$contentType] . ' WHERE id = ?');
            $stmt->execute([$contentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $counts = $row ? self::countWords($contentType, $row) : [];

            if ($owns) {
                $pdo->beginTransaction();
            }
            if ($onlyLanguage !== null) {
                $pdo->prepare('DELETE FROM voice_word_usage WHERE content_type = ? AND content_id = ? AND language = ?')
                    ->execute([$contentType, $contentId, $onlyLanguage]);
            } else {
                $pdo->prepare('DELETE FROM voice_word_usage WHERE content_type = ? AND content_id = ?')
                    ->execute([$contentType, $contentId]);
            }

            $insert = $pdo->prepare(
                'INSERT INTO voice_word_usage (language, normalized_text, content_type, content_id, occurrences, last_seen_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            foreach ($counts as $language => $words) {
                if ($onlyLanguage !== null && $language !== $onlyLanguage) {
                    continue;
                }
                foreach ($words as $word => $n) {
                    $insert->execute([$language, (string) $word, $contentType, $contentId, $n]);
                }
            }
            if ($owns) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[VoiceUsageIndex::indexPost] {$contentType}#{$contentId}: " . $e->getMessage());
        }
    }

    /**
     * Back-compat entry point (older hooks passed the Manobo text directly).
     */
    public static function indexContent(string $contentType, int $contentId, string $manoboText = ''): void
    {
        self::indexPost($contentType, $contentId);
    }

    /**
     * Word counts per language for one post row.
     *
     * @param  array<string,mixed> $row
     * @return array<string, array<string,int>>
     */
    public static function countWords(string $contentType, array $row): array
    {
        $counts = [];
        foreach (self::POST_LOCALES as $locale) {
            try {
                $script = PostScript::build($contentType, $row, $locale);
            } catch (\Throwable $e) {
                continue;
            }
            if (empty($script['available'])) {
                continue;
            }
            foreach (VoiceText::tokens((string) $script['text']) as $token) {
                if (!self::isTrackable($token)) {
                    continue;
                }
                $language = $locale === 'msm' ? self::classifyManoboToken($token) : $locale;
                $counts[$language][$token] = ($counts[$language][$token] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /**
     * A token from Manobo text: 'ceb' when it is a Bisaya headword that is
     * not also a Manobo one (the translator's Bisaya fallback), else 'msm'.
     */
    public static function classifyManoboToken(string $token): string
    {
        self::loadDictionaries();
        return (isset(self::$bisayaWords[$token]) && !isset(self::$manoboWords[$token])) ? 'ceb' : 'msm';
    }

    /**
     * Words worth a recording: has a letter, fits the column, not a URL/email
     * fragment (those are split into pieces like "https" and "www").
     */
    public static function isTrackable(string $token): bool
    {
        return VoiceText::isWord($token)
            && strlen($token) <= 191
            && !in_array($token, ['http', 'https', 'www', 'com', 'ph', 'gov', 'org', 'html'], true)
            && !str_contains($token, '@');
    }

    /**
     * Count words a resident's Voice Reader had to skip or speak with the
     * device voice. Manobo words are split into Manobo/Bisaya like posts are.
     *
     * @param list<string> $words  Normalised words.
     */
    public static function recordReaderMisses(array $words, string $language = 'msm'): void
    {
        $words = array_values(array_unique(array_filter($words, [self::class, 'isTrackable'])));
        if (!$words || !in_array($language, ['msm', 'en', 'fil', 'ceb'], true)) {
            return;
        }

        try {
            $pdo    = db();
            $update = $pdo->prepare(
                "UPDATE voice_word_usage SET occurrences = occurrences + 1, last_seen_at = NOW()
                  WHERE language = ? AND normalized_text = ? AND content_type = 'reader' AND content_id = 0"
            );
            $insert = $pdo->prepare(
                "INSERT INTO voice_word_usage (language, normalized_text, content_type, content_id, occurrences, last_seen_at)
                 VALUES (?, ?, 'reader', 0, 1, NOW())"
            );
            foreach (array_slice($words, 0, 300) as $word) {
                $lang = $language === 'msm' ? self::classifyManoboToken($word) : $language;
                $update->execute([$lang, $word]);
                if ($update->rowCount() === 0) {
                    try {
                        $insert->execute([$lang, $word]);
                    } catch (\PDOException $e) {
                        // Another request inserted it first — its count stands.
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[VoiceUsageIndex::recordReaderMisses] ' . $e->getMessage());
        }
    }

    /**
     * Re-index every post (optionally one language). Returns posts indexed.
     */
    public static function rebuild(?string $onlyLanguage = null): int
    {
        if ($onlyLanguage !== null && !in_array($onlyLanguage, self::LANGUAGES, true)) {
            $onlyLanguage = null;
        }

        $indexed = 0;
        foreach (self::TABLES as $type => $table) {
            $ids = array_map('intval', db()->query("SELECT id FROM {$table}")->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($ids as $id) {
                self::indexPost($type, $id, $onlyLanguage);
                $indexed++;
            }
            // Drop rows for posts that no longer exist.
            $stmt = db()->prepare('SELECT DISTINCT content_id FROM voice_word_usage WHERE content_type = ?');
            $stmt->execute([$type]);
            $gone = array_diff(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), $ids);
            if ($gone) {
                $marks = implode(',', array_fill(0, count($gone), '?'));
                db()->prepare("DELETE FROM voice_word_usage WHERE content_type = ? AND content_id IN ({$marks})")
                    ->execute(array_merge([$type], array_values($gone)));
            }
        }
        VoiceResolver::flush();
        return $indexed;
    }

    /**
     * Words used in resident-visible posts or requested by the reader, for
     * one language, with recording status, most-used first.
     *
     * @return array{
     *   words: list<array<string,mixed>>,
     *   used_words: int, used_recorded: int, used_missing: int, usage_coverage: float
     * }
     */
    public static function report(string $language = 'msm'): array
    {
        $empty = ['words' => [], 'used_words' => 0, 'used_recorded' => 0, 'used_missing' => 0, 'usage_coverage' => 0.0];

        try {
            $visible = "SELECT 'announcement' AS ct, id, title FROM announcements
                         WHERE status = 'published' AND (published_at IS NULL OR published_at <= NOW())
                        UNION ALL
                        SELECT 'event' AS ct, id, title FROM events WHERE status <> 'cancelled'
                        UNION ALL
                        SELECT 'ordinance' AS ct, id, title FROM ordinances WHERE status IN ('active','repealed')";

            $stmt = db()->prepare(
                "SELECT u.normalized_text, u.content_type, u.content_id, u.occurrences, u.last_seen_at, v.title
                   FROM voice_word_usage u
                   LEFT JOIN ({$visible}) v ON v.ct = u.content_type AND v.id = u.content_id
                  WHERE u.language = ? AND (u.content_type = 'reader' OR v.id IS NOT NULL)"
            );
            $stmt->execute([$language]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[VoiceUsageIndex::report] ' . $e->getMessage());
            return $empty;
        }

        $recorded = VoiceResolver::index($language)['map'];
        $dict     = match ($language) {
            'msm'   => self::dictionaryByWord(),
            'ceb'   => self::bisayaByWord(),
            default => [],
        };

        $words = [];
        foreach ($rows as $row) {
            $w = (string) $row['normalized_text'];
            $words[$w] ??= [
                'word'            => $w,
                'language'        => $language,
                'translation'     => $dict[$w]['translation'] ?? '',
                'dictionary_id'   => $language === 'msm' ? ($dict[$w]['id'] ?? null) : null,
                'occurrences'     => 0,
                'reader_requests' => 0,
                'sources'         => [],
                'last_seen_at'    => null,
                'recorded'        => isset($recorded[$w]),
            ];
            if ($row['content_type'] === 'reader') {
                $words[$w]['reader_requests'] += (int) $row['occurrences'];
            } else {
                $words[$w]['occurrences'] += (int) $row['occurrences'];
                $words[$w]['sources'][] = [
                    'type'  => (string) $row['content_type'],
                    'id'    => (int) $row['content_id'],
                    'title' => (string) ($row['title'] ?? ''),
                ];
            }
            if ($words[$w]['last_seen_at'] === null || $row['last_seen_at'] > $words[$w]['last_seen_at']) {
                $words[$w]['last_seen_at'] = $row['last_seen_at'];
            }
        }

        foreach ($words as &$entry) {
            $entry['priority'] = $entry['occurrences'] + $entry['reader_requests'];
        }
        unset($entry);

        $words = array_values($words);
        usort($words, static fn (array $a, array $b): int =>
            [$a['recorded'], -$a['priority'], $a['word']] <=> [$b['recorded'], -$b['priority'], $b['word']]);

        $used      = array_filter($words, static fn (array $w): bool => $w['occurrences'] > 0);
        $usedRec   = count(array_filter($used, static fn (array $w): bool => $w['recorded']));
        $usedTotal = count($used);

        return [
            'words'          => $words,
            'used_words'     => $usedTotal,
            'used_recorded'  => $usedRec,
            'used_missing'   => $usedTotal - $usedRec,
            'usage_coverage' => $usedTotal > 0 ? round($usedRec / $usedTotal * 100, 1) : 0.0,
        ];
    }

    /**
     * Reports for every language, keyed by code.
     *
     * @return array<string, array<string,mixed>>
     */
    public static function reports(): array
    {
        $out = [];
        foreach (self::LANGUAGES as $language) {
            $out[$language] = self::report($language);
        }
        return $out;
    }

    /**
     * Live Manobo dictionary entries keyed by normalised headword.
     *
     * @return array<string, array{id: int, manobo: string, translation: string}>
     */
    public static function dictionaryByWord(): array
    {
        static $byWord = null;
        if ($byWord !== null) {
            return $byWord;
        }

        $byWord = [];
        try {
            $rows = db()->query('SELECT id, manobo, tagalog, english FROM manobo_dictionary WHERE deleted_at IS NULL ORDER BY id ASC')
                        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $key = VoiceText::normalize((string) $row['manobo']);
                if ($key !== '' && !isset($byWord[$key])) {
                    $byWord[$key] = [
                        'id'          => (int) $row['id'],
                        'manobo'      => (string) $row['manobo'],
                        'translation' => trim((string) ($row['tagalog'] ?: $row['english'])),
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[VoiceUsageIndex::dictionaryByWord] ' . $e->getMessage());
        }
        return $byWord;
    }

    /**
     * Live Bisaya dictionary entries keyed by normalised headword.
     *
     * @return array<string, array{id: int, translation: string}>
     */
    public static function bisayaByWord(): array
    {
        static $byWord = null;
        if ($byWord !== null) {
            return $byWord;
        }

        $byWord = [];
        try {
            $rows = db()->query('SELECT id, bisaya, tagalog, english FROM bisaya_dictionary WHERE deleted_at IS NULL ORDER BY id ASC')
                        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $key = VoiceText::normalize((string) $row['bisaya']);
                if ($key !== '' && !isset($byWord[$key])) {
                    $byWord[$key] = ['id' => (int) $row['id'], 'translation' => trim((string) ($row['tagalog'] ?: $row['english']))];
                }
            }
        } catch (\Throwable $e) {
            error_log('[VoiceUsageIndex::bisayaByWord] ' . $e->getMessage());
        }
        return $byWord;
    }

    /**
     * Single-word headword sets used to tell Manobo from Bisaya-fallback tokens.
     */
    private static function loadDictionaries(): void
    {
        if (self::$manoboWords !== null) {
            return;
        }
        self::$manoboWords = [];
        self::$bisayaWords = [];
        foreach (array_keys(self::dictionaryByWord()) as $key) {
            foreach (explode(' ', $key) as $t) {
                self::$manoboWords[$t] = true;
            }
        }
        foreach (array_keys(self::bisayaByWord()) as $key) {
            if (!str_contains($key, ' ')) {
                self::$bisayaWords[$key] = true;
            }
        }
    }

    /**
     * Test seam: set the headword sets directly.
     *
     * @param list<string> $manobo
     * @param list<string> $bisaya
     */
    public static function setDictionariesForTesting(array $manobo, array $bisaya): void
    {
        self::$manoboWords = array_fill_keys($manobo, true);
        self::$bisayaWords = array_fill_keys($bisaya, true);
    }
}
