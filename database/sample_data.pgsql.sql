-- ========================================================
-- BarangGabay - Sample Data Dump (PostgreSQL 14+)
-- Generated: 2026-09-28 13:56:15 PST
-- Target Barangay: Barangay Bayogo, Madrid, Surigao del Sur
-- ========================================================

-- PostgreSQL schema for BarangGabay
-- Converted from schema.sql (MySQL) for Render free PostgreSQL

DROP TABLE IF EXISTS sms_logs CASCADE;
DROP TABLE IF EXISTS translation_attempts CASCADE;
DROP TABLE IF EXISTS translation_logs CASCADE;
DROP TABLE IF EXISTS login_attempts CASCADE;
DROP TABLE IF EXISTS ai_chat_logs CASCADE;
DROP TABLE IF EXISTS ai_logs CASCADE;
DROP TABLE IF EXISTS feedback_messages CASCADE;
DROP TABLE IF EXISTS feedbacks CASCADE;
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS media_files CASCADE;
DROP TABLE IF EXISTS post_audio CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS manobo_dictionary CASCADE;
DROP TABLE IF EXISTS bisaya_dictionary CASCADE;
DROP TABLE IF EXISTS ordinances CASCADE;
DROP TABLE IF EXISTS events CASCADE;
DROP TABLE IF EXISTS announcements CASCADE;
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    zone VARCHAR(50) NULL DEFAULT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'resident' CHECK (role IN ('resident','staff','admin','superadmin')),
    designation VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','verified','suspended')),
    id_photo_url VARCHAR(500) NULL,
    id_verified_by_ai TEXT NULL,
    id_ai_status VARCHAR(20) NULL,
    avatar_url VARCHAR(500) NULL,
    locale VARCHAR(10) NULL,
    totp_secret VARCHAR(512) NULL,
    totp_enabled SMALLINT NOT NULL DEFAULT 0,
    totp_confirmed_at TIMESTAMP NULL,
    notify_feedback SMALLINT NOT NULL DEFAULT 1,
    notify_registrations SMALLINT NOT NULL DEFAULT 1,
    notify_content SMALLINT NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    last_seen_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_id_ai_status ON users(id_ai_status);

CREATE TABLE announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    title_en VARCHAR(500) NULL,
    title_manobo VARCHAR(500) NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    body TEXT NOT NULL,
    body_en TEXT NULL,
    body_manobo TEXT NULL,
    audio_manobo_path VARCHAR(500) NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'general' CHECK (category IN ('general','health','safety','government','infrastructure','social')),
    urgency VARCHAR(20) NOT NULL DEFAULT 'normal' CHECK (urgency IN ('normal','important','urgent')),
    author_id INT NOT NULL,
    cover_image_url VARCHAR(500) NULL,
    source_url VARCHAR(500) NULL,
    source_platform VARCHAR(20) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published','archived')),
    published_at TIMESTAMP NULL,
    scheduled_at TIMESTAMP NULL,
    notify_sent_at TIMESTAMP NULL,
    target_puroks TEXT NULL,
    is_sample SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
);
CREATE INDEX idx_ann_status_published ON announcements(status, published_at);
CREATE INDEX idx_ann_category ON announcements(category);
CREATE INDEX idx_ann_is_sample ON announcements(is_sample);

CREATE TABLE events (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_en VARCHAR(255) NULL,
    title_manobo VARCHAR(500) NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    description_en TEXT NULL,
    description_manobo TEXT NULL,
    audio_manobo_path VARCHAR(500) NULL,
    venue VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    event_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NULL,
    cover_image_url VARCHAR(500) NULL,
    source_url VARCHAR(500) NULL,
    source_platform VARCHAR(20) NULL,
    created_by INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'upcoming' CHECK (status IN ('upcoming','ongoing','completed','cancelled')),
    is_sample SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);
CREATE INDEX idx_events_date ON events(event_date);

CREATE TABLE ordinances (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_en VARCHAR(255) NULL,
    title_manobo VARCHAR(500) NULL,
    ordinance_no VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    description_en TEXT NULL,
    description_manobo TEXT NULL,
    audio_manobo_path VARCHAR(500) NULL,
    category VARCHAR(100) NULL,
    file_url VARCHAR(500) NOT NULL,
    enacted_date DATE NULL,
    ai_summary TEXT NULL,
    ai_summary_manobo TEXT NULL,
    ai_summary_en TEXT NULL,
    ai_summary_at TIMESTAMP NULL,
    source_url VARCHAR(500) NULL,
    source_platform VARCHAR(20) NULL,
    uploaded_by INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','repealed','draft')),
    is_sample SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
);
CREATE INDEX idx_ord_status ON ordinances(status);

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'system',
    related_id INT NULL,
    related_type VARCHAR(50) NULL,
    is_read SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_notif_user_read ON notifications(user_id, is_read);

CREATE TABLE media_files (
    id SERIAL PRIMARY KEY,
    related_type VARCHAR(50) NOT NULL,
    related_id INT NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(20) NOT NULL DEFAULT 'image',
    file_size_kb INT NULL,
    original_name VARCHAR(255) NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX idx_audit_action ON audit_logs(action);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

CREATE TABLE ai_chat_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    context_used TEXT NULL,
    tokens_used INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(191) NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    successful SMALLINT NOT NULL DEFAULT 0,
    success SMALLINT NOT NULL DEFAULT 0,
    reason VARCHAR(50) NULL,
    reason_code VARCHAR(50) NULL,
    user_agent VARCHAR(500) NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_la_email ON login_attempts(email);
CREATE INDEX idx_la_attempted ON login_attempts(attempted_at);

CREATE TABLE ai_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NULL,
    ordinance_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (ordinance_id) REFERENCES ordinances(id) ON DELETE SET NULL
);

CREATE TABLE feedbacks (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    admin_reply TEXT NULL,
    replied_at TIMESTAMP NULL,
    replied_by INT NULL,
    is_read_admin SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX idx_fb_user ON feedbacks(user_id);

CREATE TABLE feedback_messages (
    id SERIAL PRIMARY KEY,
    feedback_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    read_by_resident SMALLINT NOT NULL DEFAULT 0,
    read_by_staff SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE translation_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NULL,
    content_type VARCHAR(30) NOT NULL,
    content_id INT NOT NULL,
    original_text TEXT NOT NULL,
    translated_text TEXT NOT NULL,
    language VARCHAR(50) NOT NULL DEFAULT 'manobo',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE sms_logs (
    id SERIAL PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    reference_id INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    response TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    value_type VARCHAR(10) NOT NULL DEFAULT 'string',
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE error_logs (
    id SERIAL PRIMARY KEY,
    error_id VARCHAR(20) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'error',
    type VARCHAR(255) NULL,
    message TEXT NOT NULL,
    file VARCHAR(500) NULL,
    line INT NULL,
    stack_trace TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_el_severity ON error_logs(severity);
CREATE INDEX idx_el_created ON error_logs(created_at);

CREATE TABLE user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) NULL,
    session_hash CHAR(64) NULL UNIQUE,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    login_at TIMESTAMP NOT NULL DEFAULT NOW(),
    last_seen_at TIMESTAMP NOT NULL DEFAULT NOW(),
    last_active_at TIMESTAMP NOT NULL DEFAULT NOW(),
    logout_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX idx_us_user ON user_sessions(user_id);
CREATE INDEX idx_us_session ON user_sessions(session_id);

CREATE TABLE ai_predictions (
    id SERIAL PRIMARY KEY,
    user_id INT NULL,
    is_valid SMALLINT NULL,
    confidence VARCHAR(20) NULL,
    id_type VARCHAR(100) NULL,
    reason TEXT NULL,
    has_photo SMALLINT NULL,
    has_name SMALLINT NULL,
    issues TEXT NULL,
    id_ai_status VARCHAR(20) NULL,
    raw_response TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE post_audio (
    id SERIAL PRIMARY KEY,
    content_type VARCHAR(30) NOT NULL,
    content_id INT NOT NULL,
    locale VARCHAR(10) NOT NULL,
    source VARCHAR(10) NOT NULL DEFAULT 'ai',
    audio_path VARCHAR(500) NOT NULL,
    voice_name VARCHAR(120) NULL,
    text_hash CHAR(64) NOT NULL,
    duration_seconds INT NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    generated_by INT NULL,
    UNIQUE (content_type, content_id, locale, source),
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE translation_attempts (
    id SERIAL PRIMARY KEY,
    content_type VARCHAR(20) NOT NULL,
    content_id INT NOT NULL,
    lang VARCHAR(10) NOT NULL,
    kind VARCHAR(10) NOT NULL DEFAULT 'text',
    ok SMALLINT NOT NULL DEFAULT 0,
    reason_code VARCHAR(32) NOT NULL DEFAULT 'PROVIDER_ERROR',
    message TEXT NULL,
    provider VARCHAR(32) NULL,
    attempts INT NOT NULL DEFAULT 1,
    retry_after TIMESTAMP NULL,
    source_hash CHAR(40) NULL,
    orphaned_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (content_type, content_id, lang, kind)
);
CREATE INDEX idx_ta_due ON translation_attempts(ok, retry_after);
CREATE INDEX idx_ta_outstanding ON translation_attempts(ok, lang, kind);

CREATE TABLE manobo_dictionary (
    id SERIAL PRIMARY KEY,
    manobo VARCHAR(150) NOT NULL,
    english VARCHAR(255) NOT NULL,
    tagalog VARCHAR(255) NOT NULL,
    bisaya VARCHAR(255) NULL,
    normalized_tagalog VARCHAR(255) NULL,
    normalized_english VARCHAR(255) NULL,
    normalized_bisaya VARCHAR(255) NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'word',
    priority INT NOT NULL DEFAULT 0,
    source_page INT NULL,
    review_status VARCHAR(30) NOT NULL DEFAULT 'approved',
    needs_review SMALLINT NOT NULL DEFAULT 0,
    aliases TEXT NULL,
    part_of_speech VARCHAR(20) NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'other',
    notes TEXT NULL,
    source VARCHAR(100) NOT NULL DEFAULT 'LOCAL',
    archived_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE bisaya_dictionary (
    id SERIAL PRIMARY KEY,
    bisaya VARCHAR(150) NOT NULL,
    english VARCHAR(255) NOT NULL,
    tagalog VARCHAR(255) NOT NULL,
    part_of_speech VARCHAR(20) NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'other',
    notes TEXT NULL,
    source VARCHAR(100) NOT NULL DEFAULT 'LOCAL',
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS two_factor_backup_codes (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_user_unused ON two_factor_backup_codes (user_id, used_at);

CREATE TABLE IF NOT EXISTS document_requests (
    id SERIAL PRIMARY KEY,
    reference_no VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    document_type VARCHAR(60) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    staff_note TEXT NULL,
    handled_by INT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT NOW(),
    ready_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_dr_status ON document_requests(status);
CREATE INDEX IF NOT EXISTS idx_dr_user ON document_requests(user_id);

CREATE TABLE IF NOT EXISTS evacuation_centers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    purok VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    capacity INT NULL,
    contact_person VARCHAR(120) NULL,
    contact_phone VARCHAR(30) NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ec_purok ON evacuation_centers(purok);
CREATE INDEX IF NOT EXISTS idx_ec_active ON evacuation_centers(is_active);

CREATE TABLE IF NOT EXISTS safety_checkins (
    id SERIAL PRIMARY KEY,
    advisory_id INT NOT NULL,
    user_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'safe',
    purok VARCHAR(50) NULL,
    note VARCHAR(255) NULL,
    checked_in_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (advisory_id, user_id),
    FOREIGN KEY (advisory_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_sc_status ON safety_checkins(status);

CREATE TABLE IF NOT EXISTS translation_cache (
    id SERIAL PRIMARY KEY,
    source_text_hash CHAR(64) NOT NULL,
    source_lang VARCHAR(10) NOT NULL DEFAULT 'fil',
    target_lang VARCHAR(10) NOT NULL DEFAULT 'msm',
    dictionary_version INT NOT NULL DEFAULT 1,
    translated_text TEXT NOT NULL,
    provenance_json TEXT NULL,
    manobo_matches INT NOT NULL DEFAULT 0,
    bisaya_fallbacks INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (source_text_hash, source_lang, target_lang, dictionary_version)
);
CREATE INDEX IF NOT EXISTS idx_tc_hash ON translation_cache(source_text_hash);

CREATE TABLE IF NOT EXISTS manobo_missing_concepts (
    id SERIAL PRIMARY KEY,
    concept VARCHAR(255) NOT NULL,
    source_lang VARCHAR(10) NOT NULL DEFAULT 'tl',
    bisaya_fallback VARCHAR(255) NULL,
    usage_count INT NOT NULL DEFAULT 1,
    first_seen_at TIMESTAMP NOT NULL DEFAULT NOW(),
    last_seen_at TIMESTAMP NOT NULL DEFAULT NOW(),
    review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    UNIQUE (concept, source_lang)
);
CREATE INDEX IF NOT EXISTS idx_mc_status ON manobo_missing_concepts(review_status);




-- ================= DATA INSERTS =================

-- Data for table "users" (6 rows)
INSERT INTO "users" ("id", "full_name", "email", "password_hash", "phone", "address", "zone", "role", "status", "id_photo_url", "avatar_url", "designation", "email_verified", "totp_enabled", "created_at", "updated_at") VALUES ('1', 'Super Admin', 'admin@baranggabay.ph', '$2y$10$iXQuqoCVtr4A48omCr0xTuL9VnUMdJqUAIWyyIe36laOBFuxZVAYy', '+63 920 444 5566', 'Barangay Hall, Bayogo, Madrid, Surigao del Sur', NULL, 'superadmin', 'verified', NULL, NULL, 'Punong Barangay / System Administrator', '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52') ON CONFLICT DO NOTHING;
INSERT INTO "users" ("id", "full_name", "email", "password_hash", "phone", "address", "zone", "role", "status", "id_photo_url", "avatar_url", "designation", "email_verified", "totp_enabled", "created_at", "updated_at") VALUES ('2', 'Rosa Santos', 'rosa.santos@baranggabay.ph', '$2y$10$giU71sd35UgwlE0wL7vbs.uvD7ITvN0pwK6.ZjMO5by7Zo4pLFknK', '+63 919 234 5678', 'Purok 1, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 1', 'staff', 'verified', NULL, NULL, 'Barangay Secretary', '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52') ON CONFLICT DO NOTHING;
INSERT INTO "users" ("id", "full_name", "email", "password_hash", "phone", "address", "zone", "role", "status", "id_photo_url", "avatar_url", "designation", "email_verified", "totp_enabled", "created_at", "updated_at") VALUES ('3', 'Michelle Prado', 'michelle@baranggabay.ph', '$2y$10$KCna3Nl0nNLYQcqLH2rzZ.JTyg0GCjLNoG4ZVCqxQQ6LjqRXq2eGG', '+63 917 111 2233', 'House 14, Purok 1, Barangay Bayogo, Madrid', 'Purok 1', 'resident', 'verified', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52') ON CONFLICT DO NOTHING;
INSERT INTO "users" ("id", "full_name", "email", "password_hash", "phone", "address", "zone", "role", "status", "id_photo_url", "avatar_url", "designation", "email_verified", "totp_enabled", "created_at", "updated_at") VALUES ('4', 'Juan Dela Cruz', 'juan.delacruz@baranggabay.ph', '$2y$10$QcM5XklgpwvjhVZKJBE1Tu4wPCnvCbq9HMoQkHTE0UX8esXc5JNc6', '+63 918 222 3344', 'Purok 2, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 2', 'resident', 'verified', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52') ON CONFLICT DO NOTHING;
INSERT INTO "users" ("id", "full_name", "email", "password_hash", "phone", "address", "zone", "role", "status", "id_photo_url", "avatar_url", "designation", "email_verified", "totp_enabled", "created_at", "updated_at") VALUES ('5', 'Maria Clara', 'maria.clara@baranggabay.ph', '$2y$10$N1COJRbm9vtlIVDL12GVgu0QcU0NcIDWJJM5S1tyWAAov8jA3sCSy', '+63 919 333 4455', 'Purok 3, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 3', 'resident', 'verified', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52') ON CONFLICT DO NOTHING;
INSERT INTO "users" ("id", "full_name", "email", "password_hash", "phone", "address", "zone", "role", "status", "id_photo_url", "avatar_url", "designation", "email_verified", "totp_enabled", "created_at", "updated_at") VALUES ('6', 'Pedro Penduko', 'pedro.penduko@baranggabay.ph', '$2y$10$vEI.dLRdsgnQegUXzdCqaecSLV7OJgk4H117JzIV6cSEC4bzOQhEC', '+63 917 555 6677', 'Purok 1, Barangay Bayogo, Madrid, Surigao del Sur', 'Purok 1', 'resident', 'pending', NULL, NULL, NULL, '1', '0', '2026-09-28 13:54:52', '2026-09-28 13:54:52') ON CONFLICT DO NOTHING;

-- Data for table "announcements" (4 rows)
INSERT INTO "announcements" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "body", "body_fil", "body_manobo", "category", "urgency", "author_id", "cover_image_url", "status", "published_at", "is_sample", "created_at", "updated_at") VALUES ('1', 'BABALA: Storm Surge at Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon', 'BABALA: Storm Surge at Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon', 'WARNING: Storm Surge and Heavy Rainfall Tonight — Evacuate Immediately', 'BABALA: Storm Surge ug Malakas na Ulan Ngayong Gabi — Lumikas Na Ngayon', 'babala-storm-surge-at-malakas-na-ulan', '<p><strong>Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin ng Barangay Bayogo simula alas-otso ngayong gabi.</strong> Ang PAGASA ay nagtaas na ng Tropical Cyclone Signal para sa lalawigan ng Surigao del Sur.</p><h3>Sino ang kailangang lumikas ngayon</h3><ul><li>Lahat ng pamilyang nakatira sa loob ng 50 metro mula sa dalampasigan sa Purok 1 at Purok 2.</li><li>Mga pamilyang nasa gilid ng sapa, lalo na ang may mga bata, buntis, at matatanda.</li><li>Mga mangingisda: iangat at itali nang maayos ang mga bangka sa itaas ng high tide line.</li></ul><h3>Saan pupunta</h3><p>Bukas na ang <strong>Bayogo Barangay Hall</strong> at <strong>Bayogo Elementary School Gym</strong> bilang opisyal na evacuation centers. May mainit na pagkain, malinis na inuming tubig, at banig para sa mga lilikas.</p>', '<p><strong>Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin ng Barangay Bayogo simula alas-otso ngayong gabi.</strong> Ang PAGASA ay nagtaas na ng Tropical Cyclone Signal para sa lalawigan ng Surigao del Sur.</p><h3>Sino ang kailangang lumikas ngayon</h3><ul><li>Lahat ng pamilyang nakatira sa loob ng 50 metro mula sa dalampasigan sa Purok 1 at Purok 2.</li><li>Mga pamilyang nasa gilid ng sapa, lalo na ang may mga bata, buntis, at matatanda.</li><li>Mga mangingisda: iangat at itali nang maayos ang mga bangka sa itaas ng high tide line.</li></ul><h3>Saan pupunta</h3><p>Bukas na ang <strong>Bayogo Barangay Hall</strong> at <strong>Bayogo Elementary School Gym</strong> bilang opisyal na evacuation centers. May mainit na pagkain, malinis na inuming tubig, at banig para sa mga lilikas.</p>', 'Inaasahan ang storm surge na aabot sa 1.5 hanggang 2.5 metro sa baybayin sa Barangay Bayogo simula alas-otso ngayong gabi. Ang PAGASA kay nagtaas na sa Tropical Cyclone Signal para sa sa lalawigan sa Surigao del Sur.Sino ang kailangang lumikas ngayonLahat sa pamilyang nakatira sa loob sa 50 metro mula sa dalampasigan sa Purok 1 ug Purok 2.Mga pamilyang nasa gilid sa sapa, lalo na ang may mga bata, buntis, ug matatanda.Mga mangingisda: iangat ug itali sa maayos ang mga bangka sa itaas sa high tide line.Saan pupuntaBukas na ang Bayogo Barangay Hall ug Bayogo Elementary School Gym bilang opisyal na evacuation centers. May mainit na pagkain, malinis na inuming tubig, ug banig para sa sa mga lilikas.', 'safety', 'urgent', '1', '/uploads/announcements/319ea905dd882b17ffdb41669c23fbe1.png', 'published', '2026-09-27 13:55:06', '1', '2026-09-28 13:55:06', '2026-09-28 13:55:06') ON CONFLICT DO NOTHING;
INSERT INTO "announcements" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "body", "body_fil", "body_manobo", "category", "urgency", "author_id", "cover_image_url", "status", "published_at", "is_sample", "created_at", "updated_at") VALUES ('2', 'Libreng Medical Mission at Bakuna para sa mga Bata sa Barangay Health Center', 'Libreng Medical Mission at Bakuna para sa mga Bata sa Barangay Health Center', 'Free Medical Mission and Child Vaccination at the Barangay Health Center', 'Libreng Medical Mission ug Bakuna para sa sa mga Bata sa Barangay Health Center', 'libreng-medical-mission-at-bakuna', '<p>Inaanyayahan ang lahat ng residente ng Barangay Bayogo sa gaganaping <strong>Libreng Medical Mission at Bakuna para sa mga Bata</strong> ngayong darating na Sabado mula 8:00 AM hanggang 3:00 PM.</p><h3>Mga Libreng Serbisyo:</h3><ul><li>Konsultasyon sa Doktor at Libreng Gamot</li><li>Pambatang Bakuna (Routine Immunization)</li><li>Blood Pressure at Fasting Blood Sugar screening</li><li>Dental Check-up at bunot ng ngipin</li></ul><p>Mangyaring magdala ng inyong Barangay Health Card o valid ID. Unang 150 pasyente ang mabibigyan ng priority number.</p>', '<p>Inaanyayahan ang lahat ng residente ng Barangay Bayogo sa gaganaping <strong>Libreng Medical Mission at Bakuna para sa mga Bata</strong> ngayong darating na Sabado mula 8:00 AM hanggang 3:00 PM.</p><h3>Mga Libreng Serbisyo:</h3><ul><li>Konsultasyon sa Doktor at Libreng Gamot</li><li>Pambatang Bakuna (Routine Immunization)</li><li>Blood Pressure at Fasting Blood Sugar screening</li><li>Dental Check-up at bunot ng ngipin</li></ul><p>Mangyaring magdala ng inyong Barangay Health Card o valid ID. Unang 150 pasyente ang mabibigyan ng priority number.</p>', 'Inaanyayahan ang lahat sa residente sa Barangay Bayogo sa gaganaping Libreng Medical Mission ug Bakuna para sa sa mga Bata ngayong darating na Sabado mula 8:00 AM hanggang 3:00 PM.Mga Libreng Serbisyo:Konsultasyon sa Doktor ug Libreng GamotPambatang Bakuna (Routine Immunization)Blood Pressure ug Fasting Blood Sugar screeningDental Check-up ug bunot sa ngipinMangyaring magdala sa inyong Barangay Health Card o valid ID. Unang 150 pasyente ang mabibigyan sa priority number.', 'health', 'important', '2', '/uploads/announcements/dee97ba721cc5629fe27ef4ecf24deed.png', 'published', '2026-09-25 13:55:13', '1', '2026-09-28 13:55:13', '2026-09-28 13:55:13') ON CONFLICT DO NOTHING;
INSERT INTO "announcements" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "body", "body_fil", "body_manobo", "category", "urgency", "author_id", "cover_image_url", "status", "published_at", "is_sample", "created_at", "updated_at") VALUES ('3', 'Pangkalahatang Asembleya ng Barangay Bayogo para sa Taong 2026', 'Pangkalahatang Asembleya ng Barangay Bayogo para sa Taong 2026', 'Barangay Bayogo General Assembly for the Year 2026', 'Pangkalahatang Asembleya sa Barangay Bayogo para sa sa Taong 2026', 'pangkalahatang-asembleya-ng-barangay-bayogo', '<p>Alinsunod sa Local Government Code, ang Sangguniang Barangay ng Bayogo ay magdaraos ng <strong>First Semester Barangay General Assembly</strong> sa darating na Linggo, 1:00 PM sa Bayogo Multi-Purpose Covered Court.</p><p>Tatalakayin ang mga sumusunod na mahahalagang paksa:</p><ul><li>Ulat sa Pananalapi at Proyekto ng Barangay (State of Barangay Address)</li><li>Plano para sa Bagong Drainage at Kalsada sa Purok 2 at 3</li><li>Open Forum para sa mga katanungan at mungkahi ng mga mamamayan</li></ul><p>Ang inyong pagdalo at boses ay mahalaga sa patuloy na pag-unlad ng ating komunidad.</p>', '<p>Alinsunod sa Local Government Code, ang Sangguniang Barangay ng Bayogo ay magdaraos ng <strong>First Semester Barangay General Assembly</strong> sa darating na Linggo, 1:00 PM sa Bayogo Multi-Purpose Covered Court.</p><p>Tatalakayin ang mga sumusunod na mahahalagang paksa:</p><ul><li>Ulat sa Pananalapi at Proyekto ng Barangay (State of Barangay Address)</li><li>Plano para sa Bagong Drainage at Kalsada sa Purok 2 at 3</li><li>Open Forum para sa mga katanungan at mungkahi ng mga mamamayan</li></ul><p>Ang inyong pagdalo at boses ay mahalaga sa patuloy na pag-unlad ng ating komunidad.</p>', 'Alinsunod sa Local Government Code, ang Sangguniang Barangay sa Bayogo kay magdaraos sa First Semester Barangay General Assembly sa darating na Linggo, 1:00 PM sa Bayogo Multi-Purpose Covered Court.Tatalakayin ang mga sumusunod na mahahalagang paksa:Ulat sa Pananalapi ug Proyekto sa Barangay (State of Barangay Address)Plano para sa sa Bagong Drainage ug Kalsada sa Purok 2 ug 3Open Forum para sa sa mga katanungan ug mungkahi sa mga mamamayanAng inyong pagdalo ug boses kay mahalaga sa patuloy na pag-unlad sa ating komunidad.', 'government', 'normal', '1', '/uploads/announcements/65873416ec0a07dda9e25c438db4971d.png', 'published', '2026-09-23 13:55:21', '1', '2026-09-28 13:55:21', '2026-09-28 13:55:21') ON CONFLICT DO NOTHING;
INSERT INTO "announcements" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "body", "body_fil", "body_manobo", "category", "urgency", "author_id", "cover_image_url", "status", "published_at", "is_sample", "created_at", "updated_at") VALUES ('4', 'Bayanihan sa Baybayin: Coastal Clean-Up at Pagtatanim ng Bakawan', 'Bayanihan sa Baybayin: Coastal Clean-Up at Pagtatanim ng Bakawan', 'Coastal Clean-Up and Mangrove Planting Community Activity', 'Bayanihan sa Baybayin: Coastal Clean-Up ug Pagtatanim sa Bakawan', 'coastal-cleanup-at-mangrove-planting', '<p>Bilang bahagi ng ating adbokasiya para sa pangangalaga ng kalikasan at proteksyon laban sa storm surge, magkakaroon tayo ng <strong>Barangay Clean-Up at Mangrove Planting</strong> sa darating na Sabado ng umaga, 6:00 AM.</p><p>Assembly Area: Bayogo Seashore malapit sa Purok 1 Wharf.</p><p>Hinihikayat ang mga kabataan, mga samahan ng mangingisda, at bawat pamilya na makiisa sa bayanihan na ito. Magdala ng bota, guwantes, at reusable water tumbler. May libreng almusal at meryenda para sa mga kalahok.</p>', '<p>Bilang bahagi ng ating adbokasiya para sa pangangalaga ng kalikasan at proteksyon laban sa storm surge, magkakaroon tayo ng <strong>Barangay Clean-Up at Mangrove Planting</strong> sa darating na Sabado ng umaga, 6:00 AM.</p><p>Assembly Area: Bayogo Seashore malapit sa Purok 1 Wharf.</p><p>Hinihikayat ang mga kabataan, mga samahan ng mangingisda, at bawat pamilya na makiisa sa bayanihan na ito. Magdala ng bota, guwantes, at reusable water tumbler. May libreng almusal at meryenda para sa mga kalahok.</p>', 'Bilang bahagi sa ating adbokasiya para sa sa pangangalaga sa kalikasan ug proteksyon laban sa storm surge, magkakaroon tayo sa Barangay Clean-Up ug Mangrove Planting sa darating na Sabado sa umaga, 6:00 AM.Assembly Area: Bayogo Seashore malapit sa Purok 1 Wharf.Hinihikayat ang mga kabataan, mga samahan sa mangingisda, ug bawat pamilya na makiisa sa bayanihan na ito. Magdala sa bota, mga gwantes, ug reusable water tumbler. Ang mga partisipante adunay libre nga pamahaw ug meryenda.', 'social', 'normal', '2', '/uploads/announcements/a3941b90d4bdb713cff9799d3010dc4c.png', 'published', '2026-09-21 13:55:31', '1', '2026-09-28 13:55:31', '2026-09-28 13:55:31') ON CONFLICT DO NOTHING;

-- Data for table "events" (4 rows)
INSERT INTO "events" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "description", "description_fil", "description_manobo", "venue", "latitude", "longitude", "event_date", "end_date", "cover_image_url", "created_by", "status", "is_sample", "created_at", "updated_at") VALUES ('1', 'Barangay Bayogo Inter-Purok Basketball League 2026', 'Barangay Bayogo Inter-Purok Basketball League 2026', 'Barangay Bayogo Inter-Purok Basketball League 2026', 'Barangay Bayogo Inter- Liga sa Basketbol 2026', 'inter-purok-basketball-league-2026', '<p>Opisyal nang magbubukas ang taunang <strong>Inter-Purok Basketball Tournament</strong> kung saan maglalaban-laban ang mga kinatawan mula Purok 1, 2, at 3 para sa kampeonato. Hinihikayat ang lahat na manood at suportahan ang inyong mga purok teams!</p>', '<p>Opisyal nang magbubukas ang taunang <strong>Inter-Purok Basketball Tournament</strong> kung saan maglalaban-laban ang mga kinatawan mula Purok 1, 2, at 3 para sa kampeonato. Hinihikayat ang lahat na manood at suportahan ang inyong mga purok teams!</p>', 'Ang tinuig nga Inter-Purok Basketball Tournament opisyal nga nagbukas diin ang mga representante gikan sa Purok 1 nakigkompetensya, 2, 3 alang sa kampiyonato. Giawhag ang tanan sa pagbantay ug pagsuporta sa imong mga koponan sa kabanikanhan!', 'Bayogo Multi-Purpose Covered Court', '9.2741', '125.9612', '2026-09-28 11:55:31', '2026-09-28 19:55:31', '/uploads/events/9ae06ffc05461d2dc5e499b5edecb9e4.png', '2', 'ongoing', '1', '2026-09-28 13:55:37', '2026-09-28 13:55:37') ON CONFLICT DO NOTHING;
INSERT INTO "events" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "description", "description_fil", "description_manobo", "venue", "latitude", "longitude", "event_date", "end_date", "cover_image_url", "created_by", "status", "is_sample", "created_at", "updated_at") VALUES ('2', 'Operasyon Kontra Dengue: Fogging at Misting sa Buong Barangay', 'Operasyon Kontra Dengue: Fogging at Misting sa Buong Barangay', 'Anti-Dengue Operation: Fogging and Misting Across the Barangay', 'Surgery Batok sa Dengue: Pag-fog and Misting tabok sa Barangay', 'dengue-prevention-at-misting-operation', '<p>Magsasagawa ang Barangay Health Sanitation Team ng malawakang fogging at misting operation upang sugpuin ang mga lamok na nagdadala ng dengue. Pakiusap sa lahat na takpan ang mga imbakan ng tubig at pagkain habang isinasagawa ang operasyon.</p>', '<p>Magsasagawa ang Barangay Health Sanitation Team ng malawakang fogging at misting operation upang sugpuin ang mga lamok na nagdadala ng dengue. Pakiusap sa lahat na takpan ang mga imbakan ng tubig at pagkain habang isinasagawa ang operasyon.</p>', 'Mahitabo Barangay Health Sanitation Team kaylap nga operasyon sa fogging ug misting aron mapugngan ang mga lamok nga nagdala sa dengue. Palihug hangyoa ang tanan sa pagtabon sa tubig ug mga reservoir sa pagkaon sa panahon sa operasyon.', 'Purok 1, Purok 2, at Purok 3', '9.2745', '125.9615', '2026-09-30 08:00:00', '2026-09-30 12:00:00', '/uploads/events/7b763935c970b119608fb546ebded3c2.png', '2', 'upcoming', '1', '2026-09-28 13:55:42', '2026-09-28 13:55:42') ON CONFLICT DO NOTHING;
INSERT INTO "events" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "description", "description_fil", "description_manobo", "venue", "latitude", "longitude", "event_date", "end_date", "cover_image_url", "created_by", "status", "is_sample", "created_at", "updated_at") VALUES ('3', 'Pagsasanay sa Pangkabuhayan: Organic Vegetable Farming & Backyard Gardening', 'Pagsasanay sa Pangkabuhayan: Organic Vegetable Farming & Backyard Gardening', 'Livelihood Workshop: Organic Vegetable Farming & Backyard Gardening', 'Pagbansay sa Ekonomiya: Organic Vegetable Farming (agrikultura sa organikong utanon) & Backyard Gardening', 'livelihood-workshop-organic-farming', '<p>Libreng seminar at praktikal na pagsasanay sa organikong pagtatanim ng gulay at paggawa ng compost fertilizer. Bawat kalahok ay makakatanggap ng libreng binhi at starter planting kit mula sa Department of Agriculture.</p>', '<p>Libreng seminar at praktikal na pagsasanay sa organikong pagtatanim ng gulay at paggawa ng compost fertilizer. Bawat kalahok ay makakatanggap ng libreng binhi at starter planting kit mula sa Department of Agriculture.</p>', 'Libre nga seminar ug praktikal nga pagbansay sa organikong pagpanguma sa utanon ug produksyon sa abono sa compost. Ang matag partisipante makadawat og libre nga binhi ug starter planting kit gikan sa Department of Agriculture.', 'Barangay Bayogo Demo Farm & Hall', '9.2738', '125.9608', '2026-10-03 09:00:00', '2026-10-03 16:00:00', '/uploads/events/1741659a2f733d4d2e4fd0104348f302.png', '1', 'upcoming', '1', '2026-09-28 13:55:49', '2026-09-28 13:55:49') ON CONFLICT DO NOTHING;
INSERT INTO "events" ("id", "title", "title_fil", "title_en", "title_manobo", "slug", "description", "description_fil", "description_manobo", "venue", "latitude", "longitude", "event_date", "end_date", "cover_image_url", "created_by", "status", "is_sample", "created_at", "updated_at") VALUES ('4', 'Araw ng Kalusugan para sa Ina at Sanggol', 'Araw ng Kalusugan para sa Ina at Sanggol', 'Mother and Child Health and Nutrition Day', 'Adlaw sa Panglawas sa Inahan ug Bata', 'mother-and-child-health-day', '<p>Espesyal na araw ng prenatal check-up, pamamahagi ng bitamina para sa mga buntis at nagpapasusong ina, at feeding program para sa mga batang edad 0 hanggang 5 taon.</p>', '<p>Espesyal na araw ng prenatal check-up, pamamahagi ng bitamina para sa mga buntis at nagpapasusong ina, at feeding program para sa mga batang edad 0 hanggang 5 taon.</p>', 'Espesyal nga adlaw sa pag-check-upsa prenatal, pag-apod-apod sa bitamina alang sa mga mabdos ug nagpasuso nga mga inahan, mga programa sa pagpakaon alang sa mga bata nga nag-edad 0-5 ka tuig.', 'Bayogo Barangay Health Station', '9.274', '125.961', '2026-10-06 08:30:00', '2026-10-06 14:00:00', '/uploads/events/d89b40bc9392d1a2f55be92438c7b749.png', '2', 'upcoming', '1', '2026-09-28 13:55:53', '2026-09-28 13:55:53') ON CONFLICT DO NOTHING;

-- Data for table "ordinances" (4 rows)
INSERT INTO "ordinances" ("id", "title", "title_fil", "title_en", "title_manobo", "ordinance_no", "description", "description_fil", "description_manobo", "category", "file_url", "enacted_date", "ai_summary", "ai_summary_at", "uploaded_by", "status", "is_sample", "created_at", "updated_at") VALUES ('1', 'Kautusang Pambarangay Blg. 01-2026: Tamang Pagtatapon ng Basura at Pagbabawal sa Single-Use Plastics', 'Kautusang Pambarangay Blg. 01-2026: Tamang Pagtatapon ng Basura at Pagbabawal sa Single-Use Plastics', 'Barangay Ordinance No. 01-2026: Ecological Solid Waste Management and Single-Use Plastic Regulation', 'Kautusang Pambarangay Blg. 01-2026: Tamang Pagtatapon sa Basura ug Pagbabawal sa Single-Use Plastics', 'ORD-2026-001', 'Ipinagbabawal ang pagtatapon ng basura sa mga kanal, estero, baybayin, at pampublikong lansangan. Inaatasan ang bawat kabahayan na maghiwalay ng nabubulok (biodegradable) at di-nabubulok (non-biodegradable) na basura alinsunod sa itinakdang iskedyul ng koleksyon.', 'Ipinagbabawal ang pagtatapon ng basura sa mga kanal, estero, baybayin, at pampublikong lansangan. Inaatasan ang bawat kabahayan na maghiwalay ng nabubulok (biodegradable) at di-nabubulok (non-biodegradable) na basura alinsunod sa itinakdang iskedyul ng koleksyon.', 'Ipinagbabawal ang pagtatapon sa basura sa mga kanal, estero, baybayin, ug pampublikong lansangan. Inaatasan ang bawat kabahayan na maghiwalay sa nabubulok (biodegradable) ug di-nabubulok (non-biodegradable) na basura alinsunod sa itinakdang iskedyul sa koleksyon.', 'Kalinisan at Kapaligiran', '/uploads/ordinances/fa13c7460bff8f581e60d171e97ae3a6.pdf', '2026-01-15', 'Mahigpit na ipinagbabawal ang pagkakalat sa kalsada at dalampasigan. Obligado ang pagbubukod ng basura sa tahanan bago ang araw ng koleksyon. May kaukulang multa mula Php 500 hanggang Php 1,500 o community service para sa lalabag.', '2026-09-28 13:56:01', '1', 'active', '1', '2026-09-28 13:56:01', '2026-09-28 13:56:01') ON CONFLICT DO NOTHING;
INSERT INTO "ordinances" ("id", "title", "title_fil", "title_en", "title_manobo", "ordinance_no", "description", "description_fil", "description_manobo", "category", "file_url", "enacted_date", "ai_summary", "ai_summary_at", "uploaded_by", "status", "is_sample", "created_at", "updated_at") VALUES ('2', 'Kautusang Pambarangay Blg. 02-2026: Curfew Hours para sa mga Kabataan mula 10:00 PM hanggang 4:00 AM', 'Kautusang Pambarangay Blg. 02-2026: Curfew Hours para sa mga Kabataan mula 10:00 PM hanggang 4:00 AM', 'Barangay Ordinance No. 02-2026: Curfew Hours for Minors from 10:00 PM to 4:00 AM', 'Kautusang Pambarangay Blg. 02-2026: Curfew Hours para sa sa mga Kabataan mula 10:00 PM hanggang 4:00 AM', 'ORD-2026-002', 'Para sa kaligtasan at kapakanan ng kabataan, ipinagbabawal sa mga menor de edad (edad 17 pababa) ang pagtambay sa mga lansangan at pampublikong lugar mula alas-diyes ng gabi hanggang alas-kuwatro ng madaling araw, maliban kung may kasamang magulang o guardian o may lehitimong dahilan tulad ng emergency o pag-aaral.', 'Para sa kaligtasan at kapakanan ng kabataan, ipinagbabawal sa mga menor de edad (edad 17 pababa) ang pagtambay sa mga lansangan at pampublikong lugar mula alas-diyes ng gabi hanggang alas-kuwatro ng madaling araw, maliban kung may kasamang magulang o guardian o may lehitimong dahilan tulad ng emergency o pag-aaral.', 'Para sa sa kaligtasan ug kapakanan sa kabataan, ipinagbabawal sa mga menor de edad (edad 17 pababa) ang pagtambay sa mga lansangan ug pampublikong lugar mula alas-diyes sa gabi hanggang alas-kuwatro sa madaling araw, maliban kung may kasamang magulang o guardian o may lehitimong dahilan tulad sa emergency o pag-aaral.', 'Kapayapaan at Kaayusan', '/uploads/ordinances/dbc2a984de7627b7330d1e43b4c0d983.pdf', '2026-02-01', 'Bawal gumala ang mga 17 anyos pababa mula 10 PM hanggang 4 AM para sa kanilang kaligtasan. Ang mga magulang ng paulit-ulit na mahuhuli ay sasailalim sa counseling at posibleng pananagutan.', '2026-09-28 13:56:06', '1', 'active', '1', '2026-09-28 13:56:06', '2026-09-28 13:56:06') ON CONFLICT DO NOTHING;
INSERT INTO "ordinances" ("id", "title", "title_fil", "title_en", "title_manobo", "ordinance_no", "description", "description_fil", "description_manobo", "category", "file_url", "enacted_date", "ai_summary", "ai_summary_at", "uploaded_by", "status", "is_sample", "created_at", "updated_at") VALUES ('3', 'Kautusang Pambarangay Blg. 03-2026: Responsableng Pag-aalaga ng Hayop at Paghuli sa Pagala-galang Aso', 'Kautusang Pambarangay Blg. 03-2026: Responsableng Pag-aalaga ng Hayop at Paghuli sa Pagala-galang Aso', 'Barangay Ordinance No. 03-2026: Responsible Pet Ownership and Stray Animal Control', 'Kautusang Pambarangay Blg. 03-2026: Responsableng Pag-aalaga sa Hayop ug Paghuli sa Pagala-galang Aso', 'ORD-2026-003', 'Inaatasan ang lahat ng nagmamay-ari ng aso at pusa sa Barangay Bayogo na iparehistro at pabakunahan laban sa rabies ang kanilang mga alaga. Ipinagbabawal ang pagpapagala ng mga hayop sa labas ng bakuran nang walang tali o tagapangalaga.', 'Inaatasan ang lahat ng nagmamay-ari ng aso at pusa sa Barangay Bayogo na iparehistro at pabakunahan laban sa rabies ang kanilang mga alaga. Ipinagbabawal ang pagpapagala ng mga hayop sa labas ng bakuran nang walang tali o tagapangalaga.', 'Inaatasan ang lahat sa nagmamay-ari sa aso ug pusa sa Barangay Bayogo na iparehistro ug pabakunahan laban sa rabies ang kanilang mga alaga. Ipinagbabawal ang pagpapagala sa mga hayop sa labas sa bakuran sa walang tali o tagapangalaga.', 'Kaligtasan at Kalusugan', '/uploads/ordinances/1c2f5af2f78fc834b259ba2b0da689a1.pdf', '2026-02-20', 'Lahat ng aso at pusa ay dapat nakarehistro, may bakuna sa rabies, at nakatali kung ilalabas sa kalsada. Huhulihin ng barangay tanod ang mga pagala-galang hayop para sa kaligtasan ng mga dumaraan.', '2026-09-28 13:56:10', '2', 'active', '1', '2026-09-28 13:56:10', '2026-09-28 13:56:10') ON CONFLICT DO NOTHING;
INSERT INTO "ordinances" ("id", "title", "title_fil", "title_en", "title_manobo", "ordinance_no", "description", "description_fil", "description_manobo", "category", "file_url", "enacted_date", "ai_summary", "ai_summary_at", "uploaded_by", "status", "is_sample", "created_at", "updated_at") VALUES ('4', 'Kautusang Pambarangay Blg. 04-2026: Regulasyon sa Paggamit ng Videoke at Sound System sa mga Pamayanan', 'Kautusang Pambarangay Blg. 04-2026: Regulasyon sa Paggamit ng Videoke at Sound System sa mga Pamayanan', 'Barangay Ordinance No. 04-2026: Noise Regulation and Videoke Hours in Residential Areas', 'Kautusang Pambarangay Blg. 04-2026: Regulasyon sa Paggamit sa Videoke ug Sound System sa mga Pamayanan', 'ORD-2026-004', 'Upang matiyak ang kapayapaan at maayos na pamamahinga ng mga mamamayan, pinahihintulutan lamang ang paggamit ng videoke, karaoke, at malalakas na amplifier hanggang alas-diyes ng gabi (10:00 PM). Kinakailangan ding mapanatili ang katamtamang lakas ng tunog upang hindi makaabala sa mga kapitbahay.', 'Upang matiyak ang kapayapaan at maayos na pamamahinga ng mga mamamayan, pinahihintulutan lamang ang paggamit ng videoke, karaoke, at malalakas na amplifier hanggang alas-diyes ng gabi (10:00 PM). Kinakailangan ding mapanatili ang katamtamang lakas ng tunog upang hindi makaabala sa mga kapitbahay.', 'Upang matiyak ang kapayapaan ug maayos na pamamahinga sa mga mamamayan, pinahihintulutan lamang ang paggamit sa videoke, karaoke, ug malalakas na amplifier hanggang alas-diyes sa gabi (10:00 PM). Kinakailangan ding mapanatili ang katamtamang lakas sa tunog upang hindi makaabala sa mga kapitbahay.', 'Kapayapaan at Kaayusan', '/uploads/ordinances/a2b66b275c6107f0068c2eb07b42fc69.pdf', '2026-03-01', 'Hanggang 10:00 PM lamang puwedeng mag-videoke o magpatugtog nang malakas sa pamayanan upang makapagpahinga ang mga mag-aaral at nagtatrabaho. Ang paglabag ay may babala sa unang beses at multa sa susunod.', '2026-09-28 13:56:15', '1', 'active', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;

-- Data for table "evacuation_centers" (3 rows)
INSERT INTO "evacuation_centers" ("id", "name", "purok", "address", "latitude", "longitude", "capacity", "contact_person", "contact_phone", "is_active", "created_at", "updated_at") VALUES ('1', 'Bayogo Barangay Hall (Disaster Command Center)', 'Purok 1', 'Barangay Hall Road, Purok 1, Bayogo, Madrid', '9.2741', '125.9612', '120', 'Brgy. Captain / BDRRMC Chief', '+63 920 444 5566', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "evacuation_centers" ("id", "name", "purok", "address", "latitude", "longitude", "capacity", "contact_person", "contact_phone", "is_active", "created_at", "updated_at") VALUES ('2', 'Bayogo Elementary School Gymnasium', 'Purok 2', 'Elementary School Compound, Purok 2, Bayogo', '9.2748', '125.962', '250', 'School DRRM Coordinator', '+63 919 234 5678', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "evacuation_centers" ("id", "name", "purok", "address", "latitude", "longitude", "capacity", "contact_person", "contact_phone", "is_active", "created_at", "updated_at") VALUES ('3', 'Bayogo Multi-Purpose Covered Court', 'Purok 3', 'Plaza Area, Purok 3, Bayogo, Madrid', '9.2735', '125.9605', '300', 'Barangay Tanod Officer-in-Charge', '+63 919 345 6789', '1', '2026-09-28 13:56:15', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;

-- Data for table "document_requests" (3 rows)
INSERT INTO "document_requests" ("id", "reference_no", "user_id", "document_type", "purpose", "notes", "status", "staff_note", "handled_by", "requested_at", "ready_at", "released_at", "updated_at") VALUES ('1', 'BRGY-2026-001', '3', 'Barangay Clearance', 'Local Employment / Job Application', 'Kailangan po para sa requirements sa munisipyo.', 'ready', 'Handa na po para sa pick-up sa Barangay Hall counter 1. Magdala ng 1 valid ID.', '2', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "document_requests" ("id", "reference_no", "user_id", "document_type", "purpose", "notes", "status", "staff_note", "handled_by", "requested_at", "ready_at", "released_at", "updated_at") VALUES ('2', 'BRGY-2026-002', '3', 'Certificate of Indigency', 'Medical Assistance / PhilHealth Claim', 'Para po sa tulong pinansyal sa ospital ng aking lola.', 'pending', NULL, NULL, '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "document_requests" ("id", "reference_no", "user_id", "document_type", "purpose", "notes", "status", "staff_note", "handled_by", "requested_at", "ready_at", "released_at", "updated_at") VALUES ('3', 'BRGY-2026-003', '4', 'Certificate of Residency', 'Bank Account Opening / Valid ID', 'Katunayan ng paninirahan sa Purok 2.', 'released', 'Nakuha na ng residente noong nakaraang araw.', '2', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;

-- Data for table "feedbacks" (2 rows)
INSERT INTO "feedbacks" ("id", "user_id", "message", "admin_reply", "replied_at", "replied_by", "is_read_admin", "created_at") VALUES ('1', '3', 'Magandang araw po sa ating barangay council. Nais ko pong i-report ang pundidong ilaw sa poste ng kalsada malapit sa Purok 1 chapel. Medyo madilim po sa gabi at delikado sa mga batang naglalakad pauwi galing eskwela.', 'Magandang araw Michelle. Maraming salamat sa iyong pag-uulat. Naitala na po ito sa ating maintenance log at nakaiskedyul na bukas ng umaga ang ating barangay electrician upang palitan ang bumbilya.', '2026-09-28 13:56:15', '2', '1', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "feedbacks" ("id", "user_id", "message", "admin_reply", "replied_at", "replied_by", "is_read_admin", "created_at") VALUES ('2', '4', 'Maaari po bang maglagay ng karagdagang communal trash bins sa bukana ng Purok 2 para sa mga mangingisda pag-ahon mula sa baybayin? Maraming salamat po.', 'Magandang araw Juan. Magandang mungkahi ito. Isasama po natin ito sa adyenda ng susunod na barangay session at makikipag-ugnayan sa MENRO.', '2026-09-28 13:56:15', '2', '1', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;

-- Data for table "feedback_messages" (4 rows)
INSERT INTO "feedback_messages" ("id", "feedback_id", "sender_id", "sender_role", "message", "read_by_resident", "read_by_staff", "created_at") VALUES ('1', '1', '3', 'resident', 'Magandang araw po sa ating barangay council. Nais ko pong i-report ang pundidong ilaw sa poste ng kalsada malapit sa Purok 1 chapel. Medyo madilim po sa gabi at delikado sa mga batang naglalakad pauwi galing eskwela.', '1', '1', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "feedback_messages" ("id", "feedback_id", "sender_id", "sender_role", "message", "read_by_resident", "read_by_staff", "created_at") VALUES ('2', '1', '2', 'staff', 'Magandang araw Michelle. Maraming salamat sa iyong pag-uulat. Naitala na po ito sa ating maintenance log at nakaiskedyul na bukas ng umaga ang ating barangay electrician upang palitan ang bumbilya.', '1', '1', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "feedback_messages" ("id", "feedback_id", "sender_id", "sender_role", "message", "read_by_resident", "read_by_staff", "created_at") VALUES ('3', '2', '4', 'resident', 'Maaari po bang maglagay ng karagdagang communal trash bins sa bukana ng Purok 2 para sa mga mangingisda pag-ahon mula sa baybayin? Maraming salamat po.', '1', '1', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "feedback_messages" ("id", "feedback_id", "sender_id", "sender_role", "message", "read_by_resident", "read_by_staff", "created_at") VALUES ('4', '2', '2', 'staff', 'Magandang araw Juan. Magandang mungkahi ito. Isasama po natin ito sa adyenda ng susunod na barangay session at makikipag-ugnayan sa MENRO.', '1', '1', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;

-- Data for table "notifications" (4 rows)
INSERT INTO "notifications" ("id", "user_id", "title", "message", "type", "related_type", "is_read", "created_at") VALUES ('1', '3', 'Handa na ang inyong Barangay Clearance', 'Ang inyong hiniling na Barangay Clearance (Ref: BRGY-2026-001) ay handa na para sa pick-up sa Barangay Hall.', 'system', 'document_request', '0', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "notifications" ("id", "user_id", "title", "message", "type", "related_type", "is_read", "created_at") VALUES ('2', '3', 'BABALA: Storm Surge at Malakas na Ulan', 'Inaasahan ang storm surge sa baybayin ng Bayogo. Bukas ang Barangay Hall bilang evacuation center.', 'announcement', 'announcement', '0', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "notifications" ("id", "user_id", "title", "message", "type", "related_type", "is_read", "created_at") VALUES ('3', '3', 'Maligayang Pagdating sa BarangGabay!', 'Na-verify na ang inyong resident account. Maaari na kayong mag-access ng mga anunsyo, kaganapan, at humiling ng mga dokumento.', 'verification', 'user', '1', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;
INSERT INTO "notifications" ("id", "user_id", "title", "message", "type", "related_type", "is_read", "created_at") VALUES ('4', '4', 'Pangkalahatang Asembleya ng Barangay Bayogo', 'Inaanyayahan ang lahat sa General Assembly ngayong Linggo sa Bayogo Multi-Purpose Covered Court.', 'announcement', 'announcement', '0', '2026-09-28 13:56:15') ON CONFLICT DO NOTHING;

