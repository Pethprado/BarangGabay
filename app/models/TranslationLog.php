<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class TranslationLog
{
    /**
     * Find the most-recent cached translation for a piece of content.
     * Returns the row array or null if not cached yet.
     */
    public static function findCached(string $contentType, int $contentId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM translation_logs
             WHERE content_type = ? AND content_id = ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$contentType, $contentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Persist a new translation log entry.
     *
     * If the user_id causes a FK constraint failure (stale session after a DB
     * reset / seeder wipe), we retry once with user_id = NULL so the translation
     * is still cached and the success response is returned to the resident.
     */
    public static function create(
        ?int   $userId,
        string $contentType,
        int    $contentId,
        string $originalText,
        string $translatedText
    ): void {
        $sql = 'INSERT INTO translation_logs
                    (user_id, content_type, content_id, original_text, translated_text, language, created_at)
                VALUES (?, ?, ?, ?, ?, "manobo", NOW())';

        try {
            db()->prepare($sql)->execute([$userId, $contentType, $contentId, $originalText, $translatedText]);
        } catch (\PDOException $e) {
            // FK violation on user_id — retry anonymously so caching still works
            if ($userId !== null && str_contains($e->getMessage(), 'foreign key constraint')) {
                db()->prepare($sql)->execute([null, $contentType, $contentId, $originalText, $translatedText]);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Recent translation entries joined with user name — for admin reports.
     */
    public static function recent(int $limit = 20): array
    {
        $limit = max(1, min($limit, 200));
        return db()->query(
            'SELECT tl.id, tl.content_type, tl.content_id, tl.language, tl.created_at,
                    u.full_name AS user_name
             FROM translation_logs tl
             LEFT JOIN users u ON u.id = tl.user_id
             ORDER BY tl.created_at DESC
             LIMIT ' . $limit
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Total translation requests this calendar month. */
    public static function totalThisMonth(): int
    {
        return (int) db()->query(
            "SELECT COUNT(*) FROM translation_logs
             WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())"
        )->fetchColumn();
    }

    /** Per content-type counts for the current month. */
    public static function monthlyByType(): array
    {
        return db()->query(
            "SELECT content_type, COUNT(*) AS total
             FROM translation_logs
             WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())
             GROUP BY content_type"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}