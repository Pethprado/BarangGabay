# Prompt for Claude — BarangGabay: two bugs (FIL/MN not translating, dark-mode invisible text)

Paste this into Claude Code, working inside the project folder. Two separate bugs. Fix them in order.

---

# Bug 1 — Clicking FIL or MN does not translate the post

## Symptom

On an announcement detail page with the header switch set to **FIL**, the navigation is correctly Filipino ("Mga Anunsyo", "Mga Kaganapan", "Diksyunaryo", "Puna") but **the post body is still English**. The voice reader also falls back to "Pakinggan na lang sa Filipino". Same for MN. The post in question was pasted/imported from a Facebook appreciation post written in English.

## Likely root cause — verify this first

The `t()` chrome strings work, so the locale switch itself is fine. The problem is **content** translation, and the pipeline appears to assume every post's original is Filipino:

- `TranslationService::autoTranslatePostToEnglish()` translates **fil → en**.
- `FreeTranslationService` (MyMemory, free, no API key) exposes **both** `toEnglish()` *and* `toFilipino()` — read its docblock, it was written precisely because *"the Anthropic path needs credits the barangay does not have."*
- So when a post's original is **English** (typed in English, or imported from an English Facebook post), nothing ever fills `title`/`body` for Filipino. `localised_content()` then falls back to the original, which is English — displayed under a FIL badge with no explanation.

Confirm that's what's happening before changing anything: check what `autoTranslatePost*()` is actually called with on save, and whether `FreeTranslationService::toFilipino()` has any caller at all.

## What to fix

1. **Stop assuming Filipino is the source.** Add a `source_locale` column to announcements, events and ordinances (`schema.sql` **and** a new numbered migration, per `runPendingMigrations()` in `config/database.php`). Set it from the admin form — with a sensible auto-detection default — and store it on save.

2. **Translate into the other locales, whatever the source is.** On save: if the source is Filipino → fill English via `FreeTranslationService::toEnglish()` (existing behaviour); if the source is **English → fill Filipino via `toFilipino()`**, which is the missing half. Respect the service's documented limits (450-byte chunks, chunk cap, `null` on failure — never store a partial or the literal `QUERY LENGTH LIMIT EXCEEDED`).

3. **Run it on the import/paste path too.** A post created by pasting a link or a Facebook caption must go through exactly the same translation step as one typed by hand. This is the case in the screenshot and it's the one that's broken.

4. **Manobo: be honest.** MN still needs either the Anthropic path (no credits) or manually entered `*_manobo` fields. Do **not** show English text under an MN badge. Use the existing `shared/_translation-notice.php` to say plainly which language the reader is actually seeing and why — "Walang Manobo na salin pa para sa anunsyong ito" — and keep offering the Filipino reading.

5. **Give staff a retry.** A "Translate now" action per post in the admin list, plus a visible per-post status (fil ✓ / en ✓ / mn ✗). MyMemory has a daily per-IP allowance, so failures will happen — they must be visible and retryable, not silent.

6. Never overwrite a translation a human typed by hand with a machine one.

## Done when

Create one post in English and one in Filipino. For each, click EN, FIL and MN and confirm you get the real translated text, or an explicit notice saying it isn't available — never another language silently shown under the wrong badge. Then do the same for a post created by pasting a link. Report which languages now fill automatically and which still need credits or manual entry.

---

# Bug 2 — Some words are invisible in dark mode (resident side)

## Don't guess — the theme system is already good

`public/assets/css/main.css` is well built: tokens on `:root`, a `[data-theme="dark"]` block, an OS `prefers-color-scheme` twin, and dark remaps for the slate/gray utilities and many Tailwind accent classes. So the invisible text is **specific gaps**, not a broken system. Find them systematically rather than patching whatever you happen to see.

### Lead 1 — Tailwind colour families with no dark remap (confirmed gap)

The remap currently covers: `amber, blue, green, orange, purple, red, rose` (plus slate/gray). **`indigo` is missing** — and `app/views/resident/announcement-detail.php` maps the *government* category to `bg-indigo-100 text-indigo-700`, so that badge is unreadable in dark mode. Note also that `resident/home.php` maps the same category to `bg-blue-100 text-blue-700`; the two files disagree.

Do this: grep every resident view for `text-*-[0-9]`, `bg-*-[0-9]` and `border-*-[0-9]` classes, diff that set against the remap list in `main.css`, add the missing families, and make the category → colour maps consistent across `home.php`, the list views and the detail views (one shared source of truth, not a copy per file).

### Lead 2 — Inline colours inside pasted post bodies (most likely cause here)

The post in the screenshot was **pasted from Facebook**. Quill preserves inline styling from pasted HTML, so the stored body very probably contains `style="color: rgb(0,0,0)"` or similar. No CSS class override can reach that — it stays near-black on the dark `#131b2c` card, which is exactly "some of the words can't see."

Fix both ends:
- **On save:** strip `color` and `background-color` declarations in the HTMLPurifier config so pasted text inherits the theme instead of carrying a foreign palette. Post content should never hardcode its own colours.
- **Defensively in CSS:** inside `.post-body` in dark mode, neutralise inline colours so existing posts already in the database become readable without re-saving them.

### Lead 3 — Inline `style="color:…"` in the views themselves

Grep the resident views for inline `style=` containing a colour literal and move those to tokens (`var(--text-primary)`, `var(--text-secondary)`, `var(--text-muted)`). `resident/home.php` has inline gradient/shadow styles — check each one against the dark tokens.

## How to verify (do this, don't eyeball it)

Check both dark paths, because they're different code paths: (a) OS set to dark with the toggle untouched, and (b) the explicit toggle. Walk the resident pages — home, announcements list + detail, events list + detail, ordinances list + detail, profile, notifications, feedback — including a **government-category** post and the pasted post from the screenshot. Measure contrast rather than judging by eye: every body text must be ≥ 4.5:1 and large text ≥ 3:1 against its actual background. List anything that fails with the file and line.

## Done when

No text on any resident page falls below WCAG AA contrast in either dark path; the government/indigo badge is readable; the pasted post body is readable without re-saving it; and newly pasted content no longer stores hardcoded colours. Report every file you changed and any spot you judged borderline.
