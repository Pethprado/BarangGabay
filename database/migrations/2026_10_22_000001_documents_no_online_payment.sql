-- Online payment (GCash / PayMongo) was removed from document requests.
--
-- Requests used to wait in 'awaiting_payment' until the resident paid online.
-- Nothing creates that status any more, so the ones still waiting move into
-- staff review, with any fee now paid in cash at the hall on pickup.
--
-- Replay-safe (this file runs on every connection): only rows still in
-- 'awaiting_payment' match, and DocumentRequest::create() never writes it.
-- The same statement lives in syncPostgresSchema() for production.

UPDATE document_requests
   SET payment_method = CASE WHEN fee_amount > 0 THEN 'pickup' ELSE payment_method END,
       payment_status = CASE
           WHEN fee_amount > 0 AND payment_status NOT IN ('PAID', 'PAID_VERIFIED', 'PAID_AT_PICKUP', 'WAIVED', 'FREE', 'NOT_REQUIRED')
           THEN 'PAY_AT_PICKUP' ELSE payment_status END,
       status = 'pending_review',
       updated_at = NOW()
 WHERE status = 'awaiting_payment';
