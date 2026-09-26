-- Room for longer headlines: title VARCHAR(255) -> VARCHAR(500).
--
-- 255 was tight enough that a descriptive title in Filipino — which runs
-- longer than the English equivalent — could hit the wall. Worse, hitting it
-- is invisible: MySQL in non-strict mode truncates silently, so a headline
-- just loses its ending and nobody is told.
--
-- Bodies are already LONGTEXT and have no limit at all. This is only about the
-- one field that must be a finite width because it is rendered in tables and
-- notification text. AnnouncementController::MAX_TITLE_LENGTH matches this
-- number and checks it in PHP, so staff get a message instead of a truncation.
--
-- Slugs stay at VARCHAR(255): they are derived rather than written, and
-- generate_slug() caps them at 180 to leave room for its "-2" suffixes.
--
-- ── Why this is wrapped in a check ───────────────────────────────────────
-- This app has no migrations tracking table: runPendingMigrations() replays
-- every file on every database connection. A bare ALTER TABLE ... MODIFY is
-- not free even when it changes nothing — MariaDB can still rebuild the table
-- by copy — so replaying one on every page load would be a permanent tax on
-- every request. The information_schema check makes it genuinely a no-op once
-- the column is already wide enough. Same hazard migration 019 documents for
-- its UPDATE, different shape.

SET @bg_alter := (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE announcements MODIFY COLUMN title VARCHAR(500) NOT NULL',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'announcements'
      AND COLUMN_NAME = 'title'
      AND CHARACTER_MAXIMUM_LENGTH < 500
);
PREPARE bg_stmt FROM @bg_alter;
EXECUTE bg_stmt;
DEALLOCATE PREPARE bg_stmt;

SET @bg_alter := (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE events MODIFY COLUMN title VARCHAR(500) NOT NULL',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'title'
      AND CHARACTER_MAXIMUM_LENGTH < 500
);
PREPARE bg_stmt FROM @bg_alter;
EXECUTE bg_stmt;
DEALLOCATE PREPARE bg_stmt;

SET @bg_alter := (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE ordinances MODIFY COLUMN title VARCHAR(500) NOT NULL',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ordinances'
      AND COLUMN_NAME = 'title'
      AND CHARACTER_MAXIMUM_LENGTH < 500
);
PREPARE bg_stmt FROM @bg_alter;
EXECUTE bg_stmt;
DEALLOCATE PREPARE bg_stmt;
