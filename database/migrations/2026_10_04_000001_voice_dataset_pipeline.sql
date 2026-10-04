-- Voice dataset pipeline: persistent audio, approval metadata, usage index.
--
-- Render's free web service has an ephemeral disk, so recordings written to
-- public/uploads/ vanish on every restart. Audio bytes now live in the
-- database (same approach as document_request_files) and are streamed by
-- GET /voice/audio/{id}.
--
-- PostgreSQL equivalents live in syncPostgresSchema() in config/database.php.
-- Every statement is idempotent: migrations replay on every connection.

ALTER TABLE voice_samples ADD COLUMN IF NOT EXISTS content_type VARCHAR(50) NULL;
ALTER TABLE voice_samples ADD COLUMN IF NOT EXISTS content_id INT NULL;
ALTER TABLE voice_samples ADD COLUMN IF NOT EXISTS translation VARCHAR(500) NULL;
ALTER TABLE voice_samples ADD COLUMN IF NOT EXISTS profile_id INT NULL;
ALTER TABLE voice_samples ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_vs_lang_status_norm ON voice_samples (language, status, normalized_text(191));
CREATE INDEX IF NOT EXISTS idx_vs_dict ON voice_samples (dictionary_entry_id);

CREATE TABLE IF NOT EXISTS voice_sample_audio (
    sample_id   BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    mime_type   VARCHAR(60)     NOT NULL,
    byte_size   INT UNSIGNED    NOT NULL,
    audio_data  LONGBLOB        NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per (word, place it was seen). content_type is 'announcement',
-- 'event', 'ordinance', or 'reader' (a resident's Voice Reader hit a word
-- with no recording; content_id is then 0 and occurrences counts requests).
CREATE TABLE IF NOT EXISTS voice_word_usage (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    language        VARCHAR(10)  NOT NULL,
    -- Binary collation: Manobo spellings that differ only by an accent are
    -- different words; unicode_ci would merge them and break the unique key.
    normalized_text VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    content_type    VARCHAR(20)  NOT NULL,
    content_id      INT UNSIGNED NOT NULL DEFAULT 0,
    occurrences     INT UNSIGNED NOT NULL DEFAULT 1,
    last_seen_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_vwu (language, normalized_text, content_type, content_id),
    KEY idx_vwu_content (content_type, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
