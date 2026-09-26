-- BarangGabay — Migration 005
-- Adds audio_manobo_path column for manual voice recordings to all 3 content tables.
-- Safe to run on an existing database (IF NOT EXISTS guard).

SET NAMES utf8mb4;

ALTER TABLE announcements
  ADD COLUMN IF NOT EXISTS audio_manobo_path VARCHAR(500) NULL AFTER body_manobo;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS audio_manobo_path VARCHAR(500) NULL AFTER description_manobo;

ALTER TABLE ordinances
  ADD COLUMN IF NOT EXISTS audio_manobo_path VARCHAR(500) NULL AFTER description_manobo;