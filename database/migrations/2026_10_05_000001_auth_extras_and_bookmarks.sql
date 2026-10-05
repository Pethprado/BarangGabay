-- Remember-me tokens, password resets and resident bookmarks.
-- PostgreSQL equivalents live in syncPostgresSchema() (config/database.php).
-- Idempotent: migrations replay on every connection.

-- "Remember me": split token. The cookie holds selector:validator; only a
-- SHA-256 of the validator is stored, so a leaked table cannot sign anyone in.
CREATE TABLE IF NOT EXISTS remember_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    selector        CHAR(18)     NOT NULL,
    validator_hash  CHAR(64)     NOT NULL,
    user_agent      VARCHAR(255) NULL,
    expires_at      DATETIME     NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_remember_selector (selector),
    KEY idx_remember_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Self-service password reset: single-use, one-hour, hashed token.
CREATE TABLE IF NOT EXISTS password_resets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    token_hash      CHAR(64)     NOT NULL,
    requested_ip    VARCHAR(45)  NULL,
    expires_at      DATETIME     NOT NULL,
    used_at         DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reset_token (token_hash),
    KEY idx_reset_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resident "My Bookmarks".
CREATE TABLE IF NOT EXISTS bookmarks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    content_type    VARCHAR(20)  NOT NULL,
    content_id      INT UNSIGNED NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_bookmark (user_id, content_type, content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
