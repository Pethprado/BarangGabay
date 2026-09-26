# Prompt for Claude Code — move BarangGabay from Zone 3, Lanuza to Barangay Bayogo, Madrid

Copy everything inside the box and paste it into Claude Code, opened in
`C:\xampp\htdocs\BarangGabay`.

---

```
You are working on BarangGabay, my BSIT capstone: a PHP 8.2 / MySQL MVC web app
(XAMPP, no framework) in C:\xampp\htdocs\BarangGabay. Read CLAUDE.md first.

## The change
The system's location and scope move:

  OLD: District Zone 3, Municipality of Lanuza, Surigao del Sur
  NEW: Barangay Bayogo, Municipality of Madrid, Surigao del Sur

Replace EVERY trace of the old location — code, interface text in all three
languages, AI prompts, SMS and email text, sample/seed posts, documentation,
and the rows already in my database. Nothing may still say Lanuza, Zone 3 or
Cantilan when you are done, except the linguistic citations listed under
"Do not touch" below.

## Canonical new values (use exactly these)
  location_name  (short, for SMS)  : Barangay Bayogo
  location_full  (emails, footers) : Barangay Bayogo, Madrid, Surigao del Sur
  municipality                     : Madrid
  province                         : Surigao del Sur
  region                           : Caraga
  SMS sign-off tag                 : Brgy. Bayogo, Madrid
  MAIL_FROM_NAME                   : "BarangGabay - Barangay Bayogo"

## Ask me before you decide these three
1. The `zone` field. Registration currently offers Zone 1–Zone 6 and defaults
   to 'Zone 3' (users.zone). Ask me what Bayogo actually uses — likely
   Purok 1–Purok N, or sitio names. Do NOT invent purok or sitio names.
2. The Manobo feature. It was built for the indigenous community in Lanuza.
   Ask me whether Bayogo has a Manobo-speaking community before you change any
   wording that claims it does.
3. Whether to also rename the database, APP_URL or the project folder. My
   default answer is no — only the location wording changes.

## Every place I already know the old location appears
Work through these, then grep the whole repo yourself to catch what I missed.

Settings / defaults (the source of truth for most screens)
- app/models/Setting.php — DEFAULTS: 'location_name', 'location_full'
- database/migrations/009_add_settings.sql — the seeded INSERT rows
- app/helpers.php — system_location() default and its docblock comment

Interface strings (keep EN, FIL and MN in sync — same keys, same meaning)
- lang/en.php and lang/fil.php, ~14 hits each:
  landing 'badge', 'subtitle'; ordinances 'subtitle'; feedback 'subtitle';
  manobo 'subtitle', 'dialect'; footer 'tagline', 'address', 'copyright';
  settings 'set_location_name_help', 'set_location_full_help';
  profile 'address_ph'; events 'venue_ph'; ordinances 'header_desc'
- lang/msm.php — the comment on line ~38

Views
- app/views/public/landing.php — <title>, hero copy, footer address, copyright
- app/views/auth/register.php — intro copy, the $zones array and its default,
  the address placeholder, the data-privacy paragraph
- app/views/auth/pending.php — the "Zone 3 Lanuza" line

Controllers (SMS and calendar text residents actually receive)
- app/controllers/AnnouncementController.php — the two SMS templates
- app/controllers/EventController.php — event SMS, and the .ics PRODID line
- app/controllers/OrdinanceController.php — the SMS tail
- app/controllers/ResidentController.php — staff-account default zone, and the
  verification SMS sign-off
- app/controllers/AuthController.php — the 'Zone 3' fallback on registration

Services
- app/services/AIService.php — 13 hits. Every system prompt names the place:
  the ordinance summarizer, the Q&A chat assistant, the English translator,
  and manoboSystemPrompt(). Rewrite the place, keep the instructions.
- app/services/MailService.php — the email body
- app/services/SocialText.php — the #Lanuza hashtag examples
- app/services/SpokenText.php, app/services/ManoboSpeech.php — "Brgy. Lanuza"
  and "Barangay Hall Zone 3" appear as pronunciation/abbreviation examples;
  update them so the examples match the real barangay
- app/models/User.php — the 'Zone 3' default on create

Database definition and seeds
- database/schema.sql — users.zone DEFAULT 'Zone 3'  (+ add a NEW migration,
  do not edit an old one — see the rules)
- database/seeders/DemoDataSeeder.php — 5 hits: addresses, a "Libreng Health
  Check-up sa Zone 3" announcement, "Covered Court, Zone 3", body text
- tools/seed-sample-content.php — the venue/location line (~584)
- tools/seed_content.php — 9 hits

Docs
- CLAUDE.md — 7 hits: project context, scope, the schema snippet, the two
  quoted AI prompts, MAIL_FROM_NAME, the footer line
- .env.example — MAIL_FROM_NAME (and tell me to update my own .env; do not
  read or print secrets from it)
- data/bisaya/README.md — the sentence about where the code-switching happens

## The database already has old data in it
Write ONE script, tools/relocate-to-bayogo.php, that I run once. It must:
- print what it will change and ask for confirmation before writing
- update the settings table: location_name, location_full
- update users.zone to the new value I chose in question 1
- find and update announcements, events and ordinances whose title, body,
  description, venue or address mentions Lanuza / Zone 3 — INCLUDING the
  translated columns (title_manobo, body_manobo, the English columns, and
  description_manobo where present)
- treat rows with is_sample = 1 separately: for those, tell me it is cleaner
  to purge and reseed with tools/seed-sample-content.php --purge than to
  rewrite them
- report a count per table when it finishes
Use PDO prepared statements, no string-built SQL.

## Rules
1. Never blind-replace the bare word "zone". It appears inside "timezone"
   (settings key, config, date handling) and in CSS class names. Match the
   full phrases: "Zone 3", "Barangay Zone 3", "District Zone 3", "Zone 3
   Lanuza", "Lanuza", "Cantilan".
2. Do not touch vendor/, composer.lock, storage/, or public/uploads/.
3. Schema changes: edit database/schema.sql AND add a new
   database/migrations/026_*.sql. Never edit an existing migration — they are
   replayed on every DB connection, so they must stay idempotent
   (IF NOT EXISTS / ON DUPLICATE KEY UPDATE).
4. Keep :placeholders (:year, :name, :email, :n) exactly as they are.
5. Keep PSR-12, htmlspecialchars/e() on output, prepared statements.
6. Do not change the meaning or structure of any AI prompt — only the place it
   names.

## Do not touch (this is the part most likely to go wrong)
data/manobo/README.md and the sourcing notes in
app/services/ManoboDictionary.php cite a real published dictionary: "Dictionary
of Manobo as spoken in the Agusan river valley and the Diwata mountain range",
SIL, and the language's ISO code msm. Those words describe the LANGUAGE and its
sources, not my barangay. Changing them would falsify a citation. Leave every
title, URL, source code (SIL-FM, SIL-WEB, WIKT) and language-scope sentence
alone. Only change a sentence if it is specifically about which barangay this
system serves — and if you are unsure which kind of sentence it is, ask me.

## When you are done
1. Run: grep -rniE "lanuza|zone ?3|cantilan" --exclude-dir=vendor .
   Every remaining hit must be one you can justify from "Do not touch".
2. php -l on every file you edited; run vendor\bin\phpunit --no-coverage.
3. Give me a table: file, what changed, how many lines.
4. List anything you were unsure about instead of guessing.

Work in small steps and show me the diff for the AI prompts and the SMS
templates before moving on — residents receive those texts directly.
Explain what you did in simple English or Taglish.
```

---

## Quick reference — confirmed hits (as of 22 Sep 2026)

| Area | Files | Hits |
|---|---|---|
| Interface strings | `lang/en.php`, `lang/fil.php`, `lang/msm.php` | 29 |
| AI prompts | `app/services/AIService.php` | 13 |
| Docs | `CLAUDE.md`, `data/bisaya/README.md`, `.env.example` | 9 |
| Seeds / sample posts | `database/seeders/DemoDataSeeder.php`, `tools/seed_content.php`, `tools/seed-sample-content.php` | 15 |
| Views | `landing.php`, `register.php`, `pending.php` | 12 |
| SMS / email / calendar | `AnnouncementController`, `EventController`, `OrdinanceController`, `ResidentController`, `MailService` | 8 |
| Settings & defaults | `Setting.php`, `helpers.php`, `009_add_settings.sql`, `schema.sql`, `User.php` | 9 |
| Speech / social text | `SpokenText.php`, `ManoboSpeech.php`, `SocialText.php` | 5 |

Plus the rows already saved in the database: the `settings` table, `users.zone`,
and any announcement, event or ordinance whose text names the old barangay.
