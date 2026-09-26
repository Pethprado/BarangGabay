-- ============================================================
-- 010 — AI accuracy tracking (Super Admin module, feature 5)
--
-- Pairs each AI decision with the outcome a human later confirmed, so the
-- Super Admin panel can report how often the AI was actually right.
--
-- The first tracked type is ID verification: at registration the AI judges an
-- uploaded ID (ai_passed / ai_flagged / manual_review), and staff later verify
-- or suspend that resident. The staff decision is the ground truth.
--
-- The table is deliberately generic (prediction_type + reference_id) so
-- ordinance summaries or other AI output can be scored later without a schema
-- change.
--
-- Run:  mysql -u root baranggabay < database/migrations/010_add_ai_predictions.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS ai_predictions (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    prediction_type   VARCHAR(50)  NOT NULL DEFAULT 'id_verification',
    reference_id      INT UNSIGNED NULL,          -- users.id for id_verification

    -- What the AI said.
    predicted_status  VARCHAR(30)  NOT NULL,      -- ai_passed | ai_flagged | manual_review | skipped | pdf_manual
    predicted_label   ENUM('valid','invalid','unsure') NOT NULL DEFAULT 'unsure',
    confidence        VARCHAR(20)  NULL,          -- high | medium | low | unknown
    model             VARCHAR(100) NULL,
    detail            TEXT         NULL,          -- raw AI JSON, for auditing a disagreement

    -- What a human decided. NULL until someone acts on it.
    actual_status     VARCHAR(30)  NULL,          -- verified | suspended
    actual_label      ENUM('valid','invalid')     NULL,

    -- Derived on resolution so reporting stays a simple GROUP BY.
    outcome           ENUM('correct','incorrect','pending','not_scored') NOT NULL DEFAULT 'pending',

    resolved_by       INT UNSIGNED NULL,
    resolved_at       DATETIME     NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (reference_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by)  REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_type_outcome (prediction_type, outcome),
    INDEX idx_created      (created_at),
    INDEX idx_reference    (prediction_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill from residents who already carry an AI verdict, so the dashboard
-- is not empty on day one. Only rows the AI actually judged are imported;
-- 'skipped'/'pdf_manual' are recorded as not_scored because the AI made no
-- real call and scoring them would flatter the accuracy figure.
INSERT INTO ai_predictions
    (prediction_type, reference_id, predicted_status, predicted_label, confidence,
     detail, actual_status, actual_label, outcome, resolved_at, created_at)
SELECT
    'id_verification',
    u.id,
    u.id_ai_status,
    CASE
        WHEN u.id_ai_status = 'ai_passed'  THEN 'valid'
        WHEN u.id_ai_status = 'ai_flagged' THEN 'invalid'
        ELSE 'unsure'
    END,
    -- The verdict JSON carries the confidence; pull it out so backfilled rows
    -- still appear in the accuracy-by-confidence breakdown.
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(u.id_verified_by_ai, '$.confidence')), 'null'),
    u.id_verified_by_ai,
    CASE WHEN u.status IN ('verified','suspended') THEN u.status ELSE NULL END,
    CASE
        WHEN u.status = 'verified'  THEN 'valid'
        WHEN u.status = 'suspended' THEN 'invalid'
        ELSE NULL
    END,
    CASE
        WHEN u.id_ai_status NOT IN ('ai_passed','ai_flagged') THEN 'not_scored'
        WHEN u.status NOT IN ('verified','suspended')         THEN 'pending'
        WHEN (u.id_ai_status = 'ai_passed'  AND u.status = 'verified')
          OR (u.id_ai_status = 'ai_flagged' AND u.status = 'suspended') THEN 'correct'
        ELSE 'incorrect'
    END,
    CASE WHEN u.status IN ('verified','suspended') THEN u.updated_at ELSE NULL END,
    u.created_at
FROM users u
WHERE u.id_ai_status IS NOT NULL
  AND u.role = 'resident'
  AND NOT EXISTS (
      SELECT 1 FROM ai_predictions p
      WHERE p.prediction_type = 'id_verification' AND p.reference_id = u.id
  );
