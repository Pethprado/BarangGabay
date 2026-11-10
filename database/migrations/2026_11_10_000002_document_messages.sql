-- Typed ("something else") requests and the resident <-> staff message thread
-- on every document request. Same DDL lives in syncPostgresSchema().

ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS request_details TEXT NULL DEFAULT NULL
    COMMENT 'What the resident typed for an "other" request.';

CREATE TABLE IF NOT EXISTS document_request_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    sender_role VARCHAR(20)  NOT NULL DEFAULT 'resident',
    message     TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_drm_request (request_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
