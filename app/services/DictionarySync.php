<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TranslationAttempt;

/**
 * Makes a dictionary change reach residents right away.
 *
 * Called after a Manobo or Bisaya word is added, corrected, removed or
 * imported at /admin/manobo or /admin/bisaya:
 *
 *  1. The translator's dictionary version goes up and every in-memory index
 *     is flushed, so the very next translation uses the new word (Bisaya
 *     changes did not bump the version before, so cached sentences ignored
 *     them).
 *  2. The short display translations (venue names, labels — translation_cache
 *     version 0) are cleared for Manobo, so they re-translate on next view.
 *  3. Posts whose English or Filipino text contains the word get their Manobo
 *     version re-translated now, within a time budget; any left over are
 *     queued, and the background sweep finishes them on the next page loads.
 *     Only machine translations are redone — text a person wrote is kept
 *     (TranslationRetryRunner checks the *_is_auto flags).
 */
final class DictionarySync
{
    private const TABLES = [
        'announcement' => ['announcements', 'body'],
        'event'        => ['events', 'description'],
        'ordinance'    => ['ordinances', 'description'],
    ];

    /**
     * @param list<string> $terms  English/Filipino meanings of the changed word(s)
     *                             (comma-separated senses are fine).
     * @return array{affected:int, updated:int, queued:int}
     */
    public static function changed(array $terms, float $budgetSeconds = 12.0): array
    {
        ManoboHybridTranslator::incrementDictionaryVersion();
        ManoboAutoTranslator::flushCache();
        BisayaDictionary::flushCache();
        try {
            db()->exec("DELETE FROM translation_cache WHERE dictionary_version = 0 AND target_lang IN ('msm', 'msm~')");
        } catch (\Throwable $e) {
            error_log('[DictionarySync] display cache: ' . $e->getMessage());
        }

        $posts  = self::affectedPosts($terms);
        $result = ['affected' => count($posts), 'updated' => 0, 'queued' => 0];
        if ($posts === []) {
            return $result;
        }

        $deadline = microtime(true) + $budgetSeconds;
        $previous = ManoboHybridTranslator::$machineDeadline;
        ManoboHybridTranslator::$machineDeadline = $deadline;
        $runner = new TranslationRetryRunner();
        try {
            foreach ($posts as [$type, $id]) {
                if (microtime(true) < $deadline) {
                    try {
                        $outcome = $runner->runOne($type, $id, 'msm');
                        if (!empty($outcome['ok'])) {
                            $result['updated']++;
                            continue;
                        }
                    } catch (\Throwable $e) {
                        error_log("[DictionarySync] {$type} #{$id}: " . $e->getMessage());
                    }
                }
                // Out of time (or it failed): the background sweep picks it up.
                TranslationAttempt::record($type, $id, 'msm', TranslationOutcome::PROVIDER_ERROR, 'text',
                    'Dictionary changed: re-translate with the new word.');
                TranslationAttempt::makeDue($type, $id, 'msm');
                $result['queued']++;
            }
        } finally {
            ManoboHybridTranslator::$machineDeadline = $previous;
        }
        return $result;
    }

    /**
     * Posts (any status) whose English or Filipino title/body contains one of
     * the terms as a whole word, and whose Manobo is machine-made.
     *
     * @param list<string> $terms
     * @return list<array{0:string,1:int}>
     */
    public static function affectedPosts(array $terms): array
    {
        $words = [];
        foreach ($terms as $term) {
            foreach (preg_split('~[,/;]~u', mb_strtolower((string) $term, 'UTF-8')) ?: [] as $sense) {
                $sense = trim(preg_replace('/\s*\([^)]*\)/u', '', $sense) ?? $sense);
                if (mb_strlen($sense) >= 2) {
                    $words[$sense] = '/(?<![\p{L}\p{N}])' . preg_quote($sense, '/') . '(?![\p{L}\p{N}])/u';
                }
            }
        }
        if ($words === []) {
            return [];
        }

        $found = [];
        foreach (self::TABLES as $type => [$table, $body]) {
            try {
                $rows = db()->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($rows as $row) {
                if (array_key_exists('manobo_is_auto', $row) && (string) $row['manobo_is_auto'] === '0'
                    && trim((string) ($row[$body . '_manobo'] ?? '')) !== '') {
                    continue;   // a person wrote this Manobo: never replaced
                }
                $text = mb_strtolower(implode("\n", array_map('strval', [
                    $row['title'] ?? '', $row['title_en'] ?? '', $row['title_fil'] ?? '',
                    strip_tags((string) ($row[$body] ?? '')), strip_tags((string) ($row[$body . '_en'] ?? '')),
                    strip_tags((string) ($row[$body . '_fil'] ?? '')),
                ])), 'UTF-8');
                foreach ($words as $pattern) {
                    if (preg_match($pattern, $text)) {
                        $found[] = [$type, (int) $row['id']];
                        break;
                    }
                }
            }
        }
        return $found;
    }
}
