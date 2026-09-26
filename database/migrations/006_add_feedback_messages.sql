-- BarangGabay — Migration 006
-- Turns each `feedbacks` row into a real two-way conversation thread.
--
-- `feedbacks` stays the thread header (id, user_id, created_at) — its legacy
-- message/admin_reply/replied_by/replied_at/is_read_admin columns are left in
-- place (not dropped) for safety under this project's per-request, per-statement
-- migration runner, but the application stops writing to admin_reply/replied_by/
-- replied_at/is_read_admin from this point on. `feedbacks.message` still gets
-- written once at thread-creation time (mirrors the opening message) purely for
-- any legacy tooling that queries it directly; feedback_messages is the single
-- source of truth for all conversation content and read state going forward.
--
-- Safe to run repeatedly: CREATE TABLE IF NOT EXISTS, and the two backfill
-- INSERTs below are each guarded by a NOT EXISTS check so they only ever
-- insert once per row, even though this file re-runs on every request (see
-- runPendingMigrations() in config/database.php).
--
-- IMPORTANT for whoever edits this file: runPendingMigrations() splits this
-- file into statements on ";\n" and skips any statement whose TRIMMED text
-- starts with "--" — which means a statement immediately preceded by a
-- comment line, with no semicolon in between, gets treated as one big comment
-- and silently skipped in its entirety, real SQL and all. That's why every
-- comment in this file lives up here in the header instead of being sprinkled
-- immediately before the statements below — keep it that way, or verify with
-- `Get-Content file.sql -Raw | mysql -u root baranggabay` AND an actual app
-- request before trusting a change here actually ran.
--
-- Backfill #1 copies each feedback's original message into feedback_messages
-- as the first row (sender = the resident who opened it).
-- Backfill #2 copies any existing admin_reply into feedback_messages as the
-- second row, attributed to the real replier and their actual role at
-- migration time — skipped if that user was since deleted (replied_by went
-- NULL via ON DELETE SET NULL), since there's no one left to attribute it to.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS feedback_messages (
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

INSERT INTO feedback_messages (feedback_id, sender_id, sender_role, message, read_by_resident, read_by_staff, created_at)
SELECT f.id, f.user_id, 'resident', f.message, 1, 1, f.created_at
FROM feedbacks f
WHERE NOT EXISTS (SELECT 1 FROM feedback_messages fm WHERE fm.feedback_id = f.id);

INSERT INTO feedback_messages (feedback_id, sender_id, sender_role, message, read_by_resident, read_by_staff, created_at)
SELECT f.id, f.replied_by, COALESCE(u.role, 'staff'), f.admin_reply, 1, 1, COALESCE(f.replied_at, f.created_at)
FROM feedbacks f
LEFT JOIN users u ON u.id = f.replied_by
WHERE f.admin_reply IS NOT NULL AND f.admin_reply <> ''
  AND f.replied_by IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM feedback_messages fm
      WHERE fm.feedback_id = f.id AND fm.sender_id = f.replied_by AND fm.message = f.admin_reply
  );