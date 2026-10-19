<?php
declare(strict_types=1);

/**
 * Remove the seeded demo accounts and their own document requests/payments
 * (App\Services\DemoCleanup). Real accounts, requests and payments are never
 * matched. Runs once automatically on Render (setting demo_accounts_removed_v1).
 *
 *   php tools/remove-demo-accounts.php          # preview only, changes nothing
 *   php tools/remove-demo-accounts.php --run    # actually remove
 */

$root = \dirname(__DIR__);
require $root . '/vendor/autoload.php';
if (is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
require $root . '/app/helpers.php';
require $root . '/config/database.php';

$run     = in_array('--run', $argv, true);
$cleanup = new \App\Services\DemoCleanup(db());
$result  = $run ? $cleanup->run() : $cleanup->preview();

echo $run ? "REMOVED\n" : "PREVIEW (nothing changed; add --run to remove)\n";
foreach ($result['accounts'] as $account) {
    echo "  - {$account['email']} ({$account['role']})\n";
}
foreach ($result['counts'] as $what => $n) {
    echo "  {$what}: {$n}\n";
}
if ($result['accounts'] === []) {
    echo "  No demo accounts found.\n";
}
