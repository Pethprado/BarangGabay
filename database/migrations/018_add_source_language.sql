-- BarangGabay — Migration 018
-- Lets a post be written in English instead of Filipino.
--
-- Until now the system assumed every post was authored in Filipino: `title`
-- and `body` WERE the Filipino version, and the only translation columns were
-- English and Manobo. A staff member who wrote in English got a page that
-- told residents "this post has no English version yet" while showing them
-- English — and the Manobo gloss ran against a Tagalog dictionary using
-- English input, so it converted almost nothing.
--
-- Two things are needed to fix that:
--
--   source_lang   which language the staff member actually wrote in.
--                 'fil' keeps every existing row behaving exactly as before.
--   *_fil columns somewhere to put the Filipino translation when the source
--                 is English. The English and Manobo columns already exist;
--                 Filipino had nowhere to go because it was assumed to be
--                 the original.
--
-- With those, `title`/`body` become simply "the source text", and each of the
-- three languages reads its own column — symmetric, with no language special.
--
-- Safe to run on an existing database (IF NOT EXISTS).

SET NAMES utf8mb4;

ALTER TABLE announcements
  ADD COLUMN IF NOT EXISTS source_lang VARCHAR(3)   NOT NULL DEFAULT 'fil' AFTER body,
  ADD COLUMN IF NOT EXISTS title_fil   VARCHAR(500) NULL AFTER title_en,
  ADD COLUMN IF NOT EXISTS body_fil    LONGTEXT     NULL AFTER body_en,
  ADD COLUMN IF NOT EXISTS fil_is_auto TINYINT(1)   NOT NULL DEFAULT 0 AFTER en_is_auto;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS source_lang    VARCHAR(3)   NOT NULL DEFAULT 'fil' AFTER description,
  ADD COLUMN IF NOT EXISTS title_fil      VARCHAR(500) NULL AFTER title_en,
  ADD COLUMN IF NOT EXISTS description_fil LONGTEXT    NULL AFTER description_en,
  ADD COLUMN IF NOT EXISTS fil_is_auto    TINYINT(1)   NOT NULL DEFAULT 0 AFTER en_is_auto;

ALTER TABLE ordinances
  ADD COLUMN IF NOT EXISTS source_lang    VARCHAR(3)   NOT NULL DEFAULT 'fil' AFTER description,
  ADD COLUMN IF NOT EXISTS title_fil      VARCHAR(500) NULL AFTER title_en,
  ADD COLUMN IF NOT EXISTS description_fil TEXT        NULL AFTER description_en,
  ADD COLUMN IF NOT EXISTS fil_is_auto    TINYINT(1)   NOT NULL DEFAULT 0 AFTER en_is_auto;
