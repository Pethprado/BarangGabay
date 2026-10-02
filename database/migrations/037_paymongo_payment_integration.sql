-- 037_paymongo_payment_integration.sql
-- PayMongo Payment Gateway Integration for BarangGabay
-- Replaces PayPal as primary online payment; processes GCash through PayMongo

-- 1. Add PayMongo tracking columns to document_payments table
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paymongo_checkout_id VARCHAR(120) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paymongo_payment_intent_id VARCHAR(120) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paymongo_payment_id VARCHAR(120) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paymongo_source_type VARCHAR(40) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paymongo_raw_response TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_dp_paymongo_checkout ON document_payments(paymongo_checkout_id);
CREATE INDEX IF NOT EXISTS idx_dp_paymongo_pi ON document_payments(paymongo_payment_intent_id);

-- 2. Update provider column default to 'paymongo' for new records
-- (existing records keep their current provider value)

-- 3. Allow webhook events table to store PayMongo events (provider column already exists)
-- Just ensure the provider column accepts 'paymongo' alongside 'paypal'
