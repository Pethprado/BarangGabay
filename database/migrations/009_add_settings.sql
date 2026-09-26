-- ============================================================
-- 009 — System settings (Super Admin module, feature 4)
--
-- Key/value store so branding, location, timezone and maintenance mode are
-- editable from the UI instead of being hardcoded. This is what makes the
-- system re-deployable for another barangay without touching code.
--
-- Run:  mysql -u root baranggabay < database/migrations/009_add_settings.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(100)    NOT NULL,
    setting_value   TEXT            NULL,

    -- Drives casting on read, so a caller gets a real bool/int back.
    value_type      ENUM('string','bool','int','json') NOT NULL DEFAULT 'string',

    updated_by      INT UNSIGNED    NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_setting_key (setting_key),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Defaults matching the current hardcoded values, so behaviour is unchanged
-- until a super admin edits something.
INSERT INTO settings (setting_key, setting_value, value_type) VALUES
    ('system_name',        'BarangGabay',                              'string'),
    ('system_tagline',     'Connecting Residents. Simplifying Governance.', 'string'),
    ('system_logo',        '',                                          'string'),
    ('location_name',      'Barangay Zone 3',                           'string'),
    ('location_full',      'District Zone 3, Lanuza, Surigao del Sur',  'string'),
    ('timezone',           'Asia/Manila',                               'string'),
    ('date_format',        'M j, Y',                                    'string'),
    ('time_format',        'g:i A',                                     'string'),
    ('maintenance_mode',   '0',                                         'bool'),
    ('maintenance_message', 'The system is temporarily unavailable for maintenance. Please try again shortly.', 'string'),
    ('login_max_attempts', '5',                                         'int'),
    ('login_lockout_min',  '15',                                        'int')
ON DUPLICATE KEY UPDATE setting_key = setting_key;   -- never clobber existing values
