-- Resident Trash (App\Models\ResidentTrash): deleting a resident is reversible.
-- deleted_at marks the account as in the Trash; deleted_prev_status is what
-- Restore puts back (the row is set to 'suspended' while trashed).
--
-- Idempotent: runPendingMigrations() replays every migration on every
-- connection. PostgreSQL gets the same columns from syncPostgresSchema().

ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_by INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_prev_status VARCHAR(20) NULL DEFAULT NULL;
