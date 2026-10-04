<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class Announcement
{
    /**
     * The SQL condition that makes a row visible to residents, right now.
     *
     * Two separate ideas live in `status` and `published_at`, and both have to
     * hold: the post must be marked published AND its go-live moment must have
     * arrived. A post scheduled for Monday 8am is `status = 'published'` from
     * the moment staff save it on Sunday night — that is what "scheduled"
     * means — so status alone is not enough to keep it hidden.
     *
     * A NULL `published_at` counts as visible. Every post created before
     * scheduling existed set it at save time, but a NULL would otherwise make
     * a live post silently vanish, and failing *open* on a barangay notice is
     * the safer of the two failure modes.
     *
     * Deliberately time-based rather than flag-based: the post appears at the
     * right minute on its own, with no cron job, no background worker and no
     * staff member logged in. Nothing has to run for this to come true.
     *
     * @param string $alias Table alias used by the calling query ('' for none).
     */
    public static function visibleSql(string $alias = 'a'): string
    {
        $p = $alias === '' ? '' : $alias . '.';

        return "{$p}status = 'published' AND ({$p}published_at IS NULL OR {$p}published_at <= NOW())";
    }

    /** Latest N published announcements (home page / AI context). */
    public static function getPublished(int $limit = 10): array
    {
        $stmt = db()->prepare(
            'SELECT a.*, u.full_name AS author_name
             FROM announcements a JOIN users u ON u.id = a.author_id
             WHERE ' . self::visibleSql('a') . '
             ORDER BY a.published_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Total count of published announcements (stats card). */
    public static function countPublished(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM announcements WHERE ' . self::visibleSql('')
        )->fetchColumn();
    }

    /**
     * Published announcements newer than a given timestamp — the "new since
     * your last visit" tile on the resident dashboard.
     */
    public static function countPublishedSince(string $since): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM announcements
             WHERE ' . self::visibleSql('') . ' AND published_at > ?'
        );
        $stmt->execute([$since]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Live urgent announcements for the dashboard banner.
     *
     * Bounded by a recency window on purpose: an "urgent" post from last year
     * is history, not an emergency, and leaving it pinned to the top of every
     * resident's screen forever would train people to ignore the banner —
     * exactly the wrong reflex for a typhoon warning in a coastal barangay.
     *
     * @return list<array<string,mixed>>
     */
    public static function activeUrgent(int $withinDays = 14, int $limit = 3): array
    {
        $isPgsql = (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
        $dateCond = $isPgsql
            ? "published_at >= NOW() - (? || ' days')::interval"
            : "published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";

        $stmt = db()->prepare(
            "SELECT id, title, title_manobo, title_en, slug, category, cover_image_url, published_at,
                    LEFT(body, 400) AS excerpt
             FROM announcements
             WHERE " . self::visibleSql('') . "
               AND urgency = 'urgent'
               AND published_at IS NOT NULL
               AND {$dateCond}
             ORDER BY published_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $withinDays), PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit),      PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Paginated list with optional category / urgency / full-text search. */
    public static function paginateFiltered(
        int    $page     = 1,
        int    $perPage  = 10,
        string $category = '',
        string $urgency  = '',
        string $search   = ''
    ): array {
        $where  = [self::visibleSql('a')];
        $params = [];

        if ($category !== '') { $where[] = 'a.category = ?'; $params[] = $category; }
        if ($urgency  !== '') { $where[] = 'a.urgency = ?';  $params[] = $urgency; }
        if ($search   !== '') {
            $where[]  = '(a.title LIKE ? OR a.body LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $countSql  = 'SELECT COUNT(*) FROM announcements a JOIN users u ON u.id = a.author_id WHERE ' . implode(' AND ', $where);
        $countStmt = db()->prepare($countSql);
        $countStmt->execute($params);
        $total     = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql    = 'SELECT a.*, u.full_name AS author_name
                   FROM announcements a JOIN users u ON u.id = a.author_id
                   WHERE ' . implode(' AND ', $where) . '
                   ORDER BY a.published_at DESC
                   LIMIT ? OFFSET ?';

        $stmt = db()->prepare($sql);
        $i = 1;
        foreach ($params as $p) { $stmt->bindValue($i++, $p); }
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i,   $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /** Lightweight search for AJAX live-search endpoint. */
    public static function search(string $query, int $limit = 8): array
    {
        $like = '%' . $query . '%';
        // The translation columns and the flags localised_content() reads come
        // back too, so the live-search dropdown can show the reader the same
        // language the rest of the page is in. Without them the dropdown
        // silently served the Filipino original next to English cards.
        // Bodies are cut in SQL — only an excerpt is ever shown, and eight full
        // announcement bodies is a lot of JSON for a keystroke.
        $stmt = db()->prepare(
            'SELECT a.id, a.slug, a.category, a.urgency, a.cover_image_url, a.published_at,
                    a.title, a.title_en, a.title_fil, a.title_manobo,
                    LEFT(a.body, 400)        AS body,
                    LEFT(a.body_en, 400)     AS body_en,
                    LEFT(a.body_fil, 400)    AS body_fil,
                    LEFT(a.body_manobo, 400) AS body_manobo,
                    a.source_lang, a.en_is_auto, a.fil_is_auto, a.manobo_is_auto, a.en_review_state,
                    u.full_name AS author_name
             FROM announcements a JOIN users u ON u.id = a.author_id
             WHERE ' . self::visibleSql('a') . ' AND (a.title LIKE ? OR a.body LIKE ?)
             ORDER BY a.published_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $like);
        $stmt->bindValue(2, $like);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Find single announcement by slug (resident detail page). */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = db()->prepare(
            'SELECT a.*,
                    u.full_name   AS author_name,
                    u.avatar_url  AS author_avatar,
                    u.role        AS author_role,
                    u.designation AS author_designation
             FROM announcements a JOIN users u ON u.id = a.author_id
             WHERE a.slug = ? LIMIT 1'
        );
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Find by primary key. */
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT a.*, u.full_name AS author_name
             FROM announcements a JOIN users u ON u.id = a.author_id
             WHERE a.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Same-category announcements excluding the current one (detail sidebar). */
    public static function getRelated(string $category, int $excludeId, int $limit = 3): array
    {
        $stmt = db()->prepare(
            'SELECT a.*, u.full_name AS author_name
             FROM announcements a JOIN users u ON u.id = a.author_id
             WHERE ' . self::visibleSql('a') . ' AND a.category = ? AND a.id != ?
             ORDER BY a.published_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $category);
        $stmt->bindValue(2, $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Is this already-loaded row visible to residents right now?
     *
     * The PHP twin of visibleSql(), for the places that fetch a row first and
     * decide afterwards — the detail page, which has to tell "no such post"
     * apart from "not yet". Kept beside visibleSql() so the two rules are read
     * and changed together.
     */
    public static function isVisibleNow(array $row): bool
    {
        if (($row['status'] ?? '') !== 'published') {
            return false;
        }

        $goLive = $row['published_at'] ?? null;
        if ($goLive === null || $goLive === '') {
            return true;    // same fail-open as visibleSql()
        }

        return strtotime((string) $goLive) <= time();
    }

    /** Is this row published, but not due yet? (i.e. waiting on the clock) */
    public static function isScheduled(array $row): bool
    {
        return ($row['status'] ?? '') === 'published' && !self::isVisibleNow($row);
    }

    /**
     * Posts whose go-live moment has passed but whose notifications have not
     * been sent yet — the queue the publisher sweep drains.
     *
     * `notified_at IS NULL` is what stops an SMS blast going out twice: the
     * visibility half of publishing is re-evaluated on every query, but the
     * dispatch half must happen exactly once, so it is a stored fact.
     *
     * Ordered oldest-first so a backlog is cleared in the order it was meant
     * to go out, and limited so a single page load never turns into a hundred
     * SMS sends.
     *
     * @return list<array<string,mixed>>
     */
    public static function dueForPublishing(int $limit = 5): array
    {
        $stmt = db()->prepare(
            "SELECT id, title, slug, body, urgency, author_id, published_at, notify_sms
               FROM announcements
              WHERE status = 'published'
                AND notified_at IS NULL
                AND published_at IS NOT NULL
                AND published_at <= NOW()
              ORDER BY published_at ASC
              LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark a post's go-live dispatch as done.
     *
     * Conditional on `notified_at IS NULL` and returns whether it actually
     * won, so two requests arriving in the same second cannot both decide they
     * are the one sending the SMS batch. The caller claims the row first and
     * only sends if the claim succeeded.
     */
    public static function claimForNotification(int $id): bool
    {
        $stmt = db()->prepare(
            'UPDATE announcements SET notified_at = NOW()
              WHERE id = ? AND notified_at IS NULL'
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    /** Release a claim, so a failed dispatch is retried by the next sweep. */
    public static function releaseNotificationClaim(int $id): void
    {
        db()->prepare('UPDATE announcements SET notified_at = NULL WHERE id = ?')
            ->execute([$id]);
    }

    /**
     * Record whether staff asked for an SMS blast on this post.
     *
     * Stored rather than acted on immediately because a scheduled post is
     * dispatched by the sweep long after the form is gone — see
     * ScheduledPublisher. Wrapped so a database that has not run migration 020
     * yet degrades to the previous always-send behaviour instead of failing
     * the save.
     */
    public static function setNotifySms(int $id, bool $send): void
    {
        try {
            db()->prepare('UPDATE announcements SET notify_sms = ? WHERE id = ?')
                ->execute([$send ? 1 : 0, $id]);
        } catch (\Throwable $e) {
            error_log('[Announcement::setNotifySms] ' . $e->getMessage());
        }
    }

    /** Set or clear the go-live time. NULL means "no scheduled moment". */
    public static function setPublishedAt(int $id, ?string $publishedAt): void
    {
        db()->prepare('UPDATE announcements SET published_at = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$publishedAt, $id]);
    }

    /** Queued posts waiting for their moment (admin list + dashboard count). */
    public static function scheduled(int $limit = 20): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT a.id, a.title, a.slug, a.urgency, a.published_at,
                        u.full_name AS author_name
                   FROM announcements a JOIN users u ON u.id = a.author_id
                  WHERE a.status = 'published' AND a.published_at > NOW()
                  ORDER BY a.published_at ASC
                  LIMIT ?"
            );
            $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** How many posts are queued for a future moment. */
    public static function countScheduled(): int
    {
        try {
            return (int) db()->query(
                "SELECT COUNT(*) FROM announcements
                  WHERE status = 'published' AND published_at > NOW()"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** All announcements (admin list, any status). */
    public static function allAdmin(): array
    {
        $stmt = db()->query(
            'SELECT a.*, u.full_name AS author_name
             FROM announcements a JOIN users u ON u.id = a.author_id
             ORDER BY a.created_at DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Insert a new row. */
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO announcements
             (title, slug, body, category, urgency, author_id,
              cover_image_url, status, published_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['body'],
            $data['category'],
            $data['urgency'],
            $data['author_id'],
            $data['cover_image_url'] ?? null,
            $data['status'],
            $data['published_at']    ?? null,
        ]);
        $id = (int) db()->lastInsertId();
        \App\Services\VoiceUsageIndex::indexPost('announcement', $id);
        return $id;
    }

    /** Update an existing row. */
    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE announcements
             SET title = ?, body = ?, category = ?, urgency = ?,
                 status = ?, published_at = ?, cover_image_url = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['body'],
            $data['category'],
            $data['urgency'],
            $data['status'],
            $data['published_at']    ?? null,
            $data['cover_image_url'] ?? null,
            $id,
        ]);
        \App\Services\VoiceUsageIndex::indexPost('announcement', $id);
    }

    /**
     * Return a slug that does not already exist in the table.
     * Appends -2, -3 … until a free slot is found.
     */
    public static function uniqueSlug(string $base, int $excludeId = 0): string
    {
        $slug = $base;
        $n    = 2;
        while (true) {
            $stmt = db()->prepare(
                'SELECT COUNT(*) FROM announcements WHERE slug = ? AND id != ?'
            );
            $stmt->execute([$slug, $excludeId]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . $n++;
        }
    }

    /** Persist the Manobo (msm — Agusan Manobo) translation of an announcement. */
    public static function updateManobo(int $id, string $titleManobo, string $bodyManobo): void
    {
        $stmt = db()->prepare('UPDATE announcements SET title_manobo = ?, body_manobo = ? WHERE id = ?');
        $stmt->execute([$titleManobo, $bodyManobo, $id]);
        // Keep the Voice Training missing-pronunciation lists current.
        \App\Services\VoiceUsageIndex::indexPost('announcement', $id);
    }

    /**
     * Store the English translation of this record.
     *
     * Mirrors updateManobo() exactly. Kept as its own method rather than a
     * generic `updateTranslation($lang, ...)` because the column names are
     * baked into the SQL, and a language argument that reaches a column name
     * is the kind of thing that turns into an injection hole later.
     */
    /** Review states for a machine-made English translation of an urgent post. */
    public const REVIEW_NONE     = 'none';
    public const REVIEW_PENDING  = 'pending';
    public const REVIEW_APPROVED = 'approved';

    /**
     * Park or release the English translation of an urgent announcement.
     *
     * Only ever one of the three constants above; anything else is ignored
     * rather than written, so a bad caller cannot invent a state that
     * localised_content() has no rule for.
     */
    public static function setEnReviewState(int $id, string $state): void
    {
        if (!\in_array($state, [self::REVIEW_NONE, self::REVIEW_PENDING, self::REVIEW_APPROVED], true)) {
            return;
        }

        db()->prepare('UPDATE announcements SET en_review_state = ? WHERE id = ?')
            ->execute([$state, $id]);
    }

    /**
     * Urgent announcements whose machine English is waiting for a person.
     *
     * Drives both the review queue and the "awaiting review" badge, so staff
     * cannot leave a storm warning sitting untranslated without noticing.
     *
     * @return list<array<string,mixed>>
     */
    public static function awaitingTranslationReview(): array
    {
        return db()->query(
            "SELECT id, title, slug, urgency, published_at, title_en, body_en
               FROM announcements
              WHERE en_review_state = '" . self::REVIEW_PENDING . "'
              ORDER BY published_at DESC, id DESC"
        )->fetchAll();
    }

    /**
     * Announcements written but never published.
     *
     * A draft is work already done that nobody can read yet — the easiest
     * thing in the system to start and forget, and invisible on a dashboard
     * that only counts what is already live.
     */
    public static function countDrafts(): int
    {
        return (int) db()->query(
            "SELECT COUNT(*) FROM announcements WHERE status = 'draft'"
        )->fetchColumn();
    }

    /** How many are waiting — for the sidebar badge, without loading the rows. */
    public static function countAwaitingTranslationReview(): int
    {
        try {
            return (int) db()->query(
                "SELECT COUNT(*) FROM announcements WHERE en_review_state = '" . self::REVIEW_PENDING . "'"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return 0;   // before migration 017 has run
        }
    }

    public static function updateEnglish(int $id, string $titleEn, string $bodyEn): void
    {
        $stmt = db()->prepare('UPDATE announcements SET title_en = ?, body_en = ? WHERE id = ?');
        $stmt->execute([$titleEn, $bodyEn, $id]);
        \App\Services\VoiceUsageIndex::indexPost('announcement', $id);
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
        $stmt = db()->prepare('UPDATE announcements SET title_fil = ?, body_fil = ? WHERE id = ?');
        $stmt->execute([$titleFil, $bodyFil, $id]);
        \App\Services\VoiceUsageIndex::indexPost('announcement', $id);
    }

    /**
     * Which purok this notice is for, and whether it asks for a check-in.
     *
     * An empty purok stores NULL, meaning the whole barangay — the ordinary
     * case, and what every post did before targeting existed.
     *
     * Never throws: a post must save even on a database that has not had
     * migration 027 applied yet. Targeting is an improvement to a notice, not
     * a precondition for publishing one.
     */
    public static function setPurokTargeting(int $id, string $purok, bool $asksCheckin = false): void
    {
        try {
            db()->prepare(
                'UPDATE announcements SET target_purok = ?, asks_safety_checkin = ? WHERE id = ?'
            )->execute([$purok !== '' ? $purok : null, $asksCheckin ? 1 : 0, $id]);
        } catch (\Throwable $e) {
            error_log('[Announcement::setPurokTargeting] #' . $id . ': ' . $e->getMessage());
        }
    }

    /** Record which language a staff member actually wrote this post in. */
    public static function setSourceLang(int $id, string $lang): void
    {
        if (!\in_array($lang, ['fil', 'en'], true)) {
            return;
        }
        db()->prepare('UPDATE announcements SET source_lang = ? WHERE id = ?')->execute([$lang, $id]);
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
                "SELECT COUNT(*) FROM announcements
                  WHERE " . self::visibleSql('') . "
                    AND COALESCE(TRIM(title_manobo), '') = ''
                    AND COALESCE(TRIM(body_manobo), '') = ''"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return 0;   // before the Manobo columns exist
        }
    }

    /** Store or clear the Manobo voice recording path. Pass null to remove. */
    public static function updateAudio(int $id, ?string $path): void
    {
        db()->prepare('UPDATE announcements SET audio_manobo_path = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$path, $id]);
    }

    /** @deprecated Alias kept for existing callers. */
    public static function paginate(int $page = 1, int $perPage = 10): array
    {
        return self::paginateFiltered($page, $perPage);
    }
}