-- The resident's check of the proof copy of their document:
-- 'ok' (details are correct) or 'issue' with what is wrong. Staff see it on
-- the request; approving a new version clears it.
-- Same columns live in syncPostgresSchema() for production.

ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS resident_check VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS resident_check_note TEXT NULL DEFAULT NULL;
ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS resident_checked_at DATETIME NULL DEFAULT NULL;
