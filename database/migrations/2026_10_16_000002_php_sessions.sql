-- PHP sessions in the database (App\Services\DbSessionHandler).
--
-- File sessions lived on the container's disk, which Render wipes on every
-- deploy and when the free instance sleeps, signing everyone out. Rows here
-- survive both. data is base64; last_activity is a Unix timestamp.
--
-- Idempotent: runPendingMigrations() replays every migration on every
-- connection. PostgreSQL gets the same table from syncPostgresSchema().

CREATE TABLE IF NOT EXISTS php_sessions (
    id            VARCHAR(128) NOT NULL PRIMARY KEY,
    data          MEDIUMTEXT   NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    INDEX idx_php_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
