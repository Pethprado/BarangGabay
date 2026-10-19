<?php
declare(strict_types=1);

/**
 * Adds the Wiktionary Bisaya word list (data/bisaya/bisaya_wiktionary.csv,
 * about 6,500 Cebuano words) to the bisaya_dictionary table, where the MN
 * translator, the /admin/bisaya page and the voice trainer all read it.
 *
 * Source: English Wiktionary's Cebuano and Tagalog entries via the
 * kaikki.org extracts, licensed CC BY-SA 4.0 (see data/bisaya/README.md).
 * Every row is source = 'WIKTIONARY-CEB'. The Tagalog column was paired
 * automatically through the shared English meaning and is blank where no
 * safe pairing existed.
 *
 * Safe to re-run: a headword already in the table (hand-written, numerals,
 * or a previous run) is skipped, so nothing is duplicated and nothing a
 * staff member edited is overwritten. The hand-written words keep the lower
 * ids, so they keep first claim on a meaning in the translator.
 *
 * Run from the project root:   php tools/import-bisaya-wiktionary.php
 * Runs once automatically on PostgreSQL (config/database.php,
 * setting 'bisaya_wiktionary_v1').
 */

if (!function_exists('bg_import_bisaya_wiktionary')) {

    /**
     * @return array{added:int, skipped:int}
     */
    function bg_import_bisaya_wiktionary(PDO $pdo, ?string $csvPath = null): array
    {
        $csvPath ??= __DIR__ . '/../data/bisaya/bisaya_wiktionary.csv';
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot read {$csvPath}");
        }
        $header = array_map('trim', (array) fgetcsv($handle));

        $existing = [];
        foreach ($pdo->query('SELECT bisaya FROM bisaya_dictionary')->fetchAll(PDO::FETCH_COLUMN) as $word) {
            $existing[mb_strtolower(trim((string) $word))] = true;
        }

        $rows = [];
        $skipped = 0;
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($header)) {
                continue;
            }
            $row  = array_combine($header, $line);
            $word = trim($row['bisaya']);
            $key  = mb_strtolower($word);
            if ($word === '' || isset($existing[$key])) {
                $skipped++;
                continue;
            }
            $existing[$key] = true;
            $rows[] = [$word, $row['english'], $row['tagalog'], $row['part_of_speech'] ?: null,
                       $row['category'] ?: 'general', $row['notes'] ?: null, 'WIKTIONARY-CEB'];
        }
        fclose($handle);

        // Multi-row inserts in one transaction: thousands of rows in seconds,
        // even against a remote database.
        $pdo->beginTransaction();
        try {
            foreach (array_chunk($rows, 200) as $chunk) {
                $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?, ?)'));
                $pdo->prepare(
                    "INSERT INTO bisaya_dictionary (bisaya, english, tagalog, part_of_speech, category, notes, source)
                     VALUES {$placeholders}"
                )->execute(array_merge(...$chunk));
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        if (class_exists(\App\Services\BisayaDictionary::class)) {
            \App\Services\BisayaDictionary::flushCache();
        }

        return ['added' => count($rows), 'skipped' => $skipped];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $root = \dirname(__DIR__);
    require $root . '/vendor/autoload.php';
    if (is_file($root . '/.env')) {
        \Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
    require $root . '/app/helpers.php';
    require $root . '/config/database.php';
    $result = bg_import_bisaya_wiktionary(db());
    echo "Added {$result['added']} Bisaya words; skipped {$result['skipped']} already in the dictionary.\n";
}
