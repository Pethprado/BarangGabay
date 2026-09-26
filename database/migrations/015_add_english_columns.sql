-- BarangGabay — Migration 015
-- Adds English translation columns to the three content tables.
--
-- Why these are needed:
-- Until now the only translated copy of a post was Manobo (migration 002).
-- The original title/body a staff member types is Filipino in practice, so
-- the header language switch could offer FIL and MN for content but had
-- nothing to show for EN — it fell back to the Filipino text.
--
-- The columns mirror the Manobo ones exactly, including their types, so both
-- translations behave the same way everywhere they are read or written.
--
-- Safe to run on an existing database (IF NOT EXISTS).

SET NAMES utf8mb4;

ALTER TABLE announcements
  ADD COLUMN IF NOT EXISTS title_en VARCHAR(500) NULL AFTER title_manobo,
  ADD COLUMN IF NOT EXISTS body_en  LONGTEXT     NULL AFTER body_manobo;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS title_en       VARCHAR(500) NULL AFTER title_manobo,
  ADD COLUMN IF NOT EXISTS description_en LONGTEXT     NULL AFTER description_manobo;

ALTER TABLE ordinances
  ADD COLUMN IF NOT EXISTS title_en       VARCHAR(500) NULL AFTER title_manobo,
  ADD COLUMN IF NOT EXISTS description_en TEXT         NULL AFTER description_manobo;
