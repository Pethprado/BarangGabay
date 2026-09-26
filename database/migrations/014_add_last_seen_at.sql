-- ============================================================
-- 014 — users.last_seen_at
--
-- Marks when a resident last opened their dashboard, so the home page can
-- answer "what is new since YOU were last here?" instead of only showing
-- barangay-wide totals.
--
-- Distinct from last_login_at (which already exists on users): a resident can
-- stay signed in for weeks, so login time is a poor proxy for "last visit".
--
-- Backfilled from last_login_at, falling back to created_at, so existing
-- accounts do not see every announcement ever published flagged as new on
-- their first load after this migration.
--
-- Run:  mysql -u root baranggabay < database/migrations/014_add_last_seen_at.sql
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL AFTER last_login_at;

UPDATE users
   SET last_seen_at = COALESCE(last_login_at, created_at)
 WHERE last_seen_at IS NULL;
