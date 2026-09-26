<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Offline Bisaya (Cebuano/Surigaonon) <-> English <-> Tagalog lookup — the
 * second-tier fallback behind the Manobo dictionary for the "MN" translate
 * feature (see data/bisaya/README.md for the resolution order this backs).
 *
 * Deliberately mirrors App\Services\ManoboDictionary's shape (same FIELDS,
 * same lookup/translate/search contract, same soft-delete-as-Trash design —
 * see migration 030) so the admin curation screen and the auto-translate
 * engine can treat the two dictionaries the same way. Kept as a separate
 * class rather than a shared base: this dataset is explicitly NOT verified
 * by a local speaker yet (source=BISAYA-CEB, "needs local review" per its
 * README), so it must never be silently merged into or mistaken for the
 * Manobo dataset — a Bisaya word recorded as Manobo would misrepresent the
 * one thing the Manobo dictionary's whole design is careful never to invent.
 */
final class BisayaDictionary
{
    public const LANGUAGES = ['bisaya', 'english', 'tagalog'];

    public const FIELDS = [
        'bisaya',
        'english',
        'tagalog',
        'part_of_speech',
        'category',
        'notes',
        'source',
    ];

    private PDO $pdo;

    /** @var list<array<string,string>> Every live row, id ascending. */
    private array $entries = [];

    /** @var array<string, array<string, list<int>>> */
    private array $index = [];

    private static array $cache = [];
    private static int $generation = 0;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
        $this->load();
    }

    // ── Public API ───────────────────────────────────────────────────

    public function lookup(string $term, ?string $from = null): array
    {
        $normalised = $this->normalise($term);
        $languages  = $from !== null ? [$this->assertLanguage($from)] : self::LANGUAGES;

        $hits = [];
        foreach ($languages as $language) {
            foreach ($this->index[$language][$normalised] ?? [] as $position) {
                $hits[$position] = $this->entries[$position];
            }
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
            'ambiguous' => \count($hits) > 1,
        ];
    }

    public function translate(string $text, string $to, ?string $from = null): array
    {
        $to    = $this->assertLanguage($to);
        $from  = $from !== null ? $this->assertLanguage($from) : null;
        $query = trim($text);

        $blank = [
            'found' => false, 'match_type' => 'none', 'query' => $query, 'from' => $from,
            'to' => $to, 'text' => null, 'tokens' => [], 'missing' => [], 'ambiguous' => false,
            'message' => null,
        ];

        if ($query === '') {
            return ['message' => 'No text was given.'] + $blank;
        }

        $phrase = $this->lookup($query, $from);
        if ($phrase['found']) {
            $renderings = $this->render($phrase['entries'], $to);

            return [
                'found' => $renderings !== [], 'match_type' => 'phrase', 'query' => $query,
                'from' => $phrase['from'], 'to' => $to, 'text' => $renderings[0] ?? null,
                'tokens' => [['token' => $query, 'found' => $renderings !== [], 'entries' => $phrase['entries']]],
                'missing' => $renderings === [] ? [$query] : [],
                'ambiguous' => $phrase['ambiguous'],
                'message' => $phrase['ambiguous']
                    ? 'More than one entry matches this spelling. Check the notes on each entry.'
                    : null,
            ];
        }

        $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (\count($words) < 2) {
            return ['message' => \sprintf('No translation found for "%s".', $query)]
                + array_merge($blank, ['missing' => [$query]]);
        }

        $tokens = []; $missing = []; $rendered = []; $ambiguous = false; $sources = [];
        foreach ($words as $word) {
            $bare = $this->stripPunctuation($word);
            $hit  = $bare === '' ? ['found' => false] : $this->lookup($bare, $from);

            $renderings = ($hit['found'] ?? false) === true ? $this->render($hit['entries'], $to) : [];

            if ($renderings !== []) {
                $rendered[] = $renderings[0];
                $ambiguous  = $ambiguous || $hit['ambiguous'];
                $sources[]  = $hit['from'];
                $tokens[]   = ['token' => $word, 'found' => true, 'entries' => $hit['entries']];
            } else {
                $rendered[] = $word;
                $missing[]  = $word;
                $tokens[]   = ['token' => $word, 'found' => false, 'entries' => []];
            }
        }

        $anyFound = \count($missing) < \count($words);

        return [
            'found' => $anyFound, 'match_type' => $anyFound ? 'word-by-word' : 'none',
            'query' => $query, 'from' => $from ?? ($sources[0] ?? null), 'to' => $to,
            'text' => $anyFound ? implode(' ', $rendered) : null,
            'tokens' => $tokens, 'missing' => $missing, 'ambiguous' => $ambiguous,
            'message' => $this->fallbackMessage($anyFound, $missing, $query),
        ];
    }

    /** @return list<array<string,string>> */
    public function all(): array
    {
        return $this->entries;
    }

    /** @return list<array<string,string>> */
    public function byCategory(string $category): array
    {
        $needle = $this->normalise($category);

        return array_values(array_filter(
            $this->entries,
            fn (array $entry): bool => $this->normalise($entry['category'] ?? '') === $needle
        ));
    }

    /** @return list<string> */
    public function categories(): array
    {
        $categories = array_unique(array_filter(array_column($this->entries, 'category')));
        sort($categories);

        return array_values($categories);
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    /** @return list<array<string,string>> */
    public function needsVerification(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => trim($entry['notes'] ?? '') !== ''
        ));
    }

    /** @return list<array<string,string>> */
    public function trash(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM bisaya_dictionary WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        );

        return array_map([$this, 'castRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE bisaya_dictionary SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL'
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new \InvalidArgumentException('No trashed entry with id ' . $id . '.');
        }
        $this->reload();
    }

    public function forceDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM bisaya_dictionary WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new \InvalidArgumentException('No trashed entry with id ' . $id . '.');
        }
        $this->reload();
    }

    public function exportJson(string $path): bool
    {
        $payload = [
            'language'     => ['name' => 'Bisaya', 'iso_639_3' => 'ceb'],
            'entry_count'  => $this->count(),
            'generated_at' => date('c'),
            'generated_by' => 'App\\Services\\BisayaDictionary::exportJson() from the bisaya_dictionary table',
            'notice'       => 'Fallback dataset behind the Manobo dictionary. See data/bisaya/README.md.',
            'entries'      => $this->entries,
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false && file_put_contents($path, $json . "\n") !== false;
    }

    // ── Write API ────────────────────────────────────────────────────

    public function addEntry(array $data, ?int $userId = null): void
    {
        $entry = $this->validated($data);

        $stmt = $this->pdo->prepare(
            'INSERT INTO bisaya_dictionary
                (bisaya, english, tagalog, part_of_speech, category, notes, source, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $entry['bisaya'], $entry['english'], $entry['tagalog'],
            $entry['part_of_speech'] !== '' ? $entry['part_of_speech'] : null,
            $entry['category'] !== '' ? $entry['category'] : 'other',
            $entry['notes'] !== '' ? $entry['notes'] : null,
            $entry['source'], $userId, $userId,
        ]);
        $this->reload();
    }

    public function updateEntry(string $originalBisaya, array $data, ?int $userId = null): void
    {
        $id = $this->positionOf($originalBisaya);
        if ($id === null) {
            throw new \InvalidArgumentException('No entry found for "' . $originalBisaya . '".');
        }

        $entry = $this->validated($data, $originalBisaya);

        $stmt = $this->pdo->prepare(
            'UPDATE bisaya_dictionary
                SET bisaya = ?, english = ?, tagalog = ?, part_of_speech = ?,
                    category = ?, notes = ?, source = ?, updated_by = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $entry['bisaya'], $entry['english'], $entry['tagalog'],
            $entry['part_of_speech'] !== '' ? $entry['part_of_speech'] : null,
            $entry['category'] !== '' ? $entry['category'] : 'other',
            $entry['notes'] !== '' ? $entry['notes'] : null,
            $entry['source'], $userId, $id,
        ]);
        $this->reload();
    }

    public function deleteEntry(string $bisaya): void
    {
        $id = $this->positionOf($bisaya);
        if ($id === null) {
            throw new \InvalidArgumentException('No entry found for "' . $bisaya . '".');
        }

        $stmt = $this->pdo->prepare('UPDATE bisaya_dictionary SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        $this->reload();
    }

    /** @return array{added:int, skipped:int, errors:list<string>} */
    public function importCsv(string $path, string $source, ?int $userId = null): array
    {
        $added = 0; $skipped = 0; $errors = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['added' => 0, 'skipped' => 0, 'errors' => ['Could not read the uploaded file.']];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return ['added' => 0, 'skipped' => 0, 'errors' => ['The file is empty.']];
        }
        $header = array_map(
            static fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))),
            $header
        );
        if (array_diff(['bisaya', 'tagalog', 'english'], $header) !== []) {
            fclose($handle);
            return ['added' => 0, 'skipped' => 0, 'errors' => [
                'The file must have bisaya, tagalog and english columns (category is optional).',
            ]];
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $data   = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $bisaya = trim((string) ($data['bisaya'] ?? ''));
            if ($bisaya === '') {
                continue;
            }
            if ($this->find($bisaya) !== null) {
                $skipped++;
                continue;
            }
            try {
                $this->addEntry([
                    'bisaya'         => $bisaya,
                    'english'        => (string) ($data['english'] ?? ''),
                    'tagalog'        => (string) ($data['tagalog'] ?? ''),
                    'part_of_speech' => (string) ($data['part_of_speech'] ?? ''),
                    'category'       => (string) ($data['category'] ?? ''),
                    'notes'          => (string) ($data['notes'] ?? ($data['note'] ?? '')),
                    'source'         => $source,
                ], $userId);
                $added++;
            } catch (\InvalidArgumentException $e) {
                $errors[] = $bisaya . ': ' . $e->getMessage();
            }
        }
        fclose($handle);

        return ['added' => $added, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)];
    }

    /** id of the FIRST live entry with this exact headword, or null. Some
     *  headwords in this not-yet-locally-verified dataset repeat (different
     *  senses under the same spelling) — see data/bisaya/README.md. */
    public function positionOf(string $bisaya): ?int
    {
        foreach ($this->entries as $entry) {
            if ($entry['bisaya'] === $bisaya) {
                return (int) $entry['id'];
            }
        }

        return null;
    }

    public function find(string $bisaya): ?array
    {
        foreach ($this->entries as $entry) {
            if ($entry['bisaya'] === $bisaya) {
                return $entry;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function partsOfSpeech(): array
    {
        $values = array_unique(array_filter(array_column($this->entries, 'part_of_speech')));
        sort($values);

        return array_values($values);
    }

    /** @return list<array<string,string>> */
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
            foreach (['bisaya', 'english', 'tagalog', 'category', 'notes', 'part_of_speech'] as $field) {
                if (str_contains($this->normalise($entry[$field] ?? ''), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function validated(array $data, ?string $ignoreHeadword = null): array
    {
        $entry = [];
        foreach (self::FIELDS as $field) {
            $value = preg_replace('/\s+/u', ' ', trim((string) ($data[$field] ?? ''))) ?? '';
            $entry[$field] = $value;
        }

        foreach (['bisaya', 'english', 'tagalog'] as $required) {
            if ($entry[$required] === '') {
                throw new \InvalidArgumentException('The ' . $required . ' field is required.');
            }
        }

        if ($entry['source'] === '') {
            $entry['source'] = 'LOCAL';
        }

        return $entry;
    }

    private function reload(): void
    {
        self::flushCache();
        $this->load();
    }

    /** See ManoboDictionary::flushCache() for why this needs to be public. */
    public static function flushCache(): void
    {
        self::$generation++;
        self::$cache = [];
    }

    private function castRow(array $row): array
    {
        $entry = [];
        foreach (['id', ...self::FIELDS, 'deleted_at', 'created_at', 'updated_at'] as $field) {
            $entry[$field] = (string) ($row[$field] ?? '');
        }

        return $entry;
    }

    private function load(): void
    {
        $key = 'gen:' . self::$generation;
        if (isset(self::$cache[$key])) {
            [$this->entries, $this->index] = self::$cache[$key];
            return;
        }

        $stmt = $this->pdo->query('SELECT * FROM bisaya_dictionary WHERE deleted_at IS NULL ORDER BY id');

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

    /** @return list<string> */
    private function searchKeys(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $keys = [$value];
        foreach (preg_split('~[,/]~u', $value) ?: [$value] as $sense) {
            $keys[] = $sense;
            $keys[] = preg_replace('/^(?:to|a|an|the)\s+/iu', '', trim($sense)) ?? '';
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
     * Fold a term for matching: lowercase and trim surrounding punctuation.
     * Unlike ManoboDictionary::normalise(), this does NOT strip a leading
     * "ang"/"mga" — in Bisaya those are real, meaningful words (matching
     * app/helpers.php's bisaya_fold(), which draws the same distinction).
     */
    private function normalise(string $term): string
    {
        $term = trim(mb_strtolower($term, 'UTF-8'));
        $term = preg_replace('/\s*\([^)]*\)/u', '', $term) ?? $term;
        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        return $this->stripPunctuation(trim($term));
    }

    private function stripPunctuation(string $word): string
    {
        return trim($word, " \t\n\r\0\x0B.,;:!?\"“”()[]");
    }

    /** @param list<array<string,string>> $entries @return list<string> */
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

    private function assertLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        $aliases  = ['ceb' => 'bisaya', 'en' => 'english', 'eng' => 'english', 'fil' => 'tagalog', 'tl' => 'tagalog'];
        $language = $aliases[$language] ?? $language;

        if (!\in_array($language, self::LANGUAGES, true)) {
            throw new \RuntimeException(
                'Unsupported language "' . $language . '". Expected one of: ' . implode(', ', self::LANGUAGES)
            );
        }

        return $language;
    }
}
