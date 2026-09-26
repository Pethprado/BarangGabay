-- ============================================================
-- 011 — Two-factor authentication (Super Admin module, feature 6)
--
-- TOTP (authenticator app) plus single-use backup codes.
--
-- The secret is stored ENCRYPTED, not in plain text: anyone holding a raw
-- secret can generate valid codes forever, so a database dump alone must not
-- be enough to bypass 2FA. Encryption happens in TwoFactorService.
--
-- Backup codes are stored as bcrypt HASHES, exactly like passwords — they are
-- credentials, and the plaintext is shown to the user once at generation.
--
-- Run:  mysql -u root baranggabay < database/migrations/011_add_two_factor.sql
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_secret       VARCHAR(512) NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS totp_enabled      TINYINT(1)   NOT NULL DEFAULT 0 AFTER totp_secret,
    ADD COLUMN IF NOT EXISTS totp_confirmed_at DATETIME     NULL AFTER totp_enabled;

CREATE TABLE IF NOT EXISTS two_factor_backup_codes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    used_at     DATETIME     NULL,          -- set the moment a code is spent
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unused (user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Whether 2FA is required per role. Enforced by TwoFactorMiddleware.
INSERT INTO settings (setting_key, setting_value, value_type) VALUES
    ('twofa_required_roles', 'superadmin', 'string'),
    ('twofa_enabled',        '1',          'bool')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
