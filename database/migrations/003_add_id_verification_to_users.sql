-- BarangGabay — Migration 003
-- Adds AI ID-verification result columns to the users table.
-- Safe to run on an existing database (IF NOT EXISTS / IGNORE approach via ADD COLUMN IF NOT EXISTS).

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS id_verified_by_ai TEXT     NULL AFTER id_photo_url,
  ADD COLUMN IF NOT EXISTS id_ai_status      VARCHAR(20) NULL AFTER id_verified_by_ai;

-- Index for quick admin filtering by AI status
CREATE INDEX IF NOT EXISTS idx_id_ai_status ON users (id_ai_status);