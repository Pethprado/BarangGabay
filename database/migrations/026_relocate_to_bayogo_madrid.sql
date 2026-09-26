-- The system moves: District Zone 3, Lanuza → Barangay Bayogo, Madrid.
--
-- Two things belong in a migration rather than in the one-off relocation
-- script: the column default, and the seeded settings rows. Everything that is
-- actual content a person wrote — announcements, events, ordinances, the zone
-- a resident chose — is handled by tools/relocate-to-bayogo.php, which shows
-- what it will change and asks before writing. A migration must never rewrite
-- someone's text silently on a page load.
--
-- ── Why the settings UPDATE is bounded ──────────────────────────────────
--
-- Migration 009 seeds location_name and location_full with the OLD values and
-- ends with "ON DUPLICATE KEY UPDATE setting_key = setting_key", so it never
-- clobbers what is already there. On a fresh database it would therefore seed
-- Lanuza again. This corrects that — but only where the value is still the old
-- one, so a super admin who has since typed their own wording keeps it.
--
-- That bound also makes the statement naturally idempotent, which matters:
-- runPendingMigrations() replays every migration file on every database
-- connection. After this runs once, nothing matches the WHERE clause again.

UPDATE settings
   SET setting_value = 'Barangay Bayogo'
 WHERE setting_key = 'location_name'
   AND setting_value = 'Barangay Zone 3';

UPDATE settings
   SET setting_value = 'Barangay Bayogo, Madrid, Surigao del Sur'
 WHERE setting_key = 'location_full'
   AND setting_value = 'District Zone 3, Lanuza, Surigao del Sur';

-- A database created before migration 009 may have no rows at all.
INSERT INTO settings (setting_key, setting_value, value_type) VALUES
    ('location_name', 'Barangay Bayogo',                          'string'),
    ('location_full', 'Barangay Bayogo, Madrid, Surigao del Sur', 'string')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ── The zone column ─────────────────────────────────────────────────────
--
-- Was DEFAULT 'Zone 3'. That value named a subdivision of the system's old
-- location, so it is wrong here in a way an empty column is not — a resident
-- of Bayogo silently recorded as living in "Zone 3" is worse data than one
-- with no purok recorded at all.
--
-- It is NOT replaced with a Bayogo purok, because Bayogo's real puroks are not
-- confirmed, and inventing place names on a government registration form is
-- not something a migration should do. The registration form now takes free
-- text; see barangay_subdivisions() in app/helpers.php for the one place to
-- restore a dropdown once the real list is known.
--
-- Existing rows keep whatever they hold; tools/relocate-to-bayogo.php offers
-- to clear the old zone values, with confirmation.
ALTER TABLE users
    MODIFY COLUMN zone VARCHAR(50) NULL DEFAULT NULL
    COMMENT 'Purok or sitio within the barangay. Free text; see barangay_subdivisions().';
