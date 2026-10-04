<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\VoiceAudioStore;
use App\Services\VoiceResolver;
use App\Services\VoiceText;
use App\Services\VoiceUsageIndex;
use PDO;
use PDOException;

class VoiceSample
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_DRAFT];

    public const LANGUAGES = ['msm', 'fil', 'en'];

    /**
     * Normalise text for matching. Delegates to the shared VoiceText rules.
     */
    public static function normalize(string $text): string
    {
        return VoiceText::normalize($text);
    }

    /**
     * Create a voice sample row and return its id.
     *
     * Throws on failure: a save that silently returned 0 is what let the
     * dataset page report success while nothing was stored.
     *
     * @throws PDOException
     */
    public static function create(array $data): int
    {
        $text   = trim((string) ($data['text'] ?? ''));
        $status = self::cleanStatus((string) ($data['status'] ?? self::STATUS_PENDING));
        $isApproved = $status === self::STATUS_APPROVED;

        $sql = "INSERT INTO voice_samples (
            language, text, normalized_text, translation, dictionary_entry_id, content_type, content_id,
            profile_id, audio_url, audio_storage_key, speaker_label, voice_type, sample_type, status,
            duration, mime_type, file_size, notes, created_by, approved_by, approved_at, created_at, updated_at
        ) VALUES (
            :language, :text, :normalized_text, :translation, :dictionary_entry_id, :content_type, :content_id,
            :profile_id, :audio_url, :audio_storage_key, :speaker_label, :voice_type, :sample_type, :status,
            :duration, :mime_type, :file_size, :notes, :created_by, :approved_by, " . ($isApproved ? 'NOW()' : 'NULL') . ", NOW(), NOW()
        )";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            'language'            => $data['language'] ?? 'msm',
            'text'                => $text,
            'normalized_text'     => self::normalize($text),
            'translation'         => !empty($data['translation']) ? mb_substr(trim((string) $data['translation']), 0, 500) : null,
            'dictionary_entry_id' => !empty($data['dictionary_entry_id']) ? (int) $data['dictionary_entry_id'] : null,
            'content_type'        => !empty($data['content_type']) ? trim((string) $data['content_type']) : null,
            'content_id'          => !empty($data['content_id']) ? (int) $data['content_id'] : null,
            'profile_id'          => !empty($data['profile_id']) ? (int) $data['profile_id'] : null,
            'audio_url'           => $data['audio_url'] ?? '',
            'audio_storage_key'   => $data['audio_storage_key'] ?? null,
            'speaker_label'       => $data['speaker_label'] ?? 'Community Speaker',
            'voice_type'          => $data['voice_type'] ?? 'community',
            'sample_type'         => (substr_count(self::normalize($text), ' ') > 0) ? 'phrase' : 'word',
            'status'              => $status,
            'duration'            => isset($data['duration']) ? (float) $data['duration'] : null,
            'mime_type'           => $data['mime_type'] ?? null,
            'file_size'           => isset($data['file_size']) ? (int) $data['file_size'] : null,
            'notes'               => $data['notes'] ?? null,
            'created_by'          => isset($data['created_by']) ? (int) $data['created_by'] : null,
            'approved_by'         => $isApproved ? ($data['approved_by'] ?? $data['created_by'] ?? null) : null,
        ]);

        $id = (int) db()->lastInsertId();
        if ($id <= 0) {
            throw new \RuntimeException('The database did not return an id for the new voice sample.');
        }
        VoiceResolver::flush();
        return $id;
    }

    /**
     * Find a voice sample by ID (with dictionary headword and audio availability).
     */
    public static function find(int $id): ?array
    {
        try {
            $stmt = db()->prepare("SELECT vs.*, md.manobo, md.english, md.tagalog,
                                          CASE WHEN vsa.sample_id IS NULL THEN 0 ELSE 1 END AS has_blob
                                     FROM voice_samples vs
                                     LEFT JOIN manobo_dictionary md ON vs.dictionary_entry_id = md.id
                                     LEFT JOIN voice_sample_audio vsa ON vsa.sample_id = vs.id
                                    WHERE vs.id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('[VoiceSample::find] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Alias for find(id)
     */
    public static function findById(int $id): ?array
    {
        return self::find($id);
    }

    /**
     * Update an existing voice sample's metadata.
     */
    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params['status'] = self::cleanStatus((string) $data['status']);
        }
        foreach (['speaker_label', 'voice_type', 'notes'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "{$col} = :{$col}";
                $params[$col] = trim((string) $data[$col]);
            }
        }
        if (array_key_exists('translation', $data)) {
            $fields[] = 'translation = :translation';
            $params['translation'] = !empty($data['translation']) ? mb_substr(trim((string) $data['translation']), 0, 500) : null;
        }
        foreach (['dictionary_entry_id', 'content_id', 'profile_id'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[$col] = !empty($data[$col]) ? (int) $data[$col] : null;
            }
        }
        if (array_key_exists('content_type', $data)) {
            $fields[] = 'content_type = :content_type';
            $params['content_type'] = !empty($data['content_type']) ? trim((string) $data['content_type']) : null;
        }
        if (isset($data['text'])) {
            $fields[] = 'text = :text';
            $fields[] = 'normalized_text = :normalized_text';
            $params['text'] = trim((string) $data['text']);
            $params['normalized_text'] = self::normalize($params['text']);
        }
        foreach (['audio_url', 'mime_type'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "{$col} = :{$col}";
                $params[$col] = (string) $data[$col];
            }
        }
        if (isset($data['file_size'])) {
            $fields[] = 'file_size = :file_size';
            $params['file_size'] = (int) $data['file_size'];
        }
        if (array_key_exists('duration', $data)) {
            $fields[] = 'duration = :duration';
            $params['duration'] = $data['duration'] !== null ? (float) $data['duration'] : null;
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = 'updated_at = NOW()';
        $stmt = db()->prepare("UPDATE voice_samples SET " . implode(', ', $fields) . " WHERE id = :id");
        $ok = $stmt->execute($params);
        VoiceResolver::flush();
        return $ok;
    }

    /**
     * Change a sample's review status, recording who reviewed it and when.
     */
    public static function updateStatus(int $id, string $status, ?int $reviewedBy = null): bool
    {
        $status = self::cleanStatus($status);
        $stmt = db()->prepare(
            "UPDATE voice_samples
                SET status = ?, approved_by = ?, approved_at = " . ($status === self::STATUS_APPROVED ? 'NOW()' : 'NULL') . ", updated_at = NOW()
              WHERE id = ?"
        );
        $ok = $stmt->execute([$status, $reviewedBy, $id]);
        VoiceResolver::flush();
        return $ok;
    }

    /**
     * Delete a voice sample and its stored audio.
     */
    public static function delete(int $id): bool
    {
        $sample = self::find($id);
        if ($sample && !empty($sample['audio_url']) && str_starts_with((string) $sample['audio_url'], '/uploads/')) {
            // Legacy disk file from before audio moved into the database.
            $path = preg_replace('#^/?(BarangGabay/public/|public/)?#', '', (string) $sample['audio_url']);
            $fullPath = dirname(__DIR__, 2) . '/public/' . ltrim((string) $path, '/');
            $uploads  = realpath(dirname(__DIR__, 2) . '/public/uploads');
            $real     = realpath($fullPath);
            if ($uploads !== false && $real !== false && str_starts_with($real, $uploads) && is_file($real)) {
                @unlink($real);
            }
        }

        $pdo  = db();
        $owns = !$pdo->inTransaction();
        if ($owns) {
            $pdo->beginTransaction();
        }
        try {
            VoiceAudioStore::delete($id);
            $ok = $pdo->prepare("DELETE FROM voice_samples WHERE id = ?")->execute([$id]);
            if ($owns) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        VoiceResolver::flush();
        return $ok;
    }

    /**
     * Fetch list of samples with optional filters.
     *
     * Filters: language, status, speaker, search, sort ('newest'|'oldest'|'text').
     */
    public static function all(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        try {
            [$whereSql, $params] = self::whereClause($filters, 'vs.');
            $order = match ($filters['sort'] ?? 'newest') {
                'oldest' => 'vs.id ASC',
                'text'   => 'vs.normalized_text ASC, vs.id DESC',
                default  => 'vs.id DESC',
            };
            $limit  = max(1, $limit);
            $offset = max(0, $offset);

            $sql = "SELECT vs.*, md.manobo, md.english, md.tagalog,
                           CASE WHEN vsa.sample_id IS NULL THEN 0 ELSE 1 END AS has_blob
                      FROM voice_samples vs
                      LEFT JOIN manobo_dictionary md ON vs.dictionary_entry_id = md.id
                      LEFT JOIN voice_sample_audio vsa ON vsa.sample_id = vs.id
                      {$whereSql}
                     ORDER BY {$order}
                     LIMIT {$limit} OFFSET {$offset}";

            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $row['playable_url'] = VoiceResolver::playableUrl($row);
            }
            unset($row);
            return $rows;
        } catch (PDOException $e) {
            error_log('[VoiceSample::all] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Alias for all() used in controllers and export functions.
     */
    public static function getAll(array $filters = [], int $limit = 5000, int $offset = 0): array
    {
        return self::all($filters, $limit, $offset);
    }

    /**
     * Count total matching records.
     */
    public static function count(array $filters = []): int
    {
        try {
            [$whereSql, $params] = self::whereClause($filters, '');
            $stmt = db()->prepare("SELECT COUNT(*) FROM voice_samples {$whereSql}");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[VoiceSample::count] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Approved, playable recording whose normalised text equals $text exactly.
     *
     * Exact only. The old substring fallback let a one-word recording such as
     * "no" match — and replace — the narration of any post containing "no".
     * Partial coverage is the resolver's job (VoiceResolver::resolve()).
     * Accepts ($language, $text) or ($text, $language).
     */
    public static function findMatchingSample(string $param1, string $param2): ?array
    {
        [$language, $text] = in_array(strtolower(trim($param2)), self::LANGUAGES, true) && !in_array(strtolower(trim($param1)), self::LANGUAGES, true)
            ? [strtolower(trim($param2)), $param1]
            : [strtolower(trim($param1)), $param2];

        $key = self::normalize($text);
        if ($key === '') {
            return null;
        }

        $hit = VoiceResolver::index($language)['map'][$key] ?? null;
        if ($hit === null) {
            return null;
        }
        $row = self::find((int) $hit['sample_id']);
        if ($row) {
            $row['audio_url'] = $hit['audio_url'];
        }
        return $row;
    }

    /**
     * Approved, playable sample explicitly linked to a post (a full narration).
     */
    public static function findSampleForContent(string $contentType, int $contentId, string $language): ?array
    {
        try {
            $stmt = db()->prepare("SELECT vs.*, CASE WHEN vsa.sample_id IS NULL THEN 0 ELSE 1 END AS has_blob
                                     FROM voice_samples vs
                                     LEFT JOIN voice_sample_audio vsa ON vsa.sample_id = vs.id
                                    WHERE vs.content_type = ? AND vs.content_id = ? AND vs.language = ? AND LOWER(vs.status) = 'approved'
                                    ORDER BY vs.id DESC");
            $stmt->execute([$contentType, $contentId, $language]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $url = VoiceResolver::playableUrl($row);
                if ($url !== null) {
                    $row['audio_url'] = $url;
                    return $row;
                }
            }
        } catch (PDOException $e) {
            error_log('[VoiceSample::findSampleForContent] ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Another approved sample with the same language and text, if any.
     */
    public static function findApprovedDuplicate(string $language, string $text, int $excludeId = 0): ?array
    {
        $stmt = db()->prepare("SELECT id, text FROM voice_samples
                                WHERE language = ? AND normalized_text = ? AND LOWER(status) = 'approved' AND id <> ?
                                ORDER BY id DESC LIMIT 1");
        $stmt->execute([$language, self::normalize($text), $excludeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retire every other approved sample for the same text (used by "Replace").
     */
    public static function retireApprovedDuplicates(string $language, string $text, int $keepId, ?int $reviewedBy): int
    {
        $stmt = db()->prepare("UPDATE voice_samples
                                  SET status = 'rejected', approved_by = ?, approved_at = NULL, updated_at = NOW(),
                                      notes = CONCAT(COALESCE(notes, ''), ' [Replaced by sample #" . (int) $keepId . "]')
                                WHERE language = ? AND normalized_text = ? AND LOWER(status) = 'approved' AND id <> ?");
        $stmt->execute([$reviewedBy, $language, self::normalize($text), $keepId]);
        VoiceResolver::flush();
        return $stmt->rowCount();
    }

    /**
     * Sample counts per language: total / approved / pending / rejected.
     */
    public static function getStatsByLanguage(): array
    {
        $blank = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
        $stats = ['msm' => $blank, 'fil' => $blank, 'en' => $blank];

        try {
            $rows = db()->query("SELECT language, LOWER(status) AS status, COUNT(*) AS cnt FROM voice_samples GROUP BY language, LOWER(status)")
                        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $lang = (string) $r['language'];
                $cnt  = (int) $r['cnt'];
                $stats[$lang] ??= $blank;
                $stats[$lang]['total'] += $cnt;
                match ((string) $r['status']) {
                    'approved'         => $stats[$lang]['approved'] += $cnt,
                    'pending', 'draft' => $stats[$lang]['pending']  += $cnt,
                    'rejected'         => $stats[$lang]['rejected'] += $cnt,
                    default            => null,
                };
            }
        } catch (PDOException $e) {
            error_log('[VoiceSample::getStatsByLanguage] ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Stats for one language code.
     */
    public static function getStats(string $language): array
    {
        $all = self::getStatsByLanguage();
        return $all[$language] ?? ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
    }

    /**
     * Live Manobo dictionary entries, each flagged with whether an approved,
     * playable recording exists (linked by id, or with the same normalised
     * headword).
     *
     * @return list<array{id: int, manobo: string, translation: string, recorded: bool}>
     */
    public static function dictionaryCoverage(): array
    {
        $map = VoiceResolver::index('msm')['map'];

        $linked = [];
        foreach ($map as $hit) {
            $linked[$hit['sample_id']] = true;
        }
        $recordedIds = [];
        if ($linked) {
            try {
                $marks = implode(',', array_fill(0, count($linked), '?'));
                $stmt  = db()->prepare("SELECT DISTINCT dictionary_entry_id FROM voice_samples WHERE id IN ({$marks}) AND dictionary_entry_id IS NOT NULL");
                $stmt->execute(array_keys($linked));
                $recordedIds = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
            } catch (PDOException $e) {
                error_log('[VoiceSample::dictionaryCoverage] ' . $e->getMessage());
            }
        }

        $out = [];
        try {
            $rows = db()->query('SELECT id, manobo, tagalog, english FROM manobo_dictionary WHERE deleted_at IS NULL ORDER BY id ASC')
                        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $out[] = [
                    'id'          => $id,
                    'manobo'      => (string) $row['manobo'],
                    'translation' => trim((string) ($row['tagalog'] ?: $row['english'])),
                    'recorded'    => isset($recordedIds[$id]) || isset($map[self::normalize((string) $row['manobo'])]),
                ];
            }
        } catch (PDOException $e) {
            error_log('[VoiceSample::dictionaryCoverage] ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Manobo Voice Coverage stats, from the database.
     */
    public static function getManoboCoverageStats(): array
    {
        $entries   = self::dictionaryCoverage();
        $total     = count($entries);
        $withAudio = count(array_filter($entries, static fn (array $e): bool => $e['recorded']));

        return [
            'total_dictionary_words' => $total,
            'words_with_audio'       => $withAudio,
            'missing_audio'          => $total - $withAudio,
            'coverage_percentage'    => $total > 0 ? round($withAudio / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Dictionary entries with no approved recording, most-used first.
     *
     * Usage = occurrences in resident-visible posts + Voice Reader requests.
     *
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public static function missingDictionaryEntries(int $limit = 15, ?array $usageWords = null): array
    {
        $usage = [];
        foreach ($usageWords ?? VoiceUsageIndex::report()['words'] as $w) {
            $usage[$w['word']] = $w;
        }

        $missing = [];
        foreach (self::dictionaryCoverage() as $entry) {
            if ($entry['recorded']) {
                continue;
            }
            $key = self::normalize($entry['manobo']);
            $entry['occurrences']     = $usage[$key]['occurrences'] ?? 0;
            $entry['reader_requests'] = $usage[$key]['reader_requests'] ?? 0;
            $entry['priority']        = $entry['occurrences'] + $entry['reader_requests'];
            $missing[] = $entry;
        }

        usort($missing, static fn (array $a, array $b): int => [-$a['priority'], $a['id']] <=> [-$b['priority'], $b['id']]);

        return ['items' => $limit > 0 ? array_slice($missing, 0, $limit) : $missing, 'total' => count($missing)];
    }

    /**
     * Back-compat: just the first $limit missing entries.
     */
    public static function getMissingPronunciations(int $limit = 50): array
    {
        return self::missingDictionaryEntries($limit)['items'];
    }

    /**
     * Dataset health counters for the admin panel.
     *
     * @return array<string,int>
     */
    public static function health(): array
    {
        $health = ['pending' => 0, 'rejected' => 0, 'broken' => 0, 'duplicates' => 0, 'orphaned' => 0];
        try {
            $rows = db()->query("SELECT vs.id, vs.language, vs.normalized_text, vs.audio_url, LOWER(vs.status) AS status, vs.dictionary_entry_id,
                                        CASE WHEN vsa.sample_id IS NULL THEN 0 ELSE 1 END AS has_blob,
                                        CASE WHEN vs.dictionary_entry_id IS NOT NULL AND md.id IS NULL THEN 1 ELSE 0 END AS orphan
                                   FROM voice_samples vs
                                   LEFT JOIN voice_sample_audio vsa ON vsa.sample_id = vs.id
                                   LEFT JOIN manobo_dictionary md ON md.id = vs.dictionary_entry_id AND md.deleted_at IS NULL")
                        ->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $approvedKeys = [];
            foreach ($rows as $r) {
                if ($r['status'] === 'pending' || $r['status'] === 'draft') {
                    $health['pending']++;
                } elseif ($r['status'] === 'rejected') {
                    $health['rejected']++;
                }
                if ($r['status'] !== 'rejected' && VoiceResolver::playableUrl($r) === null) {
                    $health['broken']++;
                }
                if ((int) $r['orphan'] === 1) {
                    $health['orphaned']++;
                }
                if ($r['status'] === 'approved') {
                    $k = $r['language'] . '|' . $r['normalized_text'];
                    $approvedKeys[$k] = ($approvedKeys[$k] ?? 0) + 1;
                }
            }
            foreach ($approvedKeys as $n) {
                if ($n > 1) {
                    $health['duplicates'] += $n - 1;
                }
            }
        } catch (PDOException $e) {
            error_log('[VoiceSample::health] ' . $e->getMessage());
        }
        return $health;
    }

    /**
     * Whether a sample row's audio can currently be played.
     */
    public static function isPlayable(array $row): bool
    {
        return VoiceResolver::playableUrl($row) !== null;
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private static function cleanStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, self::STATUSES, true) ? $status : self::STATUS_PENDING;
    }

    /**
     * @return array{0: string, 1: array<string,mixed>}
     */
    private static function whereClause(array $filters, string $p): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['language'])) {
            $where[] = "{$p}language = :language";
            $params['language'] = (string) $filters['language'];
        }
        if (!empty($filters['status'])) {
            $where[] = "LOWER({$p}status) = :status";
            $params['status'] = strtolower(trim((string) $filters['status']));
        }
        if (!empty($filters['speaker'])) {
            $where[] = "{$p}speaker_label LIKE :speaker";
            $params['speaker'] = '%' . trim((string) $filters['speaker']) . '%';
        }
        if (!empty($filters['search'])) {
            $where[] = "({$p}text LIKE :search1 OR {$p}normalized_text LIKE :search2 OR {$p}speaker_label LIKE :search3 OR {$p}translation LIKE :search4)";
            $term = '%' . trim((string) $filters['search']) . '%';
            $params += ['search1' => $term, 'search2' => $term, 'search3' => $term, 'search4' => $term];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }
}
