-- 032_dictionary_import_history.sql
-- Adds dictionary_imports table and import_batch_id tracking to manobo_dictionary.

ALTER TABLE manobo_dictionary ADD COLUMN IF NOT EXISTS import_batch_id VARCHAR(100) NULL;

CREATE TABLE IF NOT EXISTS dictionary_imports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_batch_id VARCHAR(100) NOT NULL UNIQUE,
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(20) NOT NULL DEFAULT 'doc',
    total_extracted INT NOT NULL DEFAULT 0,
    total_approved INT NOT NULL DEFAULT 0,
    total_duplicates INT NOT NULL DEFAULT 0,
    total_flagged INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    undone_at DATETIME NULL DEFAULT NULL,
    KEY idx_di_batch (import_batch_id),
    KEY idx_di_status (status),
    CONSTRAINT fk_di_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
