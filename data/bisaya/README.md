# Bisaya Dictionary (fallback for the MN button)

`bisaya_dictionary.csv` maps English and Tagalog words and short labels to
**Bisaya**. It is the second tier behind the Manobo dataset when a resident
clicks **MN** in the header language switch.

## Why it exists

In Barangay Bayogo, Madrid, Manobo is spoken mixed with Surigaonon and Bisaya.
The Manobo dataset (`data/manobo/`) only holds sourced Manobo words, so most
interface labels have no entry there. Before this file, those labels fell back
to English, which many Manobo residents cannot read comfortably.

## How MN resolves a label (app/helpers.php → t())

1. Whole label is a Manobo word → Manobo
2. Whole label is in this file → Bisaya
3. Word-by-word over the Filipino label: Manobo word if it exists, else Bisaya
   (used only if at least 70% of the words convert) → Manobo/Bisaya blend
4. Otherwise → Filipino, then English

EN and FIL are not affected.

## Kept separate from the Manobo dataset on purpose

Do **not** copy these rows into `data/manobo/manobo_dictionary.csv`. A Bisaya
word must never be recorded as Manobo there. If a local speaker confirms that
the word Manobo residents actually use *is* a Bisaya/Surigaonon word, add it
to the Manobo dataset through `/admin/manobo` with that note.

## Status: needs local review

The 279 starter entries were written in standard **Cebuano Bisaya** by an AI
assistant and marked `BISAYA-CEB`. They have **not** been checked by a
Surigaonon speaker. Where the Surigaonon form differs, edit the `bisaya`
column and set `source` to `LOCAL` (or the speaker's name).

Edit the CSV in Excel or any text editor, keeping the header:
`bisaya,english,tagalog,part_of_speech,category,notes,source`.
Comma-separated values in `english`/`tagalog` are alternative meanings. Use
one sense per row when a word is ambiguous (e.g. Tagalog "bago" = new *and*
before, so it is intentionally not mapped).

## Wiktionary word list (`bisaya_wiktionary.csv`)

About 6,500 more Cebuano Bisaya words (5,772 new headwords after skipping words
already above), added 2026-10-19 so the MN translator and the voice trainer
recognise far more Bisaya.

- **Source:** English Wiktionary's Cebuano and Tagalog entries, via the
  kaikki.org machine-readable extracts. Wiktionary text is licensed
  **CC BY-SA 4.0** (https://creativecommons.org/licenses/by-sa/4.0/); the
  `english` meanings come from it, so this file is shared under the same
  licence. Credit: Wiktionary contributors.
- **Filtered:** only nouns, verbs, adjectives, adverbs, numbers and common
  function words; no names, slang, vulgar, obsolete, rare or alternative
  spellings; no species or definition-style meanings.
- **Tagalog column:** paired automatically. A Tagalog word is filled in only
  when its *main* English meaning matches the Bisaya word's main meaning
  (1–3 synonyms). Otherwise it is blank and the word still works for English
  and for recognising Bisaya. Rows with an automatic pairing say so in `notes`.
- **False friends left out:** words spelled like a Tagalog word with a
  different meaning (Bisaya *damo* = many, Tagalog *damo* = grass).
- **Loaded by:** `php tools/import-bisaya-wiktionary.php` (MariaDB), and once
  automatically on Render (setting `bisaya_wiktionary_v1`). Rows get
  `source = WIKTIONARY-CEB`. A headword already in the table is skipped, so
  the hand-written words above keep priority and nothing is overwritten.
- **Still Cebuano, not Surigaonon:** where the local form differs, correct
  the word at `/admin/bisaya` and set source to `LOCAL`.
