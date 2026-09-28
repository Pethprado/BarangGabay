<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\TranslationOutcome;
use PDO;

/**
 * The record of what happened when a language was attempted.
 *
 * One row per (content, language, kind), holding the latest outcome — see
 * database/migrations/028_translation_attempts.sql for why the history is
 * not kept.
 *
 * Everything here is written to be safe to call from inside a catch block:
 * recording an outcome must never be the thing that turns a failed
 * translation into a failed save. A barangay notice going out matters more
 * than our bookkeeping about it, so every write is wrapped and a broken
 * table degrades to a log line rather than an exception.
 */
class TranslationAttempt
{
    /** @var list<string> */
    public const LANGS = ['fil', 'en', 'msm'];

    /** @var list<string> */
    public const KINDS = ['text', 'audio'];

    /**
     * Locale code → the suffix the content tables use for that language.
     *
     * The single place this mapping lives. The app speaks en/fil/msm
     * everywhere a reader can see — available_locales(), the FIL/EN/MN
     * buttons, post_audio.locale — while the content columns were named
     * title_manobo / body_manobo before that vocabulary settled. Spelling
     * the conversion out once stops the two from being confused in a query
     * that then silently matches nothing.
     */
    public static function columnSuffix(string $lang): string
    {
        return $lang === 'msm' ? 'manobo' : $lang;
    }

    /** The reverse, for reading a status array that is keyed by suffix. */
    public static function localeFor(string $suffix): string
    {
        return $suffix === 'manobo' ? 'msm' : $suffix;
    }

    /**
     * Record an outcome, replacing whatever was there for this target.
     *
     * The attempt counter is what backoff is computed from, so it increments
     * on repeated failure and resets on success — a language that failed
     * four times and then worked starts from zero if it ever fails again,
     * rather than being punished for its history.
     *
     * @param string      $lang       en | fil | msm
     * @param string      $kind       text | audio
     * @param string      $reasonCode One of TranslationOutcome's constants
     * @param string|null $sourceText The text this outcome was about, so a
     *                                later edit can be detected
     */
    public static function record(
        string  $contentType,
        int     $contentId,
        string  $lang,
        string  $reasonCode,
        string  $kind = 'text',
        ?string $message = null,
        ?string $provider = null,
        ?string $sourceText = null
    ): void {
        if (!\in_array($lang, self::LANGS, true) || !\in_array($kind, self::KINDS, true)) {
            \error_log("[TranslationAttempt] refusing to record unknown target {$lang}/{$kind}");
            return;
        }
        if (!TranslationOutcome::isKnown($reasonCode)) {
            \error_log("[TranslationAttempt] unknown reason code {$reasonCode}, storing as PROVIDER_ERROR");
            $reasonCode = TranslationOutcome::PROVIDER_ERROR;
        }

        $ok = $reasonCode === TranslationOutcome::OK;

        try {
            $existing = self::find($contentType, $contentId, $lang, $kind);
            $attempts = $ok ? 1 : (int) (($existing['attempts'] ?? 0)) + 1;

            $retryAfter = null;
            if (!$ok && TranslationOutcome::shouldKeepTrying($reasonCode, $attempts)) {
                $retryAfter = TranslationOutcome::retryAfter($reasonCode, $attempts)
                    ?->format('Y-m-d H:i:s');
            }

            $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
            $sql = $isPgsql
                ? 'INSERT INTO translation_attempts
                    (content_type, content_id, lang, kind, ok, reason_code, message,
                     provider, attempts, retry_after, source_hash, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                   ON CONFLICT (content_type, content_id, lang, kind) DO UPDATE SET
                      ok          = EXCLUDED.ok,
                      reason_code = EXCLUDED.reason_code,
                      message     = EXCLUDED.message,
                      provider    = EXCLUDED.provider,
                      attempts    = EXCLUDED.attempts,
                      retry_after = EXCLUDED.retry_after,
                      source_hash = EXCLUDED.source_hash,
                      orphaned_at = NULL,
                      updated_at  = NOW()'
                : 'INSERT INTO translation_attempts
                    (content_type, content_id, lang, kind, ok, reason_code, message,
                     provider, attempts, retry_after, source_hash, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE
                      ok          = VALUES(ok),
                      reason_code = VALUES(reason_code),
                      message     = VALUES(message),
                      provider    = VALUES(provider),
                      attempts    = VALUES(attempts),
                      retry_after = VALUES(retry_after),
                      source_hash = VALUES(source_hash),
                      orphaned_at = NULL,
                      updated_at  = NOW()';

            db()->prepare($sql)->execute([
                $contentType,
                $contentId,
                $lang,
                $kind,
                $ok ? 1 : 0,
                $reasonCode,
                $message !== null ? \mb_substr($message, 0, 2000) : null,
                $provider,
                $attempts,
                $retryAfter,
                $sourceText !== null ? self::hash($sourceText) : null,
            ]);
        } catch (\Throwable $e) {
            /* Never let bookkeeping break a save. If this table is missing
               or the column set is older than the code, the post still
               publishes and the reason still reaches the error log — which
               is strictly better than where this started, where there was
               no reason anywhere. */
            \error_log(\sprintf(
                '[TranslationAttempt] could not record %s #%d %s/%s = %s: %s',
                $contentType, $contentId, $lang, $kind, $reasonCode, $e->getMessage()
            ));
        }
    }

    /** Shorthand for the success case. */
    public static function recordOk(
        string  $contentType,
        int     $contentId,
        string  $lang,
        string  $kind = 'text',
        ?string $provider = null,
        ?string $sourceText = null
    ): void {
        self::record(
            $contentType, $contentId, $lang,
            TranslationOutcome::OK, $kind, null, $provider, $sourceText
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(string $contentType, int $contentId, string $lang, string $kind = 'text'): ?array
    {
        try {
            $stmt = db()->prepare(
                'SELECT * FROM translation_attempts
                  WHERE content_type = ? AND content_id = ? AND lang = ? AND kind = ?
                  LIMIT 1'
            );
            $stmt->execute([$contentType, $contentId, $lang, $kind]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::find] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Every outcome for one post, keyed "lang.kind".
     *
     * One query per post rather than six, because the admin list renders
     * this for a page of posts at a time.
     *
     * @return array<string, array<string,mixed>>
     */
    public static function forContent(string $contentType, int $contentId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT * FROM translation_attempts WHERE content_type = ? AND content_id = ?'
            );
            $stmt->execute([$contentType, $contentId]);

            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[$row['lang'] . '.' . $row['kind']] = $row;
            }
            return $out;
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::forContent] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * The same, for a page of posts at once.
     *
     * @param list<int> $contentIds
     * @return array<int, array<string, array<string,mixed>>> keyed by content id
     */
    public static function forMany(string $contentType, array $contentIds): array
    {
        $contentIds = \array_values(\array_unique(\array_filter(
            \array_map('intval', $contentIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($contentIds === []) {
            return [];
        }

        try {
            $in   = \implode(',', \array_fill(0, \count($contentIds), '?'));
            $stmt = db()->prepare(
                "SELECT * FROM translation_attempts
                  WHERE content_type = ? AND content_id IN ({$in})"
            );
            $stmt->execute(\array_merge([$contentType], $contentIds));

            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(int) $row['content_id']][$row['lang'] . '.' . $row['kind']] = $row;
            }
            return $out;
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::forMany] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Work the retry runner should pick up: failed, and the wait is over.
     *
     * Newest posts first, because a notice published this morning matters
     * more than one from last month, and the runner has a per-run cap.
     *
     * @return list<array<string,mixed>>
     */
    public static function due(int $limit = 20, ?string $lang = null, ?string $kind = null): array
    {
        $limit = \max(1, \min(200, $limit));

        /* orphaned_at IS NULL: never try to translate a post that has been
           deleted. Its rows are kept for audit, not for work. */
        $sql = 'SELECT * FROM translation_attempts
                 WHERE ok = 0
                   AND orphaned_at IS NULL
                   AND retry_after IS NOT NULL
                   AND retry_after <= NOW()
                   AND attempts < ' . TranslationOutcome::MAX_ATTEMPTS;
        $args = [];

        if ($lang !== null) { $sql .= ' AND lang = ?'; $args[] = $lang; }
        if ($kind !== null) { $sql .= ' AND kind = ?'; $args[] = $kind; }

        $sql .= ' ORDER BY content_id DESC, updated_at ASC LIMIT ' . $limit;

        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($args);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::due] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Take ownership of a due attempt before working on it.
     *
     * Two sweeps can overlap: the opportunistic one runs inside whichever
     * page load happens to be first past the interval, and a Task Scheduler
     * run can land on top of it. Without a claim, both would translate the
     * same post — and since the free provider bills by the character, that
     * spends the barangay's daily allowance twice for one result.
     *
     * The claim is a conditional UPDATE, the same shape
     * Announcement::claimForNotification() uses: whoever moves retry_after
     * forward owns the work. The window is short because a claim that is
     * never released must expire on its own — if this process dies
     * mid-translation, the row becomes due again a few minutes later rather
     * than being stuck forever.
     *
     * @return bool True if this caller owns the attempt.
     */
    public static function claim(int $id, int $holdMinutes = 10): bool
    {
        try {
            $stmt = db()->prepare(
                'UPDATE translation_attempts
                    SET retry_after = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                        updated_at  = NOW()
                  WHERE id = ?
                    AND ok = 0
                    AND orphaned_at IS NULL
                    AND retry_after IS NOT NULL
                    AND retry_after <= NOW()'
            );
            $stmt->execute([$holdMinutes, $id]);

            return $stmt->rowCount() === 1;
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::claim] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Make one language due right now, whatever its backoff said.
     *
     * Pressing "Translate now" IS new information: the staff member has
     * added credits, shortened the text, or corrected the .env file. The
     * stored retry time was calculated before any of that was true, and a
     * button that silently honoured it would appear to do nothing.
     *
     * Also revives a permanently-failed row (TEXT_TOO_LONG, NO_API_KEY),
     * which has retry_after NULL by design — the automatic runner must
     * never pick those up, and a person asking explicitly is exactly the
     * case that rule was protecting against being automated.
     *
     * Creates nothing if there is no row: a language that has never been
     * attempted is simply translated, and the attempt is recorded then.
     */
    public static function makeDue(string $contentType, int $contentId, string $lang, string $kind = 'text'): void
    {
        try {
            $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
            $dateExpr = $isPgsql ? "NOW() - INTERVAL '1 minute'" : 'DATE_SUB(NOW(), INTERVAL 1 MINUTE)';
            db()->prepare(
                "UPDATE translation_attempts
                    SET retry_after = {$dateExpr},
                        attempts    = 0,
                        orphaned_at = NULL,
                        updated_at  = NOW()
                  WHERE content_type = ? AND content_id = ? AND lang = ? AND kind = ?"
            )->execute([$contentType, $contentId, $lang, $kind]);
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::makeDue] ' . $e->getMessage());
        }
    }

    /**
     * Has the text changed since this outcome was recorded?
     *
     * The audio case is why this exists: a track recorded for a body that
     * has since been rewritten reads the old words under the new headline,
     * which is worse than having no track at all.
     */
    public static function isStale(array $attempt, string $currentText): bool
    {
        $stored = (string) ($attempt['source_hash'] ?? '');

        return $stored !== '' && $stored !== self::hash($currentText);
    }

    /** Normalised so whitespace-only edits do not count as a change. */
    public static function hash(string $text): string
    {
        return \sha1(\trim(\preg_replace('/\s+/u', ' ', \strip_tags($text)) ?? $text));
    }

    /**
     * Has this failure been seen recently, anywhere?
     *
     * The plan panel needs it. Whether an Anthropic key is SET can be read
     * from .env; whether its account still has credits cannot be known
     * without spending one. So the panel would promise "Manobo will be
     * generated" for a key whose balance is empty, and the promise would
     * break on every single save.
     *
     * The recorded outcomes are the evidence. If the last attempt anywhere
     * came back NO_CREDITS an hour ago, the next one will too, and saying
     * so is better than a cheerful guess. Bounded by a window so a problem
     * that has since been fixed stops being reported — and the moment one
     * succeeds, its row flips to ok and stops matching.
     */
    public static function seenRecently(string $reasonCode, int $withinHours = 24): bool
    {
        try {
            $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
            $dateCond = $isPgsql
                ? "updated_at >= NOW() - (? || ' hours')::interval"
                : "updated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            $stmt = db()->prepare(
                "SELECT 1 FROM translation_attempts
                  WHERE ok = 0
                    AND reason_code = ?
                    AND orphaned_at IS NULL
                    AND {$dateCond}
                  LIMIT 1"
            );
            $stmt->execute([$reasonCode, \max(1, $withinHours)]);

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::seenRecently] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Counts for the health page, per language and kind.
     *
     * @return list<array{lang:string, kind:string, ok:int, failed:int, retryable:int}>
     */
    public static function summary(): array
    {
        try {
            // Live posts only — an archived row is history, not a work item.
            return db()->query(
                'SELECT lang, kind,
                        SUM(ok = 1)                                        AS ok,
                        SUM(ok = 0)                                        AS failed,
                        SUM(ok = 0 AND retry_after IS NOT NULL)            AS retryable
                   FROM translation_attempts
                  WHERE orphaned_at IS NULL
                  GROUP BY lang, kind
                  ORDER BY lang, kind'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::summary] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * The post is gone — keep the record, stop acting on it.
     *
     * Deliberately not a DELETE. "Why did the October advisory never get an
     * English version?" is a question that outlives the advisory, and the
     * row that says QUOTA_EXHAUSTED three times is the only thing that can
     * answer it. Deleting made the health page tidy and the history
     * unrecoverable.
     *
     * Marked rather than removed, and every operational query filters the
     * mark out: the runner must not try to translate a post that does not
     * exist, and the health page must not list work nobody can act on.
     */
    public static function archive(string $contentType, int $contentId): void
    {
        try {
            db()->prepare(
                'UPDATE translation_attempts
                    SET orphaned_at = NOW(), retry_after = NULL, updated_at = NOW()
                  WHERE content_type = ? AND content_id = ? AND orphaned_at IS NULL'
            )->execute([$contentType, $contentId]);
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::archive] ' . $e->getMessage());
        }
    }

    /**
     * Attempts kept for posts that no longer exist.
     *
     * @return list<array<string,mixed>>
     */
    public static function archived(int $limit = 100): array
    {
        $limit = \max(1, \min(500, $limit));

        try {
            return db()->query(
                'SELECT * FROM translation_attempts
                  WHERE orphaned_at IS NOT NULL
                  ORDER BY orphaned_at DESC
                  LIMIT ' . $limit
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            \error_log('[TranslationAttempt::archived] ' . $e->getMessage());
            return [];
        }
    }
}
