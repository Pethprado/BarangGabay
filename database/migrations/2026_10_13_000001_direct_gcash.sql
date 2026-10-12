-- 2026_10_13_000001_direct_gcash.sql
-- Direct GCash payments: residents send to the barangay's GCash and upload proof.
-- Replay-safe (runs on every connect). Postgres mirror: syncPostgresSchema (TEXT there is unlimited).

-- A phone screenshot as base64 is far larger than TEXT's 64 KB; MariaDB in
-- non-strict mode silently truncated it, corrupting the proof image.
ALTER TABLE document_payments MODIFY receipt_file_data LONGTEXT NULL;

-- The barangay GCash QR image is stored as a setting (base64).
ALTER TABLE settings MODIFY setting_value MEDIUMTEXT NULL;
