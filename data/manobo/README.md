# Manobo Dictionary Dataset

An original, independently-structured vocabulary dataset for **Manobo**
(ISO 639-3: `msm`), with English and Tagalog equivalents, built for BarangGabay's
community language features.

> **Scope check:** this dataset covers the Manobo language of the Caraga river valleys
> and the eastern slopes of the Diwata mountain range, including inland/southern
> Surigao del Sur. It is **not** Surigaonon, which is a Visayan language, and it is
> **not** Western Bukidnon Manobo (`mbb`), a different language spoken in Bukidnon
> province.
>
> That mismatch used to be real and is now fixed: the codebase once carried a
> `lang/mbb.php` and an `AIService::translateToWesternBukidnonManobo()` alongside
> this `msm` dataset. The empty `mbb` string file has been removed and the method
> is now `translatePostToManobo()`, so the locale code, the dictionary, the AI
> prompt and the UI all name the same language.

---

## Files

| File | Purpose |
|---|---|
| `manobo_dictionary.csv` | **Source of truth.** Edit this to add words. |
| `manobo_dictionary.json` | Generated from the CSV. Do not hand-edit. |
| `../../app/services/ManoboDictionary.php` | Lookup module. |
| `../../tools/build-manobo-json.php` | Regenerates the JSON from the CSV. |

### Fields

| Field | Meaning |
|---|---|
| `manobo` | Headword in the dictionary's orthography, including stress and glottal marks. |
| `english` | English gloss. |
| `tagalog` | Tagalog equivalent. |
| `part_of_speech` | Uses the source dictionary's abbreviations (`n.`, `v.`, `adj.`, `pro.`, …). |
| `category` | Topic grouping (`health`, `numbers`, `body`, …). Free text — new values need no code change. |
| `notes` | Confidence, usage, variant and minimal-pair warnings. Says `unverified` where a form is not confirmed. |
| `source` | Citation code — see [Sources](#sources). |

---

## Current state — read this before using it

**39 entries. This dataset is a seed, not a usable barangay translator yet.**

Every entry is traceable to a published source. But the only source that could be
accessed programmatically was the **front matter** of the SIL dictionary — its
pronunciation and spelling guide. Those example words were chosen to demonstrate
vowels and consonants, not to cover daily life, which is why the dataset currently
contains `'aehu` "pestle" and `ri'pulyu` "cabbage" but **no greetings, no way to say
yes/no, and only two numerals**.

The categories the barangay system actually needs are **empty or near-empty**:

| Needed for BarangGabay | Entries |
|---|---|
| Greetings / courtesy | **0** |
| Numbers | 2 (`dadu'wa` 2, `o'nom` 6) |
| Family terms | 1 (`i'nay` — vocative only) |
| Requests & questions | **0** |
| Farming / livelihood | **0** |
| Health & emergency | 5 (none are emergency phrases) |
| Civic vocabulary | 1 (`'ngadan` "name") |

**These gaps were left empty on purpose.** The full SIL dictionary (~5,200 root
entries) is published at [webonary.org/agusan-manobo](https://www.webonary.org/agusan-manobo/)
but blocks automated access, and no open-licensed wordlist for this language exists —
the Austronesian Basic Vocabulary Database does not cover `msm`. Filling those rows
from a language model's memory would produce plausible-looking words that are
actually Cebuano, Tagalog, or another Manobo variant. In a system serving real
Manobo residents, invented vocabulary is worse than an empty table: it is wrong in a
way that no one using the app is positioned to catch.

### How to fill the gaps

1. **A Manobo speaker.** The only real answer. Sit with an elder, the barangay's IP
   Mandatory Representative, or an NCIP contact and fill the CSV directly — it is
   plain text and opens in Excel.
2. **Webonary's search box**, used by a person. Browsing
   [webonary.org/agusan-manobo](https://www.webonary.org/agusan-manobo/) by hand is
   exactly what it is published for. Look a word up, then write the entry in your own
   words with `source` set to `SIL-WEB`.
3. **Print copy.** *Dictionary of Manobo as spoken in the Agusan river valley and the
   Diwata mountain range* (2000) — see the citation below.

Mark anything you are not certain of with `unverified` in `notes`. An entry flagged
as doubtful is useful; a confident wrong one is not.

---

## Adding words

Append a row to the CSV, then regenerate the JSON:

```bash
C:\xampp\php\php.exe tools\build-manobo-json.php
C:\xampp\php\php.exe vendor\bin\phpunit --no-coverage   # verifies the two files agree
```

No code changes are needed for new words, categories or parts of speech.

---

## Orthography

Follows the source dictionary. Both marks below are **meaningful** — they distinguish
different words, so keep them on the headword:

- **Stress** — an apostrophe *before* the stressed syllable: `'hilu` "thread" vs
  `hi'lu` "poison".
- **Glottal stop** — a grave accent at the end of a word (`bakà` "jaw" vs `baka`
  "cow"), or a dash after a consonant (`agid-id`). It is not written word-initially
  or between two plain vowels.
- **Vowels** — Manobo writes seven: `a, ae, e, i, o, u, ue`, plus longer `ey` and
  `iy`. Note `o` is a schwa and `e` is a true `e`, so Cebuano/Tagalog spelling habits
  do not transfer.
- **Roots** — the dictionary lists roots, not inflected forms. `miglinaguy` "is
  running" is built from the root `'yaguy` "run".

`ManoboDictionary` strips these marks when matching, so a resident typing `wohig`
still finds `wo'hig`. The cost is that stripped spellings can collide: a search for
`hilu` returns both entries with `ambiguous => true`. **Show every match when that
flag is set** rather than silently picking the first.

---

## Sources

> The system calls this language **Manobo** throughout. The entries below are
> reproduced **verbatim** — publication titles, article names and URLs are
> quoted exactly as their publishers wrote them, so some read "Agusan Manobo".
> Those strings are not labels and must not be edited: changing a title makes
> the citation unverifiable, and changing a URL breaks the link.

| Code | Source |
|---|---|
| `SIL-FM-*` | Gelacio, Teofilo E., Jason Lee Kwok Loong & Ronald L. Schumacher. 2000. *Dictionary of Manobo as spoken in the Agusan river valley and the Diwata mountain range.* Summer Institute of Linguistics. Front matter: [msm_front_matter.pdf](https://philippines.sil.org/sites/phil/files/msm_front_matter.pdf). Suffixes: `T1` = vowel chart, `T2` = consonant chart, `1.2` = glottal stop, `1.3` = stress, `2` = finding roots. |
| `SIL-WEB` | *Agusan Manobo Dictionary.* SIL Global. <https://www.webonary.org/agusan-manobo/> — use for entries added by hand. |
| `WIKT` | [English Wiktionary](https://en.wiktionary.org/wiki/Category:Agusan_Manobo_lemmas) (CC BY-SA), entries explicitly tagged Agusan Manobo. Only two exist as of September 2026. |
| `WP` | [Agusan language](https://en.wikipedia.org/wiki/Agusan_language), Wikipedia (CC BY-SA). Background on dialects and phonology; contributed no vocabulary. |

### Sources checked that do *not* apply

Recorded so nobody repeats the search:

- **Elkins, Richard E. 1974. "A Proto-Manobo Word List."** *Oceanic Linguistics* XIII. Covers twelve Manobo languages — Tigwa, Binukid, Sarangani, Western Bukidnon, Ilianen, Dibabawon, Cotabato, Tasaday, Cagayano, Kinamiging, Tagabawa and Obo — but **not Agusan Manobo**. Its reconstructions do corroborate entries here (`*ngadan` "name", `*ʔinay` "mother", `*tabak` "answer"), but a Dibabawon or Proto-Manobo form is not an Agusan Manobo word and must not be entered as one.
- **Austronesian Basic Vocabulary Database** — no `msm` wordlist.
- **PanLex** — unreachable from this network; worth re-checking if you have access.

**The Tagalog column is not from any source.** Tagalog equivalents were written by
rendering each source's *English* gloss into Tagalog. No Manobo–Tagalog reference was
consulted, so the Tagalog is a second-hand gloss and should be checked by a speaker of
both languages. Likewise, `part_of_speech` is inferred from the gloss, because the
front matter's example words are given without parts of speech.

---

## Licensing and intent

The SIL dictionary is **copyrighted** (© Summer Institute of Linguistics, 2000; all
rights reserved). This dataset is therefore built as an **original, independently
structured compilation for educational and community use** — not a reproduction or
redistribution of that book:

- Individual word–meaning pairs were extracted; no substantial passages were copied.
- Definitions were rewritten; the CSV's wording is our own.
- The schema, categories, notes and Tagalog column are original to this project.
- Sources are credited above and in the generated JSON.

If this dataset grows substantially, or is published beyond the barangay, **contact
SIL Philippines first**. Facts about a language are not copyrightable, but a
selection and arrangement that tracks their dictionary could be — and the community
courtesy matters more than the legal line.

This language belongs to the Manobo people. This dataset is a tool for
serving them, and their corrections outrank anything written here.
