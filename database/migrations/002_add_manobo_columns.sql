-- BarangGabay — Migration 002
-- Adds Manobo translation columns to the content tables.
--
-- The language is Agusan Manobo (ISO 639-3 msm) — the variety spoken around
-- Lanuza and documented in data/manobo/README.md. This file originally said
-- "Western Bukidnon Manobo" (mbb), a different language from a different
-- province; the columns never changed, only the claim about what goes in them.
-- Safe to run on an existing database (IF NOT EXISTS / IGNORE).

SET NAMES utf8mb4;

ALTER TABLE announcements
  ADD COLUMN IF NOT EXISTS title_manobo VARCHAR(500) NULL AFTER title,
  ADD COLUMN IF NOT EXISTS body_manobo  LONGTEXT    NULL AFTER body;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS title_manobo       VARCHAR(500) NULL AFTER title,
  ADD COLUMN IF NOT EXISTS description_manobo LONGTEXT     NULL AFTER description;

ALTER TABLE ordinances
  ADD COLUMN IF NOT EXISTS title_manobo       VARCHAR(500) NULL AFTER title,
  ADD COLUMN IF NOT EXISTS description_manobo TEXT         NULL AFTER description;