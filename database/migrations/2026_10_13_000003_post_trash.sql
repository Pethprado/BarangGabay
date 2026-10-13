-- Trash for announcements, events and ordinances.
--
-- Deleting a post used to be permanent. Now the whole row is kept here as a
-- JSON snapshot and the post leaves its own table, so no resident page can
-- ever show it, yet staff can restore it exactly as it was (same id, same
-- link) from /admin/trash. Only an admin can delete it from the Trash for good.
--
-- Idempotent (CREATE TABLE IF NOT EXISTS): runPendingMigrations() replays
-- every migration on every connection. PostgreSQL gets the same table from
-- syncPostgresSchema() in config/database.php.

CREATE TABLE IF NOT EXISTS post_trash (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type VARCHAR(20)  NOT NULL COMMENT 'announcement | event | ordinance',
    content_id   INT UNSIGNED NOT NULL COMMENT 'the post id, reused on restore',
    title        VARCHAR(500) NOT NULL,
    author_id    INT UNSIGNED NULL     COMMENT 'who wrote the post (staff may restore their own)',
    snapshot     LONGTEXT     NOT NULL COMMENT 'the full row as JSON',
    deleted_by   INT UNSIGNED NULL,
    deleted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_trash_type (content_type),
    KEY idx_trash_author (author_id),
    KEY idx_trash_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
