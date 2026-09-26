# Prompt for Claude Code — make EN / FIL / MN auto-translation and voice reliable

Copy everything inside the box and paste it into Claude Code, opened in
`C:\xampp\htdocs\BarangGabay`.

---

```
You are working on BarangGabay, my BSIT capstone: a PHP 8.2 / MySQL MVC web app
(XAMPP, no framework) in C:\xampp\htdocs\BarangGabay. Read CLAUDE.md first.

## My problem
When I publish an announcement, event or ordinance, it is supposed to be
available in Filipino, English and Manobo, and residents switch with the FIL /
EN / MN buttons. Sometimes it works. Sometimes a language is just missing, and
nothing tells me why or what to do about it. The voice reader has the same
problem. I need this to be dependable, and when it cannot be done, I need the
system to TELL me what to fix.

## What the code already has — read these before changing anything
- app/services/TranslationService.php
    autoTranslatePost(), autoTranslatePostToEnglish(), autoTranslatePostToFilipino(),
    autoTranslatePostToManobo(), storeEnglish(), storeFilipino(),
    isCompleteTranslation(), statusFor(), regenerable(), resolveSourceLang(),
    flagAuto()
- app/services/FreeTranslationService.php — the free provider, with a daily
  per-IP allowance and a per-post character cap
- app/services/AIService.php — Anthropic; the only path that can produce Manobo
- app/services/LanguageGuess.php + the source_lang column (migration 018)
- the *_is_auto flags (migration 016) — machine text may be regenerated,
  human-typed text must never be overwritten
- app/services/PostAudioService.php — refresh(), ensure(), tracks(), status();
  app/services/TtsService.php; config/tts.php; app/controllers/VoiceController.php
- app/views/shared/_translation-notice.php and the admin translations pages

The known reasons a translation silently does not happen (the code comments say
so) are: the free service's daily allowance is used up; the body is longer than
the per-post cap; Manobo needs an Anthropic key with credits; the source
language was guessed wrong so the post is "translated" into the language it is
already in; a partial result is rejected by isCompleteTranslation() and nothing
is stored. Treat these as the baseline — confirm them in the code, then fix the
handling. Do not redesign the translation pipeline.

## What I want built

### 1. Nothing fails silently
Every translation attempt and every audio attempt records an outcome: which
post, which language, success or failure, a short machine-readable reason code
(QUOTA_EXHAUSTED, TEXT_TOO_LONG, NO_API_KEY, NO_CREDITS, PROVIDER_ERROR,
SAME_AS_SOURCE, PARTIAL_RESULT, OK), a human message, and when it may be
retried. Add the table in database/schema.sql AND a new
database/migrations/<next number>_*.sql (idempotent — migrations replay on
every connection).

### 2. Retry instead of giving up
Add a retry runner that picks up failed attempts whose retry time has passed
and tries them again, newest posts first, with backoff, and a cap per run so it
cannot spin. Two ways to trigger it:
  - tools/retry-translations.php, runnable by hand or by Windows Task Scheduler
  - opportunistically, the way ScheduledPublisher is already triggered in this
    app — follow that existing pattern, do not invent a new one
A post whose translation failed because the daily allowance ran out must fill
itself in automatically once the allowance resets. That is the main fix I want.

### 3. Tell me what to do — before I save
On the create and edit forms for announcements, events and ordinances, add a
small "Translation plan" panel that updates as I type:
  - the detected source language, with an override I can set (it must obey the
    existing resolveSourceLang() order of authority)
  - character count against the free provider's cap, with a warning before I
    save if the body is over it
  - which languages will be generated, which will be skipped, and why
  - if a provider is missing, the exact thing to fix, naming the .env key
    (for example: "Manobo needs ANTHROPIC_API_KEY with credits")

### 4. Tell me what to do — after I save
On the admin list pages and the edit page, each post shows three badges: FIL,
EN, MN — each one either "written by staff", "machine", or "missing". A missing
or machine badge is clickable and opens the reason, the suggested fix, and a
"Translate now" button for that one language. Regenerating must respect the
*_is_auto rule: never overwrite text a person typed.

### 5. The resident side must stay honest
When a resident taps FIL / EN / MN:
  - if that language exists, show it
  - if it does not, do NOT show another language under that button's label.
    Show the existing translation notice, say plainly that this language is not
    available yet, and offer the original language with its correct label.
Keep the existing _translation-notice.php partial; extend it if needed.

### 6. Voice, same treatment
  - Audio is generated per language AFTER the text for that language exists —
    make that ordering explicit, because audio generated first has nothing to
    read.
  - A language with no text gets no audio, and the player must never read one
    language under another language's label (PostAudioService already says
    this — keep it true).
  - Per-language audio status in the admin panel, with the same reason codes
    and a per-language "Generate audio" button.
  - When no TTS provider is configured, the browser voice fallback stays, and
    the UI says which one is speaking instead of pretending it is the real
    voice. Manobo currently uses the Filipino voice — label that honestly in
    the player.
  - Audio must be regenerated when the text of that language changes; stale
    audio for edited text is worse than none.

### 7. A health page I can check before my defense
Extend the admin translations section with a health view:
  - posts missing one or more languages, and posts missing audio
  - the last failure reason for each
  - bulk "retry all" for a chosen language
  - at the top, which providers are configured right now (free translator,
    Anthropic, TTS) and, for each one that is not, the exact .env key to set
Also add tools/check-translations.php printing the same summary in the console.

## Rules
- Do not overwrite text a staff member typed. The *_is_auto flags decide this.
- Every catch block must record a reason code — no empty catch, no silent
  return false.
- PDO prepared statements. Escape output with e(). CSRF on every POST.
- All user-facing text through t(), with new keys in BOTH lang/en.php and
  lang/fil.php.
- schema.sql AND a new numbered migration for any schema change; never edit an
  existing migration.
- Do not change the message formats residents already receive by SMS.
- Keep controllers thin; logic goes in the services.

## Acceptance tests — show me each one
1. Publish a post with the Anthropic key removed → FIL/EN still fill in, MN is
   marked missing with reason NO_API_KEY and the exact fix.
2. Publish a post with a body longer than the free cap → TEXT_TOO_LONG, the
   warning appeared BEFORE saving, and the retry runner does not loop on it.
3. Simulate the daily allowance being used up → post saves, language marked
   QUOTA_EXHAUSTED with a retry time, and after the runner is triggered past
   that time the translation fills in by itself.
4. Write a post in English while source is set to Filipino → the plan panel
   flags it, and after correcting, EN is treated as the source, not a
   translation.
5. Edit the Filipino body of a post that already has audio → audio for that
   language is regenerated, others untouched.
6. A post with no Manobo text → the MN button shows the honest notice, and the
   player offers no Manobo track.

## Order of work — stop for my review after each
1. Reason codes + the attempts table + logging (no UI yet).
2. The retry runner.
3. Admin badges and per-language retry buttons.
4. The pre-save translation plan panel.
5. Resident-side honesty + voice statuses.
6. The health page and the CLI script.

php -l everything you touch and run vendor\bin\phpunit --no-coverage at each
step. Tell me anything you are unsure about instead of guessing. Explain what
you did in simple English or Taglish.
```

---

## Notes for me

- The pipeline is not broken — it is quiet. The failures are real and expected
  (free-translator daily allowance, character caps, no Anthropic credits for
  Manobo, wrong source language). What is missing is a record of *why*, an
  automatic retry, and a screen that tells me the fix.
- The single most useful part is item 2: a post that failed because the free
  allowance ran out should fill itself in later, without me re-saving it.
- Before the defense, run `tools/check-translations.php` and clear whatever it
  lists — that is also a good slide.
