-- Run this on existing installations to add the Manobo translation feature.
-- Safe to run multiple times (IF NOT EXISTS guard).

CREATE TABLE IF NOT EXISTS translation_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED    NULL,
    content_type    ENUM('announcement','event','ordinance') NOT NULL,
    content_id      INT UNSIGNED    NOT NULL,
    original_text   TEXT            NOT NULL,
    translated_text LONGTEXT        NOT NULL,
    language        VARCHAR(50)     NOT NULL DEFAULT 'manobo',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_content  (content_type, content_id),
    INDEX idx_user     (user_id),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;