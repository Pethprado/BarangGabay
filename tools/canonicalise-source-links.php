<?php
declare(strict_types=1);

/**
 * Turn stored Facebook share stubs into the post addresses they point at.
 *
 *   php tools/canonicalise-source-links.php          # show what would change
 *   php tools/canonicalise-source-links.php --apply  # write the changes
 *
 * Why this is a script and not a migration: resolving a stub means following
 * a redirect, and a .sql file cannot make an HTTP request. It also must not
 * run on every database connection — runPendingMigrations() replays every
 * migration each time, and that would mean a request to Facebook per page
 * load.
 *
 * SourceLink::store() now resolves the stub as staff attach the link, so this
 * is only needed for rows saved before that change. Safe to re-run: a link
 * that is already canonical, or that cannot be resolved, is left alone.
 */

$root = __DIR__ . '/..';
require $root . '/vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
require $root . '/app/helpers.php';
require $root . '/config/database.php';

use App\Services\SourceLink;

$apply = in_array('--apply', $argv, true);

echo $apply ? "Applying changes.\n\n" : "Dry run — pass --apply to write.\n\n";

$changed = 0;

foreach (['announcements', 'events', 'ordinances'] as $table) {
    try {
        $rows = db()->query(
            "SELECT id, source_url FROM {$table}
              WHERE source_platform = 'facebook' AND source_url IS NOT NULL AND source_url <> ''"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        // Migration 022 not applied on this database — nothing to repair.
        echo "{$table}: skipped ({$e->getMessage()})\n";
        continue;
    }

    foreach ($rows as $row) {
        $old = (string) $row['source_url'];

        if (!SourceLink::isFacebookShareLink($old)) {
            continue;
        }

        $new = SourceLink::canonicalise($old);

        if ($new === $old) {
            printf("  %s #%-3d could not be resolved, left as-is\n    %s\n", $table, $row['id'], $old);
            continue;
        }

        printf("  %s #%-3d\n    was: %s\n    now: %s\n", $table, $row['id'], $old, $new);
        $changed++;

        if ($apply) {
            db()->prepare("UPDATE {$table} SET source_url = ? WHERE id = ?")
                ->execute([$new, $row['id']]);
        }
    }
}

echo "\n" . ($changed === 0
    ? "Nothing to change.\n"
    : $changed . ' link' . ($changed === 1 ? '' : 's') . ($apply ? " updated.\n" : " would change.\n"));
