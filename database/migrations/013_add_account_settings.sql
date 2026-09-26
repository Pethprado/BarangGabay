-- ============================================================
-- 013 — "My Account" settings for back-office users
--
-- Adds the columns the /admin/account page owns. Everything else that page
-- edits (full_name, phone, email, password_hash, avatar_url, email_verified)
-- already exists on users.
--
--   designation  – the staff member's barangay title, e.g. "Barangay
--                  Secretary". Shown beside their name in feedback threads so
--                  a resident knows who they are talking to, instead of the
--                  generic "Staff" label.
--   locale       – preferred UI language, persisted to the account so it
--                  survives logout. Session-only before this (see
--                  app/helpers.php::current_locale()).
--   notify_*     – in-app notification preferences for back-office alerts.
--                  Default ON: a staff member who has never opened this page
--                  must still be told about work waiting for them.
--
-- role and status are deliberately NOT touched here — they are never editable
-- from the account page (see AccountController::SELF_EDITABLE).
--
-- Run:  mysql -u root baranggabay < database/migrations/013_add_account_settings.sql
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS designation          VARCHAR(100) NULL AFTER role,
    ADD COLUMN IF NOT EXISTS locale               VARCHAR(10)  NULL AFTER zone,
    ADD COLUMN IF NOT EXISTS notify_feedback      TINYINT(1)   NOT NULL DEFAULT 1 AFTER email_verified,
    ADD COLUMN IF NOT EXISTS notify_registrations TINYINT(1)   NOT NULL DEFAULT 1 AFTER notify_feedback,
    ADD COLUMN IF NOT EXISTS notify_content       TINYINT(1)   NOT NULL DEFAULT 1 AFTER notify_registrations;
