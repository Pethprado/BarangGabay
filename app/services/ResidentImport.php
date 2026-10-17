<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Bulk import of residents from a CSV, Excel (.xlsx) or PDF list.
 *
 * Three steps so the resident list never gets messy:
 *   1. read()    the file into rows of cells (any of the three formats);
 *   2. analyse() rows into candidates: columns recognised from the header
 *                (English or Filipino), names tidied ("DELA CRUZ, JUAN S."
 *                → "Juan S. Dela Cruz"), puroks normalised ("P-3", "PUROK 3"
 *                → "Purok 3"; a "PUROK 3" heading line sets the purok for the
 *                names under it), phones checked, and each row marked new /
 *                already registered / needs fixing;
 *   3. import()  only the rows staff ticked on the preview, re-checking for
 *                duplicates at that moment.
 *
 * Imported residents are verified (the barangay's own list) and counted in
 * their purok. Without an email they get a private placeholder address and
 * cannot sign in until they register an email; with one they can use
 * "Forgot password" to set their own.
 */
final class ResidentImport
{
    public const MAX_ROWS = 1000;
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Header words (lower-case, without punctuation) → field. */
    private const HEADERS = [
        'name' => ['name', 'full name', 'fullname', 'pangalan', 'buong pangalan', 'resident', 'residents', 'name of resident', 'complete name', 'resident name'],
        'last' => ['last name', 'lastname', 'surname', 'family name', 'apelyido'],
        'first' => ['first name', 'firstname', 'given name', 'unang pangalan'],
        'middle' => ['middle name', 'middlename', 'middle initial', 'mi', 'm i', 'gitnang pangalan'],
        'purok' => ['purok', 'zone', 'sitio', 'purok sitio', 'purok no', 'purok zone'],
        'phone' => ['phone', 'mobile', 'mobile no', 'mobile number', 'contact', 'contact no', 'contact number', 'cellphone', 'cellphone no', 'cp no', 'cp', 'cel no', 'telepono', 'phone number'],
        'address' => ['address', 'tirahan', 'house no', 'house', 'street', 'home address', 'complete address'],
        'email' => ['email', 'e mail', 'email address'],
        'sex' => ['sex', 'gender', 'kasarian'],
        'civil' => ['civil status', 'civil', 'katayuang sibil', 'marital status'],
        'dob' => ['birthdate', 'birthday', 'date of birth', 'dob', 'kaarawan', 'petsa ng kapanganakan', 'birth date'],
    ];

    // ── 1. Reading ──────────────────────────────────────────────────────

    /**
     * @return list<list<string>> rows of cells
     */
    public static function read(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv', 'txt' => self::readCsv($path),
            'xlsx'       => self::readXlsx($path),
            'pdf'        => self::readPdf($path),
            'xls'        => throw new \RuntimeException('Old Excel files (.xls) cannot be read. In Excel choose File → Save As → Excel Workbook (.xlsx) or CSV, then upload that.'),
            default      => throw new \RuntimeException('Upload a CSV, Excel (.xlsx) or PDF file.'),
        };
    }

    /** @return list<list<string>> */
    private static function readCsv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($raw, "\n") ?: '';
        $delim = ',';
        foreach ([';', "\t", '|'] as $d) {
            if (substr_count($first, $d) > substr_count($first, $delim)) {
                $delim = $d;
            }
        }
        $rows = [];
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        while (($cells = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            $rows[] = array_map(static fn ($c): string => trim((string) $c), $cells);
        }
        fclose($fh);

        return $rows;
    }

    /** First worksheet of an .xlsx file (no extra library: it is a zip of XML). */
    private static function readXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('This Excel file could not be opened.');
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si ?? [] as $si) {
                $text = (string) $si->t;
                foreach ($si->r ?? [] as $run) {
                    $text .= (string) $run->t;
                }
                $strings[] = $text;
            }
        }
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $wbDoc = simplexml_load_string($wb);
            $firstSheet = $wbDoc->sheets->sheet[0] ?? null;
            if ($firstSheet !== null) {
                $rid = (string) $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $relDoc = simplexml_load_string($rels);
                foreach ($relDoc->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                    }
                }
            }
        }
        $sheet = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheet === false) {
            throw new \RuntimeException('The first sheet of this Excel file is empty or unreadable.');
        }

        $rows = [];
        $doc = simplexml_load_string($sheet);
        foreach ($doc->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::columnIndex(preg_replace('/\d+/', '', $ref) ?: 'A');
                $type = (string) $c['t'];
                $value = match ($type) {
                    's'         => $strings[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default     => (string) $c->v,
                };
                $cells[$col] = trim($value);
            }
            if ($cells === []) {
                continue;
            }
            $line = array_fill(0, max(array_keys($cells)) + 1, '');
            foreach ($cells as $i => $v) {
                $line[$i] = $v;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** Text PDFs: one line per row, cells split on tabs or wide gaps. */
    private static function readPdf(string $path): array
    {
        try {
            $pdf  = (new \Smalot\PdfParser\Parser())->parseFile($path);
            $text = $pdf->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException('This PDF could not be read (' . $e->getMessage() . ').');
        }
        if (trim($text) === '') {
            throw new \RuntimeException('No text was found in this PDF; it is probably a scanned image. Type the list in Excel or save it as CSV, then upload that.');
        }
        $rows = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim(str_replace("\xC2\xA0", ' ', $line));
            if ($line === '') {
                continue;
            }
            $cells = preg_split('/\t+|\s{2,}/', $line) ?: [$line];
            $rows[] = array_values(array_filter(array_map('trim', $cells), static fn (string $c): bool => $c !== ''));
        }

        return $rows;
    }

    // ── 2. Understanding ────────────────────────────────────────────────

    /**
     * Rows → candidates for the preview.
     *
     * @param list<list<string>> $rows
     * @param string $defaultPurok purok for rows that have none ('' = keep none)
     * @return array{rows: list<array<string,mixed>>, header: bool, truncated: bool}
     */
    public static function analyse(array $rows, string $defaultPurok = ''): array
    {
        [$map, $headerAt] = self::findHeader($rows);
        $existing = self::existingIndex();
        $seenInFile = [];
        $out = [];
        $currentPurok = '';
        $truncated = false;

        foreach ($rows as $i => $cells) {
            if ($headerAt !== null && $i <= $headerAt) {
                continue;
            }
            $cells = array_map(static fn ($c): string => trim((string) $c), $cells);
            $filled = array_values(array_filter($cells, static fn (string $c): bool => $c !== ''));
            if ($filled === []) {
                continue;
            }
            // A heading line such as "PUROK 3" or "Zone 2 - Mabuhay": sets the purok below it.
            if (count($filled) === 1 && preg_match('/^(purok|zone|sitio)\b/i', $filled[0]) === 1) {
                $currentPurok = self::normalisePurok($filled[0]);
                continue;
            }
            $line = strtolower(implode(' ', $filled));
            if (preg_match('/^(page \d|total|grand total|prepared by|certified|noted by|republic of the philippines|province of|municipality of|barangay\s)/', $line) === 1
                || ($headerAt !== null && self::looksLikeHeader($filled))) {
                continue;
            }
            if (count($out) >= self::MAX_ROWS) {
                $truncated = true;
                break;
            }

            $c = $map !== [] ? self::fromColumns($cells, $map) : self::guess($filled);
            $c['source_line'] = $i + 1;

            $purokRaw = $c['purok'] ?? '';
            $purok = $purokRaw !== '' ? self::normalisePurok($purokRaw) : ($currentPurok !== '' ? $currentPurok : $defaultPurok);
            $c['purok'] = $purok;
            $c['issues'] = [];
            if ($purokRaw !== '' && $purok === '') {
                $c['issues'][] = "Purok \"{$purokRaw}\" is not one of the barangay's puroks; choose one.";
            }

            [$c['full_name'], $c['first_name'], $c['middle_name'], $c['last_name']] = self::name($c);
            $c['phone'] = self::phone($c['phone'] ?? '', $c['issues']);
            $c['email'] = self::email($c['email'] ?? '', $c['issues']);
            $c['sex'] = self::sex($c['sex'] ?? '');
            $c['civil_status'] = self::civil($c['civil'] ?? '');
            $c['date_of_birth'] = self::date($c['dob'] ?? '');
            $c['address'] = mb_substr(trim((string) ($c['address'] ?? '')), 0, 255);
            unset($c['name'], $c['last'], $c['first'], $c['middle'], $c['civil'], $c['dob']);

            if ($c['full_name'] === '' || preg_match('/\p{L}{2,}/u', $c['full_name']) !== 1) {
                $c['status'] = 'invalid';
                $c['issues'][] = 'No name could be read from this line.';
            } else {
                $key = self::nameKey($c['full_name']);
                $dup = $existing['email'][$c['email']] ?? ($existing['name'][$key] ?? null);
                if ($c['email'] !== '' && isset($existing['email'][$c['email']])) {
                    $dup = $existing['email'][$c['email']];
                }
                if ($dup !== null) {
                    $c['status'] = 'duplicate';
                    $c['duplicate_of'] = $dup;
                } elseif (isset($seenInFile[$key])) {
                    $c['status'] = 'duplicate';
                    $c['duplicate_of'] = ['id' => 0, 'name' => $c['full_name'] . ' (twice in this file, line ' . $seenInFile[$key] . ')'];
                } else {
                    $c['status'] = 'new';
                    $seenInFile[$key] = $c['source_line'];
                }
            }
            $out[] = $c;
        }

        return ['rows' => $out, 'header' => $headerAt !== null, 'truncated' => $truncated];
    }

    /** @return array{0: array<string,int>, 1: ?int} column map and header row index */
    private static function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 15, true) as $i => $cells) {
            $map = [];
            foreach ($cells as $col => $cell) {
                $field = self::headerField((string) $cell);
                if ($field !== null && !isset($map[$field])) {
                    $map[$field] = $col;
                }
            }
            if (isset($map['name']) || isset($map['last']) || (isset($map['first']) && count($map) >= 2)) {
                return [$map, $i];
            }
        }

        return [[], null];
    }

    private static function headerField(string $cell): ?string
    {
        $norm = trim((string) preg_replace('/[^a-z ]+/', ' ', strtolower($cell)));
        $norm = (string) preg_replace('/\s+/', ' ', $norm);
        if ($norm === '') {
            return null;
        }
        foreach (self::HEADERS as $field => $words) {
            if (in_array($norm, $words, true)) {
                return $field;
            }
        }

        return null;
    }

    private static function looksLikeHeader(array $filled): bool
    {
        $hits = 0;
        foreach ($filled as $cell) {
            if (self::headerField($cell) !== null) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    /** @return array<string,string> */
    private static function fromColumns(array $cells, array $map): array
    {
        $c = [];
        foreach ($map as $field => $col) {
            $c[$field] = trim((string) ($cells[$col] ?? ''));
        }

        return $c;
    }

    /** No header: recognise each cell by its look. @return array<string,string> */
    private static function guess(array $cells): array
    {
        $c = ['name' => ''];
        foreach ($cells as $cell) {
            $digits = preg_replace('/\D/', '', $cell) ?? '';
            if (preg_match('/^\d{1,4}\.?$/', $cell) === 1) {
                continue;                                    // row number
            }
            if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                $c['email'] = $cell;
            } elseif (preg_match('/^(\+?63|0)?9\d{9}$/', $digits) === 1 && strlen($digits) >= 10) {
                $c['phone'] = $cell;
            } elseif (preg_match('/^(purok|zone|sitio|p)\s*[-#.]?\s*\d+/i', $cell) === 1) {
                $c['purok'] = $cell;
            } elseif (preg_match('/^(m|f|male|female|lalaki|babae)$/i', $cell) === 1) {
                $c['sex'] = $cell;
            } elseif (self::date($cell) !== '') {
                $c['dob'] = $cell;
            } elseif (preg_match('/^(single|married|widowed|widow|separated|annulled|live-?in)$/i', $cell) === 1) {
                $c['civil'] = $cell;
            } elseif ($c['name'] === '' && preg_match('/\p{L}{2,}/u', $cell) === 1) {
                $c['name'] = $cell;
            } elseif (!isset($c['address']) && preg_match('/\p{L}/u', $cell) === 1) {
                $c['address'] = $cell;
            }
        }

        return $c;
    }

    /** "Purok 3", "P-3", "3", "PUROK-3 BAYOGO" → "Purok 3" (only the barangay's own puroks). */
    public static function normalisePurok(string $raw): string
    {
        $raw = trim($raw);
        $known = barangay_subdivisions();
        foreach ($known as $name) {
            if (strcasecmp($raw, $name) === 0) {
                return $name;
            }
        }
        if (preg_match('/^(?:purok|zone|sitio|p)?\s*[-#.]?\s*(\d{1,2})\b/i', $raw, $m) === 1) {
            $candidate = 'Purok ' . (int) $m[1];
            if (in_array($candidate, $known, true)) {
                return $candidate;
            }
        }

        return '';
    }

    /** @return array{0:string,1:string,2:string,3:string} full, first, middle, last */
    public static function name(array $c): array
    {
        $tidy = static function (string $s): string {
            $s = trim((string) preg_replace('/\s+/', ' ', $s), " ,.-");
            if ($s !== '' && mb_strtoupper($s) === $s) {
                $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
            }

            return $s;
        };
        $first  = $tidy((string) ($c['first'] ?? ''));
        $middle = $tidy((string) ($c['middle'] ?? ''));
        $last   = $tidy((string) ($c['last'] ?? ''));

        if ($first === '' && $last === '' && trim((string) ($c['name'] ?? '')) !== '') {
            $name = trim((string) preg_replace('/\s+/', ' ', (string) $c['name']));
            if (str_contains($name, ',')) {
                [$l, $rest] = array_map('trim', explode(',', $name, 2));
                $last  = $tidy($l);
                $parts = preg_split('/\s+/', $tidy($rest)) ?: [];
                if (count($parts) >= 2 && preg_match('/^\p{L}\.?$/u', (string) end($parts)) === 1) {
                    $middle = rtrim((string) array_pop($parts), '.') . '.';
                }
                $first = implode(' ', $parts);
            } else {
                $parts = preg_split('/\s+/', $tidy($name)) ?: [];
                foreach ($parts as $k => $p) {
                    if ($k > 0 && $k < count($parts) - 1 && preg_match('/^\p{L}\.$/u', $p) === 1) {
                        $first  = implode(' ', array_slice($parts, 0, $k));
                        $middle = $p;
                        $last   = implode(' ', array_slice($parts, $k + 1));
                        break;
                    }
                }
                if ($first === '' && $last === '') {
                    return [mb_substr($tidy($name), 0, 150), '', '', ''];
                }
            }
        }
        if ($middle !== '' && mb_strlen(rtrim($middle, '.')) === 1) {
            $middle = rtrim($middle, '.') . '.';
        }
        $full = trim(preg_replace('/\s+/', ' ', "{$first} {$middle} {$last}") ?? '');

        return [mb_substr($full, 0, 150), mb_substr($first, 0, 100), mb_substr($middle, 0, 100), mb_substr($last, 0, 100)];
    }

    private static function phone(string $raw, array &$issues): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $d = preg_replace('/\D/', '', $raw) ?? '';
        if (str_starts_with($d, '63') && strlen($d) === 12) {
            $d = '0' . substr($d, 2);
        } elseif (str_starts_with($d, '9') && strlen($d) === 10) {
            $d = '0' . $d;
        }
        if (preg_match('/^09\d{9}$/', $d) === 1) {
            return $d;
        }
        $issues[] = "Phone \"{$raw}\" is not a Philippine mobile number; it will be left blank.";

        return '';
    }

    private static function email(string $raw, array &$issues): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }
        $issues[] = "Email \"{$raw}\" is not valid; it will be left blank.";

        return '';
    }

    private static function sex(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'm', 'male', 'lalaki' => 'male',
            'f', 'female', 'babae' => 'female',
            default => '',
        };
    }

    private static function civil(string $raw): string
    {
        $r = strtolower(trim($raw));
        return match (true) {
            str_starts_with($r, 'single') => 'single',
            str_starts_with($r, 'married') || $r === 'kasal' => 'married',
            str_starts_with($r, 'widow') || $r === 'balo' => 'widowed',
            str_starts_with($r, 'separated') || str_starts_with($r, 'annul') => 'separated',
            default => '',
        };
    }

    /** Birthdate in common formats, or an Excel date number → Y-m-d; '' when not a date. */
    private static function date(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{5}(\.\d+)?$/', $raw) === 1) {           // Excel serial
            $t = ((int) $raw - 25569) * 86400;
            return $t > -2208988800 ? gmdate('Y-m-d', $t) : '';
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m) === 1) {   // mm/dd/yyyy (PH usage)
            return checkdate((int) $m[1], (int) $m[2], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]) : '';
        }
        if (preg_match('/\d{4}/', $raw) === 1 && preg_match('/[a-z]{3}|\d{4}-\d{2}-\d{2}/i', $raw) === 1) {
            $t = strtotime($raw);
            if ($t !== false && $t < time() && $t > strtotime('-120 years')) {
                return date('Y-m-d', $t);
            }
        }

        return '';
    }

    public static function nameKey(string $name): string
    {
        $k = mb_strtolower($name);
        $k = preg_replace('/\b\p{L}\.\s*/u', '', $k) ?? $k;    // drop middle initials
        return preg_replace('/[^\p{L}]+/u', '', $k) ?? $k;
    }

    /** @return array{name: array<string,array{id:int,name:string}>, email: array<string,array{id:int,name:string}>} */
    private static function existingIndex(): array
    {
        $idx = ['name' => [], 'email' => []];
        foreach (db()->query("SELECT id, full_name, email FROM users WHERE role = 'resident'")->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $ref = ['id' => (int) $u['id'], 'name' => (string) $u['full_name']];
            $idx['name'][self::nameKey((string) $u['full_name'])] = $ref;
            $idx['email'][strtolower((string) $u['email'])] = $ref;
        }

        return $idx;
    }

    // ── 3. Importing ────────────────────────────────────────────────────

    /**
     * Create the ticked rows (verified residents in their purok).
     *
     * @param list<array<string,mixed>> $rows candidates from analyse()
     * @param array<int,string> $chosen row index => purok (only ticked rows)
     * @return array{created:int, skipped:int, ids:list<int>}
     */
    public static function import(array $rows, array $chosen, int $by): array
    {
        $pdo = db();
        $existing = self::existingIndex();
        $created = 0;
        $skipped = 0;
        $ids = [];

        foreach ($chosen as $i => $purok) {
            $r = $rows[$i] ?? null;
            if ($r === null || $r['status'] === 'invalid') {
                $skipped++;
                continue;
            }
            $key = self::nameKey((string) $r['full_name']);
            // A row shown as "new" that has been registered since the preview is
            // skipped. A row shown as "already registered" that staff ticked
            // anyway is a different person with the same name: import it. An
            // email is never used twice.
            $emailTaken = $r['email'] !== '' && isset($existing['email'][$r['email']]);
            if ($emailTaken || ($r['status'] === 'new' && isset($existing['name'][$key]))) {
                $skipped++;
                continue;
            }
            $purok = self::normalisePurok((string) $purok) !== '' ? self::normalisePurok((string) $purok) : '';
            $email = $r['email'] !== '' ? $r['email'] : 'resident-' . bin2hex(random_bytes(6)) . '@' . \App\Models\Purok::NO_EMAIL_DOMAIN;

            self::insertExisting($pdo, 'users', [
                'full_name' => $r['full_name'], 'first_name' => $r['first_name'] ?: null, 'middle_name' => $r['middle_name'] ?: null,
                'last_name' => $r['last_name'] ?: null, 'email' => $email,
                'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT),
                'phone' => $r['phone'] ?: null, 'address' => $r['address'] ?: null, 'zone' => $purok ?: null,
                'sex' => $r['sex'] ?: null, 'civil_status' => $r['civil_status'] ?: null, 'date_of_birth' => $r['date_of_birth'] ?: null,
                'role' => 'resident', 'status' => 'verified', 'email_verified' => 1,
                'created_at' => true, 'updated_at' => true,
            ]);
            $id = (int) $pdo->lastInsertId();
            $ids[] = $id;
            $existing['name'][$key] = ['id' => $id, 'name' => $r['full_name']];
            if ($r['email'] !== '') {
                $existing['email'][$r['email']] = ['id' => $id, 'name' => $r['full_name']];
            }
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'ids' => $ids];
    }

    /** A CSV template with the recognised columns and two example rows. */
    public static function templateCsv(): string
    {
        $rows = [
            ['Name', 'Purok', 'Phone', 'Address', 'Email', 'Sex', 'Civil status', 'Birthdate'],
            ['DELA CRUZ, JUAN S.', 'Purok 1', '09171234567', 'House 12, near the chapel', '', 'Male', 'Married', '03/14/1985'],
            ['Maria T. Santos', 'Purok 2', '', '', 'maria@example.com', 'Female', 'Single', '1999-07-02'],
        ];
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($fh, $r, ',', '"', '\\');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }

    /** INSERT only the columns this database has (PostgreSQL may lack some). true = NOW(). */
    private static function insertExisting(PDO $pdo, string $table, array $row): void
    {
        static $columns = [];
        if (!isset($columns[$table])) {
            $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
            $stmt = $pdo->prepare($isPgsql
                ? 'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?'
                : 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $columns[$table] = array_flip(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }
        $names = $marks = $args = [];
        foreach ($row as $name => $value) {
            if (!isset($columns[$table][strtolower($name)])) {
                continue;
            }
            $names[] = $name;
            if ($value === true) {
                $marks[] = 'NOW()';
            } else {
                $marks[] = '?';
                $args[]  = $value;
            }
        }
        $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $marks) . ')')->execute($args);
    }
}
