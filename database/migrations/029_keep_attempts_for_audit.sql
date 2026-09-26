-- Keep the record of a translation attempt after its post is gone.
--
-- Until now, a post deleted while one of its languages was queued for retry
-- took its attempt rows with it (TranslationAttempt::forget). That kept the
-- health page honest — it stopped reporting a post nobody could open — but
-- it also destroyed the only evidence that anything had been tried.
--
-- That evidence is worth keeping. "Why did the October advisory never get
-- an English version?" is a question that outlives the advisory, and for a
-- capstone defence it is exactly the sort of thing to be asked. A row that
-- says QUOTA_EXHAUSTED three times and then the post was deleted tells that
-- story; an absent row tells nothing and looks like it never happened.
--
-- So the row stays and is marked instead. orphaned_at is NULL for every
-- live post — which is every existing row, so nothing changes for them —
-- and is stamped when the post it refers to can no longer be found.
--
-- Everything operational filters it out: the retry runner must not try to
-- translate a post that does not exist, and the health page must not list
-- work nobody can act on. Only the audit view looks at these.
--
-- Replays safely: IF NOT EXISTS on both statements.

ALTER TABLE translation_attempts
    ADD COLUMN IF NOT EXISTS orphaned_at DATETIME NULL DEFAULT NULL
    COMMENT 'Set when the post is deleted. Row is kept for audit and ignored by the runner and the health page.';

-- The runner and the health page both filter on this, and both already sort
-- by the columns in idx_due / idx_outstanding. A plain index on the flag is
-- enough to keep "live rows only" from becoming a table scan as the archive
-- grows, which it now will.
CREATE INDEX IF NOT EXISTS idx_orphaned ON translation_attempts (orphaned_at);
