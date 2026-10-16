-- Automatic hazard monitoring (App\Services\HazardMonitor).
--
-- One row per hazard the monitor detected: a tropical cyclone near the
-- barangay (GDACS), heavy rain or strong wind in the forecast (Open-Meteo),
-- or a nearby earthquake / tsunami flag (USGS). (source, external_id) is
-- unique, so the same storm or quake is never posted twice; a level increase
-- updates the row and posts an update.
--
-- Idempotent: runPendingMigrations() replays every migration on every
-- connection. PostgreSQL gets the same table from syncPostgresSchema().

CREATE TABLE IF NOT EXISTS hazard_alerts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source          VARCHAR(20)  NOT NULL COMMENT 'typhoon | rain | wind | earthquake',
    external_id     VARCHAR(120) NOT NULL,
    level           VARCHAR(10)  NOT NULL COMMENT 'info | warning | danger',
    title           VARCHAR(255) NOT NULL,
    summary         TEXT         NOT NULL COMMENT 'English',
    summary_fil     TEXT         NULL     COMMENT 'Filipino',
    data            TEXT         NULL     COMMENT 'raw facts as JSON',
    announcement_id INT UNSIGNED NULL,
    detected_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME     NULL,
    resolved_at     DATETIME     NULL,
    UNIQUE KEY uq_hazard_source_ext (source, external_id),
    INDEX idx_hazard_active (resolved_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
