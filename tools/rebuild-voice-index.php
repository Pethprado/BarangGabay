<?php
declare(strict_types=1);

/**
 * Rebuild the voice word index (what the Voice Training hub counts as
 * "words residents hear"), the same as "Rescan Published Content" in the hub.
 *
 *   php tools/rebuild-voice-index.php          every language
 *   php tools/rebuild-voice-index.php msm      one language (msm, ceb, fil, en)
 */

$root = \dirname(__DIR__);
require $root . '/vendor/autoload.php';
if (is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
require $root . '/app/helpers.php';
require $root . '/config/database.php';

$language = $argv[1] ?? null;
$posts    = \App\Services\VoiceUsageIndex::rebuild($language ?: null);
echo "Re-indexed {$posts} post(s)" . ($language ? " for {$language}" : '') . ".\n";

foreach (\App\Services\VoiceUsageIndex::reports() as $lang => $report) {
    echo str_pad($lang, 4) . ": {$report['used_recorded']} of {$report['used_words']} words in posts recorded\n";
}
