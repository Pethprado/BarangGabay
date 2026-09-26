<?php
declare(strict_types=1);

/**
 * Run a database backup from the command line, then prune old ones.
 *
 * This is what Windows Task Scheduler (or cron) should call — see
 * tools/backup-task.bat and storage/backups/README.md.
 *
 *   C:\xampp\php\php.exe tools\backup.php
 *
 * Exit codes: 0 = success, 1 = failure (so a scheduler can alert on it).
 */

require __DIR__ . '/../vendor/autoload.php';

$root = \dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->load();
require $root . '/config/database.php';
require $root . '/app/helpers.php';
// config/app.php sets the timezone, so CLI backup filenames and log lines
// match the times shown in the admin panel rather than the server default.
require $root . '/config/app.php';

use App\Services\BackupService;

$service = new BackupService();

try {
    $result = $service->create();
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] Backup FAILED: " . $e->getMessage() . "\n");
    exit(1);
}

printf(
    "[%s] Backup OK: %s (%s, %.2fs)\n",
    date('Y-m-d H:i:s'),
    $result['filename'],
    number_format($result['size'] / 1024, 1) . ' KB',
    $result['duration']
);

$pruned = $service->prune();
if ($pruned > 0) {
    printf("[%s] Pruned %d old backup(s), keeping the newest %d.\n",
        date('Y-m-d H:i:s'), $pruned, BackupService::KEEP_LATEST);
}

// Record it in the audit trail as a system action (no logged-in user).
try {
    App\Models\AuditLog::record(null, 'backup.scheduled', 'Scheduled backup: ' . $result['filename']);
} catch (Throwable $e) {
    // Audit failure must not fail the backup itself.
}

exit(0);
