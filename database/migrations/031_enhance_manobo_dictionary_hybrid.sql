-- Enhances manobo_dictionary for the Manobo & Bisaya Hybrid Translator
-- Adds Bisaya equivalents, normalized keys, type, priority, source_page,
-- review_status, needs_review, aliases, and creates translation_cache and
-- manobo_missing_concepts tables.

ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS bisaya VARCHAR(255) NULL;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS normalized_tagalog VARCHAR(255) NULL;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS normalized_english VARCHAR(255) NULL;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS normalized_bisaya VARCHAR(255) NULL;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS type VARCHAR(30) NOT NULL DEFAULT 'word';
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS priority INT NOT NULL DEFAULT 0;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS source_page INT NULL;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS review_status VARCHAR(30) NOT NULL DEFAULT 'approved';
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS needs_review SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS aliases TEXT NULL;
ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS translation_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_text_hash CHAR(64) NOT NULL,
    source_lang VARCHAR(10) NOT NULL DEFAULT 'fil',
    target_lang VARCHAR(10) NOT NULL DEFAULT 'msm',
    dictionary_version INT NOT NULL DEFAULT 1,
    translated_text MEDIUMTEXT NOT NULL,
    provenance_json MEDIUMTEXT NULL,
    manobo_matches INT NOT NULL DEFAULT 0,
    bisaya_fallbacks INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_trans_cache (source_text_hash, source_lang, target_lang, dictionary_version),
    KEY idx_tc_hash (source_text_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manobo_missing_concepts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concept VARCHAR(255) NOT NULL,
    source_lang VARCHAR(10) NOT NULL DEFAULT 'tl',
    bisaya_fallback VARCHAR(255) NULL,
    usage_count INT NOT NULL DEFAULT 1,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    UNIQUE KEY uniq_missing_concept (concept, source_lang),
    KEY idx_mc_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
