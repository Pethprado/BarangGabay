<?php
declare(strict_types=1);

/**
 * Rewrite the old location in rows that are already saved.
 *
 *   php tools/relocate-to-bayogo.php            # show what would change
 *   php tools/relocate-to-bayogo.php --apply    # ask, then write
 *
 * The code and the interface strings moved with the relocation edit; this is
 * for the content a staff member typed before the move — announcements naming
 * "Zone 3", an event at a venue in Lanuza, the settings rows, the purok every
 * resident picked at registration.
 *
 * Run it once. It is safe to run again — after it succeeds nothing matches the
 * search patterns any more — but it is not a migration and must never become
 * one: migrations replay on every database connection, and silently rewriting
 * somebody's announcement on a page load is not something a system should do.
 *
 * ── What it deliberately does NOT do ────────────────────────────────────
 *
 * Sample rows (is_sample = 1) are reported but never rewritten. They are demo
 * content with a purge command of their own, and regenerating them is cleaner
 * than patching strings inside them:
 *
 *     php tools/seed-sample-content.php --purge
 *     php tools/seed-sample-content.php
 *
 * Every write is a prepared statement. The column names come from the
 * hardcoded map below and never from input — a column name cannot be
 * parameterised in SQL, so the only safe source for one is a literal in this
 * file.
 */

$root = \dirname(__DIR__);
require $root . '/vendor/autoload.php';
if (class_exists(\Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
require $root . '/app/helpers.php';
require $root . '/config/database.php';

$apply = in_array('--apply', $argv, true);

// ── The replacements ────────────────────────────────────────────────────
//
// Ordered longest-first. "District Zone 3, Lanuza" has to be matched before
// the bare "Zone 3" inside it, or the longer phrase is left half-rewritten.
//
// Note what is absent: a rule for the bare word "zone". It appears inside
// "timezone" and in CSS class names, and a blind replace would corrupt both.
const REPLACEMENTS = [
    'District Zone 3, Lanuza, Surigao del Sur' => 'Barangay Bayogo, Madrid, Surigao del Sur',
    'Barangay Zone 3, Lanuza, Surigao del Sur' => 'Barangay Bayogo, Madrid, Surigao del Sur',
    'Barangay District Zone 3, Lanuza'         => 'Barangay Bayogo, Madrid',
    'District Zone 3, Lanuza'                  => 'Barangay Bayogo, Madrid',
    'Barangay Zone 3, Lanuza'                  => 'Barangay Bayogo, Madrid',
    'Zone 3, Lanuza'                           => 'Barangay Bayogo, Madrid',
    'Zone 3 Lanuza'                            => 'Barangay Bayogo, Madrid',
    // Venue phrasings, matched before the bare "Zone 3" below. Without these,
    // "Barangay Hall Zone 3" becomes "Barangay Hall Barangay Bayogo".
    'Barangay Hall Zone 3'                     => 'Barangay Hall, Bayogo',
    'Barangay Hall, Zone 3'                    => 'Barangay Hall, Bayogo',
    'Covered Court, Zone 3'                    => 'Covered Court, Bayogo',
    'Barangay Zone 3'                          => 'Barangay Bayogo',
    'District Zone 3'                          => 'Barangay Bayogo',
    'Brgy. Lanuza'                             => 'Brgy. Bayogo',
    'Barangay Lanuza'                          => 'Barangay Bayogo',
    'Zone 3'                                   => 'Barangay Bayogo',
    'Lanuza'                                   => 'Bayogo',
    'Cantilan'                                 => 'Madrid',
];

// ── Which columns hold text a resident reads ────────────────────────────
//
// Includes every translated column, because a post whose Filipino body was
// corrected while its English and Manobo copies still say Lanuza is worse than
// one that is consistently wrong — a resident switching language would watch
// the place name change under them.
const TEXT_COLUMNS = [
    'announcements' => [
        'title', 'title_en', 'title_fil', 'title_manobo',
        'body',  'body_en',  'body_fil',  'body_manobo',
    ],
    'events' => [
        'title',       'title_en',       'title_fil',       'title_manobo',
        'description', 'description_en', 'description_fil', 'description_manobo',
        'venue',
    ],
    'ordinances' => [
        'title',       'title_en',       'title_fil',       'title_manobo',
        'description', 'description_en', 'description_fil', 'description_manobo',
        'ai_summary',
    ],
];

/**
 * Apply every replacement, longest phrase first, then tidy.
 *
 * The tidy pass exists because the replacements above can meet each other:
 * a venue written "Barangay Hall Zone 3" would otherwise come out as
 * "Barangay Hall Barangay Bayogo". The specific venue rules catch the common
 * shapes; this catches the rest rather than trying to enumerate them all.
 */
function relocate_text(string $value): string
{
    $out = str_replace(array_keys(REPLACEMENTS), array_values(REPLACEMENTS), $value);

    // "Barangay X Barangay Bayogo" → "Barangay X, Bayogo"
    $out = preg_replace('/\bBarangay\s+(\S[^,]{0,30}?)\s+Barangay Bayogo\b/u', 'Barangay $1, Bayogo', $out) ?? $out;
    // A plain doubling, from two rules both supplying the word.
    $out = preg_replace('/\bBarangay\s+Barangay\b/u', 'Barangay', $out) ?? $out;

    return $out;
}

function say(string $line = ''): void
{
    echo $line . "\n";
}

function heading(string $text): void
{
    say();
    say('── ' . $text . ' ' . str_repeat('─', max(0, 66 - mb_strlen($text))));
}

/** Columns that actually exist, so a partly-migrated database does not fatal. */
function existing_columns(string $table, array $wanted): array
{
    try {
        $have = db()->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
    } catch (\Throwable $e) {
        return [];
    }

    return array_values(array_intersect($wanted, $have));
}

$pdo = db();

say('BarangGabay — relocation to Barangay Bayogo, Madrid');
say($apply
    ? 'Mode: APPLY (you will be asked to confirm before anything is written)'
    : 'Mode: DRY RUN — nothing will be written. Add --apply to make the changes.');

// ── 1. Settings ─────────────────────────────────────────────────────────

heading('Settings');

$settingTargets = [
    'location_name' => 'Barangay Bayogo',
    'location_full' => 'Barangay Bayogo, Madrid, Surigao del Sur',
];

$settingChanges = [];

foreach ($settingTargets as $key => $want) {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        say(sprintf('  %-15s (not set — will be inserted as "%s")', $key, $want));
        $settingChanges[$key] = $want;
        continue;
    }
    if ((string) $current === $want) {
        say(sprintf('  %-15s already correct', $key));
        continue;
    }

    say(sprintf('  %-15s "%s"', $key, $current));
    say(sprintf('  %-15s  →  "%s"', '', $want));
    $settingChanges[$key] = $want;
}

// ── 2. users.zone ───────────────────────────────────────────────────────

heading('Resident purok / zone');

$zoneRows = $pdo->query(
    "SELECT zone, COUNT(*) AS n FROM users
      WHERE zone IS NOT NULL AND zone <> '' GROUP BY zone ORDER BY n DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$zoneTotal = 0;
foreach ($zoneRows as $row) {
    $zoneTotal += (int) $row['n'];
    say(sprintf('  %-22s %d user(s)', $row['zone'], $row['n']));
}

if ($zoneRows === []) {
    say('  no resident has a zone recorded — nothing to do');
} else {
    say();
    say('  These name subdivisions of the system\'s OLD location, so they are cleared');
    say('  rather than renamed. Bayogo\'s real puroks are not known to this script and');
    say('  it will not invent any; residents can enter theirs on the profile page, and');
    say('  filling in barangay_subdivisions() in app/helpers.php restores a dropdown.');
}

// ── 3. Content ──────────────────────────────────────────────────────────

$plan   = [];   // table => [ id => [column => newValue] ]
$sample = [];   // table => count of is_sample rows that were skipped

foreach (TEXT_COLUMNS as $table => $wanted) {
    $columns = existing_columns($table, $wanted);
    if ($columns === []) {
        continue;
    }

    $hasSampleFlag = existing_columns($table, ['is_sample']) !== [];
    $select        = 'id, ' . ($hasSampleFlag ? 'is_sample, ' : '') . implode(', ', $columns);

    foreach ($pdo->query("SELECT {$select} FROM {$table}")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $changed = [];

        foreach ($columns as $column) {
            $value = $row[$column];
            if ($value === null || $value === '') {
                continue;
            }
            $new = relocate_text((string) $value);
            if ($new !== (string) $value) {
                $changed[$column] = $new;
            }
        }

        if ($changed === []) {
            continue;
        }

        if ($hasSampleFlag && (int) $row['is_sample'] === 1) {
            $sample[$table] = ($sample[$table] ?? 0) + 1;
            continue;
        }

        $plan[$table][(int) $row['id']] = $changed;
    }
}

heading('Content written by staff');

foreach (array_keys(TEXT_COLUMNS) as $table) {
    $rows = $plan[$table] ?? [];
    say(sprintf('  %-15s %d row(s) to update', $table, count($rows)));

    foreach ($rows as $id => $changed) {
        say(sprintf('    #%-4d %s', $id, implode(', ', array_keys($changed))));

        // One before/after per row, so the operator sees the shape of the
        // change without the whole body scrolling past.
        $firstCol = array_key_first($changed);
        $before   = (string) $pdo->query('SELECT ' . $firstCol . ' FROM ' . $table . ' WHERE id = ' . (int) $id)->fetchColumn();
        say('          was: ' . mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($before)) ?? ''), 0, 84));
        say('          now: ' . mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($changed[$firstCol])) ?? ''), 0, 84));
    }
}

if ($sample !== []) {
    heading('Sample rows — NOT touched');
    foreach ($sample as $table => $n) {
        say(sprintf('  %-15s %d sample row(s) still mention the old location', $table, $n));
    }
    say();
    say('  These are seeded demo posts. Rewriting strings inside them is messier than');
    say('  regenerating them, and the seeder already produces Bayogo content:');
    say();
    say('      php tools/seed-sample-content.php --purge');
    say('      php tools/seed-sample-content.php');
}

// ── 4. Write ────────────────────────────────────────────────────────────

$contentRows = array_sum(array_map('count', $plan));
$totalWrites = count($settingChanges) + $contentRows + ($zoneTotal > 0 ? 1 : 0);

heading('Summary');
say(sprintf('  settings        %d change(s)', count($settingChanges)));
say(sprintf('  users.zone      %d user(s) to clear', $zoneTotal));
say(sprintf('  content rows    %d', $contentRows));

if ($totalWrites === 0) {
    say();
    say('Nothing to change.');
    exit(0);
}

if (!$apply) {
    say();
    say('Dry run. Re-run with --apply to write these changes.');
    exit(0);
}

say();
echo 'Write these changes? Type "yes" to continue: ';
$answer = trim((string) fgets(STDIN));

if (strtolower($answer) !== 'yes') {
    say('Cancelled. Nothing was written.');
    exit(0);
}

$pdo->beginTransaction();

try {
    foreach ($settingChanges as $key => $value) {
        $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value, value_type)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $value, 'string']);
    }

    $zoneCleared = 0;
    if ($zoneTotal > 0) {
        $stmt = $pdo->prepare("UPDATE users SET zone = NULL WHERE zone IS NOT NULL AND zone <> ''");
        $stmt->execute();
        $zoneCleared = $stmt->rowCount();
    }

    $written = [];
    foreach ($plan as $table => $rows) {
        foreach ($rows as $id => $changed) {
            // Column names come from TEXT_COLUMNS, a literal in this file;
            // every value is bound.
            $set    = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($changed)));
            $params = array_values($changed);
            $params[] = $id;

            $pdo->prepare("UPDATE {$table} SET {$set} WHERE id = ?")->execute($params);
            $written[$table] = ($written[$table] ?? 0) + 1;
        }
    }

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    say();
    say('FAILED — nothing was written: ' . $e->getMessage());
    exit(1);
}

heading('Done');
say(sprintf('  settings        %d row(s) written', count($settingChanges)));
say(sprintf('  users.zone      %d row(s) cleared', $zoneCleared));
foreach (array_keys(TEXT_COLUMNS) as $table) {
    say(sprintf('  %-15s %d row(s) updated', $table, $written[$table] ?? 0));
}
say();
say('Settings cache is per-request, so the site picks these up on the next page load.');
