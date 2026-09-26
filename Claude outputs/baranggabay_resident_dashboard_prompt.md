# Prompt for Claude — BarangGabay: make the resident dashboard actually personal

Paste this into Claude Code, working inside the project folder (`CLAUDE.md` is already loaded there).

## Ground truth — read first

`app/views/resident/home.php` is the logged-in resident's dashboard today. It currently has exactly three things:

1. A hero (`$showHero = true`, rendered by `layouts/main.php`).
2. Three stat tiles using the `[data-countup]` pattern: `$totalAnnouncements`, `$upcomingEventsCount`, `$activeOrdinances`.
3. A "latest announcements" grid and an "upcoming events" carousel.

**The core problem: nothing on this dashboard is about the resident.** All three stat tiles are barangay-wide totals — "there are 24 announcements" tells Juan nothing about Juan. It's a public bulletin board rendered behind a login, not a dashboard. Everything below is about fixing that.

Also read before building: `ResidentController::home()` (where those variables come from), `app/models/Notification.php`, the feedback thread model/controller (residents now have `POST /feedback/{id}/reply` and staff reply in-thread), `app/helpers.php::t()` for the translation keys, and `resources/lang` or wherever the `t()` strings live — **every new string must go through `t()`**, since this app supports English / Filipino / Manobo.

## Priority 1 — the things that make it a real dashboard

**A. Personal status strip at the top.** Greeting with the resident's `full_name`, their `avatar_url`, their `zone`, and their account status badge (verified / pending). If `email_verified = 0`, show an inline "verify your email" prompt with a resend link, because a resident who never verifies is invisible to the notification system.

**B. Replace the three generic stat tiles with personal ones.** Keep the same `[data-countup]` + `fade-up` visual pattern already in the file, but count things that belong to *this user*:
- Unread notifications (`notifications` table, this `user_id`)
- Open feedback threads + how many have an unread staff reply
- New announcements since their last visit (add a `users.last_seen_at` column — update `schema.sql` **and** a new numbered file in `database/migrations/`, per the convention in `config/database.php::runPendingMigrations()`)

Keep the barangay-wide counts if you want, but demote them — they're context, not the headline.

**C. Urgent announcement banner, pinned above everything.** Right now an `urgency = 'urgent'` announcement is just another card with a red left border, buried in a grid. In a coastal barangay in Surigao del Sur, "urgent" means typhoon signal, evacuation, brownout, water interruption — it must dominate the top of the screen, full-width, impossible to miss, with a dismiss that only hides it for that resident (store dismissal client-side per announcement id). Never auto-hide it on a timer.

**D. Unread notifications panel.** Show the latest 3–5 unread notifications inline with a link to `/notifications`. Today a resident has to go hunting for the bell.

**E. My feedback conversations widget.** List the resident's open threads with the last message preview and an unread badge when staff replied, linking straight into the thread. This feature exists now but is invisible from the dashboard.

**F. "Happening today / this week" strip.** An event happening *today* currently looks identical to one three months out. Separate them.

**G. Quick actions row.** Big, thumb-sized tap targets (most residents are on a mid-range phone): Send feedback · Browse ordinances · Ask the AI assistant · My profile. Mobile-first.

## Priority 2 — worth adding if time allows

- **Barangay contacts / emergency hotlines card**: barangay hall, tanod, BHW, MDRRMO, and the officials on duty. A resident in an emergency should not have to dig for a number. Make the numbers `tel:` links.
- **Event RSVP + "add to calendar"** surfaced on the dashboard card. The `GET /events/{slug}/calendar` route already exists and generates the `.ics` — it's currently hidden inside the detail page.
- **AI assistant card** that explains, in Tagalog, that residents can ask about barangay services and get answers — drives real usage of a feature you already built and paid for.
- **Manobo availability hint**: when a shown announcement/event has a Manobo translation, surface that on the dashboard card, not only on the detail page.
- **First-time resident checklist** (shown only until complete): verify email, complete profile, set preferred language, enable 2FA.
- **Saved/bookmarked ordinances** for the ones a resident looks up repeatedly.

## Constraints

- Keep the existing visual system: `fade-up` / `fade-up-delay-N` scroll reveals, `[data-countup]` for numbers (real value in both the text content and the attribute), rounded-2xl cards, the existing gradient icon tiles. Do not introduce a second, competing card style.
- Respect `prefers-reduced-motion` on anything new that moves.
- Every new query must be scoped to `$_SESSION['user_id']` — never accept a user id from the request.
- No N+1 queries in `ResidentController::home()`; this page loads on every visit.
- All new copy through `t()` with keys added for English, Filipino, and Manobo. Do not hardcode strings.
- Mobile-first: verify at ~390px width before calling anything done.

## Definition of done

Log in as an actual verified resident with real data — at least one unread notification, one open feedback thread with a staff reply, one urgent announcement, one event today — and confirm every widget shows the right thing for *that* resident and not barangay-wide totals. Then log in as a second resident and confirm none of the first resident's data leaks into their dashboard. Give me a short before/after summary and flag anything left incomplete.
