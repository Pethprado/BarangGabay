<?php
declare(strict_types=1);

/**
 * One-time import: standard Cebuano/Bisaya number words (one..twenty, then
 * the common tens and "hundred"), so a resident typing "eight" — or an
 * announcement that has one — has somewhere to land.
 *
 * Why this exists: neither dictionary had a real numeral sequence. The
 * original 40-word Manobo set has exactly two attested numbers (six, two —
 * data/manobo/README.md is explicit that the accessible SIL source did not
 * cover the rest, and this project's own rule is not to invent Manobo
 * vocabulary to fill a gap like that). Bisaya numbers are not under that
 * same constraint — Cebuano numerals are standard, well-documented
 * vocabulary, not a guess — so the fallback belongs here, in
 * bisaya_dictionary, not invented into manobo_dictionary.
 *
 * Forms below are the commonly-SPOKEN mix (some Spanish-loan, e.g. "baynte"
 * for 20), matching this project's own established principle of preferring
 * how people actually talk over a textbook-pure form (see AIService's
 * manoboSystemPrompt()). Marked BISAYA-CEB with the same "needs local
 * review" note the rest of that dataset already carries, per
 * data/bisaya/README.md.
 *
 * Run once from the project root:
 *     php tools/import-bisaya-numbers.php
 *
 * Safe to re-run: skipped entirely if a row with source='BISAYA-NUMERALS'
 * already exists.
 */

chdir(__DIR__ . '/..');
require __DIR__ . '/../config/database.php';

$pdo = db();

$existing = (int) $pdo->query(
    "SELECT COUNT(*) FROM bisaya_dictionary WHERE source = 'BISAYA-NUMERALS'"
)->fetchColumn();

if ($existing > 0) {
    echo "Skipping — already imported ({$existing} rows).\n";
    exit(0);
}

// [english, tagalog, bisaya]
$numbers = [
    ['one', 'isa', 'usa'],
    ['two', 'dalawa', 'duha'],
    ['three', 'tatlo', 'tulo'],
    ['four', 'apat', 'upat'],
    ['five', 'lima', 'lima'],
    ['six', 'anim', 'unom'],
    ['seven', 'pito', 'pito'],
    ['eight', 'walo', 'walo'],
    ['nine', 'siyam', 'siyam'],
    ['ten', 'sampu', 'napulo'],
    ['eleven', 'labing-isa', "napulo'g usa"],
    ['twelve', 'labindalawa', "napulo'g duha"],
    ['thirteen', 'labintatlo', "napulo'g tulo"],
    ['fourteen', 'labing-apat', "napulo'g upat"],
    ['fifteen', 'labinlima', "napulo'g lima"],
    ['sixteen', 'labing-anim', "napulo'g unom"],
    ['seventeen', 'labimpito', "napulo'g pito"],
    ['eighteen', 'labingwalo', "napulo'g walo"],
    ['nineteen', 'labinsiyam', "napulo'g siyam"],
    ['twenty', 'dalawampu', 'baynte'],
    ['thirty', 'tatlumpu', 'traynta'],
    ['forty', 'apatnapu', 'kwarenta'],
    ['fifty', 'limampu', 'singkwenta'],
    ['hundred', 'isang daan', 'usa ka gatos'],
];

$note = 'Standard Cebuano numeral, not yet checked against local Surigaonon usage.';

$stmt = $pdo->prepare(
    'INSERT INTO bisaya_dictionary (bisaya, english, tagalog, part_of_speech, category, notes, source)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$n = 0;
foreach ($numbers as [$english, $tagalog, $bisaya]) {
    $stmt->execute([$bisaya, $english, $tagalog, 'num.', 'numbers', $note, 'BISAYA-NUMERALS']);
    $n++;
}

echo "Imported {$n} number words into bisaya_dictionary.\n";
