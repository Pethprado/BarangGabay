# Prompt for Claude — BarangGabay: seed 5 sample announcements, events and ordinances for testing

Paste this into Claude Code, working inside the project folder.

## Goal

I need realistic barangay sample posts — 5 announcements, 5 events, 5 ordinances, all with pictures — so I can click through the whole system and find what's broken. Don't just create 15 generic posts: **design them as a test matrix** so that between them they exercise every case that has been breaking lately.

## Ground truth

- Content lives in the `announcements`, `events` and `ordinances` tables, each with `*_manobo` and English translation fields, `cover_image_url`, slugs, category/urgency (announcements), `event_date`/`venue`/status/lat-lng (events), and PDF + `ai_summary` (ordinances).
- Follow the project conventions: uploads through `FileService`, slug generation and HTMLPurifier through the normal save path, `schema.sql` + a numbered file in `database/migrations/` if you add anything.
- Put the seeder in `tools/` as a CLI script (there's already `tools/build-manobo-json.php`). **Not** a migration — demo content must never ride along into a real deployment.
- The barangay is **District Zone 3, Lanuza, Surigao del Sur** — coastal, typhoon-prone, with a Manobo-speaking community. Write content that fits that place, not generic filler.

## Three safety rules — read before writing the seeder

1. **Mark every sample row as sample data** (an `is_sample` flag is cleanest — schema + migration) and write a matching teardown (`--purge`) that removes the rows *and* the generated images. This is a real government system; sample notices must never be mistakable for live barangay announcements, and I must be able to wipe them in one command before go-live.

2. **Do not let seeding fire outbound messages.** The save path publishes notifications and can trigger SMS through `SemaphoreSmsService`. Fifteen seeded posts must not send fifteen SMS broadcasts or spam every test resident. Explicitly suppress SMS during seeding, and make notification creation an opt-in flag (`--with-notifications`) so I can test that path deliberately rather than by accident.

3. **Ordinances are legal instruments.** Prefix every sample ordinance title/number with something unmistakable like `[SAMPLE]` and keep the body obviously illustrative. A fabricated ordinance that reads as real barangay law is worse than no sample at all.

## The test matrix

### Announcements (5)

| # | Exercises | Content |
|---|---|---|
| 1 | Filipino original · `urgent` · `safety` · long body · has Manobo translation · cover image | Bagyo / storm-surge advisory with evacuation instructions |
| 2 | **English original** · `normal` · `health` | Free medical mission / vaccination schedule — this is the one that proves English→Filipino auto-translation now works |
| 3 | `important` · **`government` category** · **no cover image** | Barangay assembly notice — the indigo badge is the known dark-mode contrast case, and no cover tests the placeholder |
| 4 | **Body containing inline `style="color:#000"`**, as if pasted from Facebook | An appreciation/thank-you post — proves the purifier strips hardcoded colours and that dark mode stays readable |
| 5 | Very long body (2,000+ chars) · **no Manobo translation** | Road repair / water interruption notice — tests voice-reader chunking, the truncation notice, and the "no Manobo translation yet" message |

### Events (5)

1. **Upcoming**, with venue *and* lat/lng — Barangay Assembly at the Barangay Hall (map should render)
2. **Ongoing / today** — coastal clean-up drive (tests the "happening today" path)
3. **Completed / past** — fiesta or foundation day
4. **Cancelled** — a postponed sports event (tests cancelled styling)
5. **No venue, no coordinates, no cover image** — tests every fallback at once

### Ordinances (5)

1. With a real PDF **and** an `ai_summary` already filled
2. With a PDF but **no `ai_summary`** — tests the "Summarize with AI" button, including its behaviour while the Anthropic account has no credits
3. Long title with Filipino special characters (ñ, accents) — tests slugs and truncation
4. With a Manobo translation present — tests the MN path end to end
5. An older/superseded one — tests date sorting and any archive state

## Pictures

Generate the cover images **locally** with PHP GD or SVG→PNG — do not download stock photos and do not use photos of real people. A clean generated placeholder (barangay-appropriate colour block, the post title, a label marking it as sample) at realistic cover dimensions is enough, and it sidesteps every licensing and privacy question. Save them through the normal upload path so file handling, MIME validation and the delete path all get tested too. Make it obvious in the image itself that it's sample content so nobody mistakes a seeded post for a real one at a glance. Also seed one or two PDFs for the ordinances the same way.

## Other requirements

- **Idempotent**: running the seeder twice must not duplicate rows. Re-running should update or skip, and say which.
- Vary `published_at` across the last few weeks so the "latest" ordering, the dashboard "new since last visit" count, and pagination all get real data to work with.
- Attribute the posts to an existing staff/admin account rather than inventing users; if none exists, say so instead of silently creating one.
- Print a summary at the end: what was created, the URLs to click, and which test case each post covers.

## Definition of done

Run the seeder, then walk the site as a resident in **both light and dark mode** and in **all three languages**, and report what actually broke — the government-category badge, the pasted-colour post, the English-original post under FIL, the event with no venue, the ordinance with no summary. That list is the point of this exercise; a clean "everything works" is only believable if you actually clicked through all fifteen. Then run `--purge` and confirm every row and image is gone.
