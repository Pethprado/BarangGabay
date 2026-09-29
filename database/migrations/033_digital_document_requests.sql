-- 033_digital_document_requests.sql
-- Digital Document Requests: delivery method, file attachment, audit logs

-- 1. Extend document_requests with delivery method and attachment metadata
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS delivery_method VARCHAR(20) NOT NULL DEFAULT 'pickup' AFTER status;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS document_file_url VARCHAR(500) NULL AFTER staff_note;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS document_file_name VARCHAR(255) NULL AFTER document_file_url;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS document_file_type VARCHAR(100) NULL AFTER document_file_name;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS document_file_size INT UNSIGNED NULL AFTER document_file_type;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS document_uploaded_at DATETIME NULL AFTER document_file_size;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS document_uploaded_by INT UNSIGNED NULL AFTER document_uploaded_at;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER released_at;

-- 2. Create document_request_files table for database persistence (binary blob)
CREATE TABLE IF NOT EXISTS document_request_files (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id      INT UNSIGNED NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_type       VARCHAR(100) NOT NULL,
    file_size       INT UNSIGNED NOT NULL,
    file_data       LONGBLOB NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_req_file (request_id),
    CONSTRAINT fk_drf_request FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create document_request_logs table for audit trail
CREATE TABLE IF NOT EXISTS document_request_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id      INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    action          VARCHAR(50) NOT NULL,
    old_status      VARCHAR(30) NULL,
    new_status      VARCHAR(30) NULL,
    details         TEXT NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_drl_req (request_id),
    INDEX idx_drl_action (action),
    CONSTRAINT fk_drl_request FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_drl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
