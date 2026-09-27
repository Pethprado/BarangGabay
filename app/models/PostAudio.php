<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Cached voice-reader narration: one row per post, per language, per source.
 *
 * Reads here are on the hot path — every detail page asks for a post's tracks —
 * so they are one indexed query returning at most six rows.
 */
class PostAudio
{
    public const SOURCE_AI    = 'ai';
    public const SOURCE_HUMAN = 'human';

    /** @var list<string> */
    public const LOCALES = ['en', 'fil', 'msm'];

    /**
     * Every audio track for one post.
     *
     * @return list<array<string,mixed>>
     */
    public static function forPost(string $contentType, int $contentId): array
    {
        try {
            $stmt = db()->prepare(
                'SELECT * FROM post_audio WHERE content_type = ? AND content_id = ?'
            );
            $stmt->execute([$contentType, $contentId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // A missing table (migration not yet applied) must not take a
            // resident's page down — the reader falls back to browser speech.
            error_log('[PostAudio::forPost] ' . $e->getMessage());
            return [];
        }
    }

    /** One track, or null. */
    public static function find(string $contentType, int $contentId, string $locale, string $source): ?array
    {
        try {
            $stmt = db()->prepare(
                'SELECT * FROM post_audio
                  WHERE content_type = ? AND content_id = ? AND locale = ? AND source = ?
                  LIMIT 1'
            );
            $stmt->execute([$contentType, $contentId, $locale, $source]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            error_log('[PostAudio::find] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Insert or replace one track.
     *
     * Upsert on the (type, id, locale, source) key: regenerating after an edit
     * replaces the row rather than accumulating a history nothing reads.
     */
    public static function put(
        string  $contentType,
        int     $contentId,
        string  $locale,
        string  $source,
        string  $audioPath,
        ?string $voiceName,
        string  $textHash,
        ?int    $durationSeconds,
        ?int    $generatedBy
    ): void {
        $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
        $sql = $isPgsql
            ? 'INSERT INTO post_audio
                 (content_type, content_id, locale, source, audio_path,
                  voice_name, text_hash, duration_seconds, generated_at, generated_by)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
               ON CONFLICT (content_type, content_id, locale, source) DO UPDATE SET
                  audio_path       = EXCLUDED.audio_path,
                  voice_name       = EXCLUDED.voice_name,
                  text_hash        = EXCLUDED.text_hash,
                  duration_seconds = EXCLUDED.duration_seconds,
                  generated_at     = NOW(),
                  generated_by     = EXCLUDED.generated_by'
            : 'INSERT INTO post_audio
                 (content_type, content_id, locale, source, audio_path,
                  voice_name, text_hash, duration_seconds, generated_at, generated_by)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
               ON DUPLICATE KEY UPDATE
                  audio_path       = VALUES(audio_path),
                  voice_name       = VALUES(voice_name),
                  text_hash        = VALUES(text_hash),
                  duration_seconds = VALUES(duration_seconds),
                  generated_at     = NOW(),
                  generated_by     = VALUES(generated_by)';

        $stmt = db()->prepare($sql);

        try {
            $stmt->execute([
                $contentType, $contentId, $locale, $source, $audioPath,
                $voiceName, $textHash, $durationSeconds, $generatedBy,
            ]);
        } catch (\PDOException $e) {
            // Stale session after a database reset would otherwise lose the
            // audio entirely over an attribution detail.
            if ($generatedBy !== null && str_contains($e->getMessage(), 'foreign key constraint')) {
                $stmt->execute([
                    $contentType, $contentId, $locale, $source, $audioPath,
                    $voiceName, $textHash, $durationSeconds, null,
                ]);
                return;
            }
            throw $e;
        }
    }

    /** Remove one track's row. The file itself is the caller's to delete. */
    public static function forget(string $contentType, int $contentId, string $locale, string $source): void
    {
        try {
            db()->prepare(
                'DELETE FROM post_audio WHERE content_type = ? AND content_id = ? AND locale = ? AND source = ?'
            )->execute([$contentType, $contentId, $locale, $source]);
        } catch (\Throwable $e) {
            error_log('[PostAudio::forget] ' . $e->getMessage());
        }
    }

    /** Remove every row for a post. Used when the post itself is deleted. */
    public static function forgetPost(string $contentType, int $contentId): void
    {
        try {
            db()->prepare('DELETE FROM post_audio WHERE content_type = ? AND content_id = ?')
                ->execute([$contentType, $contentId]);
        } catch (\Throwable $e) {
            error_log('[PostAudio::forgetPost] ' . $e->getMessage());
        }
    }

    /**
     * Posts whose Manobo audio is still the AI approximation.
     *
     * The barangay's worklist: every row here is a notice a Manobo speaker
     * could improve by recording it properly. Surfaced in the admin lists so
     * the list can actually be worked through rather than forgotten.
     *
     * @return array<string,true> keyed "type:id" for cheap lookup in a listing
     */
    public static function approximateManoboIndex(string $contentType): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT a.content_id
                   FROM post_audio a
                  WHERE a.content_type = ?
                    AND a.locale = 'msm'
                    AND a.source = 'ai'
                    AND NOT EXISTS (
                        SELECT 1 FROM post_audio h
                         WHERE h.content_type = a.content_type
                           AND h.content_id   = a.content_id
                           AND h.locale       = 'msm'
                           AND h.source       = 'human'
                    )"
            );
            $stmt->execute([$contentType]);

            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                $out[$contentType . ':' . (int) $id] = true;
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('[PostAudio::approximateManoboIndex] ' . $e->getMessage());
            return [];
        }
    }
}
