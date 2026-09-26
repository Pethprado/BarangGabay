-- Where a post came from, when it came from somewhere.
--
-- Two columns rather than one. The URL alone would mean every page that wants
-- to render an embed has to re-parse it, and they would drift: the admin list
-- deciding a link is "other" while the detail page renders it as a video. The
-- platform is settled once, by SourceLink::detect(), at the moment staff paste
-- the link.
--
-- This is attribution as much as it is an embed. The barangay usually posts to
-- Facebook first and copies across; when the content originated elsewhere —
-- a news outlet, another person's post — showing the source beside it is the
-- difference between quoting and passing off. Empty means the post was written
-- here, which is the ordinary case.
--
-- 'other' is a real, useful value: a link worth attributing that has no
-- official embed still gets a "view original" link on the detail page.
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) NULL DEFAULT NULL
    COMMENT 'Original post this was imported from, or NULL if written here.',
    ADD COLUMN IF NOT EXISTS source_platform ENUM('facebook','youtube','drive','docs','other') NULL DEFAULT NULL
    COMMENT 'Detected by SourceLink::detect() when the link was pasted.';

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) NULL DEFAULT NULL
    COMMENT 'Original post this was imported from, or NULL if written here.',
    ADD COLUMN IF NOT EXISTS source_platform ENUM('facebook','youtube','drive','docs','other') NULL DEFAULT NULL
    COMMENT 'Detected by SourceLink::detect() when the link was pasted.';

ALTER TABLE ordinances
    ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) NULL DEFAULT NULL
    COMMENT 'Original post or Drive file this was imported from.',
    ADD COLUMN IF NOT EXISTS source_platform ENUM('facebook','youtube','drive','docs','other') NULL DEFAULT NULL
    COMMENT 'Detected by SourceLink::detect() when the link was pasted.';
