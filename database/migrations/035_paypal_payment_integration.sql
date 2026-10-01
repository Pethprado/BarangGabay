-- 035_paypal_payment_integration.sql
-- PayPal Payment Integration for BarangGabay Document Requests

-- 1. Add PayPal tracking columns to document_payments table
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS provider VARCHAR(30) NOT NULL DEFAULT 'paypal';
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS currency VARCHAR(10) NOT NULL DEFAULT 'PHP';
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paypal_order_id VARCHAR(100) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paypal_capture_id VARCHAR(100) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paypal_payer_id VARCHAR(100) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paypal_payer_email VARCHAR(150) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paypal_raw_response TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_dp_paypal_order ON document_payments(paypal_order_id);
CREATE INDEX IF NOT EXISTS idx_dp_paypal_capture ON document_payments(paypal_capture_id);

-- 2. Webhook idempotency events table
CREATE TABLE IF NOT EXISTS payment_webhook_events (
    id SERIAL PRIMARY KEY,
    event_id VARCHAR(120) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    provider VARCHAR(30) NOT NULL DEFAULT 'paypal',
    resource_id VARCHAR(120) NULL,
    payload TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pwe_event ON payment_webhook_events(event_id);
