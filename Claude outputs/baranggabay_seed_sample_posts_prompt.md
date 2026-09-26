# Prompt for Claude — BarangGabay: seed 5 realistic sample posts each (announcements, events, ordinances) to surface bugs

Paste this into Claude Code, working inside the project folder.

## Goal

I need real-looking barangay content in the system so I can click through every page and find what's broken. Five announcements, five events, five ordinances. All of it must be believable content for **Barangay District Zone 3, Lanuza, Surigao del Sur** — a coastal barangay — not lorem ipsum.

## Ground truth — don't add a third seeder

These already exist:

- `tools/seed_content.php` — **outdated**. It predates `source_locale`, the English/Manobo translation fields, the voice-audio tables, source links and scheduled publishing, and it writes rows with raw `INSERT IGNORE` straight into `announcements` / `events` / `ordinances`.
- `database/seeders/DemoDataSeeder.php` — also old.
- `tools/cleanup_test_data.php` — the removal counterpart.

Modernise `tools/seed_content.php` (and fold in or retire the other seeder — don't leave three competing ones). Keep its good parts: it already finds-or-creates a superadmin and a staff account, and it is safe to run against a DB with real registered users.

## The most important rule

**Seed through the same code path the admin controllers use — models and services — not raw SQL.**

The old seeder's `INSERT IGNORE` bypasses slug generation, HTMLPurifier, the translation services, notification creation, voice-audio generation and SMS. Rows seeded that way look fine in the database and then prove nothing, because the features I actually want to test never ran. Go through the real create path so that seeding a post exercises the whole pipeline.

And **do not swallow failures.** `INSERT IGNORE` hides them. Every post should report per step — created / slug / purified / EN translation / FIL translation / MN translation / audio / notification — and print a summary table at the end of what succeeded and what failed and why. That report is the actual thing I want out of this; the content is just the vehicle.

## Make the 15 posts cover the edge cases, not 15 happy paths

Vary them on purpose so bugs surface:

**Announcements (5)** — spread across categories `general, health, safety, government, infrastructure, social`, and include:
- one **urgent** (test the urgent banner and pulse) — a storm-surge / typhoon advisory suits a coastal barangay
- one **important**, the rest normal
- one with a **government** category specifically — that badge uses the indigo classes that are unreadable in dark mode
- one written in **Filipino** and one written in **English** — this is what exposes the source-language translation bug
- one with a very **long body** (several hundred words) and one very short — tests truncation, the voice reader's chunking, and the free translator's 450-byte chunk limit
- at least one **without** a cover image

**Events (5)** — include one happening **today**, one upcoming, one **ongoing**, one **completed**, one **cancelled**; at least one with venue coordinates set and one without; one multi-day. Realistic: barangay assembly, clean-up drive, feeding program, basketball league opening, medical mission.

**Ordinances (5)** — different years, realistic subjects (curfew for minors, anti-littering, no-burning, tricycle fare, anti-noise). At least one **with** an `ai_summary` and one **without**, so the "Summarize with AI" path and the empty state both get tested. If a PDF is required, generate a simple one locally rather than pulling from the internet.

Leave Manobo fields filled on **one or two only** — the rest should exercise the "walang Manobo na salin pa" notice and the voice reader's fallback.

## Keep it obviously sample data, and reversible

This is a system that a real barangay will use, so seeded content must never be mistaken for a real notice:

- Use clearly fictional ordinance numbers and mark every seeded row so it can be found later (a `is_sample` flag, or a consistent slug prefix — your call, but it must be queryable).
- Give the script a `--remove` flag that deletes exactly what it seeded and nothing else, and wire `tools/cleanup_test_data.php` to it. I need to be able to wipe all of this before the real deployment with one command.
- Make re-running the script idempotent — running it twice must not produce ten copies.
- Authors should be the existing demo staff/superadmin accounts, not invented names presented as real barangay officials.

## Also seed enough accounts to actually view the content

Resident routes need `auth` + `verified`, so include at least one **verified resident** (and one still `pending`, to test the holding page). Print the demo credentials at the end so I can log in — and make the passwords obviously demo-only, not something that could survive into production.

## Definition of done

Run the seeder on a clean database and on one that already has data, and confirm neither duplicates nor breaks anything. Then give me the report: for each of the 15 posts, which pipeline steps succeeded and which failed with the actual error. Then walk the resident side — home, all three list pages, all 15 detail pages — in light and dark mode, in EN, FIL and MN, and list every visible problem you find with the file and line responsible. That list is what I'm really after.
