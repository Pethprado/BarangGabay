-- ============================================================
-- 012 — Per-IP login limiting (Super Admin module, feature 7)
--
-- Per-email limits alone do not stop someone spraying one password across
-- many accounts from a single machine. This adds a second, looser ceiling
-- counted per IP address.
--
-- The IP limit is deliberately HIGHER than the per-email one: a barangay
-- hall, school or internet cafe puts many legitimate residents behind one
-- address, and a tight IP limit would lock out a whole building.
--
-- Run:  mysql -u root baranggabay < database/migrations/012_add_ip_lockout_setting.sql
-- ============================================================

INSERT INTO settings (setting_key, setting_value, value_type) VALUES
    ('login_max_attempts_ip', '20', 'int'),
    ('login_ip_limit_enabled', '1', 'bool')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Failures are looked up by ip_address + time on every sign-in attempt.
ALTER TABLE login_attempts
    ADD INDEX IF NOT EXISTS idx_ip_attempted (ip_address, attempted_at);
