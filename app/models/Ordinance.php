<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Ordinance
{
    /**
     * All published ordinances (active + repealed) for the admin panel.
     * Sorted by enacted_date DESC then created_at DESC.
     */
    /**
     * The most recently AI-summarised active ordinance, or null.
     *
     * Powers the dashboard's "explained in plain language" card. Returns null
     * whenever nothing has been summarised yet — which is the normal state
     * until an ANTHROPIC_API_KEY is configured and a resident has asked for a
     * summary — so the caller can simply omit the card rather than render an
     * empty promise.
     *
     * @return array<string,mixed>|null
     */
    public static function latestSummarised(): ?array
    {
        $stmt = db()->query(
            "SELECT id, title, title_manobo, title_en, ordinance_no, category, ai_summary, ai_summary_at
             FROM ordinances
             WHERE status = 'active'
               AND ai_summary IS NOT NULL
               AND TRIM(ai_summary) <> ''
             ORDER BY ai_summary_at DESC, id DESC
             LIMIT 1"
        );

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function all(): array
    {
        $stmt = db()->query(
            'SELECT o.*, u.full_name AS uploader_name
             FROM ordinances o
             JOIN users u ON u.id = o.uploaded_by
             ORDER BY o.enacted_date DESC, o.created_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Filtered list for the resident policy library.
     * Excludes drafts. Supports search (title / number / description) and category.
     */
    public static function searchFiltered(string $search = '', string $category = ''): array
    {
        $where  = ["o.status IN ('active','repealed')"];
        $params = [];

        if ($search !== '') {
            $where[]  = '(o.title LIKE ? OR o.ordinance_no LIKE ? OR o.description LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($category !== '') {
            $where[]  = 'o.category = ?';
            $params[] = $category;
        }

        $sql = 'SELECT o.*, u.full_name AS uploader_name
                FROM ordinances o
                JOIN users u ON u.id = o.uploaded_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY o.enacted_date DESC, o.created_at DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Return distinct non-null categories that have at least one ordinance. */
    public static function getCategories(): array
    {
        $stmt = db()->query(
            "SELECT DISTINCT category FROM ordinances
             WHERE category IS NOT NULL AND category != ''
             ORDER BY category ASC"
        );
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category');
    }

    /** Find one ordinance by ID, with uploader name. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT o.*,
                    u.full_name   AS uploader_name,
                    u.full_name   AS author_name,
                    u.avatar_url  AS author_avatar,
                    u.role        AS author_role,
                    u.designation AS author_designation
             FROM ordinances o
             JOIN users u ON u.id = o.uploaded_by
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Insert a new ordinance record and return its ID. */
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO ordinances
             (title, ordinance_no, description, category, file_url,
              enacted_date, uploaded_by, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['title'],
            $data['ordinance_no'],
            $data['description']  ?? null,
            $data['category']     ?? null,
            $data['file_url'],
            $data['enacted_date'] ?? null,
            $data['uploaded_by'],
            $data['status'],
        ]);
        return (int) db()->lastInsertId();
    }

    /** Update core ordinance fields (does not clear ai_summary). */
    public static function updateRecord(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE ordinances
             SET title = ?, ordinance_no = ?, description = ?, category = ?,
                 file_url = ?, enacted_date = ?, status = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['ordinance_no'],
            $data['description'] ?? null,
            $data['category']    ?? null,
            $data['file_url'],
            $data['enacted_date'] ?? null,
            $data['status'],
            $id,
        ]);
    }

    /** Delete an ordinance record. */
    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM ordinances WHERE id = ?')->execute([$id]);
    }

    /** Persist the Manobo (msm — Agusan Manobo) translation of an ordinance. */
    public static function updateManobo(int $id, string $titleManobo, string $descManobo): void
    {
        $stmt = db()->prepare('UPDATE ordinances SET title_manobo = ?, description_manobo = ? WHERE id = ?');
        $stmt->execute([$titleManobo, $descManobo, $id]);
    }

    /**
     * Store the English translation of this record.
     *
     * Mirrors updateManobo() exactly. Kept as its own method rather than a
     * generic `updateTranslation($lang, ...)` because the column names are
     * baked into the SQL, and a language argument that reaches a column name
     * is the kind of thing that turns into an injection hole later.
     */
    public static function updateEnglish(int $id, string $titleEn, string $bodyEn): void
    {
        $stmt = db()->prepare('UPDATE ordinances SET title_en = ?, description_en = ? WHERE id = ?');
        $stmt->execute([$titleEn, $bodyEn, $id]);
    }

    /**
     * Store the Filipino translation of this record.
     *
     * Needed only when the post was WRITTEN in English — otherwise the source
     * text is already the Filipino version and there is nothing to store. See
     * migration 018.
     */
    public static function updateFilipino(int $id, string $titleFil, string $bodyFil): void
    {
        $stmt = db()->prepare('UPDATE ordinances SET title_fil = ?, description_fil = ? WHERE id = ?');
        $stmt->execute([$titleFil, $bodyFil, $id]);
    }

    /** Record which language a staff member actually wrote this post in. */
    public static function setSourceLang(int $id, string $lang): void
    {
        if (!\in_array($lang, ['fil', 'en'], true)) {
            return;
        }
        db()->prepare('UPDATE ordinances SET source_lang = ? WHERE id = ?')->execute([$lang, $id]);
    }

    /**
     * Live records that have no Manobo version at all.
     *
     * The number staff can actually act on: every one of these is a post a
     * Manobo-speaking resident cannot read. No machine translation service
     * supports Manobo, so these only clear when somebody opens the post and
     * uses the dictionary draft button or types a translation — which is
     * exactly why it belongs on the dashboard rather than buried in a list.
     */
    public static function countWithoutManobo(): int
    {
        try {
            return (int) db()->query(
                "SELECT COUNT(*) FROM ordinances
                  WHERE status = 'active'
                    AND COALESCE(TRIM(title_manobo), '') = ''
                    AND COALESCE(TRIM(description_manobo), '') = ''"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return 0;   // before the Manobo columns exist
        }
    }

    /** Store or clear the Manobo voice recording path. Pass null to remove. */
    public static function updateAudio(int $id, ?string $path): void
    {
        db()->prepare('UPDATE ordinances SET audio_manobo_path = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$path, $id]);
    }

    /** Cache the AI-generated summary for an ordinance. */
    public static function updateSummary(int $id, string $summary): void
    {
        $stmt = db()->prepare(
            'UPDATE ordinances SET ai_summary = ?, ai_summary_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$summary, $id]);
    }
}
