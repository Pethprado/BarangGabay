<?php
declare(strict_types=1);

/**
 * Check the SMS setup from the command line.
 *
 *   php tools/sms-test.php                    # provider, configuration, balance
 *   php tools/sms-test.php 09171234567        # also send one test SMS to that number
 *   php tools/sms-test.php 09171234567 "Hello from BarangGabay"
 *
 * Sends through the same provider the app uses (SmsGateway::make()), so a
 * success here means announcements, payments and the admin SMS page work too.
 * A real send uses one SMS credit unless MOCEAN_TEST_MODE=true.
 */

$root = \dirname(__DIR__);
require $root . '/vendor/autoload.php';
if (is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
require $root . '/app/helpers.php';
require $root . '/config/database.php';

$sms = \App\Services\SmsGateway::make();
echo 'Provider:    ', $sms->providerName(), "\n";
echo 'Configured:  ', $sms->isConfigured() ? 'yes' : 'NO — set MOCEAN_API_TOKEN in .env', "\n";
echo 'Test mode:   ', $sms->isTestMode() ? 'yes (nothing is really sent)' : 'no (real SMS)', "\n";
echo 'Sender ID:   ', $sms->getSenderId(), "\n";
$balance = $sms->getBalance();
echo 'Balance:     ', $balance === null ? 'could not be read' : json_encode($balance), "\n";

$to = $argv[1] ?? '';
if ($to === '') {
    exit(0);
}
if ($sms->cleanPhone($to) === null) {
    echo "\n'{$to}' is not a Philippine mobile number (use 09XXXXXXXXX).\n";
    exit(1);
}
$text = $argv[2] ?? 'BarangGabay test: SMS is working. (Barangay Bayogo)';
echo "\nSending to ", $sms->cleanPhone($to), ": \"{$text}\"\n";
$result = $sms->send($to, $text, 'manual');
echo $result['success'] ? "SENT\n" : 'FAILED: ' . ($result['error'] ?? 'unknown error') . "\n";
exit($result['success'] ? 0 : 1);
