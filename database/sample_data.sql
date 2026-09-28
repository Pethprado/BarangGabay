-- ========================================================
-- BarangGabay - Sample Data Dump (MySQL 8.0+)
-- Generated: 2026-09-28 13:56:15 PST
-- Target Barangay: Barangay Bayogo, Madrid, Surigao del Sur
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

﻿SET NAMES utf8mb4;
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


-- ================= DATA INSERTS =================

-- Data for table `users` (6 rows)
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone`, `address`, `zone`, `role`, `status`, `id_photo_url`, `avatar_url`, `designation`, `email_verified`, `totp_enabled`, `created_at`, `updated_at`) VALUES ('1', 'Super Admin', 'admin@baranggabay.ph', '$2y$10$iXQuqoCVtr4A48omCr0xTuL9VnUMdJqUAIWyyIe36laOBFuxZVAYy', '+63 920 444 5566', 'Barangay Hall, Bayogo, Madrid, Surigao del Sur', NULL, 'superadmin', 'verified', NULL, NULL, 'Punong Barangay / System Administrator', '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52');
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone`, `address`, `zone`, `role`, `status`, `id_photo_url`, `avatar_url`, `designation`, `email_verified`, `totp_enabled`, `created_at`, `updated_at`) VALUES ('2', 'Rosa Santos', 'rosa.santos@baranggabay.ph', '$2y$10$giU71sd35UgwlE0wL7vbs.uvD7ITvN0pwK6.ZjMO5by7Zo4pLFknK', '+63 919 234 5678', 'Purok 1, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 1', 'staff', 'verified', NULL, NULL, 'Barangay Secretary', '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52');
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone`, `address`, `zone`, `role`, `status`, `id_photo_url`, `avatar_url`, `designation`, `email_verified`, `totp_enabled`, `created_at`, `updated_at`) VALUES ('3', 'Michelle Prado', 'michelle@baranggabay.ph', '$2y$10$KCna3Nl0nNLYQcqLH2rzZ.JTyg0GCjLNoG4ZVCqxQQ6LjqRXq2eGG', '+63 917 111 2233', 'House 14, Purok 1, Barangay Bayogo, Madrid', 'Purok 1', 'resident', 'verified', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52');
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone`, `address`, `zone`, `role`, `status`, `id_photo_url`, `avatar_url`, `designation`, `email_verified`, `totp_enabled`, `created_at`, `updated_at`) VALUES ('4', 'Juan Dela Cruz', 'juan.delacruz@baranggabay.ph', '$2y$10$QcM5XklgpwvjhVZKJBE1Tu4wPCnvCbq9HMoQkHTE0UX8esXc5JNc6', '+63 918 222 3344', 'Purok 2, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 2', 'resident', 'verified', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52');
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone`, `address`, `zone`, `role`, `status`, `id_photo_url`, `avatar_url`, `designation`, `email_verified`, `totp_enabled`, `created_at`, `updated_at`) VALUES ('5', 'Maria Clara', 'maria.clara@baranggabay.ph', '$2y$10$N1COJRbm9vtlIVDL12GVgu0QcU0NcIDWJJM5S1tyWAAov8jA3sCSy', '+63 919 333 4455', 'Purok 3, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 3', 'resident', 'verified', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52');
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone`, `address`, `zone`, `role`, `status`, `id_photo_url`, `avatar_url`, `designation`, `email_verified`, `totp_enabled`, `created_at`, `updated_at`) VALUES ('6', 'Pedro Penduko', 'pedro.penduko@baranggabay.ph', '$2y$10$vEI.dLRdsgnQegUXzdCqaecSLV7OJgk4H117JzIV6cSEC4bzOQhEC', '+63 917 555 6677', 'Purok 1, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 1', 'resident', 'pending', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52');

-- Data for table `announcements` (4 rows)
INSERT INTO `announcements` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `body`, `body_fil`, `body_manobo`, `category`, `urgency`, `author_id`, `cover_image_url`, `status`, `published_at`, `is_sample`, `created_at`, `updated_at`) VALUES ('1', 'BABALA: Storm Surge at Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon', 'BABALA: Storm Surge at Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon', 'WARNING: Storm Surge and Heavy Rainfall Tonight — Evacuate Immediately', 'BABALA: Storm Surge ug Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon', 'babala-storm-surge-at-malakas-na-ulan', '<p><strong>Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin ng Barangay Bayogo simula alas-otso ngayong gabi.</strong> Ang PAGASA ay nagtaas na ng Tropical Cyclone Signal para sa lalawigan ng Surigao del Sur.</p><h3>Sino ang kailangang lumikas ngayon</h3><ul><li>Lahat ng pamilyang nakatira sa loob ng 50 metro mula sa dalampasigan sa Purok 1 at Purok 2.</li><li>Mga pamilyang nasa gilid ng sapa, lalo na ang may mga bata, buntis, at matatanda.</li><li>Mga mangingisda: iangat at itali nang maayos ang mga bangka sa itaas ng high tide line.</li></ul><h3>Saan pupunta</h3><p>Bukas na ang <strong>Bayogo Barangay Hall</strong> at <strong>Bayogo Elementary School Gym</strong> bilang opisyal na evacuation centers. May mainit na pagkain, malinis na inuming tubig, at banig para sa mga lilikas.</p>', '<p><strong>Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin ng Barangay Bayogo simula alas-otso ngayong gabi.</strong> Ang PAGASA ay nagtaas na ng Tropical Cyclone Signal para sa lalawigan ng Surigao del Sur.</p><h3>Sino ang kailangang lumikas ngayon</h3><ul><li>Lahat ng pamilyang nakatira sa loob ng 50 metro mula sa dalampasigan sa Purok 1 at Purok 2.</li><li>Mga pamilyang nasa gilid ng sapa, lalo na ang may mga bata, buntis, at matatanda.</li><li>Mga mangingisda: iangat at itali nang maayos ang mga bangka sa itaas ng high tide line.</li></ul><h3>Saan pupunta</h3><p>Bukas na ang <strong>Bayogo Barangay Hall</strong> at <strong>Bayogo Elementary School Gym</strong> bilang opisyal na evacuation centers. May mainit na pagkain, malinis na inuming tubig, at banig para sa mga lilikas.</p>', 'Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin sa Barangay Bayogo simula alas-otso ngayong gabi. Ang PAGASA kay nagtaas na sa Tropical Cyclone Signal para sa sa lalawigan sa Surigao del Sur.Sino ang kailangang lumikas ngayonLahat sa pamilyang nakatira sa loob sa 50 metro mula sa dalampasigan sa Purok 1 ug Purok 2.Mga pamilyang nasa gilid sa sapa, lalo na ang may mga bata, buntis, ug matatanda.Mga mangingisda: iangat ug itali sa maayos ang mga bangka sa itaas sa high tide line.Saan pupuntaBukas na ang Bayogo Barangay Hall ug Bayogo Elementary School Gym bilang opisyal na evacuation centers. May mainit na pagkain, malinis na inuming tubig, ug banig para sa sa mga lilikas.', 'safety', 'urgent', '1', '/uploads/announcements/319ea905dd882b17ffdb41669c23fbe1.png', 'published', '2026-09-27 13:55:06', '1', '2026-09-28 13:55:06', '2026-09-28 13:55:06');
INSERT INTO `announcements` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `body`, `body_fil`, `body_manobo`, `category`, `urgency`, `author_id`, `cover_image_url`, `status`, `published_at`, `is_sample`, `created_at`, `updated_at`) VALUES ('2', 'Libreng Medical Mission at Bakuna para sa mga Bata sa Barangay Health Center', 'Libreng Medical Mission at Bakuna para sa mga Bata sa Barangay Health Center', 'Free Medical Mission and Child Vaccination at the Barangay Health Center', 'Libreng Medical Mission ug Bakuna para sa sa mga Bata sa Barangay Health Center', 'libreng-medical-mission-at-bakuna', '<p>Inaanyayahan ang lahat ng residente ng Barangay Bayogo sa gaganaping <strong>Libreng Medical Mission at Bakuna para sa mga Bata</strong> ngayong darating na Sabado mula 8:00 AM hanggang 3:00 PM.</p><h3>Mga Libreng Serbisyo:</h3><ul><li>Konsultasyon sa Doktor at Libreng Gamot</li><li>Pambatang Bakuna (Routine Immunization)</li><li>Blood Pressure at Fasting Blood Sugar screening</li><li>Dental Check-up at bunot ng ngipin</li></ul><p>Mangyaring magdala ng inyong Barangay Health Card o valid ID. Unang 150 pasyente ang mabibigyan ng priority number.</p>', '<p>Inaanyayahan ang lahat ng residente ng Barangay Bayogo sa gaganaping <strong>Libreng Medical Mission at Bakuna para sa mga Bata</strong> ngayong darating na Sabado mula 8:00 AM hanggang 3:00 PM.</p><h3>Mga Libreng Serbisyo:</h3><ul><li>Konsultasyon sa Doktor at Libreng Gamot</li><li>Pambatang Bakuna (Routine Immunization)</li><li>Blood Pressure at Fasting Blood Sugar screening</li><li>Dental Check-up at bunot ng ngipin</li></ul><p>Mangyaring magdala ng inyong Barangay Health Card o valid ID. Unang 150 pasyente ang mabibigyan ng priority number.</p>', 'Inaanyayahan ang lahat sa residente sa Barangay Bayogo sa gaganaping Libreng Medical Mission ug Bakuna para sa sa mga Bata ngayong darating na Sabado mula 8:00 AM hanggang 3:00 PM.Mga Libreng Serbisyo:Konsultasyon sa Doktor ug Libreng GamotPambatang Bakuna (Routine Immunization)Blood Pressure ug Fasting Blood Sugar screeningDental Check-up ug bunot sa ngipinMangyaring magdala sa inyong Barangay Health Card o valid ID. Unang 150 pasyente ang mabibigyan sa priority number.', 'health', 'important', '2', '/uploads/announcements/dee97ba721cc5629fe27ef4ecf24deed.png', 'published', '2026-09-25 13:55:13', '1', '2026-09-28 13:55:13', '2026-09-28 13:55:13');
INSERT INTO `announcements` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `body`, `body_fil`, `body_manobo`, `category`, `urgency`, `author_id`, `cover_image_url`, `status`, `published_at`, `is_sample`, `created_at`, `updated_at`) VALUES ('3', 'Pangkalahatang Asembleya ng Barangay Bayogo para sa Taong 2026', 'Pangkalahatang Asembleya ng Barangay Bayogo para sa Taong 2026', 'Barangay Bayogo General Assembly for the Year 2026', 'Pangkalahatang Asembleya sa Barangay Bayogo para sa sa Taong 2026', 'pangkalahatang-asembleya-ng-barangay-bayogo', '<p>Alinsunod sa Local Government Code, ang Sangguniang Barangay ng Bayogo ay magdaraos ng <strong>First Semester Barangay General Assembly</strong> sa darating na Linggo, 1:00 PM sa Bayogo Multi-Purpose Covered Court.</p><p>Tatalakayin ang mga sumusunod na mahahalagang paksa:</p><ul><li>Ulat sa Pananalapi at Proyekto ng Barangay (State of Barangay Address)</li><li>Plano para sa Bagong Drainage at Kalsada sa Purok 2 at 3</li><li>Open Forum para sa mga katanungan at mungkahi ng mga mamamayan</li></ul><p>Ang inyong pagdalo at boses ay mahalaga sa patuloy na pag-unlad ng ating komunidad.</p>', '<p>Alinsunod sa Local Government Code, ang Sangguniang Barangay ng Bayogo ay magdaraos ng <strong>First Semester Barangay General Assembly</strong> sa darating na Linggo, 1:00 PM sa Bayogo Multi-Purpose Covered Court.</p><p>Tatalakayin ang mga sumusunod na mahahalagang paksa:</p><ul><li>Ulat sa Pananalapi at Proyekto ng Barangay (State of Barangay Address)</li><li>Plano para sa Bagong Drainage at Kalsada sa Purok 2 at 3</li><li>Open Forum para sa mga katanungan at mungkahi ng mga mamamayan</li></ul><p>Ang inyong pagdalo at boses ay mahalaga sa patuloy na pag-unlad ng ating komunidad.</p>', 'Alinsunod sa Local Government Code, ang Sangguniang Barangay sa Bayogo kay magdaraos sa First Semester Barangay General Assembly sa darating na Linggo, 1:00 PM sa Bayogo Multi-Purpose Covered Court.Tatalakayin ang mga sumusunod na mahahalagang paksa:Ulat sa Pananalapi ug Proyekto sa Barangay (State of Barangay Address)Plano para sa sa Bagong Drainage ug Kalsada sa Purok 2 ug 3Open Forum para sa sa mga katanungan ug mungkahi sa mga mamamayanAng inyong pagdalo ug boses kay mahalaga sa patuloy na pag-unlad sa ating komunidad.', 'government', 'normal', '1', '/uploads/announcements/65873416ec0a07dda9e25c438db4971d.png', 'published', '2026-09-23 13:55:21', '1', '2026-09-28 13:55:21', '2026-09-28 13:55:21');
INSERT INTO `announcements` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `body`, `body_fil`, `body_manobo`, `category`, `urgency`, `author_id`, `cover_image_url`, `status`, `published_at`, `is_sample`, `created_at`, `updated_at`) VALUES ('4', 'Bayanihan sa Baybayin: Coastal Clean-Up at Pagtatanim ng Bakawan', 'Bayanihan sa Baybayin: Coastal Clean-Up at Pagtatanim ng Bakawan', 'Coastal Clean-Up and Mangrove Planting Community Activity', 'Bayanihan sa Baybayin: Coastal Clean-Up ug Pagtatanim sa Bakawan', 'coastal-cleanup-at-mangrove-planting', '<p>Bilang bahagi ng ating adbokasiya para sa pangangalaga ng kalikasan at proteksyon laban sa storm surge, magkakaroon tayo ng <strong>Barangay Clean-Up at Mangrove Planting</strong> sa darating na Sabado ng umaga, 6:00 AM.</p><p>Assembly Area: Bayogo Seashore malapit sa Purok 1 Wharf.</p><p>Hinihikayat ang mga kabataan, mga samahan ng mangingisda, at bawat pamilya na makiisa sa bayanihan na ito. Magdala ng bota, guwantes, at reusable water tumbler. May libreng almusal at meryenda para sa mga kalahok.</p>', '<p>Bilang bahagi ng ating adbokasiya para sa pangangalaga ng kalikasan at proteksyon laban sa storm surge, magkakaroon tayo ng <strong>Barangay Clean-Up at Mangrove Planting</strong> sa darating na Sabado ng umaga, 6:00 AM.</p><p>Assembly Area: Bayogo Seashore malapit sa Purok 1 Wharf.</p><p>Hinihikayat ang mga kabataan, mga samahan ng mangingisda, at bawat pamilya na makiisa sa bayanihan na ito. Magdala ng bota, guwantes, at reusable water tumbler. May libreng almusal at meryenda para sa mga kalahok.</p>', 'Bilang bahagi sa ating adbokasiya para sa sa pangangalaga sa kalikasan ug proteksyon laban sa storm surge, magkakaroon tayo sa Barangay Clean-Up ug Mangrove Planting sa darating na Sabado sa umaga, 6:00 AM.Assembly Area: Bayogo Seashore malapit sa Purok 1 Wharf.Hinihikayat ang mga kabataan, mga samahan sa mangingisda, ug bawat pamilya na makiisa sa bayanihan na ito. Magdala sa bota, mga gwantes, ug reusable water tumbler. Ang mga partisipante adunay libre nga pamahaw ug meryenda.', 'social', 'normal', '2', '/uploads/announcements/a3941b90d4bdb713cff9799d3010dc4c.png', 'published', '2026-09-21 13:55:31', '1', '2026-09-28 13:55:31', '2026-09-28 13:55:31');

-- Data for table `events` (4 rows)
INSERT INTO `events` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `description`, `description_fil`, `description_manobo`, `venue`, `latitude`, `longitude`, `event_date`, `end_date`, `cover_image_url`, `created_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('1', 'Barangay Bayogo Inter-Purok Basketball League 2026', 'Barangay Bayogo Inter-Purok Basketball League 2026', 'Barangay Bayogo Inter-Purok Basketball League 2026', 'Barangay Bayogo Inter- Liga sa Basketbol 2026', 'inter-purok-basketball-league-2026', '<p>Opisyal nang magbubukas ang taunang <strong>Inter-Purok Basketball Tournament</strong> kung saan maglalaban-laban ang mga kinatawan mula Purok 1, 2, at 3 para sa kampeonato. Hinihikayat ang lahat na manood at suportahan ang inyong mga purok teams!</p>', '<p>Opisyal nang magbubukas ang taunang <strong>Inter-Purok Basketball Tournament</strong> kung saan maglalaban-laban ang mga kinatawan mula Purok 1, 2, at 3 para sa kampeonato. Hinihikayat ang lahat na manood at suportahan ang inyong mga purok teams!</p>', 'Ang tinuig nga Inter-Purok Basketball Tournament opisyal nga nagbukas diin ang mga representante gikan sa Purok 1 nakigkompetensya, 2, 3 alang sa kampiyonato. Giawhag ang tanan sa pagbantay ug pagsuporta sa imong mga koponan sa kabanikanhan!', 'Bayogo Multi-Purpose Covered Court', '9.2741', '125.9612', '2026-09-28 11:55:31', '2026-09-28 19:55:31', '/uploads/events/9ae06ffc05461d2dc5e499b5edecb9e4.png', '2', 'ongoing', '1', '2026-09-28 13:55:37', '2026-09-28 13:55:37');
INSERT INTO `events` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `description`, `description_fil`, `description_manobo`, `venue`, `latitude`, `longitude`, `event_date`, `end_date`, `cover_image_url`, `created_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('2', 'Operasyon Kontra Dengue: Fogging at Misting sa Buong Barangay', 'Operasyon Kontra Dengue: Fogging at Misting sa Buong Barangay', 'Anti-Dengue Operation: Fogging and Misting Across the Barangay', 'Surgery Batok sa Dengue: Pag-fog and Misting tabok sa Barangay', 'dengue-prevention-at-misting-operation', '<p>Magsasagawa ang Barangay Health Sanitation Team ng malawakang fogging at misting operation upang sugpuin ang mga lamok na nagdadala ng dengue. Pakiusap sa lahat na takpan ang mga imbakan ng tubig at pagkain habang isinasagawa ang operasyon.</p>', '<p>Magsasagawa ang Barangay Health Sanitation Team ng malawakang fogging at misting operation upang sugpuin ang mga lamok na nagdadala ng dengue. Pakiusap sa lahat na takpan ang mga imbakan ng tubig at pagkain habang isinasagawa ang operasyon.</p>', 'Mahitabo Barangay Health Sanitation Team kaylap nga operasyon sa fogging ug misting aron mapugngan ang mga lamok nga nagdala sa dengue. Palihug hangyoa ang tanan sa pagtabon sa tubig ug mga reservoir sa pagkaon sa panahon sa operasyon.', 'Purok 1, Purok 2, at Purok 3', '9.2745', '125.9615', '2026-09-30 08:00:00', '2026-09-30 12:00:00', '/uploads/events/7b763935c970b119608fb546ebded3c2.png', '2', 'upcoming', '1', '2026-09-28 13:55:42', '2026-09-28 13:55:42');
INSERT INTO `events` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `description`, `description_fil`, `description_manobo`, `venue`, `latitude`, `longitude`, `event_date`, `end_date`, `cover_image_url`, `created_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('3', 'Pagsasanay sa Pangkabuhayan: Organic Vegetable Farming & Backyard Gardening', 'Pagsasanay sa Pangkabuhayan: Organic Vegetable Farming & Backyard Gardening', 'Livelihood Workshop: Organic Vegetable Farming & Backyard Gardening', 'Pagbansay sa Ekonomiya: Organic Vegetable Farming (agrikultura sa organikong utanon) & Backyard Gardening', 'livelihood-workshop-organic-farming', '<p>Libreng seminar at praktikal na pagsasanay sa organikong pagtatanim ng gulay at paggawa ng compost fertilizer. Bawat kalahok ay makakatanggap ng libreng binhi at starter planting kit mula sa Department of Agriculture.</p>', '<p>Libreng seminar at praktikal na pagsasanay sa organikong pagtatanim ng gulay at paggawa ng compost fertilizer. Bawat kalahok ay makakatanggap ng libreng binhi at starter planting kit mula sa Department of Agriculture.</p>', 'Libre nga seminar ug praktikal nga pagbansay sa organikong pagpanguma sa utanon ug produksyon sa abono sa compost. Ang matag partisipante makadawat og libre nga binhi ug starter planting kit gikan sa Department of Agriculture.', 'Barangay Bayogo Demo Farm & Hall', '9.2738', '125.9608', '2026-10-03 09:00:00', '2026-10-03 16:00:00', '/uploads/events/1741659a2f733d4d2e4fd0104348f302.png', '1', 'upcoming', '1', '2026-09-28 13:55:49', '2026-09-28 13:55:49');
INSERT INTO `events` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `slug`, `description`, `description_fil`, `description_manobo`, `venue`, `latitude`, `longitude`, `event_date`, `end_date`, `cover_image_url`, `created_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('4', 'Araw ng Kalusugan para sa Ina at Sanggol', 'Araw ng Kalusugan para sa Ina at Sanggol', 'Mother and Child Health and Nutrition Day', 'Adlaw sa Panglawas sa Inahan ug Bata', 'mother-and-child-health-day', '<p>Espesyal na araw ng prenatal check-up, pamamahagi ng bitamina para sa mga buntis at nagpapasusong ina, at feeding program para sa mga batang edad 0 hanggang 5 taon.</p>', '<p>Espesyal na araw ng prenatal check-up, pamamahagi ng bitamina para sa mga buntis at nagpapasusong ina, at feeding program para sa mga batang edad 0 hanggang 5 taon.</p>', 'Espesyal nga adlaw sa pag-check-upsa prenatal, pag-apod-apod sa bitamina alang sa mga mabdos ug nagpasuso nga mga inahan, mga programa sa pagpakaon alang sa mga bata nga nag-edad 0-5 ka tuig.', 'Bayogo Barangay Health Station', '9.274', '125.961', '2026-10-06 08:30:00', '2026-10-06 14:00:00', '/uploads/events/d89b40bc9392d1a2f55be92438c7b749.png', '2', 'upcoming', '1', '2026-09-28 13:55:53', '2026-09-28 13:55:53');

-- Data for table `ordinances` (4 rows)
INSERT INTO `ordinances` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `ordinance_no`, `description`, `description_fil`, `description_manobo`, `category`, `file_url`, `enacted_date`, `ai_summary`, `ai_summary_at`, `uploaded_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('1', 'Kautusang Pambarangay Blg. 01-2026: Tamang Pagtatapon ng Basura at Pagbabawal sa Single-Use Plastics', 'Kautusang Pambarangay Blg. 01-2026: Tamang Pagtatapon ng Basura at Pagbabawal sa Single-Use Plastics', 'Barangay Ordinance No. 01-2026: Ecological Solid Waste Management and Single-Use Plastic Regulation', 'Kautusang Pambarangay Blg. 01-2026: Tamang Pagtatapon sa Basura ug Pagbabawal sa Single-Use Plastics', 'ORD-2026-001', 'Ipinagbabawal ang pagtatapon ng basura sa mga kanal, estero, baybayin, at pampublikong lansangan. Inaatasan ang bawat kabahayan na maghiwalay ng nabubulok (biodegradable) at di-nabubulok (non-biodegradable) na basura alinsunod sa itinakdang iskedyul ng koleksyon.', 'Ipinagbabawal ang pagtatapon ng basura sa mga kanal, estero, baybayin, at pampublikong lansangan. Inaatasan ang bawat kabahayan na maghiwalay ng nabubulok (biodegradable) at di-nabubulok (non-biodegradable) na basura alinsunod sa itinakdang iskedyul ng koleksyon.', 'Ipinagbabawal ang pagtatapon sa basura sa mga kanal, estero, baybayin, ug pampublikong lansangan. Inaatasan ang bawat kabahayan na maghiwalay sa nabubulok (biodegradable) ug di-nabubulok (non-biodegradable) na basura alinsunod sa itinakdang iskedyul sa koleksyon.', 'Kalinisan at Kapaligiran', '/uploads/ordinances/fa13c7460bff8f581e60d171e97ae3a6.pdf', '2026-01-15', 'Mahigpit na ipinagbabawal ang pagkakalat sa kalsada at dalampasigan. Obligado ang pagbubukod ng basura sa tahanan bago ang araw ng koleksyon. May kaukulang multa mula Php 500 hanggang Php 1,500 o community service para sa lalabag.', '2026-09-28 13:56:01', '1', 'active', '1', '2026-09-28 13:56:01', '2026-09-28 13:56:01');
INSERT INTO `ordinances` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `ordinance_no`, `description`, `description_fil`, `description_manobo`, `category`, `file_url`, `enacted_date`, `ai_summary`, `ai_summary_at`, `uploaded_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('2', 'Kautusang Pambarangay Blg. 02-2026: Curfew Hours para sa mga Kabataan mula 10:00 PM hanggang 4:00 AM', 'Kautusang Pambarangay Blg. 02-2026: Curfew Hours para sa mga Kabataan mula 10:00 PM hanggang 4:00 AM', 'Barangay Ordinance No. 02-2026: Curfew Hours for Minors from 10:00 PM to 4:00 AM', 'Kautusang Pambarangay Blg. 02-2026: Curfew Hours para sa sa mga Kabataan mula 10:00 PM hanggang 4:00 AM', 'ORD-2026-002', 'Para sa kaligtasan at kapakanan ng kabataan, ipinagbabawal sa mga menor de edad (edad 17 pababa) ang pagtambay sa mga lansangan at pampublikong lugar mula alas-diyes ng gabi hanggang alas-kuwatro ng madaling araw, maliban kung may kasamang magulang o guardian o may lehitimong dahilan tulad ng emergency o pag-aaral.', 'Para sa kaligtasan at kapakanan ng kabataan, ipinagbabawal sa mga menor de edad (edad 17 pababa) ang pagtambay sa mga lansangan at pampublikong lugar mula alas-diyes ng gabi hanggang alas-kuwatro ng madaling araw, maliban kung may kasamang magulang o guardian o may lehitimong dahilan tulad ng emergency o pag-aaral.', 'Para sa sa kaligtasan ug kapakanan sa kabataan, ipinagbabawal sa mga menor de edad (edad 17 pababa) ang pagtambay sa mga lansangan ug pampublikong lugar mula alas-diyes sa gabi hanggang alas-kuwatro sa madaling araw, maliban kung may kasamang magulang o guardian o may lehitimong dahilan tulad sa emergency o pag-aaral.', 'Kapayapaan at Kaayusan', '/uploads/ordinances/dbc2a984de7627b7330d1e43b4c0d983.pdf', '2026-02-01', 'Bawal gumala ang mga 17 anyos pababa mula 10 PM hanggang 4 AM para sa kanilang kaligtasan. Ang mga magulang ng paulit-ulit na mahuhuli ay sasailalim sa counseling at posibleng pananagutan.', '2026-09-28 13:56:06', '1', 'active', '1', '2026-09-28 13:56:06', '2026-09-28 13:56:06');
INSERT INTO `ordinances` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `ordinance_no`, `description`, `description_fil`, `description_manobo`, `category`, `file_url`, `enacted_date`, `ai_summary`, `ai_summary_at`, `uploaded_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('3', 'Kautusang Pambarangay Blg. 03-2026: Responsableng Pag-aalaga ng Hayop at Paghuli sa Pagala-galang Aso', 'Kautusang Pambarangay Blg. 03-2026: Responsableng Pag-aalaga ng Hayop at Paghuli sa Pagala-galang Aso', 'Barangay Ordinance No. 03-2026: Responsible Pet Ownership and Stray Animal Control', 'Kautusang Pambarangay Blg. 03-2026: Responsableng Pag-aalaga sa Hayop ug Paghuli sa Pagala-galang Aso', 'ORD-2026-003', 'Inaatasan ang lahat ng nagmamay-ari ng aso at pusa sa Barangay Bayogo na iparehistro at pabakunahan laban sa rabies ang kanilang mga alaga. Ipinagbabawal ang pagpapagala ng mga hayop sa labas ng bakuran nang walang tali o tagapangalaga.', 'Inaatasan ang lahat ng nagmamay-ari ng aso at pusa sa Barangay Bayogo na iparehistro at pabakunahan laban sa rabies ang kanilang mga alaga. Ipinagbabawal ang pagpapagala ng mga hayop sa labas ng bakuran nang walang tali o tagapangalaga.', 'Inaatasan ang lahat sa nagmamay-ari sa aso ug pusa sa Barangay Bayogo na iparehistro ug pabakunahan laban sa rabies ang kanilang mga alaga. Ipinagbabawal ang pagpapagala sa mga hayop sa labas sa bakuran sa walang tali o tagapangalaga.', 'Kaligtasan at Kalusugan', '/uploads/ordinances/1c2f5af2f78fc834b259ba2b0da689a1.pdf', '2026-02-20', 'Lahat ng aso at pusa ay dapat nakarehistro, may bakuna sa rabies, at nakatali kung ilalabas sa kalsada. Huhulihin ng barangay tanod ang mga pagala-galang hayop para sa kaligtasan ng mga dumaraan.', '2026-09-28 13:56:10', '2', 'active', '1', '2026-09-28 13:56:10', '2026-09-28 13:56:10');
INSERT INTO `ordinances` (`id`, `title`, `title_fil`, `title_en`, `title_manobo`, `ordinance_no`, `description`, `description_fil`, `description_manobo`, `category`, `file_url`, `enacted_date`, `ai_summary`, `ai_summary_at`, `uploaded_by`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES ('4', 'Kautusang Pambarangay Blg. 04-2026: Regulasyon sa Paggamit ng Videoke at Sound System sa mga Pamayanan', 'Kautusang Pambarangay Blg. 04-2026: Regulasyon sa Paggamit ng Videoke at Sound System sa mga Pamayanan', 'Barangay Ordinance No. 04-2026: Noise Regulation and Videoke Hours in Residential Areas', 'Kautusang Pambarangay Blg. 04-2026: Regulasyon sa Paggamit sa Videoke ug Sound System sa mga Pamayanan', 'ORD-2026-004', 'Upang matiyak ang kapayapaan at maayos na pamamahinga ng mga mamamayan, pinahihintulutan lamang ang paggamit ng videoke, karaoke, at malalakas na amplifier hanggang alas-diyes ng gabi (10:00 PM). Kinakailangan ding mapanatili ang katamtamang lakas ng tunog upang hindi makaabala sa mga kapitbahay.', 'Upang matiyak ang kapayapaan at maayos na pamamahinga ng mga mamamayan, pinahihintulutan lamang ang paggamit ng videoke, karaoke, at malalakas na amplifier hanggang alas-diyes ng gabi (10:00 PM). Kinakailangan ding mapanatili ang katamtamang lakas ng tunog upang hindi makaabala sa mga kapitbahay.', 'Upang matiyak ang kapayapaan ug maayos na pamamahinga sa mga mamamayan, pinahihintulutan lamang ang paggamit sa videoke, karaoke, ug malalakas na amplifier hanggang alas-diyes sa gabi (10:00 PM). Kinakailangan ding mapanatili ang katamtamang lakas sa tunog upang hindi makaabala sa mga kapitbahay.', 'Kapayapaan at Kaayusan', '/uploads/ordinances/a2b66b275c6107f0068c2eb07b42fc69.pdf', '2026-03-01', 'Hanggang 10:00 PM lamang puwedeng mag-videoke o magpatugtog nang malakas sa pamayanan upang makapagpahinga ang mga mag-aaral at nagtatrabaho. Ang paglabag ay may babala sa unang beses at multa sa susunod.', '2026-09-28 13:56:15', '1', 'active', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15');

-- Data for table `evacuation_centers` (3 rows)
INSERT INTO `evacuation_centers` (`id`, `name`, `purok`, `address`, `latitude`, `longitude`, `capacity`, `contact_person`, `contact_phone`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'Bayogo Barangay Hall (Disaster Command Center)', 'Purok 1', 'Barangay Hall Road, Purok 1, Bayogo, Madrid', '9.2741', '125.9612', '120', 'Brgy. Captain / BDRRMC Chief', '+63 920 444 5566', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15');
INSERT INTO `evacuation_centers` (`id`, `name`, `purok`, `address`, `latitude`, `longitude`, `capacity`, `contact_person`, `contact_phone`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'Bayogo Elementary School Gymnasium', 'Purok 2', 'Elementary School Compound, Purok 2, Bayogo', '9.2748', '125.962', '250', 'School DRRM Coordinator', '+63 919 234 5678', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15');
INSERT INTO `evacuation_centers` (`id`, `name`, `purok`, `address`, `latitude`, `longitude`, `capacity`, `contact_person`, `contact_phone`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'Bayogo Multi-Purpose Covered Court', 'Purok 3', 'Plaza Area, Purok 3, Bayogo, Madrid', '9.2735', '125.9605', '300', 'Barangay Tanod Officer-in-Charge', '+63 919 345 6789', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15');

-- Data for table `document_requests` (3 rows)
INSERT INTO `document_requests` (`id`, `reference_no`, `user_id`, `document_type`, `purpose`, `notes`, `status`, `staff_note`, `handled_by`, `requested_at`, `ready_at`, `released_at`, `updated_at`) VALUES ('1', 'BRGY-2026-001', '3', 'Barangay Clearance', 'Local Employment / Job Application', 'Kailangan po para sa requirements sa munisipyo.', 'ready', 'Handa na po para sa pick-up sa Barangay Hall counter 1. Magdala ng 1 valid ID.', '2', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15');
INSERT INTO `document_requests` (`id`, `reference_no`, `user_id`, `document_type`, `purpose`, `notes`, `status`, `staff_note`, `handled_by`, `requested_at`, `ready_at`, `released_at`, `updated_at`) VALUES ('2', 'BRGY-2026-002', '3', 'Certificate of Indigency', 'Medical Assistance / PhilHealth Claim', 'Para po sa tulong pinansyal sa ospital ng aking lola.', 'pending', NULL, NULL, '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15');
INSERT INTO `document_requests` (`id`, `reference_no`, `user_id`, `document_type`, `purpose`, `notes`, `status`, `staff_note`, `handled_by`, `requested_at`, `ready_at`, `released_at`, `updated_at`) VALUES ('3', 'BRGY-2026-003', '4', 'Certificate of Residency', 'Bank Account Opening / Valid ID', 'Katunayan ng paninirahan sa Purok 2.', 'released', 'Nakuha na ng residente noong nakaraang araw.', '2', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15');

-- Data for table `feedbacks` (2 rows)
INSERT INTO `feedbacks` (`id`, `user_id`, `message`, `admin_reply`, `replied_at`, `replied_by`, `is_read_admin`, `created_at`) VALUES ('1', '3', 'Magandang araw po sa ating barangay council. Nais ko pong i-report ang pundidong ilaw sa poste ng kalsada malapit sa Purok 1 chapel. Medyo madilim po sa gabi at delikado sa mga batang naglalakad pauwi galing eskwela.', 'Magandang araw Michelle. Maraming salamat sa iyong pag-uulat. Naitala na po ito sa ating maintenance log at nakaiskedyul na bukas ng umaga ang ating barangay electrician upang palitan ang bumbilya.', '2026-09-28 13:56:15', '2', '1', '2026-09-28 13:56:15');
INSERT INTO `feedbacks` (`id`, `user_id`, `message`, `admin_reply`, `replied_at`, `replied_by`, `is_read_admin`, `created_at`) VALUES ('2', '4', 'Maaari po bang maglagay ng karagdagang communal trash bins sa bukana ng Purok 2 para sa mga mangingisda pag-ahon mula sa baybayin? Maraming salamat po.', 'Magandang araw Juan. Magandang mungkahi ito. Isasama po natin ito sa adyenda ng susunod na barangay session at makikipag-ugnayan sa MENRO.', '2026-09-28 13:56:15', '2', '1', '2026-09-28 13:56:15');

-- Data for table `feedback_messages` (4 rows)
INSERT INTO `feedback_messages` (`id`, `feedback_id`, `sender_id`, `sender_role`, `message`, `read_by_resident`, `read_by_staff`, `created_at`) VALUES ('1', '1', '3', 'resident', 'Magandang araw po sa ating barangay council. Nais ko pong i-report ang pundidong ilaw sa poste ng kalsada malapit sa Purok 1 chapel. Medyo madilim po sa gabi at delikado sa mga batang naglalakad pauwi galing eskwela.', '1', '1', '2026-09-28 13:56:15');
INSERT INTO `feedback_messages` (`id`, `feedback_id`, `sender_id`, `sender_role`, `message`, `read_by_resident`, `read_by_staff`, `created_at`) VALUES ('2', '1', '2', 'staff', 'Magandang araw Michelle. Maraming salamat sa iyong pag-uulat. Naitala na po ito sa ating maintenance log at nakaiskedyul na bukas ng umaga ang ating barangay electrician upang palitan ang bumbilya.', '1', '1', '2026-09-28 13:56:15');
INSERT INTO `feedback_messages` (`id`, `feedback_id`, `sender_id`, `sender_role`, `message`, `read_by_resident`, `read_by_staff`, `created_at`) VALUES ('3', '2', '4', 'resident', 'Maaari po bang maglagay ng karagdagang communal trash bins sa bukana ng Purok 2 para sa mga mangingisda pag-ahon mula sa baybayin? Maraming salamat po.', '1', '1', '2026-09-28 13:56:15');
INSERT INTO `feedback_messages` (`id`, `feedback_id`, `sender_id`, `sender_role`, `message`, `read_by_resident`, `read_by_staff`, `created_at`) VALUES ('4', '2', '2', 'staff', 'Magandang araw Juan. Magandang mungkahi ito. Isasama po natin ito sa adyenda ng susunod na barangay session at makikipag-ugnayan sa MENRO.', '1', '1', '2026-09-28 13:56:15');

-- Data for table `notifications` (4 rows)
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_type`, `is_read`, `created_at`) VALUES ('1', '3', 'Handa na ang inyong Barangay Clearance', 'Ang inyong hiniling na Barangay Clearance (Ref: BRGY-2026-001) ay handa na para sa pick-up sa Barangay Hall.', 'system', 'document_request', '0', '2026-09-28 13:56:15');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_type`, `is_read`, `created_at`) VALUES ('2', '3', 'BABALA: Storm Surge at Malakas na Ulan', 'Inaasahan ang storm surge sa baybayin ng Bayogo. Bukas ang Barangay Hall bilang evacuation center.', 'announcement', 'announcement', '0', '2026-09-28 13:56:15');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_type`, `is_read`, `created_at`) VALUES ('3', '3', 'Maligayang Pagdating sa BarangGabay!', 'Na-verify na ang inyong resident account. Maaari na kayong mag-access ng mga anunsyo, kaganapan, at humiling ng mga dokumento.', 'verification', 'user', '1', '2026-09-28 13:56:15');
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_type`, `is_read`, `created_at`) VALUES ('4', '4', 'Pangkalahatang Asembleya ng Barangay Bayogo', 'Inaanyayahan ang lahat sa General Assembly ngayong Linggo sa Bayogo Multi-Purpose Covered Court.', 'announcement', 'announcement', '0', '2026-09-28 13:56:15');

SET FOREIGN_KEY_CHECKS = 1;
