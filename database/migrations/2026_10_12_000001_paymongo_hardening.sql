-- 2026_10_12_000001_paymongo_hardening.sql
-- PayMongo GCash workflow hardening (MariaDB side; Postgres mirror lives in
-- config/database.php::syncPostgresSchema). Every statement is replay-safe:
-- this runner applies all migrations on every connect.

-- 1. Payment tracking the confirmation logic needs.
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS verification_type VARCHAR(20) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS failure_reason VARCHAR(255) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS paymongo_event_id VARCHAR(120) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS checkout_url VARCHAR(500) NULL;
ALTER TABLE document_payments ADD COLUMN IF NOT EXISTS checkout_created_at TIMESTAMP NULL DEFAULT NULL;

-- One PayMongo payment can settle exactly one BarangGabay payment record.
-- NULLs are allowed many times, so unpaid rows are unaffected.
CREATE UNIQUE INDEX IF NOT EXISTS uq_dp_paymongo_payment ON document_payments(paymongo_payment_id);

-- 2. Document fee management: what residents see before requesting.
ALTER TABLE document_fees ADD COLUMN IF NOT EXISTS description TEXT NULL;
ALTER TABLE document_fees ADD COLUMN IF NOT EXISTS requirements TEXT NULL;

INSERT IGNORE INTO document_fees (document_type, amount, is_free, is_active, created_at, updated_at) VALUES
    ('clearance', 50.00, 0, 1, NOW(), NOW()),
    ('residency', 30.00, 0, 1, NOW(), NOW()),
    ('indigency', 0.00, 1, 1, NOW(), NOW()),
    ('business', 100.00, 0, 1, NOW(), NOW()),
    ('other', 50.00, 0, 1, NOW(), NOW());

-- 3. Remove the three demo document requests the old demo seeder created
-- (fixed reference numbers, a "released" clearance that was never paid).
-- Bounded by exact reference and by "no payment row attached", so a real
-- request can never match.
DELETE FROM document_requests
 WHERE reference_no IN ('BRGY-2026-001', 'BRGY-2026-002', 'BRGY-2026-003')
   AND NOT EXISTS (SELECT 1 FROM document_payments p WHERE p.request_id = document_requests.id);

-- 4. The workflow uses statuses the old 5-value ENUM never had (awaiting_payment,
-- pending_review, approved, ready_for_pickup, available_for_download, …).
-- MariaDB in non-strict mode silently stored them as ''. Widen to VARCHAR.
ALTER TABLE document_requests MODIFY status VARCHAR(40) NOT NULL DEFAULT 'pending';

-- Repair rows the ENUM blanked: only status = '' matches, so replays are no-ops.
UPDATE document_requests
   SET status = CASE
         WHEN payment_status IN ('PAID', 'PAID_VERIFIED', 'PAID_AT_PICKUP', 'WAIVED', 'NOT_REQUIRED', 'FREE', 'PAY_AT_PICKUP') THEN 'pending_review'
         ELSE 'awaiting_payment' END
 WHERE status = '';
