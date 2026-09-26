-- ============================================================
-- 007 — System error logs (Super Admin module, feature 1)
--
-- Persists application errors and uncaught exceptions so a super admin can
-- review them in the browser instead of reading storage/logs/error.log.
--
-- Run:  mysql -u root baranggabay < database/migrations/007_add_error_logs.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS error_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Short code also shown on the friendly 500 page, so a resident can quote
    -- it and staff can find the exact row.
    error_id        VARCHAR(16)     NOT NULL,

    severity        ENUM('notice','warning','error','critical') NOT NULL DEFAULT 'error',
    type            VARCHAR(191)    NOT NULL,   -- exception class or PHP error constant
    message         TEXT            NOT NULL,
    file            VARCHAR(500)    NULL,
    line            INT UNSIGNED    NULL,

    -- Request context, all nullable because CLI and shutdown errors have none.
    method          VARCHAR(10)     NULL,
    route           VARCHAR(500)    NULL,
    user_id         INT UNSIGNED    NULL,
    ip_address      VARCHAR(45)     NULL,
    user_agent      VARCHAR(500)    NULL,

    stack_trace     LONGTEXT        NULL,
    resolved_at     DATETIME        NULL,       -- set when a super admin marks it handled
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_created  (created_at),
    INDEX idx_severity (severity, created_at),
    INDEX idx_error_id (error_id),
    INDEX idx_resolved (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
