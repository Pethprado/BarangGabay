-- Social media links on posts: any platform, plus a stored preview.
--
-- Migration 022 typed source_platform as an ENUM of the four platforms it
-- knew. Staff now paste TikTok, Instagram, X, Threads, Vimeo, Dailymotion and
-- plain video files too, and an ENUM would silently store '' for every one of
-- them — the post would lose its embed with no error anywhere. VARCHAR(20)
-- matches the Postgres schema, which never had the ENUM.
--
-- source_meta holds what SourceLink::fetchMeta() found when the link was
-- attached (caption, thumbnail, site name) as JSON, so a resident's page load
-- never makes an outbound request to draw a preview card.
--
-- The MODIFY is wrapped in an information_schema check for the reason
-- migration 023 gives: this file is replayed on every database connection,
-- and a bare MODIFY can rebuild the table each time even when nothing changes.

ALTER TABLE announcements ADD COLUMN IF NOT EXISTS source_meta TEXT NULL DEFAULT NULL
    COMMENT 'Preview found when the link was attached (JSON: title, description, image, site, author).';
ALTER TABLE events ADD COLUMN IF NOT EXISTS source_meta TEXT NULL DEFAULT NULL
    COMMENT 'Preview found when the link was attached (JSON: title, description, image, site, author).';
ALTER TABLE ordinances ADD COLUMN IF NOT EXISTS source_meta TEXT NULL DEFAULT NULL
    COMMENT 'Preview found when the link was attached (JSON: title, description, image, site, author).';

SET @bg_alter := (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE announcements MODIFY COLUMN source_platform VARCHAR(20) NULL DEFAULT NULL',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'announcements'
      AND COLUMN_NAME = 'source_platform'
      AND DATA_TYPE = 'enum'
);
PREPARE bg_stmt FROM @bg_alter;
EXECUTE bg_stmt;
DEALLOCATE PREPARE bg_stmt;

SET @bg_alter := (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE events MODIFY COLUMN source_platform VARCHAR(20) NULL DEFAULT NULL',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'events'
      AND COLUMN_NAME = 'source_platform'
      AND DATA_TYPE = 'enum'
);
PREPARE bg_stmt FROM @bg_alter;
EXECUTE bg_stmt;
DEALLOCATE PREPARE bg_stmt;

SET @bg_alter := (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE ordinances MODIFY COLUMN source_platform VARCHAR(20) NULL DEFAULT NULL',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ordinances'
      AND COLUMN_NAME = 'source_platform'
      AND DATA_TYPE = 'enum'
);
PREPARE bg_stmt FROM @bg_alter;
EXECUTE bg_stmt;
DEALLOCATE PREPARE bg_stmt;
