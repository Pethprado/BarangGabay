<?php
declare(strict_types=1);

/**
 * "Is anything missing?" — the console version.
 *
 * Reads TranslationHealth, the same service behind /admin/translation-health,
 * so the two cannot disagree. A defence-day check that says something
 * different from the page being demonstrated is worse than having neither.
 *
 *   php tools/check-translations.php
 *   php tools/check-translations.php --lang=msm
 *   php tools/check-translations.php --quiet      only the summary
 *   php tools/check-translations.php --limit=500  scan deeper
 *
 * Exit codes, so this can sit in a pre-deploy check:
 *   0  nothing missing
 *   1  something is missing, or a provider is down
 *   2  the check itself could not run
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require __DIR__ . '/../app/bootstrap.php';

use App\Services\TranslationHealth;
use App\Services\TranslationOutcome;

/*
 * Render as the back office does.
 *
 * Anything outside the front controller defaults to the resident side, so
 * this printed Filipino while /admin/translation-health — the page it is
 * supposed to mirror — printed English. "The same summary" has to mean the
 * same words, or comparing the two becomes an exercise in translation.
 */
$GLOBALS['bg_is_back_office'] = true;

$opts  = getopt('', ['lang::', 'limit::', 'quiet']);
$quiet = isset($opts['quiet']);

$lang = isset($opts['lang']) ? strtolower(trim((string) $opts['lang'])) : null;
if ($lang !== null && !in_array($lang, ['en', 'fil', 'msm'], true)) {
    fwrite(STDERR, "Unknown --lang={$lang}. Use en, fil or msm.\n");
    exit(2);
}

$limit = isset($opts['limit'])
    ? max(1, min(1000, (int) $opts['limit']))
    : TranslationHealth::SCAN_LIMIT;

/** Plain ASCII: a Windows console will mangle box-drawing characters. */
function rule(string $title): void
{
    echo PHP_EOL . '== ' . $title . ' ' . str_repeat('=', max(0, 60 - strlen($title))) . PHP_EOL;
}

try {
    $providers = TranslationHealth::providers();
    $overview  = TranslationHealth::overview($limit);
    $rows      = TranslationHealth::incomplete($lang, $limit);
} catch (\Throwable $e) {
    fwrite(STDERR, '[check-translations] ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

// ── Providers ────────────────────────────────────────────────────────────
rule('Providers');

$providerDown = false;
foreach ($providers as $p) {
    printf("  %-16s %s\n", $p['label'], $p['ready'] ? 'ready' : 'NOT READY');

    if ($p['ready']) {
        continue;
    }
    $providerDown = true;

    printf("      %s\n", t(TranslationOutcome::messageKey((string) $p['reason'])));
    printf("      fix: %s\n", t(TranslationOutcome::fixKey((string) $p['reason'])));
    if ($p['envKey'] !== null) {
        printf("      .env key: %s\n", $p['envKey']);
    }
}

// ── Summary ──────────────────────────────────────────────────────────────
rule('Summary');
printf("  posts with a gap      %d\n", $overview['posts']);
printf("  languages missing     %d\n", $overview['missingText']);
printf("  audio missing         %d\n", $overview['missingAudio']);
printf("  queued for retry      %d\n", $overview['retryable']);

echo "  by language           ";
foreach ($overview['byLang'] as $code => $n) {
    printf('%s:%d  ', strtoupper(locale_short_code($code)), $n);
}
echo PHP_EOL;

// ── The posts ────────────────────────────────────────────────────────────
if (!$quiet && $rows !== []) {
    rule('Posts' . ($lang !== null ? ' (' . strtoupper(locale_short_code($lang)) . ' only)' : ''));

    foreach ($rows as $r) {
        printf(
            "\n  %s #%d  %s\n",
            $r['type'],
            $r['id'],
            mb_strimwidth((string) $r['title'], 0, 58, '...', 'UTF-8')
        );
        printf("      written in %s\n", strtoupper(locale_short_code($r['source'])));

        if ($r['missing'] !== []) {
            printf("      no text:  %s\n", implode(', ', array_map(
                static fn (string $l): string => strtoupper(locale_short_code($l)),
                $r['missing']
            )));
        }
        if ($r['audioMissing'] !== []) {
            printf("      no audio: %s\n", implode(', ', array_map(
                static fn (string $l): string => strtoupper(locale_short_code($l)),
                $r['audioMissing']
            )));
        }

        foreach ($r['reasons'] as $rLang => $why) {
            printf("      %s: %s\n", strtoupper(locale_short_code((string) $rLang)), $why['message']);
            printf("          %s\n", $why['fix']);
        }

        if ($r['reasons'] === []) {
            echo "      nothing has tried yet\n";
        }
    }
}

// ── Verdict ──────────────────────────────────────────────────────────────
echo PHP_EOL;

if ($rows === [] && !$providerDown) {
    echo "  Nothing missing.\n";
    exit(0);
}

if ($rows === []) {
    echo "  No gaps, but a provider is down (see above).\n";
    exit(1);
}

printf(
    "  %d post(s) need attention%s.\n",
    count($rows),
    $providerDown ? ', and a provider is down' : ''
);
echo "  Run tools/retry-translations.php to work through what a retry can fix.\n";

exit(1);
