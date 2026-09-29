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
    import_batch_id VARCHAR(100) NULL,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS dictionary_imports (
    id SERIAL PRIMARY KEY,
    import_batch_id VARCHAR(100) NOT NULL UNIQUE,
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) NOT NULL DEFAULT 'doc',
    total_extracted INT NOT NULL DEFAULT 0,
    total_approved INT NOT NULL DEFAULT 0,
    total_duplicates INT NOT NULL DEFAULT 0,
    total_flagged INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    uploaded_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    undone_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_di_batch ON dictionary_imports(import_batch_id);
CREATE INDEX IF NOT EXISTS idx_di_status ON dictionary_imports(status);

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
    delivery_method VARCHAR(20) NOT NULL DEFAULT 'pickup',
    staff_note TEXT NULL,
    document_file_url VARCHAR(500) NULL,
    document_file_name VARCHAR(255) NULL,
    document_file_type VARCHAR(100) NULL,
    document_file_size INT NULL,
    document_uploaded_at TIMESTAMP NULL,
    document_uploaded_by INT NULL,
    handled_by INT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT NOW(),
    ready_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (document_uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_dr_status ON document_requests(status);
CREATE INDEX IF NOT EXISTS idx_dr_user ON document_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_dr_delivery ON document_requests(delivery_method);

CREATE TABLE IF NOT EXISTS document_request_files (
    id SERIAL PRIMARY KEY,
    request_id INT NOT NULL UNIQUE,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    file_data BYTEA NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_drf_request_id ON document_request_files(request_id);

CREATE TABLE IF NOT EXISTS document_request_logs (
    id SERIAL PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_drl_req ON document_request_logs(request_id);
CREATE INDEX IF NOT EXISTS idx_drl_action ON document_request_logs(action);

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


