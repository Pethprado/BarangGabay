-- Three resident-facing features, all built on the barangay's seven puroks.
--
-- Bayogo has Purok 1 through Purok 7 (confirmed by the barangay; see
-- barangay_subdivisions() in app/helpers.php, which is the single list every
-- form and filter reads).
--
-- ── 1. Purok targeting ──────────────────────────────────────────────────
--
-- NULL means "every purok", which is the ordinary case and the safe default:
-- a post saved before this column existed, or by a staff member who did not
-- choose, reaches everyone exactly as it did before.
--
-- Why this matters beyond tidiness. Today a water interruption affecting one
-- purok sends an SMS to all seven. That is seven times the cost of a message
-- six of them cannot act on — and worse, it teaches residents that barangay
-- notices are usually not about them. The next notice they ignore might be the
-- storm-surge advisory that is.
--
-- The post itself stays readable by everyone on the site; only the push
-- (notification + SMS) is narrowed. Hiding a public notice from residents of
-- other puroks would be the wrong trade on a government notice board.

ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS target_purok VARCHAR(50) NULL DEFAULT NULL
    COMMENT 'Purok this notice is for. NULL = all puroks. Narrows the push, not who may read it.';

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS target_purok VARCHAR(50) NULL DEFAULT NULL
    COMMENT 'Purok this event is for. NULL = all puroks.';

CREATE INDEX IF NOT EXISTS idx_target_purok ON announcements (target_purok);
CREATE INDEX IF NOT EXISTS idx_target_purok ON events (target_purok);

-- ── 2. Document requests ────────────────────────────────────────────────
--
-- The commonest reason a resident walks to the barangay hall is to ask for a
-- clearance or a certificate. From Purok 7 that is a trip, and a wasted one if
-- the office is closed or the signatory is out. Requesting here and being told
-- by SMS when it is ready turns two trips into one.
--
-- Deliberately NOT an issuing system. No document is generated, signed or
-- released by this app — a barangay clearance is a signed instrument and must
-- stay that way. This is a queue: the resident asks, staff prepare it by hand
-- as they do now, and the resident is told when to collect it.
--
-- reference_no is what the resident quotes at the counter. Generated in PHP
-- (DocumentRequest::create) rather than derived from the id, so it carries the
-- year and is not a guessable row count.
CREATE TABLE IF NOT EXISTS document_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no    VARCHAR(30)     NOT NULL,
    user_id         INT UNSIGNED    NOT NULL,
    document_type   VARCHAR(60)     NOT NULL COMMENT 'clearance | residency | indigency | business | other',
    purpose         VARCHAR(255)    NOT NULL COMMENT 'Required: barangay clearances are issued for a stated purpose.',
    notes           TEXT            NULL,
    status          ENUM('pending','processing','ready','released','rejected') NOT NULL DEFAULT 'pending',
    staff_note      TEXT            NULL COMMENT 'Shown to the resident — why it was rejected, or what to bring.',
    handled_by      INT UNSIGNED    NULL,
    requested_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ready_at        DATETIME        NULL,
    released_at     DATETIME        NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_reference (reference_no),
    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Evacuation centres, per purok ────────────────────────────────────
--
-- Bayogo is coastal and on the typhoon side of Mindanao. During a warning a
-- resident does not need the barangay's full list of centres — they need to
-- know where THEY go, which is a different question and a faster one to
-- answer when the wind is already up.
--
-- purok NULL means the centre serves the whole barangay.
CREATE TABLE IF NOT EXISTS evacuation_centers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)    NOT NULL,
    purok           VARCHAR(50)     NULL COMMENT 'Purok this centre serves. NULL = whole barangay.',
    address         VARCHAR(255)    NULL,
    latitude        DECIMAL(10,7)   NULL,
    longitude       DECIMAL(10,7)   NULL,
    capacity        INT UNSIGNED    NULL,
    contact_person  VARCHAR(120)    NULL,
    contact_phone   VARCHAR(30)     NULL,
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_purok (purok),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. "Ligtas ako" check-in ────────────────────────────────────────────
--
-- After a typhoon the barangay's first question is who has not been heard
-- from. Asking residents to tap once is far faster than a roll call, and the
-- useful output is not the list of the safe — it is the list of the silent,
-- per purok, so the tanod know which houses to walk to.
--
-- Tied to an advisory (an announcement id) rather than free-floating, so
-- "safe" always means "safe as of THIS event" and a check-in from last year's
-- storm cannot be mistaken for today's.
--
-- needs_help is a real state, not an afterthought: a resident who can reach a
-- phone but not safety is exactly who this should surface first.
CREATE TABLE IF NOT EXISTS safety_checkins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advisory_id     INT UNSIGNED    NOT NULL COMMENT 'The announcement this check-in answers.',
    user_id         INT UNSIGNED    NOT NULL,
    status          ENUM('safe','needs_help') NOT NULL DEFAULT 'safe',
    purok           VARCHAR(50)     NULL COMMENT 'Copied at check-in time so the roll-up survives a profile edit.',
    note            VARCHAR(255)    NULL,
    checked_in_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One standing answer per resident per advisory; tapping again updates it,
    -- so "safe" can be corrected to "needs help".
    UNIQUE KEY uniq_advisory_user (advisory_id, user_id),
    FOREIGN KEY (advisory_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which announcements ask for a check-in. Only urgent safety advisories
-- should, so the button does not become background noise on ordinary notices.
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS asks_safety_checkin TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Show the "Ligtas ako" button on this advisory.';
