<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\TranslationAttempt;
use App\Services\TranslationHealth;
use App\Services\TranslationOutcome;
use App\Services\TranslationPlanner;
use App\Services\TranslationRetryRunner;

/**
 * "Translate this one language, now."
 *
 * The manual counterpart to TranslationRetryRunner. The runner catches up
 * on its own schedule and only touches what a wait can fix; this is for the
 * staff member looking at a post with a red MN badge who has just added
 * Anthropic credits and wants it filled in before they leave for the day.
 *
 * Thin on purpose: it validates, delegates to the service that already
 * knows how to translate, and reports. Every decision about WHETHER a
 * language may be written — the *_is_auto rule above all — belongs to the
 * service and is not repeated here, because a second copy of that rule is a
 * second chance to get it wrong.
 */
class TranslationHealthController
{
    /**
     * GET /admin/translation-health — is anything missing?
     *
     * The page to open before a defence, and the one that should have
     * existed all along: every post with a language gap, why, and what to
     * do, with the provider state at the top because most of the lines
     * below it trace back to one of three things being unset.
     */
    public function index(): void
    {
        $lang = (string) ($_GET['lang'] ?? '');
        $lang = \in_array($lang, TranslationAttempt::LANGS, true) ? $lang : '';

        $providers = TranslationHealth::providers();
        $overview  = TranslationHealth::overview();
        $rows      = TranslationHealth::incomplete($lang !== '' ? $lang : null);

        $pageTitle    = t('translation_health.page_title');
        $pendingCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

        view('admin/translations/health', compact(
            'providers', 'overview', 'rows', 'lang', 'pageTitle', 'pendingCount'
        ));
    }

    /**
     * POST /admin/retranslate-all — retry one language across everything.
     *
     * Deliberately capped and deliberately not a background job. A staff
     * member pressing this is waiting on the page, and each item is an HTTP
     * call to a translation service; clearing forty of them in one request
     * would time out and look like a failure. The runner picks up whatever
     * this does not reach, which is what it is for.
     */
    /**
     * POST /admin/translation-health/rebuild-manobo — re-resolve machine-made
     * Manobo with the current dictionaries (Manobo first, Bisaya fallback,
     * gaps flagged — never Filipino passed off as Bisaya).
     *
     * Rebuilds rows whose Manobo is machine output, sample data, or missing.
     * Hand-written Manobo (manobo_is_auto = 0 on a non-sample post) is never
     * touched. Runs a few posts per call (machine translation is slow) and
     * the page loops until done; `offset` walks a stable candidate list.
     */
    public function rebuildManobo(): void
    {
        check_csrf();
        header('Content-Type: application/json; charset=utf-8');

        $offset = max(0, (int) ($_POST['offset'] ?? 0));
        $batch  = 1;
        // Keep well inside max_execution_time (120 s): after 70 s no more
        // machine-translation calls; the dictionaries still translate.
        \App\Services\ManoboHybridTranslator::$machineDeadline = microtime(true) + 70;

        $tables = [
            'announcement' => ['announcements', 'body'],
            'event'        => ['events', 'description'],
            'ordinance'    => ['ordinances', 'description'],
        ];
        $candidates = [];
        foreach ($tables as $type => [$table, $bodyCol]) {
            $rows = db()->query(
                "SELECT id, title, {$bodyCol} AS body, source_lang FROM {$table}
                  WHERE manobo_is_auto = 1 OR is_sample = 1 OR COALESCE(title_manobo, '') = ''
                  ORDER BY id"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $candidates[] = ['type' => $type] + $row;
            }
        }

        if ($offset === 0) {
            // New dictionary version: cached glosses from the old rules are not reused.
            \App\Services\ManoboHybridTranslator::incrementDictionaryVersion();
        }

        $translator = new \App\Services\ManoboHybridTranslator();
        $done = [];
        foreach (array_slice($candidates, $offset, $batch) as $c) {
            $source = in_array($c['source_lang'] ?? '', ['fil', 'en'], true) ? $c['source_lang'] : 'fil';
            $ok = \App\Services\TranslationService::autoTranslatePostToManobo(
                $c['type'], (int) $c['id'], (string) $c['title'], (string) ($c['body'] ?? ''), $source
            );
            // Same inputs, now cached: read back the provenance counts.
            $unresolved = 0;
            foreach ([(string) $c['title'], (string) ($c['body'] ?? '')] as $text) {
                if (trim(strip_tags($text)) === '') {
                    continue;
                }
                $r = $translator->translate($text, $source);
                $unresolved += (int) ($r['unresolved'] ?? 0);
            }
            $done[] = [
                'type' => $c['type'], 'id' => (int) $c['id'], 'title' => (string) $c['title'],
                'ok' => $ok, 'unresolved' => $unresolved,
            ];
        }

        $next = $offset + count($done);
        if ($next >= count($candidates)) {
            \App\Models\AuditLog::record((int) ($_SESSION['user_id'] ?? 0), 'translation.rebuild_manobo',
                'Rebuilt machine Manobo for ' . count($candidates) . ' posts');
        }
        echo json_encode([
            'success' => true,
            'total'   => count($candidates),
            'next'    => $next,
            'done'    => $next >= count($candidates),
            'posts'   => $done,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function retryAll(): void
    {
        check_csrf();

        $lang = (string) ($_POST['lang'] ?? '');
        if (!\in_array($lang, TranslationAttempt::LANGS, true)) {
            flash('error', t('translation_health.err_bad_request'));
            redirect('/admin/translation-health');
        }

        /* Make every gap in this language due, including the ones the
           runner would never touch on its own. A person asking explicitly
           IS the new information — they have added credits or fixed .env. */
        $queued = 0;
        foreach (TranslationHealth::incomplete($lang) as $row) {
            foreach ($row['missing'] as $missingLang) {
                TranslationAttempt::makeDue($row['type'], $row['id'], $missingLang);
                $queued++;
            }
        }

        $result = (new TranslationRetryRunner())->run(self::BULK_LIMIT, $lang);

        AuditLog::record(
            (int) ($_SESSION['user_id'] ?? 0),
            'translation.retry_all',
            \sprintf('%s — %d queued, %d attempted, %d succeeded',
                $lang, $queued, $result['attempted'], $result['succeeded'])
        );

        flash('success', t('translation_health.retried_all', [
            'lang'      => strtoupper(locale_short_code($lang)),
            'done'      => (string) $result['succeeded'],
            'attempted' => (string) $result['attempted'],
            'left'      => (string) \max(0, $queued - $result['attempted']),
        ]));

        redirect('/admin/translation-health?lang=' . $lang);
    }

    /**
     * How many one press of "retry all" will attempt.
     *
     * Small enough to come back before a browser gives up, because this
     * runs in the foreground while somebody watches. Anything left over is
     * already queued and the sweep will finish it.
     */
    private const BULK_LIMIT = 10;

    /**
     * POST /admin/retranslate
     *
     * Deliberately not routed under /admin/translations/{id}: that path is
     * already the review controller's, and a POST there means "confirm this
     * machine translation". Two different actions on one route is how the
     * wrong one eventually fires.
     */
    public function retry(): void
    {
        check_csrf();

        $type = (string) ($_POST['content_type'] ?? '');
        $id   = (int)    ($_POST['content_id']   ?? 0);
        $lang = (string) ($_POST['lang']         ?? '');
        $back = (string) ($_POST['redirect']     ?? '');

        if (!\in_array($type, ['announcement', 'event', 'ordinance'], true)
            || $id <= 0
            || !\in_array($lang, TranslationAttempt::LANGS, true)) {
            flash('error', t('translation_health.err_bad_request'));
            $this->goBack($back);
        }

        /*
         * Clear the retry clock so this attempt is due immediately.
         *
         * Without it, a language whose backoff has not elapsed — or one
         * marked permanent, like a post that was too long before it was
         * shortened — would be skipped, and the button would appear to do
         * nothing. A person pressing it IS the new information: they have
         * added credits, edited the text, or fixed the .env file.
         */
        TranslationAttempt::makeDue($type, $id, $lang);

        $result = (new TranslationRetryRunner())->runOne($type, $id, $lang);

        AuditLog::record(
            (int) ($_SESSION['user_id'] ?? 0),
            'translation.retry',
            \sprintf('%s #%d %s — %s', $type, $id, $lang, $result['ok'] ? 'ok' : 'failed')
        );

        if ($result['ok']) {
            flash('success', t('translation_health.retried_ok', [
                'lang' => strtoupper(locale_short_code($lang)),
            ]));
        } else {
            /* Say WHY, and say what to do — the whole point of this work.
               A bare "could not translate" is what the system did before. */
            $code = $result['reason'];
            flash('error', t('translation_health.retried_failed', [
                'lang'   => strtoupper(locale_short_code($lang)),
                'reason' => t(TranslationOutcome::messageKey($code)),
                'fix'    => t(TranslationOutcome::fixKey($code)),
            ]));
        }

        $this->goBack($back);
    }

    /**
     * POST /admin/translation-plan — what will happen when I press Save.
     *
     * JSON, called as the form is typed in. On the server because the two
     * questions that matter cannot be answered honestly in the browser:
     * whether the post exceeds the free provider's cap depends on its real
     * sentence chunker, and which language it is written in depends on
     * LanguageGuess and the order of authority in resolveSourceLang().
     * Re-implementing either in JavaScript would be a second copy free to
     * disagree with the one that actually runs.
     */
    public function plan(): void
    {
        header('Content-Type: application/json');
        check_csrf();

        $title    = (string) ($_POST['title']       ?? '');
        $body     = (string) ($_POST['body']        ?? '');
        $override = (string) ($_POST['source_lang'] ?? 'auto');
        $existing = (string) ($_POST['existing_source_lang'] ?? 'fil');

        /* Bounded before any work. This runs on every pause in typing, and
           a Quill body can be long — chunking an unbounded string on each
           keystroke would make the form feel broken. Well past any real
           barangay notice. */
        $title = mb_substr($title, 0, 1000);
        $body  = mb_substr($body, 0, 50000);

        echo json_encode(
            TranslationPlanner::plan($title, $body, $override, $existing)
        );
    }

    /**
     * Back where they pressed the button.
     *
     * The target is checked against the app's own admin paths rather than
     * being followed as given — a redirect built from POST data is an open
     * redirect unless something refuses the ones that point elsewhere.
     */
    private function goBack(string $back): void
    {
        $back = \trim($back);

        if ($back === '' || !\preg_match('~^/?admin(/[A-Za-z0-9/_-]*)?$~', $back)) {
            $back = '/admin/announcements';
        }

        redirect($back);
    }
}
