<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Ordinance;
use App\Models\TranslationAttempt;

/**
 * Picks up translations that failed for a reason that has since gone away.
 *
 * The case this exists for is the ordinary one here. The free provider has a
 * daily character allowance per IP. A staff member publishing three notices
 * on a busy afternoon can exhaust it on the second, and the third saves
 * correctly with no English version — silently, before Step 1. The allowance
 * resets overnight. Nobody is in the office at midnight, and nobody goes
 * back through last week's posts looking for missing languages, so that post
 * simply stays half-translated until a resident complains.
 *
 * This runner closes that gap: the post fills itself in on its own, the next
 * time anybody loads a page after the allowance resets.
 *
 * ── What it deliberately does NOT do ────────────────────────────────────
 *
 * It never touches text a person typed. The *_is_auto flags are checked
 * before any work starts, so a staff member who hand-wrote the English while
 * the machine was failing does not come back to find it replaced by a
 * machine translation overnight. That check happens here rather than being
 * left to the translate methods, because by the time they run the allowance
 * has already been spent.
 *
 * It does not retry what waiting cannot fix. TranslationOutcome decides
 * that; a post that is too long for the provider has retry_after NULL and is
 * never selected, so the runner cannot grind at it.
 *
 * ── How it is triggered ─────────────────────────────────────────────────
 *
 * The same two ways ScheduledPublisher is: opportunistically from
 * public/index.php on ordinary GET traffic, and from tools/retry-translations.php
 * for deployments that have a real scheduler. Both are safe to run together —
 * each attempt is claimed with a conditional UPDATE before any API call, so
 * two sweeps cannot spend the allowance twice on one post.
 */
class TranslationRetryRunner
{
    /**
     * How many languages one sweep will attempt.
     *
     * Low for the same reason ScheduledPublisher's batch is low: a sweep
     * runs inside somebody's page load, and each item here is an HTTP call
     * to a translation service that can take seconds. Clearing a backlog
     * three at a time across several page loads is kinder than making one
     * staff member wait on twenty.
     */
    public const SWEEP_BATCH = 3;

    /** A CLI run has nobody waiting, so it may take a bigger bite. */
    public const CLI_BATCH = 25;

    /**
     * Which body column each content type uses.
     *
     * Announcements call it `body`; events and ordinances call it
     * `description`. Getting this wrong does not error — it reads an absent
     * key, translates an empty string, and records a confident failure
     * about a post that was fine.
     */
    private const BODY_FIELD = [
        'announcement' => 'body',
        'event'        => 'description',
        'ordinance'    => 'description',
    ];

    /**
     * Work through what is due.
     *
     * @param  int         $limit How many attempts to make at most.
     * @param  string|null $lang  Restrict to one language, for the admin's
     *                            "retry all Manobo" button.
     * @return array{attempted:int, succeeded:int, failed:int, skipped:int,
     *               details:list<string>}
     */
    public function run(int $limit = self::SWEEP_BATCH, ?string $lang = null): array
    {
        $summary = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'skipped' => 0, 'details' => []];

        foreach (TranslationAttempt::due($limit * 3, $lang, 'text') as $attempt) {
            if ($summary['attempted'] >= $limit) {
                break;
            }

            $id = (int) $attempt['id'];

            /* Claim before any API call. A sweep that loses the race skips
               the row rather than paying for the same translation twice. */
            if (!TranslationAttempt::claim($id)) {
                $summary['skipped']++;
                continue;
            }

            $outcome = $this->retryOne($attempt);

            $summary['attempted']++;
            $summary[$outcome['ok'] ? 'succeeded' : 'failed']++;
            $summary['details'][] = $outcome['detail'];
        }

        return $summary;
    }

    /**
     * Translate one language for one post, on request.
     *
     * The manual path, behind the admin's "Translate now" button. It skips
     * the claim — a person pressing a button is not a background sweep
     * racing another sweep, and making them lose to one would be baffling —
     * but goes through exactly the same retryOne() afterwards, so the rule
     * about not overwriting staff text is the same rule, not a copy of it.
     *
     * @return array{ok:bool, detail:string, reason:string}
     */
    public function runOne(string $contentType, int $contentId, string $lang): array
    {
        $existing = TranslationAttempt::find($contentType, $contentId, $lang);

        $outcome = $this->retryOne([
            'id'           => (int) ($existing['id'] ?? 0),
            'content_type' => $contentType,
            'content_id'   => $contentId,
            'lang'         => $lang,
        ]);

        $after = TranslationAttempt::find($contentType, $contentId, $lang) ?? [];

        return $outcome + [
            'reason' => (string) ($after['reason_code'] ?? TranslationOutcome::PROVIDER_ERROR),
        ];
    }

    /**
     * Retry one language for one post.
     *
     * @param  array<string,mixed> $attempt A row from translation_attempts.
     * @return array{ok:bool, detail:string}
     */
    private function retryOne(array $attempt): array
    {
        $type = (string) $attempt['content_type'];
        $id   = (int) $attempt['content_id'];
        $lang = (string) $attempt['lang'];

        $label = "{$type} #{$id} {$lang}";

        $row = $this->loadPost($type, $id);
        if ($row === null) {
            /* The post was deleted while this was queued. The row is kept
               and marked rather than removed: it is the only record that
               anything was ever tried for that post, and that question
               outlives the post. Marking it stops the runner selecting it
               and keeps it off the health page. */
            TranslationAttempt::archive($type, $id);

            return ['ok' => false, 'detail' => "{$label}: post no longer exists, kept for audit"];
        }

        $bodyField = self::BODY_FIELD[$type] ?? 'body';
        $title     = (string) ($row['title'] ?? '');
        $body      = (string) ($row[$bodyField] ?? '');

        if (trim($title) === '' && trim(strip_tags($body)) === '') {
            TranslationAttempt::record(
                $type, $id, $lang, TranslationOutcome::NO_SOURCE_TEXT, 'text',
                'The post has no source text to translate.', null, ''
            );

            return ['ok' => false, 'detail' => "{$label}: no source text"];
        }

        // ── Partly hand-written: fill the missing half, keep the person's ──
        // A title typed by staff with no body (or the reverse) used to count
        // as "written by a person", so the body was never translated and
        // readers got the source language under their language's label.
        // Translate the post, then put the hand-written part back.
        $suffix     = TranslationAttempt::columnSuffix($lang);
        $humanTitle = trim((string) ($row['title_' . $suffix] ?? ''));
        $humanBody  = trim((string) ($row[$bodyField . '_' . $suffix] ?? ''));
        $partial    = $lang !== (string) ($row['source_lang'] ?? 'fil')
            && self::wasWrittenByAPerson($row, $lang, $bodyField)
            && (($humanTitle === '' && trim($title) !== '') || ($humanBody === '' && trim(strip_tags($body)) !== ''));
        if ($partial) {
            $sourceLang = \in_array($row['source_lang'] ?? 'fil', ['fil', 'en'], true) ? (string) $row['source_lang'] : 'fil';
            $ok = match ($lang) {
                'en'  => TranslationService::autoTranslatePostToEnglish($type, $id, $title, $body),
                'fil' => TranslationService::autoTranslatePostToFilipino($type, $id, $title, $body),
                'msm' => TranslationService::autoTranslatePostToManobo($type, $id, $title, $body, $sourceLang),
                default => false,
            };
            if ($ok) {
                $fresh = $this->loadPost($type, $id) ?? [];
                $t = $humanTitle !== '' ? $humanTitle : (string) ($fresh['title_' . $suffix] ?? '');
                $b = $humanBody !== '' ? $humanBody : (string) ($fresh[$bodyField . '_' . $suffix] ?? '');
                $model = match ($type) {
                    'announcement' => Announcement::class,
                    'event'        => Event::class,
                    'ordinance'    => Ordinance::class,
                };
                match ($lang) {
                    'en'  => $model::updateEnglish($id, $t, $b),
                    'fil' => $model::updateFilipino($id, $t, $b),
                    'msm' => $model::updateManobo($id, $t, $b),
                };
            }
            return ['ok' => $ok, 'detail' => "{$label}: filled the untranslated part, kept the hand-written part"];
        }

        // ── The rule that outranks everything else ───────────────────────
        if (self::wasWrittenByAPerson($row, $lang, $bodyField)) {
            TranslationAttempt::recordOk($type, $id, $lang, 'text', 'human', $title . "\n" . $body);

            return ['ok' => true, 'detail' => "{$label}: already written by staff, left alone"];
        }

        $sourceLang = \in_array($row['source_lang'] ?? 'fil', ['fil', 'en'], true)
            ? (string) $row['source_lang']
            : 'fil';

        /* Asking for the language the post is already written in is never
           work — it is a wrong source_lang, which a person has to correct.
           Recorded as such so the health page can say so, and made
           permanent so the runner stops selecting it. */
        if ($lang === $sourceLang) {
            TranslationAttempt::record(
                $type, $id, $lang, TranslationOutcome::SAME_AS_SOURCE, 'text',
                "The post is recorded as written in {$sourceLang}.", null, $title . "\n" . $body
            );

            return ['ok' => false, 'detail' => "{$label}: same as source language"];
        }

        $ok = match ($lang) {
            'en'  => TranslationService::autoTranslatePostToEnglish($type, $id, $title, $body),
            'fil' => TranslationService::autoTranslatePostToFilipino($type, $id, $title, $body),
            'msm' => TranslationService::autoTranslatePost($type, $id, $title, $body)
                  || TranslationService::autoTranslatePostToManobo($type, $id, $title, $body, $sourceLang),
            default => false,
        };

        if ($lang !== 'en' && $lang !== 'fil' && $lang !== 'msm') {
            TranslationAttempt::record(
                $type, $id, $lang, TranslationOutcome::PROVIDER_ERROR, 'text',
                "Unknown language {$lang}", null, $title . "\n" . $body
            );

            return ['ok' => false, 'detail' => "{$label}: unknown language"];
        }

        /* The translate methods have already recorded their own outcome —
           that is what Step 1 wired in — so nothing is written here. Reading
           the row back is how the runner reports what happened without
           having a second, drifting opinion about it. */
        $after  = TranslationAttempt::find($type, $id, $lang) ?? [];
        $reason = (string) ($after['reason_code'] ?? TranslationOutcome::PROVIDER_ERROR);

        return [
            'ok'     => $ok,
            'detail' => $ok ? "{$label}: translated" : "{$label}: {$reason}",
        ];
    }

    /**
     * Did a person write this language, rather than a machine?
     *
     * Filled field + auto flag clear = typed by staff. That is the whole
     * meaning of the *_is_auto columns, and the one rule this runner must
     * never break: it works unattended, overnight, on posts nobody is
     * looking at, and a staff member who hand-wrote the English while the
     * machine was failing must not come back to find it replaced.
     *
     * Public and static so it can be tested directly against rows rather
     * than inferred from what the runner did to a database. A rule this
     * consequential should not only be checked through its side effects.
     *
     * @param array<string,mixed> $row
     */
    public static function wasWrittenByAPerson(array $row, string $lang, string $bodyField = 'body'): bool
    {
        $suffix = TranslationAttempt::columnSuffix($lang);   // msm -> manobo

        $filled = trim((string) ($row['title_' . $suffix] ?? '')) !== ''
               || trim((string) ($row[$bodyField . '_' . $suffix] ?? '')) !== '';

        if (!$filled) {
            return false;
        }

        $flag = $suffix === 'manobo' ? 'manobo_is_auto' : $suffix . '_is_auto';

        return (int) ($row[$flag] ?? 0) === 0;
    }

    /** @return array<string,mixed>|null */
    private function loadPost(string $type, int $id): ?array
    {
        try {
            $row = match ($type) {
                'announcement' => Announcement::find($id),
                'event'        => Event::find($id),
                'ordinance'    => Ordinance::find($id),
                default        => null,
            };

            return \is_array($row) && $row !== [] ? $row : null;
        } catch (\Throwable $e) {
            \error_log('[TranslationRetryRunner] could not load ' . $type . ' #' . $id . ': ' . $e->getMessage());
            return null;
        }
    }
}
