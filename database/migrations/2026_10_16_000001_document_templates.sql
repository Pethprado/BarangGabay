-- Digital Barangay Bayogo documents (Indigency, Clearance, Residency,
-- Business Permit) generated from templates.
--
--  business_*        what a Business Permit is for (typed by the resident)
--  or_number         official receipt number, assigned on approval
--  permit_no         business permit number (e.g. 2026-014), on approval
--  valid_until       business permit validity (one year)
--  print_*           resident asked for a printed copy → staff Print Queue
--  revoked_*         a cancelled document; the verification page says so
--
-- Idempotent: runPendingMigrations() replays every migration on every
-- connection (MariaDB supports ADD COLUMN IF NOT EXISTS). PostgreSQL gets the
-- same columns from syncPostgresSchema() in config/database.php.

ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS business_name      VARCHAR(200) NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS business_type      VARCHAR(120) NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS business_address   VARCHAR(255) NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS or_number          VARCHAR(40)  NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS permit_no          VARCHAR(40)  NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS valid_until        DATE         NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS print_requested_at DATETIME     NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS printed_at         DATETIME     NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS printed_by         INT UNSIGNED NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS revoked_at         DATETIME     NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS revoked_by         INT UNSIGNED NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS revoke_reason      VARCHAR(255) NULL;
