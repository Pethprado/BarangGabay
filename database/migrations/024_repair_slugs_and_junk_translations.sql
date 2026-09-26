-- Repair two things a styled-Unicode title broke.
--
-- Staff copy titles out of Facebook, and Facebook captions are full of styled
-- Unicode: "𝐂𝐞𝐥𝐞𝐛𝐫𝐚𝐭𝐢𝐧𝐠" is the mathematical-bold block at U+1D400, not the
-- letters C-e-l-e-b. Two separate failures followed from that.
--
--   1. generate_slug() matched [a-z0-9] only, so every character became a
--      separator and the slug collapsed to an empty string. The post's URL
--      became /announcements/ and the link on the resident list did not open.
--      A published notice nobody can click is about the worst outcome this
--      app has, so any row still carrying an empty slug is given a working
--      one here. generate_slug() now transliterates, so newly saved posts get
--      a readable slug; this is the safety net for rows already stored.
--
--   2. MyMemory, handed that same title, answered "< < < < < < …" with a 200
--      status. It was stored as title_en, so residents reading in English saw
--      a headline of angle brackets while the admin form showed the real
--      title. FreeTranslationService::looksLikeLanguage() now rejects that on
--      the way in; these clear what already landed. Emptying an auto
--      translation is safe: localised_content() falls back to the original
--      and tells the reader no translation exists yet.
--
-- Both statements are naturally idempotent — after they run, nothing matches
-- their WHERE clauses — which matters because runPendingMigrations() replays
-- every file on every database connection. No date cutoff is needed for the
-- same reason (contrast migration 019, whose UPDATE was not self-limiting).

-- ── 1. Slugs that cannot be linked to ───────────────────────────────────
UPDATE announcements
   SET slug = CONCAT('post-', id)
 WHERE slug IS NULL OR slug = '';

UPDATE events
   SET slug = CONCAT('event-', id)
 WHERE slug IS NULL OR slug = '';

-- ── 2. Machine translations that contain no words at all ────────────────
-- Only auto-generated values are touched. Anything a person typed is left
-- exactly as they wrote it, whatever it looks like.
UPDATE announcements
   SET title_en = NULL
 WHERE en_is_auto = 1 AND title_en IS NOT NULL AND title_en <> '' AND title_en NOT REGEXP '[[:alnum:]]';

UPDATE announcements
   SET body_en = NULL
 WHERE en_is_auto = 1 AND body_en IS NOT NULL AND body_en <> '' AND body_en NOT REGEXP '[[:alnum:]]';

UPDATE events
   SET title_en = NULL
 WHERE en_is_auto = 1 AND title_en IS NOT NULL AND title_en <> '' AND title_en NOT REGEXP '[[:alnum:]]';

UPDATE events
   SET description_en = NULL
 WHERE en_is_auto = 1 AND description_en IS NOT NULL AND description_en <> '' AND description_en NOT REGEXP '[[:alnum:]]';

UPDATE ordinances
   SET title_en = NULL
 WHERE en_is_auto = 1 AND title_en IS NOT NULL AND title_en <> '' AND title_en NOT REGEXP '[[:alnum:]]';

UPDATE ordinances
   SET description_en = NULL
 WHERE en_is_auto = 1 AND description_en IS NOT NULL AND description_en <> '' AND description_en NOT REGEXP '[[:alnum:]]';
