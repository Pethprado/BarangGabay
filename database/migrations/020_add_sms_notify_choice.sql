-- Remember whether staff asked for an SMS blast when the post was written.
--
-- Events and ordinances act on the choice immediately, at save time, so they
-- need nothing stored. Announcements do need it: a scheduled announcement is
-- written now and dispatched later by the publisher sweep, which runs long
-- after the form has gone. Without this column the sweep has no way to know
-- whether the person who wrote the post wanted it sent by SMS, and would have
-- to guess — expensively, since every send costs Semaphore credits.
--
-- DEFAULT 1 deliberately: SMS previously fired on every published announcement
-- with no way to opt out, so existing rows and any code path that does not set
-- the column keep behaving exactly as they do today. The checkbox adds the
-- ability to say no; it does not quietly switch notifications off.
--
-- No backfill UPDATE here on purpose. This project has no migrations tracking
-- table — every statement replays on every database connection — so a data
-- statement must be bounded by a fixed cutoff or it fires forever. The column
-- DEFAULT already gives existing rows the right value, so none is needed.
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS notify_sms TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Did staff ask for an SMS blast when this was published? Read by ScheduledPublisher.';
