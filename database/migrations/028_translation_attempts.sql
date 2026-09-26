-- Why a post is missing a language, recorded instead of guessed at.
--
-- The problem this solves. Today a translation that does not happen leaves
-- no trace anywhere. FreeTranslationService returns null whether its daily
-- allowance ran out, the body was too long for one post, or the far end
-- timed out; TranslationService turns every one of those into `return
-- false`; the save succeeds and the post is stored with no English version
-- and no explanation. Staff find out when a resident tells them, and even
-- then nobody can say which of the five causes it was — so the fix is
-- guessed at, or the post is re-saved repeatedly in the hope it takes.
--
-- ── One row per post, per language, per kind ────────────────────────────
--
-- This table holds the LATEST outcome for each (content, language, kind),
-- not an append-only history. That is a deliberate trade:
--
--   + the questions actually asked are all about the present — which posts
--     are missing a language, what was the last reason, what is due for
--     retry. Each is a plain indexed lookup rather than a per-post subquery
--     for the most recent row.
--   + the table stays proportional to the content, not to how many times
--     the runner has swept. A barangay on shared hosting does not need a
--     log that grows forever and nobody prunes.
--   - the trade-off is that the history of past attempts is lost. The
--     attempt COUNT is kept, because backoff needs it; the individual past
--     failures are not, because nothing asks for them.
--
-- If an audit trail is ever needed, it belongs in a separate append-only
-- table with its own retention rule, not by making this one unbounded.
--
-- ── retry_after is the operational field ────────────────────────────────
--
-- NULL means "no runner will ever fix this" — the text is too long for the
-- provider, the API key is absent, the source language is wrong. Each needs
-- a person to change something, and a retry runner that kept grinding at
-- them would spend the daily allowance on posts that cannot succeed.
--
-- A timestamp means "worth trying again then". For QUOTA_EXHAUSTED that is
-- just after midnight UTC, when the provider's allowance resets, which is
-- the single most common case here and the one that should fill itself in
-- overnight with nobody watching.

CREATE TABLE IF NOT EXISTS translation_attempts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    content_type  VARCHAR(20)  NOT NULL COMMENT 'announcement | event | ordinance',
    content_id    INT UNSIGNED NOT NULL,

    -- 'fil' | 'en' | 'manobo'. The language being PRODUCED, never the source.
    lang          VARCHAR(10)  NOT NULL,

    -- 'text' | 'audio'. Audio for a language can only be attempted after the
    -- text for that language exists, so the two outcomes are tracked apart.
    kind          VARCHAR(10)  NOT NULL DEFAULT 'text',

    ok            TINYINT(1)   NOT NULL DEFAULT 0,

    -- One of TranslationOutcome's constants. Stored as the string rather
    -- than an ENUM so adding a code is a code change, not a migration on a
    -- live table.
    reason_code   VARCHAR(32)  NOT NULL DEFAULT 'PROVIDER_ERROR',

    -- The provider's own sentence, kept for staff and for the log. Never
    -- shown to residents.
    message       TEXT         NULL,

    -- 'mymemory' | 'anthropic' | 'dictionary' | 'browser' | NULL
    provider      VARCHAR(32)  NULL,

    attempts      INT UNSIGNED NOT NULL DEFAULT 1,

    -- NULL = never retry automatically. See the note above.
    retry_after   DATETIME     NULL,

    -- Which text this outcome was about. When the body is edited the hash
    -- changes, which is how stale audio is detected: a track recorded for
    -- text that has since been rewritten is worse than no track at all,
    -- because the player reads the old words under the new headline.
    source_hash   CHAR(40)     NULL,

    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One outcome per post per language per kind. This is what makes the
    -- write an UPSERT and keeps the table bounded.
    UNIQUE KEY uq_target (content_type, content_id, lang, kind),

    -- The retry runner's query: unfinished work whose time has come, newest
    -- posts first.
    KEY idx_due (ok, retry_after),

    -- The health page's query: everything still failing, by language.
    KEY idx_outstanding (ok, lang, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Older databases may have the table without the later columns. Added
-- separately and guarded, because this file replays on every connection and
-- a second run must be a no-op rather than an error.
ALTER TABLE translation_attempts
    ADD COLUMN IF NOT EXISTS source_hash CHAR(40) NULL
    COMMENT 'SHA-1 of the source text this outcome was about; changes when the post is edited.';

ALTER TABLE translation_attempts
    ADD COLUMN IF NOT EXISTS provider VARCHAR(32) NULL
    COMMENT 'Which service was asked: mymemory | anthropic | dictionary | browser.';
