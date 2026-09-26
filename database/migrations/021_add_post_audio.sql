-- Cached voice-reader audio, one row per post per language per source.
--
-- Why a table rather than columns on the three content tables: the reader now
-- carries three languages and two possible sources (a generated AI voice and a
-- recording made by a person), which would be six columns duplicated across
-- announcements, events and ordinances. As a table it is one shape, one set of
-- queries, and a fourth content type later costs nothing.
--
-- text_hash is the load-bearing column. It fingerprints the exact spoken
-- script — SpokenText's output, not the raw body — so when staff edit a post
-- the hash stops matching and the audio is known to be stale. Stale must mean
-- "stop serving this", because the difference between the old and new wording
-- is the entire reason they edited it. A human recording carries the hash of
-- the script that existed when it was uploaded, for the same reason: the
-- barangay should be told its recording no longer matches the notice.
--
-- source='human' rows always win over source='ai' for the same language. That
-- is the Manobo rule in particular: no provider anywhere has an Agusan Manobo
-- voice, so the AI track for Manobo is a Filipino voice reading Manobo text —
-- an approximation, labelled as one, and only used when nobody has recorded it.
CREATE TABLE IF NOT EXISTS post_audio (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type     ENUM('announcement','event','ordinance') NOT NULL,
    content_id       INT UNSIGNED    NOT NULL,
    locale           ENUM('en','fil','msm')                   NOT NULL,
    source           ENUM('ai','human')                       NOT NULL DEFAULT 'ai',
    audio_path       VARCHAR(500)    NOT NULL,
    voice_name       VARCHAR(120)    NULL,
    text_hash        CHAR(64)        NOT NULL,
    duration_seconds INT UNSIGNED    NULL,
    generated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    generated_by     INT UNSIGNED    NULL,
    UNIQUE KEY uniq_post_audio (content_type, content_id, locale, source),
    INDEX idx_post   (content_type, content_id),
    CONSTRAINT fk_post_audio_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
