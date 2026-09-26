-- ============================================================
-- 008 — User sessions + richer login history
--       (Super Admin module, feature 2)
--
-- PHP stores sessions as files, so there is no way to enumerate who is
-- logged in. This table mirrors each login so the Super Admin panel can show
-- active sessions and revoke them.
--
-- Only a SHA-256 hash of the session id is stored: enough to match the
-- current request against its row, useless to anyone who reads the table.
--
-- Run:  mysql -u root baranggabay < database/migrations/008_add_user_sessions.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS user_sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NOT NULL,
    session_hash    CHAR(64)        NOT NULL,

    ip_address      VARCHAR(45)     NULL,
    user_agent      VARCHAR(500)    NULL,

    login_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    logout_at       DATETIME        NULL,   -- set on an explicit sign-out
    revoked_at      DATETIME        NULL,   -- set when a super admin force-ends it

    UNIQUE KEY uniq_session_hash (session_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user     (user_id),
    INDEX idx_activity (logout_at, revoked_at, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Extend the existing login_attempts table into a full login history ──
-- It previously stored failures only, for rate limiting. It now records
-- successes too, with the reason a failure happened.

ALTER TABLE login_attempts
    ADD COLUMN IF NOT EXISTS user_id    INT UNSIGNED NULL      AFTER email,
    ADD COLUMN IF NOT EXISTS successful TINYINT(1)   NOT NULL DEFAULT 0 AFTER ip_address,
    ADD COLUMN IF NOT EXISTS reason     VARCHAR(40)  NULL      AFTER successful,
    ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) NULL      AFTER reason;

ALTER TABLE login_attempts
    ADD INDEX IF NOT EXISTS idx_successful (successful, attempted_at);

-- Existing rows predate the new columns and were all failures by definition.
UPDATE login_attempts
   SET successful = 0,
       reason     = 'bad_credentials'
 WHERE reason IS NULL;
