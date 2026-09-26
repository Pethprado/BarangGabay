-- Scheduled publishing for announcements.
--
-- `published_at` already existed and already held the go-live time, but
-- nothing ever set it to a future value and no resident-facing query compared
-- it against NOW(), so it was only ever "when was this posted".
--
-- Two things are needed to let staff write a notice now and have it appear
-- later:
--
--   1. Visibility. Handled entirely in the queries (published_at <= NOW()),
--      so a post becomes readable at the right minute whether or not any
--      background job ever runs. Nothing to store for that.
--
--   2. The one-time go-live dispatch (in-app notification + SMS + email).
--      That cannot be query-driven because it has side effects, so it needs a
--      marker saying "the announcements for this post have already gone out".
--      Without it, every sweep would re-send the SMS blast.
--
-- `notified_at` is that marker. NULL means the dispatch has not happened yet.
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS notified_at DATETIME NULL DEFAULT NULL
    COMMENT 'When the publish notification/SMS/email batch was sent. NULL = not yet sent.';

-- Existing published posts already had their notifications sent at save time.
-- Backfill them so the first sweep does not re-blast the entire archive by SMS.
--
-- The `created_at` cutoff is load-bearing, not decoration. This app has no
-- migrations tracking table — runPendingMigrations() re-runs every statement
-- on every database connection, which is safe for the DDL because it all uses
-- IF NOT EXISTS, but an UPDATE has no such guard. Without the cutoff this
-- statement would fire on every request and stamp each newly scheduled post
-- as "already notified" within seconds of it being saved, so its moment would
-- arrive and residents would never be told: the sweep skips anything already
-- stamped. Bounding it to rows written before this feature shipped makes the
-- backfill genuinely one-shot no matter how often it is replayed.
UPDATE announcements
   SET notified_at = COALESCE(published_at, created_at)
 WHERE status = 'published'
   AND notified_at IS NULL
   AND created_at < '2026-09-17 00:00:00';

-- The sweep query is "published, due, not yet notified". Index the two columns
-- it filters on so it stays cheap enough to run on ordinary page loads.
CREATE INDEX IF NOT EXISTS idx_due_for_publishing ON announcements (notified_at, published_at);
