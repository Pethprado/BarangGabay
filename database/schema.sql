SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS sms_logs;
DROP TABLE IF EXISTS translation_logs;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS ai_chat_logs;
DROP TABLE IF EXISTS ai_logs;
DROP TABLE IF EXISTS feedback_messages;
DROP TABLE IF EXISTS feedbacks;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS media_files;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS ordinances;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS users;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    -- Purok / sitio within the barangay. No default: the old fixed Zone 1–6
    -- list belonged to the system's previous location, and Bayogo's real
    -- subdivisions are not yet confirmed. See barangay_subdivisions() in
    -- app/helpers.php and migration 026.
    zone VARCHAR(50) NULL DEFAULT NULL,
    role ENUM('resident','staff','admin','superadmin') NOT NULL DEFAULT 'resident',
    -- Barangay title for back-office users, e.g. "Barangay Secretary". Set by
    -- the user on /admin/account and shown beside their name in feedback threads.
    designation VARCHAR(100) NULL,
    status ENUM('pending','verified','suspended') NOT NULL DEFAULT 'pending',
    id_photo_url VARCHAR(500) NULL,
    id_verified_by_ai TEXT NULL,
    id_ai_status VARCHAR(20) NULL,
    avatar_url VARCHAR(500) NULL,
    -- Preferred UI language, persisted so it survives logout (helpers.php::current_locale()).
    locale VARCHAR(10) NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    -- Back-office in-app notification preferences. Default ON so a staff member
    -- who never opens the account page is still told about pending work.
    notify_feedback TINYINT(1) NOT NULL DEFAULT 1,
    notify_registrations TINYINT(1) NOT NULL DEFAULT 1,
    notify_content TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    -- When this user last opened their dashboard. Powers the "new since your
    -- last visit" count on the resident home page — a signed-in resident can go
    -- weeks without re-logging in, so last_login_at is a poor proxy for this.
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_id_ai_status (id_ai_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ANNOUNCEMENTS
-- ============================================================
CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_manobo VARCHAR(500) NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    body LONGTEXT NOT NULL,
    body_manobo LONGTEXT NULL,
    audio_manobo_path VARCHAR(500) NULL,
    category ENUM('general','health','safety','government','infrastructure','social') NOT NULL DEFAULT 'general',
    urgency ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal',
    author_id INT UNSIGNED NOT NULL,
    cover_image_url VARCHAR(500) NULL,
    -- Where this came from, when it came from somewhere. NULL is the ordinary
    -- case: written here. See migration 022 and SourceLink::detect().
    source_url VARCHAR(500) NULL,
    source_platform ENUM('facebook','youtube','drive','docs','other') NULL,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    -- Seeded demo content, never something a staff member wrote. Lets
    -- tools/seed-sample-content.php --purge remove exactly the samples and
    -- nothing else. See migration 025.
    is_sample TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_status_published (status, published_at),
    INDEX idx_category (category),
    INDEX idx_is_sample (is_sample)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EVENTS
-- ============================================================
CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_manobo VARCHAR(500) NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description LONGTEXT NOT NULL,
    description_manobo LONGTEXT NULL,
    audio_manobo_path VARCHAR(500) NULL,
    venue VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    event_date DATETIME NOT NULL,
    end_date DATETIME NULL,
    cover_image_url VARCHAR(500) NULL,
    -- See the note on announcements.source_url.
    source_url VARCHAR(500) NULL,
    source_platform ENUM('facebook','youtube','drive','docs','other') NULL,
    created_by INT UNSIGNED NOT NULL,
    status ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    -- See the note on announcements.is_sample.
    is_sample TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_event_date (event_date),
    INDEX idx_is_sample (is_sample)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ORDINANCES
-- ============================================================
CREATE TABLE ordinances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_manobo VARCHAR(500) NULL,
    ordinance_no VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    description_manobo TEXT NULL,
    audio_manobo_path VARCHAR(500) NULL,
    category VARCHAR(100) NULL,
    file_url VARCHAR(500) NOT NULL,
    enacted_date DATE NULL,
    ai_summary LONGTEXT NULL,
    ai_summary_at DATETIME NULL,
    -- See the note on announcements.source_url. For an ordinance this is
    -- typically the Drive link the PDF was pulled from.
    source_url VARCHAR(500) NULL,
    source_platform ENUM('facebook','youtube','drive','docs','other') NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    status ENUM('active','repealed','draft') NOT NULL DEFAULT 'active',
    -- See the note on announcements.is_sample. Especially load-bearing here:
    -- a fabricated ordinance that reads as real barangay law is the worst
    -- thing this seeder could leave behind.
    is_sample TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_is_sample (is_sample)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('announcement','event','ordinance','system','verification') NOT NULL DEFAULT 'system',
    related_id INT UNSIGNED NULL,
    related_type VARCHAR(50) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MEDIA FILES
-- ============================================================
CREATE TABLE media_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    related_type VARCHAR(50) NOT NULL,
    related_id INT UNSIGNED NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type ENUM('image','pdf','document','other') NOT NULL DEFAULT 'image',
    file_size_kb INT UNSIGNED NULL,
    original_name VARCHAR(255) NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AI CHAT LOGS
-- ============================================================
CREATE TABLE ai_chat_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    question TEXT NOT NULL,
    answer LONGTEXT NOT NULL,
    context_used TEXT NULL,
    tokens_used INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LOGIN ATTEMPTS (rate-limiting for auth brute-force protection)
-- ============================================================
CREATE TABLE login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(191) NOT NULL,
    ip_address   VARCHAR(45)  NULL,
    attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email        (email),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AI LOGS (tracks ordinance simplification usage per user)
-- ============================================================
CREATE TABLE ai_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NULL,
    ordinance_id    INT UNSIGNED    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (ordinance_id) REFERENCES ordinances(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_ordinance_id (ordinance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FEEDBACKS (thread header — one row per resident-opened conversation)
-- The actual back-and-forth lives in feedback_messages below. message/
-- admin_reply/replied_by/replied_at/is_read_admin are kept only so this
-- table's shape matches an already-migrated install (see migration 006);
-- the app writes `message` once at thread creation and never writes the
-- other four again — feedback_messages is the source of truth.
-- ============================================================
CREATE TABLE feedbacks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    message         TEXT            NOT NULL,
    admin_reply     TEXT            NULL,
    replied_at      DATETIME        NULL,
    replied_by      INT UNSIGNED    NULL,
    is_read_admin   TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FEEDBACK MESSAGES (the actual two-way conversation per thread)
-- ============================================================
CREATE TABLE feedback_messages (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feedback_id       INT UNSIGNED    NOT NULL,
    sender_id         INT UNSIGNED    NOT NULL,
    sender_role       ENUM('resident','staff','admin','superadmin') NOT NULL,
    message           TEXT            NOT NULL,
    read_by_resident  TINYINT(1)      NOT NULL DEFAULT 0,
    read_by_staff     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)   REFERENCES users(id)      ON DELETE CASCADE,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRANSLATION LOGS (Manobo AI translation cache + usage log)
-- ============================================================
CREATE TABLE translation_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NULL,
    content_type    ENUM('announcement','event','ordinance') NOT NULL,
    content_id      INT UNSIGNED    NOT NULL,
    original_text   TEXT            NOT NULL,
    translated_text LONGTEXT        NOT NULL,
    language        VARCHAR(50)     NOT NULL DEFAULT 'manobo',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_content  (content_type, content_id),
    INDEX idx_user     (user_id),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SMS LOGS (Semaphore SMS send history)
-- ============================================================
CREATE TABLE sms_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone           VARCHAR(20)     NOT NULL,
    message         TEXT            NOT NULL,
    type            VARCHAR(50)     NOT NULL DEFAULT 'general',
    reference_id    INT UNSIGNED    NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
    response        TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type   (type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- POST AUDIO (voice reader — cached narration per post per language)
-- ============================================================
-- One row per post per language per source. text_hash fingerprints the exact
-- spoken script, so an edited post makes its audio detectably stale instead of
-- quietly serving the old wording. source='human' outranks source='ai' for the
-- same language — which is the Manobo rule: no provider has an Agusan Manobo
-- voice, so its AI track is a Filipino voice reading Manobo text, labelled as
-- an approximation and used only when nobody has recorded it.
CREATE TABLE post_audio (
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
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRANSLATION / AUDIO ATTEMPTS
-- ============================================================
-- Why a post is missing a language, recorded instead of guessed at.
--
-- Every failure in the translation pipeline used to look identical from the
-- outside: the free provider returns null whether its daily allowance ran
-- out, the body was too long, or the far end timed out, and
-- TranslationService turned all of them into `return false`. The post saved
-- fine with no English version and no explanation, so staff learned about it
-- from a resident and still could not tell which of five causes it was.
--
-- One row per (content, language, kind) holding the LATEST outcome — not an
-- append-only history. Every question actually asked is about the present
-- (which posts are missing a language, what was the last reason, what is due
-- for retry), so this keeps those as indexed lookups and keeps the table
-- proportional to the content rather than to how often the runner sweeps.
--
-- retry_after NULL means no runner can fix it: the text is too long, the API
-- key is absent, the source language is wrong. Each needs a person. A
-- timestamp means it is worth trying again then — for QUOTA_EXHAUSTED that
-- is just after the provider's midnight reset, which is the case this
-- barangay actually hits and the one that should fill itself in overnight.
--
-- `lang` uses the app's locale codes (en / fil / msm), matching post_audio
-- and available_locales(). The content columns use a `_manobo` suffix for
-- the same language; TranslationAttempt::columnSuffix() is the single place
-- that mapping lives.
CREATE TABLE translation_attempts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type  VARCHAR(20)  NOT NULL COMMENT 'announcement | event | ordinance',
    content_id    INT UNSIGNED NOT NULL,
    lang          VARCHAR(10)  NOT NULL COMMENT 'Language being PRODUCED: en | fil | msm',
    kind          VARCHAR(10)  NOT NULL DEFAULT 'text' COMMENT 'text | audio',
    ok            TINYINT(1)   NOT NULL DEFAULT 0,
    reason_code   VARCHAR(32)  NOT NULL DEFAULT 'PROVIDER_ERROR',
    message       TEXT         NULL,
    provider      VARCHAR(32)  NULL COMMENT 'mymemory | anthropic | dictionary | browser',
    attempts      INT UNSIGNED NOT NULL DEFAULT 1,
    retry_after   DATETIME     NULL COMMENT 'NULL = never retry automatically',
    source_hash   CHAR(40)     NULL COMMENT 'SHA-1 of the source text; changes when the post is edited',
    -- Set when the post is deleted. The row is KEPT: "why did that advisory
    -- never get an English version" is a question that outlives the
    -- advisory. The runner and the health page filter these out; only the
    -- audit view reads them.
    orphaned_at   DATETIME     NULL DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_target (content_type, content_id, lang, kind),
    KEY idx_due (ok, retry_after),
    KEY idx_outstanding (ok, lang, kind),
    KEY idx_orphaned (orphaned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ADDED (migration 030): the Manobo and Bisaya dictionaries, moved off CSV
-- files onto real tables — see that migration's header comment for why, and
-- tools/import-dictionaries-to-db.php for the one-time data import.
CREATE TABLE manobo_dictionary (
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

CREATE TABLE bisaya_dictionary (
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

SET FOREIGN_KEY_CHECKS = 1;
