<?php
declare(strict_types=1);

/**
 * One-time sweep: translate every EXISTING post's missing languages.
 *
 * The difference from tools/retry-translations.php: that script only retries
 * what is already recorded in translation_attempts as due for a retry — a
 * post that predates that tracking, or whose auto-translate never ran at
 * all (most of a freshly-seeded or imported site), has no such row and that
 * script's due()-based selection will never surface it.
 *
 * This one finds every post with a language gap directly from the content
 * tables (the same query the /admin/translation-health page uses) and calls
 * TranslationRetryRunner::runOne() on each gap DIRECTLY, one at a time,
 * rather than routing through TranslationAttempt::makeDue() + due(). That
 * matters: makeDue() only updates a row that already exists — for a post
 * with no tracked attempt yet it is a silent no-op, and due() then never
 * selects it either, so /admin/retranslate-all's own "mark due, then drain"
 * approach quietly skips exactly the posts a real backfill has to reach.
 * runOne() carries no such assumption; it works whether or not a row exists.
 *
 * Run once after deploying the on-demand voice-player translation feature, or
 * any time you want every old post filled in without opening the admin page
 * and pressing "Retry all" per language:
 *
 *     php tools/backfill-translations.php
 *
 * Options:
 *   --lang=en|fil|msm   only this language (default: all three)
 *   --dry-run           report what is missing without translating anything
 *
 * Safe to re-run: text a person typed is never overwritten (see
 * TranslationRetryRunner::wasWrittenByAPerson()), and each translate call
 * still records its own outcome, so running this twice in a row simply
 * re-attempts whatever is still missing.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require __DIR__ . '/../app/bootstrap.php';

use App\Services\TranslationHealth;
use App\Services\TranslationRetryRunner;

$opts   = getopt('', ['lang::', 'dry-run']);
$dryRun = isset($opts['dry-run']);

$lang = isset($opts['lang']) ? strtolower(trim((string) $opts['lang'])) : null;
if ($lang !== null && !in_array($lang, ['en', 'fil', 'msm'], true)) {
    fwrite(STDERR, "Unknown --lang={$lang}. Use en, fil or msm.\n");
    exit(1);
}

// Every post with a gap, not just the last 200 — this is meant to run once
// and finish the job, not to behave like the paginated admin page.
$rows = TranslationHealth::incomplete($lang, PHP_INT_MAX);

if ($rows === []) {
    echo "Nothing missing — every post already has every language it should.\n";
    exit(0);
}

$gaps = [];
foreach ($rows as $row) {
    foreach ($row['missing'] as $missingLang) {
        $gaps[] = [$row['type'], (int) $row['id'], $missingLang, $row['title']];
    }
}

echo count($gaps) . ' language gap(s) across ' . count($rows) . " post(s) found.\n";

if ($dryRun) {
    foreach ($gaps as [$type, $id, $missingLang, $title]) {
        echo sprintf("  %s #%d \"%s\" — missing %s\n", $type, $id, $title, strtoupper($missingLang));
    }
    echo "Dry run — nothing was translated.\n";
    exit(0);
}

$runner    = new TranslationRetryRunner();
$succeeded = 0;
$failed    = 0;

foreach ($gaps as [$type, $id, $missingLang, $title]) {
    $result = $runner->runOne($type, $id, $missingLang);

    echo sprintf(
        "  %s #%d %s — %s\n",
        $type,
        $id,
        strtoupper($missingLang),
        $result['ok'] ? 'OK' : $result['reason']
    );

    $result['ok'] ? $succeeded++ : $failed++;
}

echo sprintf("Done — %d succeeded, %d failed.\n", $succeeded, $failed);

if ($failed > 0) {
    echo "A failure here is usually provider state (no API key, no credits, daily\n";
    echo "allowance spent) rather than a real error — check /admin/translation-health\n";
    echo "for what each reason code means and how to fix it.\n";
}

exit(0);
