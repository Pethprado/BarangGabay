-- BarangGabay — Migration 016
-- Marks which stored translations were produced by a machine.
--
-- Why this matters:
-- A translation can now arrive three ways — typed by a staff member, produced
-- by a translation service, or glossed word-by-word from the barangay
-- dictionary. Those are not equally trustworthy, and a resident deserves to
-- know which one they are reading. Without a flag the columns look identical
-- and a rough machine gloss would be presented with the same authority as a
-- sentence a Manobo speaker wrote by hand.
--
-- 0 = written or corrected by a person (the default, and what manual entry sets)
-- 1 = produced automatically, shown to residents with a "machine translation" note
--
-- Safe to run on an existing database (IF NOT EXISTS).

SET NAMES utf8mb4;

ALTER TABLE announcements
  ADD COLUMN IF NOT EXISTS manobo_is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER body_manobo,
  ADD COLUMN IF NOT EXISTS en_is_auto     TINYINT(1) NOT NULL DEFAULT 0 AFTER body_en;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS manobo_is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER description_manobo,
  ADD COLUMN IF NOT EXISTS en_is_auto     TINYINT(1) NOT NULL DEFAULT 0 AFTER description_en;

ALTER TABLE ordinances
  ADD COLUMN IF NOT EXISTS manobo_is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER description_manobo,
  ADD COLUMN IF NOT EXISTS en_is_auto     TINYINT(1) NOT NULL DEFAULT 0 AFTER description_en;
