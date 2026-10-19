-- Database copy of every file under public/uploads/ (App\Services\UploadStore).
--
-- Render wipes the container disk on every deploy, so uploads saved only to
-- public/uploads/ (valid IDs, avatars, covers, ordinance PDFs) vanished while
-- the database still pointed at them. A missing /uploads/ file is now served
-- from here and written back to disk. data is base64.
--
-- Idempotent: runPendingMigrations() replays every migration on every
-- connection. PostgreSQL gets the same table from syncPostgresSchema().

CREATE TABLE IF NOT EXISTS uploaded_files (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path       VARCHAR(255) NOT NULL,
    mime       VARCHAR(100) NOT NULL,
    size       INT UNSIGNED NOT NULL,
    data       LONGTEXT     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_uploaded_files_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
