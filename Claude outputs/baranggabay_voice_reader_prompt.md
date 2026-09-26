# Prompt for Claude — BarangGabay: AI voice reader for announcements, events, and ordinances

Paste this into Claude Code, working inside the project folder (`CLAUDE.md` is already loaded there).

## Goal

Add a **"Pakinggan" (Listen) voice reader** so a resident can have any announcement, event, or ordinance read aloud instead of reading it. This is an accessibility feature first: it is for elderly residents, residents with low literacy or poor eyesight, and anyone reading on a phone in bright sunlight. Treat it as such — not as a gimmick button hidden at the bottom of the page.

## Ground truth — read these before writing any code

- `app/views/resident/announcement-detail.php`, `event-detail.php`, `ordinance-detail.php` are the three pages that need the player.
- **Content language already follows a global FIL / EN / MN switch in the header.** The detail views call `localised_content($row, 'title')` / `localised_content($row, 'body')`, which return `['text' => ..., 'translated' => bool, 'locale' => ...]`. There is deliberately **no per-post language toggle any more** (see the comment block in `announcement-detail.php` explaining why). The reader must therefore speak **whatever localised text is currently on screen, in the matching voice language** — never blindly read the Filipino original while the page is showing English.
- The Filipino original body is **HTML** (Quill output, purified on save); translations are stored as **plain text**. `$bodyIsHtml` in the detail view already encodes this distinction. Strip tags before speaking, the way `_manobo-translator.php` already does with `strip_tags()`.
- `audio_manobo_path` already exists on all three tables and is passed into `shared/_manobo-translator.php` as `$__mAudio`. Do not duplicate or break it — see the Manobo section below.
- Follow the established shared-partial convention (`shared/_post-header.php`, `_translation-notice.php`, `_manobo-translator.php`): build this as **one** `shared/_voice-reader.php` included by all three detail views, not three copies.
- All UI strings go through `t()` with keys for English, Filipino, and Manobo. Nothing hardcoded.
- `config/ai.php` holds only an Anthropic key. **Anthropic has no text-to-speech API** — Claude cannot generate the audio. Whatever you build must use either the browser's own speech engine or a separate TTS provider. Do not invent an Anthropic TTS call.

## Architecture — build it in this order

### Phase 1 (required): browser speech, zero cost, no API key

Use the Web Speech API (`window.speechSynthesis`) to read the on-screen text. This works offline-ish, costs nothing, needs no new credentials, and is the realistic baseline for a barangay system.

- Set `utterance.lang` from the active locale: `fil-PH` for Filipino, `en-PH` (fall back to `en-US`) for English.
- `speechSynthesis.getVoices()` is **async** — listen for `onvoiceschanged` before picking a voice. If no `fil-PH` voice exists on the device, fall back to the closest available voice and show an honest note that the device has no Filipino voice installed, rather than silently reading Tagalog with an American voice and sounding like nonsense.
- Known Chrome bug: speech cuts out after roughly 15 seconds on long text. Handle it — chunk the text into sentences and queue them, and/or use the `resume()` keep-alive workaround. A barangay announcement read that dies mid-sentence is worse than no button.
- iOS Safari requires speech to be started from inside the user's tap handler. Kick off the first utterance synchronously in the click listener, not after an `await`.
- Use `onboundary` events to **highlight the sentence (or word) currently being spoken** in the post body. This is the single biggest accessibility win and it demos extremely well. Respect `prefers-reduced-motion` for the highlight transition.

### Phase 2 (optional, only if you want consistent quality): cached server-side audio

If device voices prove too inconsistent, add a server-generated MP3 as the preferred source with Phase 1 as the fallback. Use a real TTS provider with Filipino support — Google Cloud TTS (`fil-PH`, WaveNet) or Azure Speech (`fil-PH-BlessicaNeural` / `fil-PH-AngeloNeural`); both have free monthly tiers large enough for a barangay's posting volume.

- **Generate once per post, not per listener.** Staff trigger generation when publishing; the file is cached and every resident plays the same MP3. Never call the TTS API on each page view — that is how a free tier disappears in a week.
- Cache in a new table rather than adding six columns across three tables, e.g. `post_audio (id, content_type ENUM('announcement','event','ordinance'), content_id, locale, audio_path, text_hash, generated_at, generated_by)`, unique on `(content_type, content_id, locale)`.
- Store `text_hash` (a hash of the exact text that was spoken) so that when staff edit the post, the stale audio is detected and flagged for regeneration instead of quietly serving the old version.
- Update **both** `schema.sql` and a new numbered file in `database/migrations/` — the established convention (see `runPendingMigrations()` in `config/database.php`).
- Store files under the existing uploads structure, follow the existing upload/serving conventions, and delete the audio when the post is deleted.
- Put the provider key in `.env` + a config file alongside `config/ai.php`; never hardcode it. Fail gracefully to Phase 1 if the key is missing or the API errors.

### Manobo — read this carefully

**No text-to-speech engine supports Agusan Manobo.** Google, Azure, ElevenLabs, and the browser all lack a Manobo voice, and forcing a Filipino voice to read Manobo text produces something a Manobo speaker will not recognise as their language. Do not fake it.

The correct behaviour: when the active locale is **MN**, the voice reader plays the **human-recorded** `audio_manobo_path` file if one exists, and if none exists, it says so plainly ("walang Manobo audio para sa post na ito") and offers the Filipino audio instead. Keep — and ideally make more prominent in the admin form — the existing ability for staff to upload a recorded Manobo reading. A real recording from a community member is both more accurate and more respectful than synthetic audio here, and it's a defensible design decision to state in your capstone.

## Content preparation (do this server-side, shared by both phases)

- Strip HTML from the Quill body; preserve sentence boundaries so chunking and highlighting work.
- Build the spoken text as: title → category/urgency (if urgent, say so first) → date → body. For **events**, include the date, time, and venue near the start, because that's what a listener actually needs. For **ordinances**, read the **`ai_summary`**, not the PDF — reading a 20-page legal document aloud helps nobody and costs a fortune in Phase 2. Say clearly at the end that it was a summary and the full ordinance is available as a document.
- Expand abbreviations so they're spoken properly: `Brgy.` → "Barangay", `Hon.` → "Honorable", `SK` → "Es-Key", `BHW`, `MDRRMO`, dates, and times. A Filipino voice reading "Brgy." literally sounds broken.
- Cap the length of what gets spoken and log/flag anything over the cap.

## UI requirements

- A large, obvious **Pakinggan / Listen** button placed **near the top of the post**, right after the header — not buried after the share buttons. Elderly users should not have to scroll to find it.
- Controls: play / pause / stop, a progress indicator, and a speed control (0.75× / 1× / 1.25×). Older listeners frequently want slower.
- A sticky mini-player so playback controls stay reachable while the resident scrolls through the post.
- Stop playback on navigation away; never autoplay, ever — an announcement that starts talking by itself in a quiet room is a bug, not a feature.
- Fully keyboard accessible, proper ARIA labels, and an `aria-live` region for status changes.
- Mobile-first: verify at ~390px width.
- Degrade cleanly: if the browser has no speech support and no server MP3 exists, hide the player rather than showing a dead button.

## Admin side

In the create/edit forms for announcements, events, and ordinances:

- A **"Preview voice"** button so staff can hear how the post will sound *before* publishing — this is how they catch abbreviations and typos that read badly.
- If you built Phase 2: a "Generate audio" action with a visible status (none / generating / ready / stale-after-edit), and regeneration when the `text_hash` no longer matches.
- Keep the Manobo audio upload field, and label it clearly as a human recording.

## Definition of done

Open a real announcement as a resident and press Listen — it reads the title and body correctly in Filipino, highlights as it goes, and survives past 15 seconds on a long post. Switch the header language to English and confirm it now reads the English text with an English voice, not the Filipino original. Switch to Manobo and confirm it plays the uploaded recording, or clearly says none exists — and confirm it never tries to synthesise Manobo. Test an event (date/time/venue spoken early) and an ordinance (reads the summary, not the PDF). Test on an actual Android phone and on iOS Safari, since that's what residents use. Then give me a short summary of what you built, which phase you implemented, and anything left incomplete.
