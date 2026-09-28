<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Event
{
    /**
     * All non-cancelled events for the resident listing, compound-sorted:
     * ongoing ASC → upcoming ASC → completed DESC
     */
    public static function allForListing(): array
    {
        $stmt = db()->query(
            "SELECT e.*, u.full_name AS organizer_name
             FROM events e
             JOIN users u ON u.id = e.created_by
             WHERE e.status != 'cancelled'
             ORDER BY
               CASE e.status WHEN 'ongoing' THEN 1 WHEN 'upcoming' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
               e.event_date ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Non-cancelled events sorted by date ascending (home page widget & count).
     */
    public static function getUpcoming(): array
    {
        $stmt = db()->prepare(
            'SELECT e.*, u.full_name AS organizer_name
             FROM events e
             JOIN users u ON u.id = e.created_by
             WHERE e.status != ?
             ORDER BY e.event_date ASC'
        );
        $stmt->execute(['cancelled']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Events that have not finished yet, soonest first — what "upcoming"
     * should actually mean on the resident dashboard.
     *
     * getUpcoming() above filters only on status, so a completed event from
     * last March still comes back as "upcoming". That is fine for the events
     * page (which shows history deliberately) but wrong for a dashboard, so
     * this filters on the date as well. An event is still current until its
     * end_date passes, or until the end of its start day when it has none —
     * a fiesta running today should not vanish from the dashboard at 00:01.
     *
     * @return list<array<string,mixed>>
     */
    public static function upcomingFrom(int $limit = 6): array
    {
        $isPgsql = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $endExpr = $isPgsql
            ? "COALESCE(e.end_date, e.event_date + INTERVAL '1 day')"
            : "COALESCE(e.end_date, DATE_ADD(DATE(e.event_date), INTERVAL 1 DAY))";

        $stmt = db()->prepare(
            "SELECT e.*, u.full_name AS organizer_name
             FROM events e
             JOIN users u ON u.id = e.created_by
             WHERE e.status != 'cancelled'
               AND {$endExpr} >= NOW()
             ORDER BY e.event_date ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** How many events are still ahead — the counterpart count to upcomingFrom(). */
    public static function countUpcoming(): int
    {
        $isPgsql = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $endExpr = $isPgsql
            ? "COALESCE(end_date, event_date + INTERVAL '1 day')"
            : "COALESCE(end_date, DATE_ADD(DATE(event_date), INTERVAL 1 DAY))";

        return (int) db()->query(
            "SELECT COUNT(*) FROM events
             WHERE status != 'cancelled'
               AND {$endExpr} >= NOW()"
        )->fetchColumn();
    }

    /** Find one event by slug, with organizer name. */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = db()->prepare(
            // organizer_name is kept for existing callers; the author_* aliases
            // are the shape the shared post header reads, identical across all
            // three content types so one partial can render any of them.
            'SELECT e.*,
                    u.full_name   AS organizer_name,
                    u.full_name   AS author_name,
                    u.avatar_url  AS author_avatar,
                    u.role        AS author_role,
                    u.designation AS author_designation
             FROM events e
             JOIN users u ON u.id = e.created_by
             WHERE e.slug = ? LIMIT 1'
        );
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Find one event by ID, with organizer name. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT e.*, u.full_name AS organizer_name
             FROM events e
             JOIN users u ON u.id = e.created_by
             WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** All events for the admin table, sorted by event date descending. */
    public static function allAdmin(): array
    {
        $stmt = db()->query(
            'SELECT e.*, u.full_name AS organizer_name
             FROM events e
             JOIN users u ON u.id = e.created_by
             ORDER BY e.event_date DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Insert a new event and return its new ID. */
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO events
             (title, slug, description, venue, latitude, longitude,
              event_date, end_date, cover_image_url, created_by, status,
              created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['description'],
            $data['venue']            ?? null,
            $data['latitude']         ?? null,
            $data['longitude']        ?? null,
            $data['event_date'],
            $data['end_date']         ?? null,
            $data['cover_image_url']  ?? null,
            $data['created_by'],
            $data['status'],
        ]);
        return (int) db()->lastInsertId();
    }

    /** Update core event fields (does not touch cover_image_url). */
    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE events
             SET title = ?, slug = ?, description = ?, venue = ?,
                 latitude = ?, longitude = ?, event_date = ?, end_date = ?,
                 status = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['description'],
            $data['venue']     ?? null,
            $data['latitude']  ?? null,
            $data['longitude'] ?? null,
            $data['event_date'],
            $data['end_date']  ?? null,
            $data['status'],
            $id,
        ]);
    }

    /** Update only the cover image URL for an event. */
    public static function updateCover(int $id, string $url): void
    {
        $stmt = db()->prepare(
            'UPDATE events SET cover_image_url = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$url, $id]);
    }

    /** Persist the Manobo (msm — Agusan Manobo) translation of an event. */
    public static function updateManobo(int $id, string $titleManobo, string $descManobo): void
    {
        $stmt = db()->prepare('UPDATE events SET title_manobo = ?, description_manobo = ? WHERE id = ?');
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
        $stmt = db()->prepare('UPDATE events SET title_en = ?, description_en = ? WHERE id = ?');
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
        $stmt = db()->prepare('UPDATE events SET title_fil = ?, description_fil = ? WHERE id = ?');
        $stmt->execute([$titleFil, $bodyFil, $id]);
    }

    /** Record which language a staff member actually wrote this post in. */
    public static function setSourceLang(int $id, string $lang): void
    {
        if (!\in_array($lang, ['fil', 'en'], true)) {
            return;
        }
        db()->prepare('UPDATE events SET source_lang = ? WHERE id = ?')->execute([$lang, $id]);
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
                "SELECT COUNT(*) FROM events
                  WHERE status <> 'cancelled'
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
        db()->prepare('UPDATE events SET audio_manobo_path = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$path, $id]);
    }

    /** Delete an event record. */
    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM events WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * All non-cancelled events formatted for FullCalendar JSON feed.
     * Returns raw DB rows; the controller maps them to FullCalendar objects.
     */
    public static function allForCalendar(): array
    {
        $stmt = db()->query(
            "SELECT id, title, title_manobo, title_en, slug, event_date, end_date, status, venue
             FROM events
             WHERE status != 'cancelled'
             ORDER BY event_date ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return a slug unique in the events table.
     * Appends -2, -3, … when the base slug is already taken.
     */
    public static function uniqueSlug(string $base, int $excludeId = 0): string
    {
        $slug    = $base;
        $counter = 2;
        $pdo     = db();

        while (true) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM events WHERE slug = ? AND id != ?'
            );
            $stmt->execute([$slug, $excludeId]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $counter++;
        }
    }
}
