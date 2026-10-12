# PayMongo GCash payments — setup and operations

BarangGabay charges document fees through **PayMongo Hosted Checkout (GCash)**.
Keys live only in Render's environment; nothing secret is in Git, the database,
the browser, or the logs.

## How a payment works

1. A resident files a request. The fee comes from **Admin → Payment Management →
   Document Fees** (the `document_fees` table) and is copied onto the request.
   The browser never sends an amount that is used.
2. Paid requests start as `awaiting_payment`. Free documents ("Payment required =
   No") skip payment and go straight to `pending_review`.
3. **Pay with GCash** asks the server to create a PayMongo Checkout Session
   (`POST /v2/checkout_sessions`, falling back to `/v1`) for the stored fee in
   centavos. A still-open session is reused, so double clicks and refreshes
   never create a second checkout.
4. The resident pays on PayMongo's page and returns to
   `/documents/payment/success`, which shows *Payment verification in progress*.
   Returning proves nothing on its own.
5. A payment becomes **PAID** only when PayMongo itself reports it paid **and**
   the amount equals the official fee **and** the currency is PHP. This is
   checked in one place (`App\Services\PaymentConfirmation`) for every path:
   - the **webhook** `checkout_session.payment.paid` (authoritative),
   - the return page and status poll (server asks PayMongo directly),
   - the staff/admin **Check PayMongo Status** button.
   A paid-but-wrong-amount payment goes to **Manual Review**, never to PAID.
6. The request moves to `pending_review` (processing queue). Staff cannot
   approve, release or complete a document whose payment is not verified — the
   rule lives in `DocumentRequest::paymentBlocksMove()` and every status change
   goes through it.

Manual proof upload still exists for reconciliation (a resident paid but the
status did not update). It only puts the payment in **Manual Review**; staff
verify it after finding the payment in the PayMongo dashboard.

## Render environment variables

Render → your service → **Environment** → add:

| Key | Value |
|---|---|
| `APP_URL` | `https://<your-service>.onrender.com` (no trailing slash) |
| `PAYMONGO_SECRET_KEY` | `sk_test_…` (test) — later `sk_live_…` |
| `PAYMONGO_PUBLIC_KEY` | `pk_test_…` — later `pk_live_…` |
| `PAYMONGO_WEBHOOK_SECRET` | `whsk_…` from the webhook below |
| `PAYMONGO_PAYMENT_METHODS` | `gcash` (add `,qrph` etc. once enabled on the account) |

Save → Render redeploys. `GET /health` shows
`"paymongo": {"configured": true, "mode": "test", "webhook_secret": true}` —
yes/no only, never a key.

## Webhook

- **URL:** `https://<your-service>.onrender.com/api/webhooks/paymongo`
- **Event:** `checkout_session.payment.paid` (optionally `payment.failed`)
- Create it in PayMongo Dashboard → Developers → Webhooks (or via the API),
  then copy its **signing secret** (`whsk_…`) into `PAYMONGO_WEBHOOK_SECRET`.
- The route is public on purpose (no login, no CSRF): the
  `Paymongo-Signature` HMAC is the authentication. Without a secret configured
  the endpoint answers 503 and accepts nothing. It stays open during
  maintenance mode.
- Duplicate deliveries are harmless: events are recorded by id, and the PAID
  update only applies once.

## Testing GCash in test mode

1. As a verified resident, request a paid document (e.g. Barangay Clearance).
2. Click **Pay with GCash** → PayMongo test page → E-Wallets → GCash →
   enter any name/email → **Pay**.
3. On "GCash Test Payment Page" choose **Authorize Test Payment** (success) or
   **Expire/Fail Test Payment** (failure).
4. You return to BarangGabay. Success → the request shows **Paid**
   ("Automatically verified through PayMongo"); failure → it stays unpaid and
   can be retried. Admin → Payment Management shows the transaction and its
   audit history.

No real money moves with `sk_test_` keys.

## Switching to live

1. Activate the PayMongo account and GCash for live payments.
2. In Render, replace `PAYMONGO_SECRET_KEY` / `PAYMONGO_PUBLIC_KEY` with the
   `sk_live_` / `pk_live_` keys.
3. Create a **live-mode** webhook to the same URL and put its signing secret in
   `PAYMONGO_WEBHOOK_SECRET`.
4. Redeploy. No code changes. The admin page then shows **LIVE mode — real money**.

If a key is ever exposed, roll it in the PayMongo dashboard and update Render.
