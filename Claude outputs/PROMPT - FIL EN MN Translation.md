# Prompt for Claude — BarangGabay FIL / EN / MN language switch

Copy everything inside the box below and paste it into Claude (Claude Code or a
Claude chat with the BarangGabay folder attached).

---

```
You are working on BarangGabay, my BSIT capstone: a PHP 8.2 / MySQL MVC web app
(XAMPP, no framework) for District Zone 3, Lanuza, Surigao del Sur, Philippines.
Read CLAUDE.md in the project root first.

## Why this feature matters
The main goal of the system for residents is that they understand what the
barangay officials want to tell them: announcements, events, ordinances,
emergency advisories. Many residents are Manobo and have a hard time
understanding English, and sometimes Filipino. In our place, Manobo is not
spoken "pure". It is mixed with Surigaonon and Bisaya, and many everyday words
are the same as Surigaonon/Bisaya words.

## What must happen when a resident clicks the header language buttons
- FIL → every interface word on every page is in Filipino.
- EN  → every interface word on every page is in English.
- MN  → every interface word is in the Manobo that we actually speak here:
        a real Manobo word where one exists, otherwise the Bisaya/Surigaonon
        word, and Filipino only if neither exists. Never English in the
        middle of an MN page if a Bisaya or Filipino word is available.

## How it is built already (do not rebuild it, extend it)
- Header buttons: app/views/layouts/main.php loops available_locales()
  ('en', 'fil', 'msm') and links to /set-locale/{code} → LocaleController@set,
  which stores the choice in $_SESSION['locale'].
- Button label: locale_short_code() in app/helpers.php shows "MN" for msm.
- Every interface string goes through t('key') in app/helpers.php.
  lang/en.php and lang/fil.php are complete. lang/msm.php is intentionally
  empty.
- For msm, t() resolves each label in this order:
    1. Whole label in the Manobo dataset (data/manobo/manobo_dictionary.csv,
       read via ManoboDictionary / manobo_word()) → Manobo
    2. Whole label in the Bisaya dataset (data/bisaya/bisaya_dictionary.csv,
       read via bisaya_word()) → Bisaya
    3. local_blend_gloss() over the Filipino label: each word becomes Manobo
       if it exists, else Bisaya; used only if ≥70% of words convert
    4. Otherwise Filipino, then English, then the raw key
- Long content (announcement/event/ordinance bodies) is NOT translated by the
  dictionaries. It uses the AI translator: views/shared/_manobo-translator.php
  → POST /api/ai/translate → AIController@translateManobo →
  AIService::translateToManobo(). Both AI Manobo methods share
  AIService::manoboSystemPrompt(), which already tells the model to blend
  Manobo with Surigaonon and Bisaya, and communityVocabHint() injects matching
  words from the Manobo dictionary.

## Rules you must follow
1. Never write a Bisaya word into data/manobo/manobo_dictionary.csv. That file
   is sourced Manobo only (see data/manobo/README.md). Bisaya goes in
   data/bisaya/bisaya_dictionary.csv.
2. Never invent a "Manobo" word. If you are not sure a word is Manobo, it is
   not Manobo — put the Bisaya word in the Bisaya file instead.
3. Mark anything you add with source BISAYA-CEB and a note that a local
   Surigaonon speaker must verify it.
4. Do not change EN or FIL output. After any change, t() for 'en' and 'fil'
   must return exactly the strings in lang/en.php and lang/fil.php.
5. Keep placeholders like :name, :email, :n untouched in every translation.
6. One sense per row for ambiguous words (Tagalog "bago" = new AND before, so
   it stays unmapped).
7. Follow the project rules in CLAUDE.md: PSR-12, e() for output, prepared
   statements, and if you touch the database add to schema.sql AND a
   database/migrations/*.sql file.
8. Make small, incremental changes. Do not rewrite working files.

## Tasks
1. Run a coverage check: for every key in lang/en.php, call t() with
   $_SESSION['locale'] = 'msm' and list the resident-facing keys (nav.*,
   resident_home.*, announcements*, events*, ordinances*, notifications*,
   feedback*, profile*, manobo_widget.*) that still fall back to plain
   Filipino. Show me that list grouped by page.
2. Add Bisaya rows to data/bisaya/bisaya_dictionary.csv for the missing
   words from task 1, starting with navigation, buttons, status badges,
   emergency/advisory words, and greetings. Prefer whole-label rows for
   short labels so the result reads naturally, not word-by-word.
3. Re-run the coverage check and the EN/FIL regression check. Report:
   how many MN strings are now Manobo/Bisaya, and confirm EN and FIL have
   0 changed strings.
4. Show me 20 before/after samples (FIL vs MN) from resident pages so I can
   check them with a local speaker.
5. Tell me which words you were unsure about so I can ask a Manobo/Surigaonon
   speaker.

Explain what you changed in simple English or Taglish.
```

---

## Where things are

| What | File |
|---|---|
| Header FIL / EN / MN buttons | `app/views/layouts/main.php` |
| Language switch route | `routes/web.php` → `LocaleController@set` |
| `t()`, fallback order, `bisaya_word()`, `local_blend_gloss()` | `app/helpers.php` |
| Manobo words (sourced only) | `data/manobo/manobo_dictionary.csv` (edit at `/admin/manobo`) |
| Bisaya words (MN fallback) | `data/bisaya/bisaya_dictionary.csv` (edit in Excel) |
| AI translation of full announcements | `app/services/AIService.php` → `manoboSystemPrompt()` |
