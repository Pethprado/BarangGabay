<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Offline Manobo (ISO 639-3: msm) <-> English <-> Tagalog lookup.
 *
 * This is a dictionary lookup, NOT a translator. It resolves words and fixed
 * phrases that exist in the manobo_dictionary table and reports honestly when
 * something is missing. It does not inflect verbs, apply the affix system
 * described in the source grammar, or reorder words, so a word-by-word result
 * is a gloss chain and should be labelled as such in the UI rather than
 * presented as a finished sentence.
 *
 * It is deliberately independent of AIService/TranslationService: those call
 * the Anthropic API (and target a different Manobo variant), whereas this
 * class works with no network and no API credits.
 *
 * CHANGED: this class used to read/write data/manobo/manobo_dictionary.csv
 * directly (see migration 030's header comment for why it moved). The
 * matching/normalisation logic below — lookup(), translate(), normalise(),
 * search(), render(), etc. — is UNCHANGED from the CSV version; only load()
 * and the write methods (persist -> real INSERT/UPDATE/soft-DELETE) changed,
 * because that logic never cared where the rows came from.
 *
 * Soft delete: deleteEntry() sets deleted_at rather than removing the row —
 * see trash()/restore()/forceDelete() for the Trash feature this enables.
 * Every read method (all/lookup/translate/search/...) only ever sees rows
 * with deleted_at IS NULL, so a trashed word behaves exactly like the old
 * hard-deleted one from every caller's point of view.
 *
 * @see data/manobo/README.md for sources, licensing and orthography notes.
 */
final class ManoboDictionary
{
    /** Languages this dictionary can read from and write to. */
    public const LANGUAGES = ['manobo', 'english', 'tagalog'];

    /** Dictionary columns a form/import row is keyed by. */
    public const FIELDS = [
        'manobo',
        'english',
        'tagalog',
        'bisaya',
        'part_of_speech',
        'category',
        'notes',
        'source',
        'source_page',
        'review_status',
        'needs_review',
        'aliases',
        'type',
        'priority',
    ];

    private PDO $pdo;

    /** @var list<array<string,string>> Every live row, id ascending. */
    private array $entries = [];

    /**
     * Lookup indexes, one per language.
     * Shape: [language => [normalised term => list<int> entry indexes]]
     *
     * @var array<string, array<string, list<int>>>
     */
    private array $index = [];

    /** In-process cache so repeated instantiation in one request is cheap. */
    private static array $cache = [];

    /** Bump this when a write happens, so every instance's cache invalidates. */
    private static int $generation = 0;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
        $this->load();
    }

    // ── Public API ───────────────────────────────────────────────────

    /**
     * Look up a single word or fixed phrase.
     *
     * @param string      $term Word or phrase to look up.
     * @param string|null $from Source language, or null to auto-detect.
     *
     * @return array{
     *     found: bool,
     *     query: string,
     *     from: string|null,
     *     entries: list<array<string,string>>,
     *     ambiguous: bool
     * }
     */
    public function lookup(string $term, ?string $from = null): array
    {
        $normalised = $this->normalise($term);
        $languages  = $from !== null ? [$this->assertLanguage($from)] : self::LANGUAGES;

        $hits = [];
        foreach ($languages as $language) {
            foreach ($this->index[$language][$normalised] ?? [] as $position) {
                $hits[$position] = $this->entries[$position];
            }
            // Stop at the first language that matched so an auto-detected
            // Manobo word is not also reported as an English one.
            if ($hits !== []) {
                $from = $language;
                break;
            }
        }

        return [
            'found'     => $hits !== [],
            'query'     => $term,
            'from'      => $hits !== [] ? $from : null,
            'entries'   => array_values($hits),
            // Stress and the final glottal stop are meaningful in Manobo but
            // rarely typed, so one plain-text query can legitimately hit two
            // different words (baka "cow" vs bakà "jaw").
            'ambiguous' => \count($hits) > 1,
        ];
    }

    /**
     * Translate a word, phrase or short sentence.
     *
     * Tries the whole input as one phrase first, then falls back to looking up
     * each word on its own. Never throws for unknown input — unresolved words
     * are returned in the `missing` list and left in place in `text`.
     *
     * @param string      $text Input text.
     * @param string      $to   Target language.
     * @param string|null $from Source language, or null to auto-detect.
     *
     * @return array{
     *     found: bool,
     *     match_type: string,
     *     query: string,
     *     from: string|null,
     *     to: string,
     *     text: string|null,
     *     tokens: list<array<string,mixed>>,
     *     missing: list<string>,
     *     ambiguous: bool,
     *     message: string|null
     * }
     */
    public function translate(string $text, string $to, ?string $from = null): array
    {
        $to    = $this->assertLanguage($to);
        $from  = $from !== null ? $this->assertLanguage($from) : null;
        $query = trim($text);

        $blank = [
            'found'      => false,
            'match_type' => 'none',
            'query'      => $query,
            'from'       => $from,
            'to'         => $to,
            'text'       => null,
            'tokens'     => [],
            'missing'    => [],
            'ambiguous'  => false,
            'message'    => null,
        ];

        if ($query === '') {
            return ['message' => 'No text was given.'] + $blank;
        }

        // 1. Whole-phrase match.
        $phrase = $this->lookup($query, $from);
        if ($phrase['found']) {
            $renderings = $this->render($phrase['entries'], $to);

            return [
                'found'      => $renderings !== [],
                'match_type' => 'phrase',
                'query'      => $query,
                'from'       => $phrase['from'],
                'to'         => $to,
                'text'       => $renderings[0] ?? null,
                'tokens'     => [[
                    'token'   => $query,
                    'found'   => $renderings !== [],
                    'entries' => $phrase['entries'],
                ]],
                'missing'    => $renderings === [] ? [$query] : [],
                'ambiguous'  => $phrase['ambiguous'],
                'message'    => $phrase['ambiguous']
                    ? 'More than one entry matches this spelling. Check the notes on each entry.'
                    : null,
            ];
        }

        // 2. Word-by-word fallback.
        $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (\count($words) < 2) {
            return [
                'message' => \sprintf('No translation found for "%s".', $query),
            ] + array_merge($blank, ['missing' => [$query]]);
        }

        $tokens    = [];
        $missing   = [];
        $rendered  = [];
        $ambiguous = false;
        $sources   = [];

        foreach ($words as $word) {
            $bare = $this->stripPunctuation($word);
            $hit  = $bare === '' ? ['found' => false] : $this->lookup($bare, $from);

            if (($hit['found'] ?? false) === true) {
                $renderings = $this->render($hit['entries'], $to);
            } else {
                $renderings = [];
            }

            if ($renderings !== []) {
                $rendered[]  = $renderings[0];
                $ambiguous   = $ambiguous || $hit['ambiguous'];
                $sources[]   = $hit['from'];
                $tokens[]    = ['token' => $word, 'found' => true, 'entries' => $hit['entries']];
            } else {
                // Keep the original word so the shape of the sentence survives.
                $rendered[] = $word;
                $missing[]  = $word;
                $tokens[]   = ['token' => $word, 'found' => false, 'entries' => []];
            }
        }

        $anyFound = \count($missing) < \count($words);

        return [
            'found'      => $anyFound,
            'match_type' => $anyFound ? 'word-by-word' : 'none',
            'query'      => $query,
            'from'       => $from ?? ($sources[0] ?? null),
            'to'         => $to,
            'text'       => $anyFound ? implode(' ', $rendered) : null,
            'tokens'     => $tokens,
            'missing'    => $missing,
            'ambiguous'  => $ambiguous,
            'message'    => $this->fallbackMessage($anyFound, $missing, $query),
        ];
    }

    /**
     * Every live entry in the dataset, id ascending.
     *
     * @return list<array<string,string>>
     */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Alias for all() for backwards compatibility with voice training and dataset hub.
     *
     * @return list<array<string,string>>
     */
    public function getAllWords(): array
    {
        return $this->all();
    }

    /**
     * Entries filtered by category (e.g. "health", "numbers").
     *
     * @return list<array<string,string>>
     */
    public function byCategory(string $category): array
    {
        $needle = $this->normalise($category);

        return array_values(array_filter(
            $this->entries,
            fn (array $entry): bool => $this->normalise($entry['category'] ?? '') === $needle
        ));
    }

    /**
     * Distinct category names present in the dataset, sorted.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        $categories = array_unique(array_filter(array_column($this->entries, 'category')));
        sort($categories);

        return array_values($categories);
    }

    /** Number of live entries loaded. */
    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * Entries carrying a non-empty note — the "Kailangang i-verify" worklist.
     * The `notes` field has always been where an uncertain entry is flagged
     * (see data/manobo/README.md); this just surfaces every such row in one
     * list instead of leaving it to be spotted while scrolling the full table.
     *
     * @return list<array<string,string>>
     */
    public function needsVerification(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => trim($entry['notes'] ?? '') !== ''
        ));
    }

    /**
     * Soft-deleted entries — the Trash.
     *
     * @return list<array<string,string>>
     */
    public function trash(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM manobo_dictionary WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC"
        );

        return array_map([$this, 'castRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Restore a soft-deleted entry by id.
     *
     * @throws \InvalidArgumentException When no trashed entry has this id.
     */
    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE manobo_dictionary SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL'
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new \InvalidArgumentException('No trashed entry with id ' . $id . '.');
        }
        $this->reload();
    }

    /**
     * Permanently remove a soft-deleted entry. Admin-only at the controller
     * level — this cannot be undone.
     *
     * @throws \InvalidArgumentException When no trashed entry has this id.
     */
    public function forceDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM manobo_dictionary WHERE id = ? AND deleted_at IS NOT NULL'
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new \InvalidArgumentException('No trashed entry with id ' . $id . '.');
        }
        $this->reload();
    }

    /**
     * Export the live dataset as JSON — used by the admin "Export" download
     * and by tools/build-manobo-json.php's successor. The CSV is no longer
     * the source of truth, so this is a snapshot, not a sync target.
     */
    public function exportJson(string $path): bool
    {
        $payload = [
            'language'     => ['name' => 'Manobo', 'iso_639_3' => 'msm'],
            'entry_count'  => $this->count(),
            'generated_at' => date('c'),
            'generated_by' => 'App\\Services\\ManoboDictionary::exportJson() from the manobo_dictionary table',
            'notice'       => 'Original compilation for community/educational use. '
                . 'See data/manobo/README.md for sources and licensing.',
            'entries'      => $this->entries,
        ];

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $json !== false && file_put_contents($path, $json . "\n") !== false;
    }

    // ── Write API (used by the admin curation screen) ────────────────

    /**
     * Append a new entry.
     *
     * @param  array<string,string> $data Keyed by self::FIELDS.
     * @param  int|null $userId Who added it, for created_by.
     * @throws \InvalidArgumentException When required fields are missing or the
     *                                   headword already exists.
     */
    public function addEntry(array $data, ?int $userId = null): int
    {
        $entry = $this->validated($data);

        $normTagalog = mb_strtolower(trim($entry['tagalog'] ?? ''));
        $normEnglish = mb_strtolower(trim($entry['english'] ?? ''));
        $normBisaya  = mb_strtolower(trim($entry['bisaya'] ?? ''));

        $stmt = $this->pdo->prepare(
            'INSERT INTO manobo_dictionary
                (manobo, english, tagalog, bisaya, normalized_tagalog, normalized_english, normalized_bisaya, 
                 part_of_speech, category, notes, source, source_page, review_status, needs_review, 
                 aliases, type, priority, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $entry['manobo'], $entry['english'], $entry['tagalog'],
            $entry['bisaya'] !== '' ? $entry['bisaya'] : null,
            $normTagalog !== '' ? $normTagalog : null,
            $normEnglish !== '' ? $normEnglish : null,
            $normBisaya !== '' ? $normBisaya : null,
            $entry['part_of_speech'] !== '' ? $entry['part_of_speech'] : null,
            $entry['category'] !== '' ? $entry['category'] : 'other',
            $entry['notes'] !== '' ? $entry['notes'] : null,
            $entry['source'],
            !empty($entry['source_page']) ? (int) $entry['source_page'] : null,
            $entry['review_status'] !== '' ? $entry['review_status'] : 'pending_review',
            !empty($entry['needs_review']) ? 1 : 0,
            $entry['aliases'] !== '' ? $entry['aliases'] : null,
            $entry['type'] !== '' ? $entry['type'] : 'word',
            !empty($entry['priority']) ? (int) $entry['priority'] : 1,
            $userId, $userId,
        ]);
        $lastId = (int) $this->pdo->lastInsertId();
        ManoboHybridTranslator::incrementDictionaryVersion();
        $this->reload();
        return $lastId;
    }

    /**
     * Replace an existing entry, located by its current Manobo headword.
     *
     * @param  array<string,string> $data
     * @param  int|null $userId Who edited it, for updated_by.
     * @throws \InvalidArgumentException When the entry is missing or invalid.
     */
    public function updateEntry(string $originalManobo, array $data, ?int $userId = null): void
    {
        $id = $this->positionOf($originalManobo);
        if ($id === null) {
            throw new \InvalidArgumentException('No entry found for "' . $originalManobo . '".');
        }

        $entry = $this->validated($data, $originalManobo);

        $normTagalog = mb_strtolower(trim($entry['tagalog'] ?? ''));
        $normEnglish = mb_strtolower(trim($entry['english'] ?? ''));
        $normBisaya  = mb_strtolower(trim($entry['bisaya'] ?? ''));

        $stmt = $this->pdo->prepare(
            'UPDATE manobo_dictionary
                SET manobo = ?, english = ?, tagalog = ?, bisaya = ?,
                    normalized_tagalog = ?, normalized_english = ?, normalized_bisaya = ?,
                    part_of_speech = ?, category = ?, notes = ?, source = ?,
                    source_page = ?, review_status = ?, needs_review = ?,
                    aliases = ?, type = ?, priority = ?, updated_by = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $entry['manobo'], $entry['english'], $entry['tagalog'],
            $entry['bisaya'] !== '' ? $entry['bisaya'] : null,
            $normTagalog !== '' ? $normTagalog : null,
            $normEnglish !== '' ? $normEnglish : null,
            $normBisaya !== '' ? $normBisaya : null,
            $entry['part_of_speech'] !== '' ? $entry['part_of_speech'] : null,
            $entry['category'] !== '' ? $entry['category'] : 'other',
            $entry['notes'] !== '' ? $entry['notes'] : null,
            $entry['source'],
            !empty($entry['source_page']) ? (int) $entry['source_page'] : null,
            $entry['review_status'] !== '' ? $entry['review_status'] : 'approved',
            !empty($entry['needs_review']) ? 1 : 0,
            $entry['aliases'] !== '' ? $entry['aliases'] : null,
            $entry['type'] !== '' ? $entry['type'] : 'word',
            !empty($entry['priority']) ? (int) $entry['priority'] : 1,
            $userId, $id,
        ]);
        ManoboHybridTranslator::incrementDictionaryVersion();
        $this->reload();
    }

    /**
     * Mark an entry as reviewed and approved by an authorized language reviewer.
     */
    public function approve(int $id, ?int $userId = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE manobo_dictionary 
                SET review_status = 'approved', needs_review = 0, updated_by = ?
              WHERE id = ?"
        );
        $stmt->execute([$userId, $id]);
        ManoboHybridTranslator::incrementDictionaryVersion();
        $this->reload();
    }

    /**
     * Soft-delete an entry by its Manobo headword — moves it to Trash rather
     * than removing it outright. See restore()/forceDelete().
     *
     * @throws \InvalidArgumentException When no such live entry exists.
     */
    public function deleteEntry(string $manobo): void
    {
        $id = $this->positionOf($manobo);
        if ($id === null) {
            throw new \InvalidArgumentException('No entry found for "' . $manobo . '".');
        }

        $stmt = $this->pdo->prepare('UPDATE manobo_dictionary SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        $this->reload();
    }

    /**
     * Import rows from an uploaded CSV (manobo,tagalog,english,category — the
     * same column order as the community's own spreadsheets, per the admin
     * screen's "Optional: import from CSV" feature). Skips any row whose
     * headword already exists (case-sensitive, live rows only); returns
     * counts so the admin screen can report what happened.
     *
     * @return array{added:int, skipped:int, errors:list<string>}
     */
    public function importCsv(string $path, string $source, ?int $userId = null): array
    {
        $added   = 0;
        $skipped = 0;
        $errors  = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['added' => 0, 'skipped' => 0, 'errors' => ['Could not read the uploaded file.']];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return ['added' => 0, 'skipped' => 0, 'errors' => ['The file is empty.']];
        }
        $header    = array_map(static fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $required  = ['manobo', 'tagalog', 'english'];
        if (array_diff($required, $header) !== []) {
            fclose($handle);
            return ['added' => 0, 'skipped' => 0, 'errors' => [
                'The file must have manobo, tagalog and english columns (category is optional).',
            ]];
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $manobo = trim((string) ($data['manobo'] ?? ''));
            if ($manobo === '') {
                continue;
            }
            if ($this->find($manobo) !== null) {
                $skipped++;
                continue;
            }
            try {
                $this->addEntry([
                    'manobo'         => $manobo,
                    'english'        => (string) ($data['english'] ?? ''),
                    'tagalog'        => (string) ($data['tagalog'] ?? ''),
                    'part_of_speech' => (string) ($data['part_of_speech'] ?? ''),
                    'category'       => (string) ($data['category'] ?? ''),
                    'notes'          => (string) ($data['notes'] ?? ($data['note'] ?? '')),
                    'source'         => $source,
                ], $userId);
                $added++;
            } catch (\InvalidArgumentException $e) {
                $errors[] = $manobo . ': ' . $e->getMessage();
            }
        }
        fclose($handle);

        return ['added' => $added, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)];
    }

    /** id of the live entry with this exact headword, or null. */
    public function positionOf(string $manobo): ?int
    {
        foreach ($this->entries as $entry) {
            if ($entry['manobo'] === $manobo) {
                return (int) $entry['id'];
            }
        }

        return null;
    }

    /** The single live entry with this exact headword, or null. */
    public function find(string $manobo): ?array
    {
        foreach ($this->entries as $entry) {
            if ($entry['manobo'] === $manobo) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Distinct parts of speech present, sorted — powers the form's suggestions.
     *
     * @return list<string>
     */
    public function partsOfSpeech(): array
    {
        $values = array_unique(array_filter(array_column($this->entries, 'part_of_speech')));
        sort($values);

        return array_values($values);
    }

    /**
     * Free-text search across every column. Empty query returns everything.
     *
     * @return list<array<string,string>>
     */
    public function search(string $query, string $category = ''): array
    {
        $needle   = $this->normalise($query);
        $category = trim($category);

        return array_values(array_filter($this->entries, function (array $entry) use ($needle, $category): bool {
            if ($category !== '' && ($entry['category'] ?? '') !== $category) {
                return false;
            }
            if ($needle === '') {
                return true;
            }
            foreach (['manobo', 'english', 'tagalog', 'category', 'notes', 'part_of_speech'] as $field) {
                if (str_contains($this->normalise($entry[$field] ?? ''), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Normalise and validate submitted fields into a complete entry row.
     *
     * @param  array<string,string> $data
     * @param  string|null $ignoreHeadword Existing headword to exclude from the
     *                                     duplicate check (when editing).
     * @return array<string,string>
     * @throws \InvalidArgumentException
     */
    private function validated(array $data, ?string $ignoreHeadword = null): array
    {
        $entry = [];
        foreach (self::FIELDS as $field) {
            // Collapse newlines so a pasted value can never break a row.
            $value = preg_replace('/\s+/u', ' ', trim((string) ($data[$field] ?? ''))) ?? '';
            $entry[$field] = $value;
        }

        foreach (['manobo', 'english', 'tagalog'] as $required) {
            if ($entry[$required] === '') {
                throw new \InvalidArgumentException(
                    'The ' . $required . ' field is required.'
                );
            }
        }

        if ($entry['source'] === '') {
            $entry['source'] = 'LOCAL';
        }

        return $entry;
    }

    /** Drop the in-process cache and re-read from the database. */
    private function reload(): void
    {
        self::flushCache();
        $this->load();
    }

    /**
     * Drop the in-process cache without reloading. Public so tests that wrap
     * a write in a transaction and then roll it back can invalidate the
     * cache too — otherwise a later test in the same PHP process would still
     * see the (now-reverted) change, since a ROLLBACK is invisible to a
     * plain in-memory cache.
     */
    public static function flushCache(): void
    {
        self::$generation++;
        self::$cache = [];
    }

    /** Cast every DB column to string, matching the CSV version's shape. */
    private function castRow(array $row): array
    {
        $entry = [];
        foreach (['id', ...self::FIELDS, 'deleted_at', 'created_at', 'updated_at'] as $field) {
            $entry[$field] = (string) ($row[$field] ?? '');
        }

        return $entry;
    }

    /** Read every live row and build the per-language indexes (cached per generation). */
    private function load(): void
    {
        $key = 'gen:' . self::$generation;
        if (isset(self::$cache[$key])) {
            [$this->entries, $this->index] = self::$cache[$key];
            return;
        }

        $stmt = $this->pdo->query(
            'SELECT * FROM manobo_dictionary WHERE deleted_at IS NULL ORDER BY id'
        );

        $this->entries = [];
        $this->index   = array_fill_keys(self::LANGUAGES, []);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $entry    = $this->castRow($row);
            $position = \count($this->entries);
            $this->entries[] = $entry;

            foreach (self::LANGUAGES as $language) {
                foreach ($this->searchKeys($entry[$language] ?? '') as $searchKey) {
                    if (!\in_array($position, $this->index[$language][$searchKey] ?? [], true)) {
                        $this->index[$language][$searchKey][] = $position;
                    }
                }
            }
        }

        self::$cache[$key] = [$this->entries, $this->index];
    }

    /**
     * Every form a field should be findable by: the whole value, each
     * comma- or slash-separated sense, and each of those minus a leading
     * article or infinitive marker ("to walk" -> "walk").
     *
     * Slash-separated senses ("Sandali / Maghintay", "Paumanhin / Patawad")
     * come from the 2026 dataset, which records two Tagalog senses for one
     * Manobo word in a single field; comma-separated senses were already
     * supported for the original dataset's "one, two" style glosses.
     *
     * @return list<string>
     */
    private function searchKeys(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $keys = [$value];
        foreach (preg_split('~[,/]~u', $value) ?: [$value] as $sense) {
            $keys[] = $sense;
            $keys[] = preg_replace('/^(?:to|a|an|the|ang|mga)\s+/iu', '', trim($sense)) ?? '';
        }

        $normalised = [];
        foreach ($keys as $key) {
            $key = $this->normalise($key);
            if ($key !== '' && !\in_array($key, $normalised, true)) {
                $normalised[] = $key;
            }
        }

        return $normalised;
    }

    /**
     * Fold a term into its lookup key: lowercase, without the stress marks and
     * glottal-stop accents of the dictionary orthography, and without
     * parenthetical asides. This is what lets a resident type "baktas" and
     * find the entry stored as "'baktas".
     */
    private function normalise(string $term): string
    {
        $term = trim(mb_strtolower($term, 'UTF-8'));
        $term = preg_replace('/\s*\([^)]*\)/u', '', $term) ?? $term;  // drop "(pambayo)"
        $term = strtr($term, [
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $term = str_replace(["'", '’', '`', '´'], '', $term);         // stress marks
        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        // Surrounding punctuation too, so a gloss recorded with its citation
        // form still answers a plain query: "mother!" is stored that way
        // because the source marks it as a term of address, and without this a
        // lookup for "mother" missed it entirely. Applied to BOTH the index and
        // the query, so the two always agree.
        //
        // Deliberately leaves the hyphen and apostrophe alone — in this
        // orthography they mark a glottal stop and are part of the spelling
        // (agid-id, a-ae), not punctuation.
        return $this->stripPunctuation(trim($term));
    }

    /** Drop surrounding punctuation from a word before looking it up. */
    private function stripPunctuation(string $word): string
    {
        return trim($word, " \t\n\r\0\x0B.,;:!?\"“”()[]");
    }

    /**
     * Pull the target-language values out of matched entries, de-duplicated
     * and with blanks removed.
     *
     * @param  list<array<string,string>> $entries
     * @return list<string>
     */
    private function render(array $entries, string $to): array
    {
        $values = [];
        foreach ($entries as $entry) {
            $value = trim((string) ($entry[$to] ?? ''));
            if ($value !== '' && !\in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /** Human-readable summary of a word-by-word result. */
    private function fallbackMessage(bool $anyFound, array $missing, string $query): ?string
    {
        if (!$anyFound) {
            return \sprintf('No translation found for "%s".', $query);
        }
        if ($missing !== []) {
            return \sprintf(
                'Partial match — no entry for: %s. Words are glossed one by one, not grammatically joined.',
                implode(', ', $missing)
            );
        }

        return 'Words are glossed one by one, not grammatically joined.';
    }

    /**
     * Archive an entry (mark review_status = 'archived').
     */
    public function archive(int $id, ?int $userId = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE manobo_dictionary SET review_status = 'archived', archived_at = NOW(), updated_by = ? WHERE id = ?"
        );
        $stmt->execute([$userId, $id]);
        ManoboHybridTranslator::incrementDictionaryVersion();
        $this->reload();
    }

    /**
     * Check if a Manobo headword or English/Tagalog meaning already exists in live entries.
     *
     * @return array<string, mixed>|null
     */
    public function findDuplicate(string $manobo, string $english = '', string $tagalog = ''): ?array
    {
        $normManobo = mb_strtolower(trim($manobo));
        $normEn     = mb_strtolower(trim($english));
        $normTl     = mb_strtolower(trim($tagalog));

        $stmt = $this->pdo->prepare(
            'SELECT * FROM manobo_dictionary
              WHERE deleted_at IS NULL
                AND (
                     LOWER(manobo) = ?
                     OR (LOWER(english) = ? AND ? != "")
                     OR (LOWER(tagalog) = ? AND ? != "")
                    )
              LIMIT 1'
        );
        $stmt->execute([$normManobo, $normEn, $normEn, $normTl, $normTl]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Execute a bulk batch import of candidate entries.
     *
     * @param list<array<string, mixed>> $entries
     * @return array{batch_id: string, added: int, updated: int, skipped: int}
     */
    public function importBatch(array $entries, string $filename, string $fileType = 'doc', ?int $userId = null): array
    {
        $batchId = 'BATCH-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)) . '-' . date('Ymd');
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $flagged = 0;

        foreach ($entries as $item) {
            $action = $item['action'] ?? 'add';
            if ($action === 'skip') {
                $skipped++;
                continue;
            }

            $manobo  = trim((string) ($item['manobo'] ?? ''));
            $english = trim((string) ($item['english'] ?? ''));
            $tagalog = trim((string) ($item['tagalog'] ?? ''));
            $bisaya  = trim((string) ($item['bisaya'] ?? ''));
            if ($manobo === '') {
                $skipped++;
                continue;
            }

            $cat   = trim((string) ($item['category'] ?? 'general'));
            $pos   = trim((string) ($item['part_of_speech'] ?? 'word'));
            $type  = trim((string) ($item['type'] ?? (str_contains($manobo, ' ') ? 'phrase' : 'word')));
            $notes = trim((string) ($item['notes'] ?? ''));
            $needsReview = !empty($item['needs_review']) ? 1 : 0;
            $status = $needsReview ? 'pending_review' : 'approved';

            if ($needsReview) $flagged++;

            if ($action === 'update' && !empty($item['existing_id'])) {
                $stmt = $this->pdo->prepare(
                    'UPDATE manobo_dictionary
                        SET manobo = ?, english = ?, tagalog = ?, bisaya = ?,
                            category = ?, part_of_speech = ?, type = ?, notes = ?,
                            review_status = ?, needs_review = ?, import_batch_id = ?, updated_by = ?
                      WHERE id = ?'
                );
                $stmt->execute([
                    $manobo, $english, $tagalog, $bisaya !== '' ? $bisaya : null,
                    $cat !== '' ? $cat : 'general', $pos !== '' ? $pos : 'word', $type,
                    $notes !== '' ? $notes : null, $status, $needsReview, $batchId, $userId, (int) $item['existing_id']
                ]);
                $updated++;
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO manobo_dictionary
                        (manobo, english, tagalog, bisaya, category, part_of_speech, type, notes,
                         source, review_status, needs_review, import_batch_id, created_by, updated_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $manobo, $english, $tagalog, $bisaya !== '' ? $bisaya : null,
                    $cat !== '' ? $cat : 'general', $pos !== '' ? $pos : 'word', $type,
                    $notes !== '' ? $notes : null, 'DOC_IMPORT: ' . $filename, $status,
                    $needsReview, $batchId, $userId, $userId
                ]);
                $added++;
            }
        }

        // Record import metadata
        $stmt = $this->pdo->prepare(
            "INSERT INTO dictionary_imports
                (import_batch_id, filename, file_type, total_extracted, total_approved, total_duplicates, total_flagged, status, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)"
        );
        $stmt->execute([
            $batchId, $filename, $fileType, count($entries), $added + $updated, $skipped, $flagged, $userId
        ]);

        ManoboHybridTranslator::incrementDictionaryVersion();
        $this->reload();

        return [
            'batch_id' => $batchId,
            'added'    => $added,
            'updated'  => $updated,
            'skipped'  => $skipped,
        ];
    }

    /**
     * Safely undo a batch import.
     *
     * @return array{deleted: int, restored: int}
     */
    public function undoImport(string $batchId, ?int $userId = null): array
    {
        $stmt = $this->pdo->prepare('UPDATE manobo_dictionary SET deleted_at = NOW() WHERE import_batch_id = ? AND deleted_at IS NULL');
        $stmt->execute([$batchId]);
        $deleted = $stmt->rowCount();

        $stmt2 = $this->pdo->prepare("UPDATE dictionary_imports SET status = 'undone', undone_at = NOW() WHERE import_batch_id = ?");
        $stmt2->execute([$batchId]);

        ManoboHybridTranslator::incrementDictionaryVersion();
        $this->reload();

        return ['deleted' => $deleted, 'restored' => 0];
    }

    /**
     * Get import history.
     */
    public function getImportHistory(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT di.*, u.full_name AS author_name
                   FROM dictionary_imports di
              LEFT JOIN users u ON u.id = di.uploaded_by
               ORDER BY di.created_at DESC
                  LIMIT 50'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /** Guard against typos in the language argument. */
    private function assertLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        $aliases  = ['msm' => 'manobo', 'en' => 'english', 'eng' => 'english', 'fil' => 'tagalog', 'tl' => 'tagalog'];
        $language = $aliases[$language] ?? $language;

        if (!\in_array($language, self::LANGUAGES, true)) {
            throw new \RuntimeException(
                'Unsupported language "' . $language . '". Expected one of: ' . implode(', ', self::LANGUAGES)
            );
        }

        return $language;
    }
}
