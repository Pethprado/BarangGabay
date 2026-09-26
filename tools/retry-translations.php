<?php
declare(strict_types=1);

/**
 * Retry translations that failed for a reason that has since gone away.
 *
 * OPTIONAL. The web app already does this on ordinary page traffic (see
 * public/index.php), the same way scheduled announcements are dispatched.
 * Run this where you want the catch-up to be punctual instead of
 * traffic-driven — a barangay portal can easily go from 5pm to 8am without
 * a single visitor, which is exactly the window the free translator's daily
 * allowance resets in.
 *
 *   Windows Task Scheduler — hourly is plenty:
 *     Program:   C:\xampp\php\php.exe
 *     Arguments: C:\xampp\htdocs\BarangGabay\tools\retry-translations.php
 *
 *   Linux / cPanel:
 *     0 * * * * /usr/bin/php /home/user/baranggabay/tools/retry-translations.php
 *
 * Safe to run alongside web traffic: every attempt is claimed with a
 * conditional UPDATE before any API call, so a scheduled run and a page load
 * racing over the same post cannot both spend the daily allowance on it.
 *
 * Options:
 *   --lang=en|fil|msm   only this language
 *   --limit=N           at most N attempts (default 25)
 *   --quiet             print nothing unless something actually happened
 *
 * Exits 0 on success, 1 on failure, so a scheduler can report a real problem.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require __DIR__ . '/../app/bootstrap.php';

use App\Services\TranslationRetryRunner;

// ── Arguments ────────────────────────────────────────────────────────────
$opts  = getopt('', ['lang::', 'limit::', 'quiet']);
$quiet = isset($opts['quiet']);

$lang = isset($opts['lang']) ? strtolower(trim((string) $opts['lang'])) : null;
if ($lang !== null && !in_array($lang, ['en', 'fil', 'msm'], true)) {
    fwrite(STDERR, "Unknown --lang={$lang}. Use en, fil or msm.\n");
    exit(1);
}

$limit = isset($opts['limit'])
    ? max(1, min(200, (int) $opts['limit']))
    : TranslationRetryRunner::CLI_BATCH;

// ── Run ──────────────────────────────────────────────────────────────────
try {
    $result = (new TranslationRetryRunner())->run($limit, $lang);
} catch (\Throwable $e) {
    fwrite(STDERR, '[retry-translations] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/*
 * Silent when there was nothing to do, so an hourly schedule does not fill
 * the mail spool with "0 retried" all day — the same courtesy
 * bin/publish-scheduled.php extends.
 */
if ($result['attempted'] === 0 && $result['skipped'] === 0) {
    if (!$quiet) {
        echo date('Y-m-d H:i:s') . " — nothing due for retry." . PHP_EOL;
    }
    exit(0);
}

echo date('Y-m-d H:i:s') . sprintf(
    " — attempted %d, succeeded %d, failed %d, skipped %d (claimed elsewhere)" . PHP_EOL,
    $result['attempted'],
    $result['succeeded'],
    $result['failed'],
    $result['skipped']
);

if (!$quiet) {
    foreach ($result['details'] as $line) {
        echo '    ' . $line . PHP_EOL;
    }
}

exit(0);
