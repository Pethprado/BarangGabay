<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

class VoiceSample
{
    /**
     * Create a new voice sample entry.
     */
    public static function create(array $data): int
    {
        $db = \App\Database::getInstance();

        $text = trim((string) ($data['text'] ?? ''));
        $normalizedText = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', '', $text) ?? '');
        $normalizedText = trim(preg_replace('/\s+/u', ' ', $normalizedText) ?? '');

        $sql = "INSERT INTO voice_samples (
            language, text, normalized_text, dictionary_entry_id, audio_url, audio_storage_key,
            speaker_label, voice_type, sample_type, status, duration, mime_type, file_size,
            notes, created_by, approved_by, created_at, updated_at
        ) VALUES (
            :language, :text, :normalized_text, :dictionary_entry_id, :audio_url, :audio_storage_key,
            :speaker_label, :voice_type, :sample_type, :status, :duration, :mime_type, :file_size,
            :notes, :created_by, :approved_by, NOW(), NOW()
        )";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'language'            => $data['language'] ?? 'msm',
            'text'                => $text,
            'normalized_text'     => $normalizedText,
            'dictionary_entry_id' => !empty($data['dictionary_entry_id']) ? (int) $data['dictionary_entry_id'] : null,
            'audio_url'           => $data['audio_url'] ?? '',
            'audio_storage_key'   => $data['audio_storage_key'] ?? null,
            'speaker_label'       => $data['speaker_label'] ?? 'Community Speaker',
            'voice_type'          => $data['voice_type'] ?? 'community',
            'sample_type'         => $data['sample_type'] ?? 'word',
            'status'              => $data['status'] ?? 'approved',
            'duration'            => isset($data['duration']) ? (float) $data['duration'] : null,
            'mime_type'           => $data['mime_type'] ?? 'audio/webm',
            'file_size'           => isset($data['file_size']) ? (int) $data['file_size'] : null,
            'notes'               => $data['notes'] ?? null,
            'created_by'          => isset($data['created_by']) ? (int) $data['created_by'] : null,
            'approved_by'         => isset($data['approved_by']) ? (int) $data['approved_by'] : null,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Find a voice sample by ID.
     */
    public static function find(int $id): ?array
    {
        $db = \App\Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM voice_samples WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update sample status.
     */
    public static function updateStatus(int $id, string $status, ?int $approvedBy = null): bool
    {
        $db = \App\Database::getInstance();
        $stmt = $db->prepare("UPDATE voice_samples SET status = ?, approved_by = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $approvedBy, $id]);
    }

    /**
     * Delete a voice sample entry.
     */
    public static function delete(int $id): bool
    {
        $db = \App\Database::getInstance();
        $sample = self::find($id);
        if ($sample && !empty($sample['audio_storage_key'])) {
            $fullPath = \App\Services\FileService::publicToAbsolutePath($sample['audio_url'] ?? '');
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
        $stmt = $db->prepare("DELETE FROM voice_samples WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Fetch list of samples with optional filters.
     */
    public static function all(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $db = \App\Database::getInstance();

        $where = [];
        $params = [];

        if (!empty($filters['language'])) {
            $where[] = "language = :language";
            $params['language'] = $filters['language'];
        }

        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['sample_type'])) {
            $where[] = "sample_type = :sample_type";
            $params['sample_type'] = $filters['sample_type'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(text LIKE :search OR normalized_text LIKE :search OR speaker_label LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT vs.*, md.manobo, md.english, md.tagalog 
                FROM voice_samples vs
                LEFT JOIN manobo_dictionary md ON vs.dictionary_entry_id = md.id
                {$whereSql}
                ORDER BY vs.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Count total matching records.
     */
    public static function count(array $filters = []): int
    {
        $db = \App\Database::getInstance();
        $where = [];
        $params = [];

        if (!empty($filters['language'])) {
            $where[] = "language = :language";
            $params['language'] = $filters['language'];
        }
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(text LIKE :search OR normalized_text LIKE :search OR speaker_label LIKE :search)";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare("SELECT COUNT(*) FROM voice_samples {$whereSql}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Find matching approved audio recording for text string.
     */
    public static function findMatchingSample(string $language, string $text): ?array
    {
        $db = \App\Database::getInstance();

        $text = trim($text);
        $normalizedText = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', '', $text) ?? '');
        $normalizedText = trim(preg_replace('/\s+/u', ' ', $normalizedText) ?? '');

        if ($normalizedText === '') {
            return null;
        }

        $stmt = $db->prepare("SELECT * FROM voice_samples 
                              WHERE language = ? AND status = 'approved' 
                              AND (normalized_text = ? OR text = ?)
                              ORDER BY length(normalized_text) DESC, id DESC LIMIT 1");
        $stmt->execute([$language, $normalizedText, $text]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Get dataset statistics grouped by language.
     */
    public static function getStatsByLanguage(): array
    {
        $db = \App\Database::getInstance();

        $stats = [
            'msm' => ['total' => 0, 'approved' => 0, 'pending' => 0],
            'fil' => ['total' => 0, 'approved' => 0, 'pending' => 0],
            'en'  => ['total' => 0, 'approved' => 0, 'pending' => 0],
        ];

        try {
            $stmt = $db->query("SELECT language, status, COUNT(*) as count FROM voice_samples GROUP BY language, status");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $r) {
                $lang = $r['language'] ?? 'msm';
                $status = $r['status'] ?? 'pending';
                $cnt = (int) $r['count'];

                if (!isset($stats[$lang])) {
                    $stats[$lang] = ['total' => 0, 'approved' => 0, 'pending' => 0];
                }
                $stats[$lang]['total'] += $cnt;
                if ($status === 'approved') {
                    $stats[$lang]['approved'] += $cnt;
                } elseif ($status === 'pending') {
                    $stats[$lang]['pending'] += $cnt;
                }
            }
        } catch (PDOException $e) {
            error_log('[VoiceSample::getStatsByLanguage] ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Manobo Voice Coverage stats.
     */
    public static function getManoboCoverageStats(): array
    {
        $db = \App\Database::getInstance();

        $totalWords = 0;
        $wordsWithAudio = 0;

        try {
            $totalWords = (int) $db->query("SELECT COUNT(*) FROM manobo_dictionary")->fetchColumn();
            $wordsWithAudio = (int) $db->query("SELECT COUNT(DISTINCT dictionary_entry_id) FROM voice_samples WHERE language = 'msm' AND status = 'approved' AND dictionary_entry_id IS NOT NULL")->fetchColumn();
        } catch (PDOException $e) {
            error_log('[VoiceSample::getManoboCoverageStats] ' . $e->getMessage());
        }

        $missing = max(0, $totalWords - $wordsWithAudio);
        $percentage = $totalWords > 0 ? round(($wordsWithAudio / $totalWords) * 100, 1) : 0;

        return [
            'total_dictionary_words' => $totalWords,
            'words_with_audio'       => $wordsWithAudio,
            'missing_audio'          => $missing,
            'coverage_percentage'    => $percentage,
        ];
    }

    /**
     * Get dictionary entries missing voice sample recordings.
     */
    public static function getMissingPronunciations(int $limit = 50): array
    {
        $db = \App\Database::getInstance();

        $sql = "SELECT md.* FROM manobo_dictionary md
                LEFT JOIN voice_samples vs ON md.id = vs.dictionary_entry_id AND vs.status = 'approved'
                WHERE vs.id IS NULL
                ORDER BY md.id ASC
                LIMIT {$limit}";

        try {
            $stmt = $db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[VoiceSample::getMissingPronunciations] ' . $e->getMessage());
            return [];
        }
    }
}
