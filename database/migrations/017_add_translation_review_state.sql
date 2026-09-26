-- BarangGabay — Migration 017
-- Holds a machine translation of an URGENT announcement back until a person
-- has checked it.
--
-- Why only urgent announcements, and only the English column:
--
-- Free machine translation is good enough for routine notices but it can
-- invert meaning. A real case from testing:
--
--   Filipino : "May bagyo, lumikas na sa evacuation center."
--              (there is a typhoon, evacuate to the evacuation center)
--   Free MT  : "A hurricane has evacuated the evacuation center."
--
-- On a routine notice that is an annoyance. On a storm warning it is the
-- difference between a family leaving and a family staying put. So for
-- urgency = 'urgent' the machine's English is parked in 'pending' and is not
-- served to residents until a staff member confirms or corrects it.
--
-- Deliberately narrow:
--   - Only announcements have an urgency column, so only this table needs it.
--   - Manobo is untouched: it is glossed from the barangay's own dictionary,
--     already carries its own machine notice, and has no alternative source.
--   - Manually typed English is never gated — a person already wrote it.
--
-- States:
--   none     – nothing awaiting review (manual text, non-urgent, or no translation)
--   pending  – machine text held back, not visible to residents
--   approved – a staff member confirmed or corrected it; visible
--
-- Safe to run on an existing database (IF NOT EXISTS).

SET NAMES utf8mb4;

ALTER TABLE announcements
  ADD COLUMN IF NOT EXISTS en_review_state VARCHAR(10) NOT NULL DEFAULT 'none' AFTER en_is_auto;

-- Finding the review queue is a "where state = 'pending'" scan on every admin
-- page load, so give it an index rather than a full table scan.
CREATE INDEX IF NOT EXISTS idx_en_review_state ON announcements (en_review_state);
