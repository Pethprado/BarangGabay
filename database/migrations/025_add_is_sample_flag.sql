-- Mark demo content as demo content.
--
-- This is a live government notice board. A seeded storm-surge advisory that
-- looks exactly like a real one is not a harmless test fixture — a resident
-- who acts on it has been misled by the barangay, and a fabricated ordinance
-- is worse still. So every sample row carries a flag that says what it is.
--
-- The flag does three jobs, and the third is the one that matters:
--
--   1. tools/seed-sample-content.php --purge deletes exactly these rows and
--      nothing a staff member wrote. Without a marker, teardown would have to
--      guess from titles or ids, and guessing wrong on a government system
--      means deleting a real advisory.
--   2. Re-running the seeder matches on it, so seeding twice updates rather
--      than duplicates.
--   3. Anything rendering these rows can say "sample" out loud. The generated
--      cover images carry the same word for the same reason.
--
-- Defaults to 0, so every row that already exists — everything a person
-- actually wrote — is real content, which is the safe direction for this
-- default to point.
--
-- Indexed because --purge and the seeder's idempotency check both filter on
-- it, and because a "are there still samples in here?" check before go-live
-- should be instant rather than a table scan.
--
-- Idempotent by construction (ADD COLUMN IF NOT EXISTS), which matters here:
-- runPendingMigrations() replays every migration file on every database
-- connection, so a migration that is not safe to re-run is a bug that fires
-- on every page load.

ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS is_sample TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Seeded demo content. Removable with tools/seed-sample-content.php --purge.';

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS is_sample TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Seeded demo content. Removable with tools/seed-sample-content.php --purge.';

ALTER TABLE ordinances
    ADD COLUMN IF NOT EXISTS is_sample TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Seeded demo content. Removable with tools/seed-sample-content.php --purge.';

CREATE INDEX IF NOT EXISTS idx_is_sample ON announcements (is_sample);
CREATE INDEX IF NOT EXISTS idx_is_sample ON events (is_sample);
CREATE INDEX IF NOT EXISTS idx_is_sample ON ordinances (is_sample);
