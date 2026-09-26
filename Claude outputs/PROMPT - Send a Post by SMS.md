# Prompt for Claude Code — "Send a Post by SMS" (pick a post, send it to residents)

Copy everything inside the box and paste it into Claude Code, opened in
`C:\xampp\htdocs\BarangGabay`.

---

```
You are working on BarangGabay, my BSIT capstone: a PHP 8.2 / MySQL MVC web app
(XAMPP, no framework) in C:\xampp\htdocs\BarangGabay. Read CLAUDE.md first.

## What I want
On the SMS page I can already send a manual message to one number, or broadcast
a custom message to everyone. What I CANNOT do is send an existing post.

Build a feature where staff pick an already-published announcement, event or
ordinance from a list, see the exact text that will be sent, choose who
receives it, and send it as SMS to residents' phone numbers.

## What already exists — reuse it, do not rebuild it
- app/controllers/SmsController.php — index(), send(), broadcast(),
  recipients() (JSON search), balance(), and the static helpers
  getVerifiedPhones() and getVerifiedPhonesInPurok($purok)
- app/services/SemaphoreSmsService.php — send(), sendBulk($phones, $message,
  $type, $referenceId), isConfigured(), isTestMode(), getBalance()
- app/models/SmsLog.php — create(), paginate(), stats(); the sms_logs table
  already has `type` and `reference_id` columns, so NO schema change is needed
  to record which post a message belongs to
- Auto-send on publish already exists and must keep working:
    AnnouncementController::sendAnnouncementSms()
    EventController::sendEventSms()
    OrdinanceController::buildOrdinanceSms() + sendOrdinanceSms()
- app/views/admin/sms/index.php — the page with the "Send Manual SMS" and
  "Broadcast to All Residents" panels
- The recipient picker JSON endpoint: GET /api/admin/sms/recipients?q=
- Routes live in routes/web.php and routes/api.php (array format, with
  middleware ['auth', 'role:admin,staff'])

## Step 1 — one message builder, used everywhere
Right now each controller builds its own SMS text, and two of those builders
are private. Extract them into a single service, app/services/PostSms.php:

    PostSms::build(string $type, array $post): string   // type: announcement|event|ordinance
    PostSms::reference(string $type, array $post): ?string  // optional short portal link

Rules for build():
- Keep the wording of the existing messages, do not redesign them.
- Result must fit 160 characters (1 credit). Truncate the TITLE only, with
  mb_strimwidth and an ellipsis — never cut the barangay sign-off or the date.
- Use the barangay name from setting('location_name') — do not hardcode it.
- Urgent announcements keep their existing "URGENT" prefix.
Then make the three controllers call PostSms::build() instead of their own
copies, so auto-send and manual send always produce identical text.

## Step 2 — the new panel: "Send a Post by SMS"
Add it to app/views/admin/sms/index.php, in the same card style as the two
existing panels. It contains:

1. Post type selector: Announcement / Event / Ordinance.
2. A searchable post list for that type — published posts only, newest first,
   showing title + date. Load it over AJAX as the staff types, the same way
   the recipient picker works. Mark rows with is_sample = 1 as "SAMPLE" so
   nobody texts demo content to real residents.
3. A live message preview showing the exact text from PostSms::build(), with a
   character counter and the credit cost, matching the existing counters. The
   preview is EDITABLE — staff may adjust wording before sending, and the
   edited text is what gets sent.
4. Recipients, choose one:
   - All verified residents with a phone number (getVerifiedPhones)
   - One purok (getVerifiedPhonesInPurok) — list the puroks that actually
     exist in users.zone, do not hardcode a list
   - Specific residents picked by name, and/or numbers typed by hand
     (reuse GET /api/admin/sms/recipients)
   Show the recipient count and total credits (recipients × segments) before
   the send button, like the broadcast panel does.
5. A confirmation step before sending. Sending SMS costs real credits and
   cannot be undone, so the button must state what will happen:
   "Send to 34 residents (34 credits)".

## Step 3 — do not send the same post twice by accident
Add SmsLog::lastForPost(string $type, int $postId): ?array.
If the chosen post already has SMS logs, show a warning in the panel:
"This post was already sent by SMS on Sep 22, 2026 to 34 numbers." and require
an explicit confirm before sending it again.

## Step 4 — send from the post itself
On the admin list pages for announcements, events and ordinances, add a
"Send by SMS" action per row. It goes to /admin/sms with the post preselected
(e.g. /admin/sms?post_type=event&post_id=12), and the panel opens already
filled in with that post's preview.

## Step 5 — the backend
Add to SmsController:
    POST /admin/sms/post        → sendPost()
    GET  /api/admin/sms/posts?type=&q=  → posts()   (JSON list for the picker)
Register both in routes/web.php / routes/api.php with
['auth', 'role:admin,staff'].

sendPost() must:
- check_csrf()
- validate the type against announcement|event|ordinance and load the post
  from its model; reject an id that does not exist or is not published
- rebuild the message server-side; if the staff edited it, use their text but
  re-validate the 160-character limit server-side (never trust the form)
- resolve the recipient list server-side from the chosen mode; refuse to send
  when the list is empty
- call sendBulk($phones, $message, $type, $postId) so every row in sms_logs
  points back to the post
- AuditLog::record() with who sent what post to how many numbers
- flash the sent/failed counts and redirect back to /admin/sms
- if the SMS service is not configured, say so instead of failing silently;
  if isTestMode() is on, label the result clearly as a test

## Rules
1. PDO prepared statements only. Escape output with e(). CSRF on every POST.
2. Phone numbers are personal data — the JSON endpoints stay behind
   ['auth', 'role:admin,staff'] and stay capped, like recipients() already is.
3. All user-facing text goes through t() with new keys added to BOTH
   lang/en.php and lang/fil.php, in the same section style as the existing
   sms.* keys. Do not hardcode English in the view.
4. If you need any schema change, edit database/schema.sql AND add a new
   database/migrations/<next number>_*.sql. Never edit an existing migration —
   they replay on every DB connection, so they must stay idempotent.
5. Do not change how auto-send-on-publish behaves.
6. Follow PSR-12 and keep controllers thin — logic goes in the service.

## When you are done
1. php -l on every file you touched; run vendor\bin\phpunit --no-coverage.
2. With the SMS service in test mode, walk me through: pick an event → preview
   → choose one purok → confirm → show me the sms_logs rows it wrote.
3. Show me the exact message text produced for one announcement, one event and
   one ordinance, with their character counts.
4. Tell me anything you were unsure about instead of guessing.

Work in small steps: build Step 1 and show me the diff before continuing.
Explain what you did in simple English or Taglish.
```

---

## Notes for me

- No database change is needed. `sms_logs.type` and `sms_logs.reference_id`
  already exist, so each sent post is traceable in the log table at the bottom
  of the SMS page.
- Credits are real money: the panel shows the cost before sending, warns on a
  repeat send, and flags sample posts.
- Only residents with `status = 'verified'` and a saved phone number can be
  reached. The SMS page currently shows **1** such resident, so test with that
  one first, or in test mode.
