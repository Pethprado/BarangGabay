-- Migrates the Manobo/Bisaya dictionaries from CSV files to real tables.
--
-- Until now, data/manobo/manobo_dictionary.csv and data/bisaya/bisaya_dictionary.csv
-- were the only source of truth, read directly by App\Services\ManoboDictionary
-- and the bisaya_* helpers in app/helpers.php. That was a deliberate choice —
-- "adding vocabulary requires no code change, append a CSV row" — but it also
-- meant no soft-delete/trash, no per-word audit trail (who added/edited a
-- word, when), and no SQL-level search/filter. This table replaces that file
-- storage; App\Services\ManoboDictionary and the new BisayaDictionary service
-- now read/write here instead of the CSV.
--
-- Schema mirrors the CSV columns exactly (manobo/bisaya, english, tagalog,
-- part_of_speech, category, notes, source) so existing data carries over with
-- no loss, plus: deleted_at (soft delete / Trash), created_by/updated_by (who
-- touched the row), and real timestamps.
--
-- No UNIQUE index on the headword: with soft-delete, a "deleted" word must not
-- permanently block re-adding the same spelling. Duplicate-headword checking
-- (scoped to deleted_at IS NULL) stays in the service layer, same as the CSV
-- version's find()-based check.
--
-- This migration only creates the tables — it does NOT insert the CSV data.
-- Every *.sql file under database/migrations/ replays on every request
-- (see config/database.php::runPendingMigrations), so a one-time bulk import
-- belongs in a manually-run script, not here: see
-- tools/import-dictionaries-to-db.php. Run it once after this migration
-- applies; running it again is safe (it checks row counts first).
--
-- Replays safely: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS manobo_dictionary (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    manobo          VARCHAR(150)    NOT NULL,
    english         VARCHAR(255)    NOT NULL,
    tagalog         VARCHAR(255)    NOT NULL,
    part_of_speech  VARCHAR(20)     NULL,
    category        VARCHAR(30)     NOT NULL DEFAULT 'other',
    notes           TEXT            NULL,
    source          VARCHAR(100)    NOT NULL DEFAULT 'LOCAL',
    deleted_at      DATETIME        NULL DEFAULT NULL COMMENT 'Soft delete (Trash). NULL = live entry.',
    created_by      INT UNSIGNED    NULL,
    updated_by      INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_manobo_headword (manobo),
    KEY idx_manobo_english (english(100)),
    KEY idx_manobo_tagalog (tagalog(100)),
    KEY idx_manobo_category (category),
    KEY idx_manobo_deleted (deleted_at),
    CONSTRAINT fk_manobo_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_manobo_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bisaya_dictionary (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bisaya          VARCHAR(150)    NOT NULL,
    english         VARCHAR(255)    NOT NULL,
    tagalog         VARCHAR(255)    NOT NULL,
    part_of_speech  VARCHAR(20)     NULL,
    category        VARCHAR(30)     NOT NULL DEFAULT 'other',
    notes           TEXT            NULL,
    source          VARCHAR(100)    NOT NULL DEFAULT 'LOCAL',
    deleted_at      DATETIME        NULL DEFAULT NULL COMMENT 'Soft delete (Trash). NULL = live entry.',
    created_by      INT UNSIGNED    NULL,
    updated_by      INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_bisaya_headword (bisaya),
    KEY idx_bisaya_english (english(100)),
    KEY idx_bisaya_tagalog (tagalog(100)),
    KEY idx_bisaya_category (category),
    KEY idx_bisaya_deleted (deleted_at),
    CONSTRAINT fk_bisaya_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_bisaya_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
