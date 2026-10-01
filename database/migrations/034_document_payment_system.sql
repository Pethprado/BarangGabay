-- 034_document_payment_system.sql
-- Complete Payment Management System for BarangGabay Document Requests

-- 1. Extend document_requests with payment columns
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NOT NULL DEFAULT 'free';
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS payment_status VARCHAR(40) NOT NULL DEFAULT 'FREE';
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS payment_id INT NULL;

-- 2. Document Fee Settings Table
CREATE TABLE IF NOT EXISTS document_fees (
    id SERIAL PRIMARY KEY,
    document_type VARCHAR(60) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_free SMALLINT NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Seed default fees if empty
INSERT INTO document_fees (document_type, amount, is_free, is_active, created_at, updated_at)
VALUES 
    ('clearance', 50.00, 0, 1, NOW(), NOW()),
    ('residency', 30.00, 0, 1, NOW(), NOW()),
    ('indigency', 0.00, 1, 1, NOW(), NOW()),
    ('business', 100.00, 0, 1, NOW(), NOW()),
    ('other', 50.00, 0, 1, NOW(), NOW())
ON CONFLICT (document_type) DO NOTHING;

-- 3. GCash Accounts Table
CREATE TABLE IF NOT EXISTS gcash_accounts (
    id SERIAL PRIMARY KEY,
    account_name VARCHAR(120) NOT NULL,
    mobile_number VARCHAR(30) NOT NULL,
    qr_image_data TEXT NULL,
    qr_mime_type VARCHAR(50) NULL,
    description VARCHAR(255) NULL,
    is_default SMALLINT NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Seed default GCash account if none exists
INSERT INTO gcash_accounts (account_name, mobile_number, description, is_default, is_active, created_at, updated_at)
SELECT 'Barangay Bayogo Official', '0917 123 4567', 'Official Barangay Treasurer GCash Account', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM gcash_accounts LIMIT 1);

-- 4. Document Payments Table
CREATE TABLE IF NOT EXISTS document_payments (
    id SERIAL PRIMARY KEY,
    payment_ref VARCHAR(50) NOT NULL UNIQUE,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    document_type VARCHAR(60) NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_reported DECIMAL(10,2) NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'gcash',
    gcash_account_id INT NULL,
    gcash_reference_no VARCHAR(100) NULL,
    payment_status VARCHAR(40) NOT NULL DEFAULT 'UNPAID',
    receipt_file_name VARCHAR(255) NULL,
    receipt_mime VARCHAR(100) NULL,
    receipt_size INT NULL,
    receipt_file_data TEXT NULL,
    receipt_file_hash VARCHAR(64) NULL,
    rejection_reason VARCHAR(255) NULL,
    rejection_note TEXT NULL,
    waiver_reason TEXT NULL,
    refund_reason TEXT NULL,
    notes TEXT NULL,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_dp_request ON document_payments(request_id);
CREATE INDEX IF NOT EXISTS idx_dp_user ON document_payments(user_id);
CREATE INDEX IF NOT EXISTS idx_dp_status ON document_payments(payment_status);
CREATE INDEX IF NOT EXISTS idx_dp_gcash_ref ON document_payments(gcash_reference_no);
CREATE INDEX IF NOT EXISTS idx_dp_hash ON document_payments(receipt_file_hash);

-- 5. Payment Audit Logs Table
CREATE TABLE IF NOT EXISTS payment_audit_logs (
    id SERIAL PRIMARY KEY,
    payment_id INT NULL,
    request_id INT NOT NULL,
    user_id INT NULL,
    action VARCHAR(60) NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pal_payment ON payment_audit_logs(payment_id);
CREATE INDEX IF NOT EXISTS idx_pal_request ON payment_audit_logs(request_id);
CREATE INDEX IF NOT EXISTS idx_pal_action ON payment_audit_logs(action);
