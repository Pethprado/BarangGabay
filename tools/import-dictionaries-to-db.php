<?php
declare(strict_types=1);

/**
 * One-time import: moves the Manobo and Bisaya dictionaries from their CSV
 * files onto the manobo_dictionary / bisaya_dictionary tables added by
 * migration 030. Run once from the project root:
 *
 *     php tools/import-dictionaries-to-db.php
 *
 * Safe to re-run: each of the three sources below is skipped if the target
 * table already has rows carrying that source's data, so this never
 * duplicates a batch it already inserted.
 *
 * Three sources, all going into manobo_dictionary / bisaya_dictionary:
 *   1. data/manobo/manobo_dictionary.csv     — the existing 40-word, sourced
 *      (SIL/Wiktionary-cited) Manobo dataset. Imported unchanged.
 *   2. data/manobo/manobo_dataset_2026.csv   — the new 234-word/phrase set.
 *      Two headwords collide with #1 by exact spelling — both SKIPPED here
 *      (not duplicated), because manobo_dictionary has no unique constraint
 *      on the headword (soft-delete needs that freedom — see migration 030),
 *      which means the application layer is what keeps headwords unique, and
 *      it does that by exact-string match. A real duplicate would silently
 *      break find()/update/delete, which key off that same exact string:
 *        - "mata" (eye, body) is the SAME word as the existing sourced entry.
 *        - "sed" collides with an existing entry meaning "inside" (prep.,
 *          SIL-FM-T1); this dataset gives it as "pumasok / enter" (verb) —
 *          possibly a real homonym, possibly a transcription mix-up in
 *          whichever source is wrong. Either way it needs a Manobo speaker
 *          to resolve it, and a second "sed" row is not a safe way to hold
 *          that question — logged to the console instead so it is not
 *          silently lost, and can be added by hand at /admin/manobo under a
 *          disambiguated spelling once someone has checked it.
 *   3. data/bisaya/bisaya_dictionary.csv     — the existing 414-word Bisaya
 *      fallback dataset. Imported unchanged.
 */

chdir(__DIR__ . '/..');
require __DIR__ . '/../config/database.php';

$pdo = db();

function readCsv(string $path): array
{
    $rows   = [];
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        fwrite(STDERR, "Could not open {$path}\n");
        exit(1);
    }
    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        return [];
    }
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [null] || $row === []) {
            continue;
        }
        $entry = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
        if ($entry === false) {
            continue;
        }
        $rows[] = array_map(static fn ($v) => trim((string) $v), $entry);
    }
    fclose($handle);
    return $rows;
}

function insertBatch(PDO $pdo, string $table, string $headwordCol, array $rows): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO {$table} ({$headwordCol}, english, tagalog, part_of_speech, category, notes, source)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $n = 0;
    foreach ($rows as $row) {
        $stmt->execute([
            $row[$headwordCol],
            $row['english'],
            $row['tagalog'],
            $row['part_of_speech'] !== '' ? $row['part_of_speech'] : null,
            $row['category'] !== '' ? $row['category'] : 'other',
            $row['notes'] !== '' ? $row['notes'] : null,
            $row['source'] !== '' ? $row['source'] : 'LOCAL',
        ]);
        $n++;
    }
    return $n;
}

// ── 1. Existing sourced Manobo dataset (40 entries) ─────────────────────────
$existingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM manobo_dictionary WHERE source LIKE 'SIL-%' OR source = 'WIKT'"
)->fetchColumn();

if ($existingCount > 0) {
    echo "Skipping data/manobo/manobo_dictionary.csv — already imported ({$existingCount} rows).\n";
} else {
    $rows = readCsv('data/manobo/manobo_dictionary.csv');
    $n    = insertBatch($pdo, 'manobo_dictionary', 'manobo', $rows);
    echo "Imported {$n} rows from data/manobo/manobo_dictionary.csv\n";
}

// ── 2. The new 234-word/phrase dataset ──────────────────────────────────────
$newCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM manobo_dictionary WHERE source = 'USER-2026'"
)->fetchColumn();

if ($newCount > 0) {
    echo "Skipping data/manobo/manobo_dataset_2026.csv — already imported ({$newCount} rows).\n";
} else {
    $rows = readCsv('data/manobo/manobo_dataset_2026.csv');
    $skipWords = ['mata', 'sed'];
    $skipped   = [];
    $mapped    = [];
    foreach ($rows as $row) {
        // This dataset's columns are manobo,tagalog,english,category,note —
        // tagalog/english swapped relative to manobo_dictionary's own column
        // order, and "note" (singular) instead of "notes".
        $manobo = $row['manobo'];

        if (in_array(strtolower($manobo), $skipWords, true)) {
            $skipped[] = $manobo . ' (' . $row['english'] . ')';
            continue;
        }

        $mapped[] = [
            'manobo'         => $manobo,
            'english'        => $row['english'],
            'tagalog'        => $row['tagalog'],
            'part_of_speech' => '',
            'category'       => $row['category'],
            'notes'          => $row['note'],
            'source'         => 'USER-2026',
        ];
    }
    $n = insertBatch($pdo, 'manobo_dictionary', 'manobo', $mapped);
    echo "Imported {$n} rows from data/manobo/manobo_dataset_2026.csv\n";
    if ($skipped !== []) {
        echo "  Skipped (exact headword collision with the existing sourced dataset — needs a\n"
           . "  Manobo speaker to resolve before adding by hand under a disambiguated spelling):\n";
        foreach ($skipped as $s) {
            echo "    - {$s}\n";
        }
    }
}

// ── 3. Existing Bisaya fallback dataset (414 entries) ───────────────────────
$bisayaCount = (int) $pdo->query('SELECT COUNT(*) FROM bisaya_dictionary')->fetchColumn();

if ($bisayaCount > 0) {
    echo "Skipping data/bisaya/bisaya_dictionary.csv — already imported ({$bisayaCount} rows).\n";
} else {
    $rows = readCsv('data/bisaya/bisaya_dictionary.csv');
    $n    = insertBatch($pdo, 'bisaya_dictionary', 'bisaya', $rows);
    echo "Imported {$n} rows from data/bisaya/bisaya_dictionary.csv\n";
}

echo "Done. manobo_dictionary now has "
   . $pdo->query('SELECT COUNT(*) FROM manobo_dictionary')->fetchColumn() . " rows; "
   . 'bisaya_dictionary has '
   . $pdo->query('SELECT COUNT(*) FROM bisaya_dictionary')->fetchColumn() . " rows.\n";
