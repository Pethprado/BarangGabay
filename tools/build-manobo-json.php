<?php
declare(strict_types=1);

/**
 * Regenerate data/manobo/manobo_dictionary.json from the CSV.
 *
 * The CSV is the single source of truth. Add words there, then run:
 *     C:\xampp\php\php.exe tools\build-manobo-json.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ManoboDictionary;

$csv  = __DIR__ . '/../data/manobo/manobo_dictionary.csv';
$json = __DIR__ . '/../data/manobo/manobo_dictionary.json';

$dictionary = new ManoboDictionary($csv);

if (!$dictionary->exportJson($json)) {
    fwrite(STDERR, "Failed to write {$json}\n");
    exit(1);
}

printf(
    "Wrote %d entries to %s\nCategories: %s\n",
    $dictionary->count(),
    basename($json),
    implode(', ', $dictionary->categories())
);
