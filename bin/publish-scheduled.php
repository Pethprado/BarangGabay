<?php
declare(strict_types=1);

/**
 * Dispatch notifications for announcements whose scheduled moment has passed.
 *
 * OPTIONAL. Scheduled posts appear to residents on time without this script —
 * visibility is decided by a WHERE clause on every read, not by a job. What
 * this sends is the one-time notification / SMS / email batch, which the web
 * app otherwise piggy-backs on ordinary page traffic (see public/index.php).
 *
 * Run it where you want that batch to be punctual instead of traffic-driven,
 * or where the portal can go hours without a visitor.
 *
 *   Linux / cPanel — every five minutes:
 *     * / 5 * * * * /usr/bin/php /home/user/baranggabay/bin/publish-scheduled.php
 *     (write that as *​/5 — no space; the space above is only to keep this
 *     comment block from being read as the end of a C comment)
 *
 *   Windows Task Scheduler:
 *     Program:   C:\xampp\php\php.exe
 *     Arguments: C:\xampp\htdocs\BarangGabay\bin\publish-scheduled.php
 *
 * Running it alongside web traffic is safe: dispatch is claimed with a
 * conditional UPDATE, so a cron run and a page load racing over the same post
 * cannot both send the SMS blast.
 *
 * Exits 0 on success, 1 on failure, so cron can report a genuine problem.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for the command line only.\n");
}

require __DIR__ . '/../app/bootstrap.php';

try {
    $sent = (new App\Services\ScheduledPublisher())->run(50);
} catch (\Throwable $e) {
    fwrite(STDERR, '[publish-scheduled] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Silent when there is nothing to do, so a five-minute cron does not fill the
// mail spool with "0 published" all day.
if ($sent > 0) {
    echo date('Y-m-d H:i:s') . " — published {$sent} scheduled announcement(s)." . PHP_EOL;
}

exit(0);
