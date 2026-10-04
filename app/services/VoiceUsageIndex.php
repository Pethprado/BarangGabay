<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Where Manobo words are actually used, so recording effort goes to the words
 * residents meet first.
 *
 * - Every time a post's Manobo text is saved (manually or by auto-translate),
 *   indexContent() replaces that post's word counts. No full scan per request.
 * - When the Resident Voice Reader meets a word with no recording,
 *   recordReaderMisses() counts it (content_type 'reader').
 * - rebuild() re-indexes every post: the admin's "Rescan Published Content".
 *
 * Words are indexed for drafts too; queries only count posts residents can
 * see, so a draft's words show up the moment it is published.
 */
final class VoiceUsageIndex
{
    public const LANGUAGE = 'msm';

    /** Content tables and their Manobo columns. Table/column names are fixed, never user input. */
    private const SOURCES = [
        'announcement' => ['table' => 'announcements', 'title' => 'title_manobo', 'body' => 'body_manobo'],
        'event'        => ['table' => 'events',        'title' => 'title_manobo', 'body' => 'description_manobo'],
        'ordinance'    => ['table' => 'ordinances',    'title' => 'title_manobo', 'body' => 'description_manobo'],
    ];

    /**
     * Replace the word counts for one post. Never throws: indexing must not
     * break saving a post.
     */
    public static function indexContent(string $contentType, int $contentId, string $manoboText): void
    {
        if (!isset(self::SOURCES[$contentType]) || $contentId <= 0) {
            return;
        }

        $pdo  = db();
        // Only own the transaction when the caller is not already inside one;
        // rolling back a caller's transaction would undo their post save.
        $owns = !$pdo->inTransaction();
        try {
            $counts = [];
            foreach (VoiceText::tokens($manoboText) as $token) {
                if (VoiceText::isWord($token) && strlen($token) <= 191) {
                    $counts[$token] = ($counts[$token] ?? 0) + 1;
                }
            }

            if ($owns) {
                $pdo->beginTransaction();
            }
            $pdo->prepare('DELETE FROM voice_word_usage WHERE language = ? AND content_type = ? AND content_id = ?')
                ->execute([self::LANGUAGE, $contentType, $contentId]);

            $insert = $pdo->prepare(
                'INSERT INTO voice_word_usage (language, normalized_text, content_type, content_id, occurrences, last_seen_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            foreach ($counts as $word => $n) {
                $insert->execute([self::LANGUAGE, $word, $contentType, $contentId, $n]);
            }
            if ($owns) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[VoiceUsageIndex::indexContent] {$contentType}#{$contentId}: " . $e->getMessage());
        }
    }

    /**
     * Count Manobo words the Voice Reader had to play without a recording.
     *
     * @param list<string> $words  Normalised words.
     */
    public static function recordReaderMisses(array $words): void
    {
        $words = array_values(array_unique(array_filter($words, [VoiceText::class, 'isWord'])));
        if (!$words) {
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
            foreach (array_slice($words, 0, 200) as $word) {
                $update->execute([self::LANGUAGE, $word]);
                if ($update->rowCount() === 0) {
                    try {
                        $insert->execute([self::LANGUAGE, $word]);
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
     * Re-index every post's Manobo text. Returns the number of posts indexed.
     */
    public static function rebuild(): int
    {
        $indexed = 0;
        foreach (self::SOURCES as $type => $src) {
            $rows = db()->query("SELECT id, {$src['title']} AS t, {$src['body']} AS b FROM {$src['table']}")
                        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $live = [];
            foreach ($rows as $row) {
                $live[] = (int) $row['id'];
                self::indexContent($type, (int) $row['id'], trim(($row['t'] ?? '') . "\n" . ($row['b'] ?? '')));
                $indexed++;
            }
            // Drop rows for posts that no longer exist.
            $stmt = db()->prepare('SELECT DISTINCT content_id FROM voice_word_usage WHERE content_type = ?');
            $stmt->execute([$type]);
            $gone = array_diff(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), $live);
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
     * Words used in resident-visible posts or requested by the reader,
     * with recording status, sorted by priority (most used first).
     *
     * @return array{
     *   words: list<array<string,mixed>>,
     *   used_words: int, used_recorded: int, used_missing: int, usage_coverage: float
     * }
     */
    public static function report(): array
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
            $stmt->execute([self::LANGUAGE]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[VoiceUsageIndex::report] ' . $e->getMessage());
            return $empty;
        }

        $recorded = VoiceResolver::index(self::LANGUAGE)['map'];
        $dict     = self::dictionaryByWord();

        $words = [];
        foreach ($rows as $row) {
            $w = (string) $row['normalized_text'];
            $words[$w] ??= [
                'word'            => $w,
                'translation'     => $dict[$w]['translation'] ?? '',
                'dictionary_id'   => $dict[$w]['id'] ?? null,
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
     * Live dictionary entries keyed by normalised Manobo headword.
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
}
