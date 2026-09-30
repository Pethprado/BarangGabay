-- Voice Training & Dataset Management Tables

CREATE TABLE IF NOT EXISTS voice_samples (
    id SERIAL PRIMARY KEY,
    language VARCHAR(10) NOT NULL DEFAULT 'msm',
    text VARCHAR(500) NOT NULL,
    normalized_text VARCHAR(500) NOT NULL,
    dictionary_entry_id INT NULL,
    audio_url VARCHAR(500) NOT NULL,
    audio_storage_key VARCHAR(255) NULL,
    speaker_label VARCHAR(100) NULL,
    voice_type VARCHAR(50) NOT NULL DEFAULT 'community',
    sample_type VARCHAR(50) NOT NULL DEFAULT 'word',
    status VARCHAR(20) NOT NULL DEFAULT 'approved',
    duration FLOAT NULL,
    mime_type VARCHAR(50) NULL,
    file_size INT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS voice_profiles (
    id SERIAL PRIMARY KEY,
    language VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'dataset',
    provider_voice_id VARCHAR(100) NULL,
    fallback_voice VARCHAR(100) NULL,
    speaking_rate FLOAT NOT NULL DEFAULT 0.95,
    is_active SMALLINT NOT NULL DEFAULT 1,
    description TEXT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
