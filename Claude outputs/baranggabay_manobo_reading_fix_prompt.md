# Prompt for Claude — BarangGabay: make the Manobo voice reading actually work

Paste this into Claude Code, working inside the project folder.

## Start by reading these — the causes are already documented in the repo

The Manobo player shows "Manobo translation is not available right now." There are **three separate causes**, and two of them are written down in the codebase already. Read these before changing anything:

1. `app/services/TranslationService.php`, the docblock on `autoTranslatePostToEnglish()` — it states plainly: *"at the time of writing the Anthropic account has no credits, so this returns false on every call."* Every API-backed translation path (`autoTranslate`, `autoTranslatePost`, English and Manobo alike) fails for this reason. **No code change fixes an unfunded API account.** Verify whether this is still true before assuming anything else is broken: check that `ANTHROPIC_API_KEY` is set in `.env`, then make one real call and report the actual HTTP status and error body instead of letting it fail silently. If it's a credit/billing error, say so in your summary — that's a decision for the project owner, not a bug to patch.

2. `data/manobo/README.md` — the dictionary has **39 entries**, and the README states: *"This dataset is a seed, not a usable barangay translator yet."* Greetings: 0. Requests and questions: 0. Civic vocabulary: 1. Numbers: 2. It also explains why those gaps were deliberately left empty: filling them from a language model's memory produces words that are actually Cebuano, Tagalog, or a different Manobo variant, and *"in a system serving real Manobo residents, invented vocabulary is worse than an empty table."* **Respect that decision. Do not fill the dictionary with model-generated words.**

3. **A real bug you can fix today:** the dataset is Agusan Manobo (`msm`, Caraga / Diwata range / inland Surigao del Sur — the right language for Lanuza), but `TranslationService::autoTranslatePost()` calls `AIService::translateToWesternBukidnonManobo()`, and there's a `lang/mbb.php`. Western Bukidnon Manobo (`mbb`) is a **different language**. The README calls this mismatch out explicitly. Even with credits, the post translation would target the wrong variant.

## What to actually build

### 1. Fix the language mismatch (`msm` vs `mbb`)

Settle on **`msm` (Agusan Manobo)** everywhere — the locale code, the AI prompt and method name, the `lang/` chrome-string file, and the dictionary lookups — so the translation target, the dictionary, and the UI all refer to the same language. Rename `translateToWesternBukidnonManobo()` accordingly and update every caller. If `lang/mbb.php` holds real strings someone wrote, migrate them rather than deleting them, and note in your summary which strings may now be the wrong variant and need a speaker's review.

### 2. Make Manobo reading work **without any API credits** — this is the main path

The voice reader does not need Anthropic at all. It needs Manobo **text**. That text can come from a person, and the system already supports that: `shared/_manobo-fields.php` and the `title_manobo` / `body_manobo` / `description_manobo` columns exist.

So make the human path the primary, reliable one:

- In the admin create/edit forms, make the Manobo fields prominent and clearly optional-but-encouraged, with the Filipino text shown beside them for reference while typing.
- When `body_manobo` has content, the MN language button in the player **must work** — generate/queue the audio from that text, mark it ready, and never show "not available."
- Only fall back to the API when the manual fields are empty *and* the API is actually working.
- In the admin list views, show per-post Manobo status (manual / AI / missing) so staff can see what still needs a Manobo speaker's attention.

This alone makes Manobo reading functional today with zero credits, which is the outcome being asked for.

### 3. Add a pronunciation layer before Manobo text reaches the voice engine

This is the part that decides whether Manobo audio sounds like Manobo or like gibberish, and `data/manobo/README.md` has the facts you need in its **Orthography** section:

- **Stress is written as an apostrophe before the stressed syllable** (`'hilu`, `hi'lu`) and **glottal stops as a grave accent or a dash** (`bakà`, `agid-id`). A TTS engine will read these as punctuation — inserting pauses or skipping — so they must be stripped or converted before synthesis, never sent raw.
- **The vowels do not map the way Tagalog habits assume**: in this orthography **`o` is a schwa and `e` is a true `e`** — the opposite of Cebuano/Tagalog spelling. A `fil-PH` voice reading Manobo spelling literally will mispronounce every single `o`. Re-spell the text phonetically for the voice engine (map the schwa to whichever Filipino spelling the chosen voice renders closest to a schwa, handle the `ae`, `ue`, `ey`, `iy` digraphs) so the audio approximates the real sounds.
- Keep this transliteration **separate from the stored text** — it is a speech-only transform. The displayed Manobo text must keep its stress and glottal marks, because those marks distinguish different words.
- Put it in its own class (e.g. `App\Services\ManoboSpeech`) with unit tests over the README's own minimal pairs (`'hilu` / `hi'lu`, `bakà` / `baka`), so a future change can't silently break pronunciation.

Label the result honestly in the UI: a Filipino voice reading Manobo is an **approximation**, not a Manobo speaker. A human recording (`audio_manobo_path`) always outranks it and should be used first when present.

### 4. Make it automatic

Generate the Manobo audio at publish time (and regenerate when the Manobo text changes, via the `text_hash` staleness check), not on each page view. If the source Manobo text is missing, the MN button should say specifically *why* — "no Manobo translation yet for this post" — and offer the Filipino reading, instead of the current generic unavailable message.

### 5. Use the 39 dictionary entries as grounding, not as a translator

If and when the API works, pass the verified dictionary entries into the translation prompt as a glossary, instruct the model to use the attested forms where they exist, and mark which words came from the dictionary versus the model. Flag model-generated Manobo as unreviewed until a speaker confirms it. Do not expand the CSV yourself.

## Definition of done

Type Manobo text by hand into a post's Manobo fields, save, and confirm the MN button in the player plays that text — with stress/glottal marks stripped for speech but intact on screen — with no API call involved. Confirm a post with no Manobo text says specifically why and offers Filipino instead. Confirm `msm`/`mbb` is consistent across the codebase. Then report: whether the Anthropic account actually has credits (with the real error if not), what the pronunciation layer changed, and which strings still need a Manobo speaker's review.

## One thing to put in your summary, not in the code

The dictionary gaps and the translation quality are not engineering problems — the README is right that they need a Manobo speaker, the barangay's IP Mandatory Representative, or an NCIP contact. Anything the system generates for Manobo should be treated as a draft awaiting that review, especially before it is spoken aloud to residents as official barangay information.
