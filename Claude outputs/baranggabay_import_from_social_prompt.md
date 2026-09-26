# Prompt for Claude — BarangGabay: import / attach posts from Facebook, Google and other links

Paste this into Claude Code, working inside the project folder.

## What I want

When staff create an **announcement, event, or ordinance**, they should be able to paste a link from Facebook, Google Drive, YouTube or any website and have the system either (a) pull the content in to pre-fill the form, or (b) attach/embed the original post alongside ours. The barangay usually posts on Facebook first, and re-typing everything into this system is the slow part.

## Read this before designing — what is actually possible

Do not promise more than the platforms allow. Build to these facts and tell me in your summary which tiers you implemented.

- **Facebook blocks server-side fetching.** Requesting a `facebook.com` post URL from PHP returns a login wall, not the post. Open Graph parsing of Facebook links will fail for almost everything. Facebook's oEmbed endpoints have required an app access token since 2020. So: **no scraping**. It breaks constantly and violates their terms.
- **The Facebook path that works without an API key is the official embed** (the Social Plugin iframe / `fb-post` embed) for a **public** post. It renders the real post inside our page. That covers "attach a post from Facebook."
- **The Facebook path that genuinely pulls text and images is the Graph API**, and only for a Page the barangay itself administers: a Meta developer app, a Page access token, `pages_read_engagement`, and — the part that stops most student projects — App Review plus Business Verification. Treat this as an **optional Tier 4** that is switched on by config, not as the default. If the barangay's own Page is available and verified, it's the best experience; if not, everything else must still work.
- **"Google" means several different things** — handle them separately: Google Drive (a shared file → `/preview` iframe embed, or download the PDF for an ordinance), Google Docs published-to-web (parses fine), Google Calendar (public ICS feed, genuinely useful for events), YouTube (free oEmbed + iframe).
- **Ordinances are PDFs**, so for them "import from Google" mostly means: paste a Drive link to the PDF, download it, and store it through the existing upload path.

## Build it in tiers, cheapest first

### Tier 1 — Paste a link, pre-fill the form from Open Graph tags

A "Import from link" field at the top of each create form. Server fetches the URL, parses `og:title`, `og:description`, `og:image`, `article:published_time`, and pre-fills the title, body and cover image as **editable drafts** — staff always review before saving, never auto-publish. Works for news sites, LGU websites, published Google Docs, YouTube. Show a clear, specific message when a site blocks us (Facebook will) rather than a generic failure.

**Security — this is the part that matters most.** Fetching a user-supplied URL server-side is a classic SSRF hole, and this is a government system:
- Allow `http`/`https` only; reject everything else.
- Resolve the hostname and **block private and loopback ranges** (127.0.0.0/8, 10/8, 172.16/12, 192.168/16, 169.254/16, ::1, fc00::/7) — before *and* after redirects, since a redirect can point back inside.
- Cap redirects, set a short timeout, cap the response size, and only parse `text/html`.
- Never render fetched HTML into the page. Extract text, escape it, and run the body through the same HTMLPurifier path the Quill editor already uses.
- Re-host the `og:image` locally through the existing `FileService` validation (real MIME sniffing, size cap) instead of hotlinking — a hotlinked image dies when the source deletes it.

### Tier 2 — Attach / embed the original post

Add a "source link" to announcements, events and ordinances: store the URL plus a detected platform (`facebook`, `youtube`, `drive`, `other`), and render the platform's **official embed** on the detail page — the Facebook Social Plugin for a public FB post, the YouTube iframe, the Drive `/preview` iframe — with a plain "View original post" link as the fallback when the embed can't load. Sandbox the iframes, lazy-load them, and make sure they don't break the mobile layout at 390px.

New columns go in `schema.sql` **and** a new numbered file in `database/migrations/`, per `runPendingMigrations()` in `config/database.php`.

### Tier 3 — Paste the text (the one that always works)

A "Paste from Facebook" textarea where staff copy the caption and upload the photo themselves. Clean it up automatically: trim the trailing hashtag blocks (and optionally turn them into tags), strip the "See more" artefacts, normalise the line breaks, and convert bare URLs into links. This needs no API, no keys and no platform cooperation, and it's what the barangay will realistically use most days — so make it good, not an afterthought.

If the Anthropic API is working, add an "AI assist" button on top of the pasted text that suggests the title, category and urgency, and for events extracts the date, time and venue into the form fields. Note that `TranslationService.php` currently documents the account as having no credits — so this must degrade to plain manual entry, not break the form.

### Tier 4 — Optional: the barangay's own Facebook Page via Graph API

Behind a config flag with the app ID, secret and Page token in `.env` (never committed). Pull recent posts from the barangay's own Page into a "pick a post to import" list. If the token is missing or expired, the feature hides itself cleanly and Tiers 1–3 keep working. Handle token expiry with a clear admin message — Page tokens expire, and a silent failure here looks like the whole feature is broken.

## Rules that apply to every tier

- **Nothing auto-publishes.** Imported content lands as an editable draft that a staff member reviews and saves. An official barangay channel must not repost whatever a link contained.
- **Attribution.** If the content came from somewhere else, store and display the source. Content from the barangay's own Page is theirs to reuse; a news outlet's article or another person's post is not — for those, prefer the Tier 2 embed with a link over copying the text wholesale.
- Everything goes through the existing CSRF, `role:admin,staff` middleware, and audit logging (`AuditLog::record()`) — record what was imported and from where.
- Rate-limit the fetch endpoint. It makes outbound requests on demand.
- All new UI strings through `t()` for English, Filipino and Manobo.

## Definition of done

Paste a YouTube link and a news article link and confirm the form pre-fills with the title, description and a locally re-hosted image. Paste a public Facebook post link and confirm you get either a working embed or a clear explanation — never a silent failure or a broken card. Paste a Drive PDF link on the ordinance form and confirm the file is stored through the normal upload path. Paste a raw Facebook caption into the text box and confirm the hashtag/"See more" cleanup works. Then try `http://127.0.0.1/`, `http://localhost/`, and a public URL that redirects to a private IP, and confirm all three are refused. Summarise which tiers you built, which need keys I haven't set up, and anything left incomplete.
