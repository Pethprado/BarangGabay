-- 036_document_system_upgrade.sql
-- Full upgrade for BARANGGABAY:
-- 1. Structured resident profile fields with locked verification support
-- 2. Resident profile update requests with binary supporting document storage
-- 3. Document request extensions (certificate_no, claimed_at, delivery tracking)
-- 4. Safe sequential document numbering counter

-- 1. Users table profile fields
ALTER TABLE users ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NULL AFTER full_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS middle_name VARCHAR(100) NULL AFTER first_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NULL AFTER middle_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS suffix VARCHAR(20) NULL AFTER last_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER suffix;
ALTER TABLE users ADD COLUMN IF NOT EXISTS sex VARCHAR(20) NULL AFTER date_of_birth;
ALTER TABLE users ADD COLUMN IF NOT EXISTS civil_status VARCHAR(30) NULL AFTER sex;
ALTER TABLE users ADD COLUMN IF NOT EXISTS house_no VARCHAR(100) NULL AFTER address;
ALTER TABLE users ADD COLUMN IF NOT EXISTS street VARCHAR(100) NULL AFTER house_no;
ALTER TABLE users ADD COLUMN IF NOT EXISTS purok VARCHAR(100) NULL AFTER street;
ALTER TABLE users ADD COLUMN IF NOT EXISTS barangay VARCHAR(100) NULL DEFAULT 'Bayogo' AFTER purok;
ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL DEFAULT 'Madrid' AFTER barangay;
ALTER TABLE users ADD COLUMN IF NOT EXISTS province VARCHAR(100) NULL DEFAULT 'Surigao del Sur' AFTER city;
ALTER TABLE users ADD COLUMN IF NOT EXISTS household_no VARCHAR(50) NULL AFTER province;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_household_head TINYINT(1) NOT NULL DEFAULT 0 AFTER household_no;
ALTER TABLE users ADD COLUMN IF NOT EXISTS head_relationship VARCHAR(50) NULL AFTER is_household_head;

-- 2. Document requests extensions
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS certificate_no VARCHAR(50) NULL AFTER reference_no;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS issued_at DATETIME NULL AFTER document_uploaded_by;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS issued_by INT UNSIGNED NULL AFTER issued_at;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS claimed_at DATETIME NULL AFTER completed_at;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS claimed_by INT UNSIGNED NULL AFTER claimed_at;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS delivery_address TEXT NULL AFTER delivery_method;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER delivery_address;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(40) NULL AFTER delivery_fee;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS dispatched_at DATETIME NULL AFTER delivery_status;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER dispatched_at;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS delivered_by VARCHAR(120) NULL AFTER delivered_at;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS requirements_submitted TEXT NULL AFTER notes;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS admin_remarks TEXT NULL AFTER staff_note;

-- 3. Document sequences table for concurrency-safe sequential document numbers
CREATE TABLE IF NOT EXISTS document_sequences (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_prefix  VARCHAR(20) NOT NULL,
    doc_year    INT NOT NULL,
    last_number INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_prefix_year (doc_prefix, doc_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Resident profile update requests table
CREATE TABLE IF NOT EXISTS profile_update_requests (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id              INT UNSIGNED NOT NULL,
    requested_changes    LONGTEXT NOT NULL,
    current_values       LONGTEXT NULL,
    reason               TEXT NOT NULL,
    supporting_doc_name  VARCHAR(255) NULL,
    supporting_doc_type  VARCHAR(100) NULL,
    supporting_doc_size  INT UNSIGNED NULL,
    supporting_doc_data  LONGBLOB NULL,
    status               VARCHAR(30) NOT NULL DEFAULT 'pending',
    rejection_reason     TEXT NULL,
    admin_notes          TEXT NULL,
    reviewed_by          INT UNSIGNED NULL,
    reviewed_at          DATETIME NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_pur_user (user_id),
    INDEX idx_pur_status (status),
    CONSTRAINT fk_pur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pur_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
