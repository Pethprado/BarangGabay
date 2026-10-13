<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Trash for announcements, events and ordinances.
 *
 * Moving a post to the Trash stores its whole row as JSON in post_trash and
 * removes it from its own table. That is deliberate: every list, detail page,
 * search, sitemap and AI context in the app reads those tables, and a soft-
 * delete flag would have to be remembered in every one of those queries. One
 * forgotten WHERE would show a deleted notice to residents.
 *
 * Restoring re-inserts the row with its original id, so its link, its
 * translations and anything else that points at it work again.
 */
class PostTrash
{
    /** content_type => [table, author column, label] */
    public const TYPES = [
        'announcement' => ['announcements', 'author_id',   'Announcement'],
        'event'        => ['events',        'created_by',  'Event'],
        'ordinance'    => ['ordinances',    'uploaded_by', 'Ordinance'],
    ];

    /**
     * Move one post to the Trash.
     *
     * @return array<string,mixed>|null the trashed row, or null if it does not exist
     */
    public static function moveToTrash(string $type, int $id, int $deletedBy): ?array
    {
        [$table, $authorCol] = self::TYPES[$type] ?? throw new \InvalidArgumentException("Unknown type {$type}");

        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $snapshot = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($snapshot === false) {
            throw new \RuntimeException('Could not save the post to the Trash.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO post_trash (content_type, content_id, title, author_id, snapshot, deleted_by, deleted_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $type,
                $id,
                mb_substr((string) ($row['title'] ?? ('#' . $id)), 0, 500),
                isset($row[$authorCol]) ? (int) $row[$authorCol] : null,
                $snapshot,
                $deletedBy ?: null,
            ]);
            $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $row;
    }

    /**
     * Everything in the Trash, newest first.
     *
     * @param int|null $authorId only posts written by this user (staff view)
     * @return list<array<string,mixed>>
     */
    public static function all(?int $authorId = null, ?string $type = null): array
    {
        $where = [];
        $args  = [];
        if ($authorId !== null) {
            $where[] = 't.author_id = ?';
            $args[]  = $authorId;
        }
        if ($type !== null && isset(self::TYPES[$type])) {
            $where[] = 't.content_type = ?';
            $args[]  = $type;
        }

        $stmt = db()->prepare(
            'SELECT t.id, t.content_type, t.content_id, t.title, t.author_id, t.deleted_by, t.deleted_at,
                    a.full_name AS author_name, d.full_name AS deleted_by_name
               FROM post_trash t
               LEFT JOIN users a ON a.id = t.author_id
               LEFT JOIN users d ON d.id = t.deleted_by'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY t.deleted_at DESC, t.id DESC'
        );
        $stmt->execute($args);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function count(?int $authorId = null): int
    {
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM post_trash' . ($authorId !== null ? ' WHERE author_id = ?' : ''));
            $stmt->execute($authorId !== null ? [$authorId] : []);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;   // table not created yet
        }
    }

    /** @return array<string,mixed>|null */
    public static function find(int $trashId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM post_trash WHERE id = ? LIMIT 1');
        $stmt->execute([$trashId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Put a post back exactly as it was and remove it from the Trash.
     *
     * The original id is reused. If another post has since taken its slug (or
     * an ordinance its number), the restored one gets a suffix rather than
     * failing.
     *
     * @return array{type:string, id:int, title:string, renamed:bool}
     */
    public static function restore(int $trashId): array
    {
        $item = self::find($trashId) ?? throw new \RuntimeException('That item is no longer in the Trash.');
        [$table] = self::TYPES[$item['content_type']] ?? throw new \RuntimeException('Unknown post type.');

        $row = json_decode((string) $item['snapshot'], true);
        if (!is_array($row) || empty($row['id'])) {
            throw new \RuntimeException('The saved copy of this post is damaged and cannot be restored.');
        }

        $pdo = db();
        $exists = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = ? LIMIT 1");
        $exists->execute([(int) $row['id']]);
        if ($exists->fetchColumn()) {
            throw new \RuntimeException('A post with the same id already exists, so this one cannot be restored.');
        }

        $renamed = false;
        foreach (['slug' => '-restored', 'ordinance_no' => ' (restored)'] as $unique => $suffix) {
            if (!array_key_exists($unique, $row) || $row[$unique] === null) {
                continue;
            }
            $base = (string) $row[$unique];
            $candidate = $base;
            for ($n = 1; $n < 50; $n++) {
                $check = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$unique} = ? LIMIT 1");
                $check->execute([$candidate]);
                if (!$check->fetchColumn()) {
                    break;
                }
                $candidate = $base . $suffix . ($n > 1 ? '-' . $n : '');
                $renamed   = true;
            }
            $row[$unique] = $candidate;
        }

        $pdo->beginTransaction();
        try {
            self::insertExisting($pdo, $table, $row);
            $pdo->prepare('DELETE FROM post_trash WHERE id = ?')->execute([$trashId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'type'    => (string) $item['content_type'],
            'id'      => (int) $row['id'],
            'title'   => (string) $item['title'],
            'renamed' => $renamed,
        ];
    }

    /**
     * Delete a trashed post for good: its files, generated audio and the
     * rows that pointed at it.
     */
    public static function purge(int $trashId): ?array
    {
        $item = self::find($trashId);
        if ($item === null) {
            return null;
        }
        $type = (string) $item['content_type'];
        $id   = (int) $item['content_id'];
        $row  = json_decode((string) $item['snapshot'], true) ?: [];

        try {
            \App\Services\PostAudioService::purgeFor($type, $id);
        } catch (\Throwable $e) {
        }

        $pdo = db();
        foreach ([
            'DELETE FROM notifications WHERE related_type = ? AND related_id = ?',
            'DELETE FROM translation_attempts WHERE content_type = ? AND content_id = ?',
            'DELETE FROM translation_logs WHERE content_type = ? AND content_id = ?',
            'DELETE FROM post_audio WHERE content_type = ? AND content_id = ?',
            'DELETE FROM bookmarks WHERE content_type = ? AND content_id = ?',
            'DELETE FROM media_files WHERE related_type = ? AND related_id = ?',
            'DELETE FROM voice_word_usage WHERE content_type = ? AND content_id = ?',
            'UPDATE voice_samples SET content_type = NULL, content_id = NULL WHERE content_type = ? AND content_id = ?',
        ] as $sql) {
            try {
                $pdo->prepare($sql)->execute([$type, $id]);
            } catch (\Throwable $e) {
                // The table may not exist on this install.
            }
        }

        // Uploaded files only; committed assets under assets/ are shared.
        $root = \dirname(__DIR__, 2);
        foreach (['cover_image_url', 'file_url', 'audio_manobo_path'] as $col) {
            $path = ltrim((string) ($row[$col] ?? ''), '/');
            if (str_starts_with($path, 'uploads/') && !str_contains($path, '..')) {
                @unlink($root . '/public/' . $path);
            }
        }

        $pdo->prepare('DELETE FROM post_trash WHERE id = ?')->execute([$trashId]);

        return $item;
    }

    /**
     * INSERT only the columns this database actually has (a snapshot taken
     * before a migration added or dropped a column still restores).
     *
     * @param array<string,mixed> $row
     */
    private static function insertExisting(PDO $pdo, string $table, array $row): void
    {
        $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $stmt = $pdo->prepare($isPgsql
            ? 'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?'
            : 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $columns = array_flip(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN)));

        $names = [];
        $args  = [];
        foreach ($row as $name => $value) {
            if (isset($columns[strtolower((string) $name)])) {
                $names[] = $name;
                $args[]  = is_bool($value) ? (int) $value : $value;
            }
        }
        $pdo->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')'
        )->execute($args);
    }
}
