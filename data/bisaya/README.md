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
