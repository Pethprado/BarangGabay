<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Re-spells Manobo text for a speech engine. Speech only — never for display.
 *
 * The problem this solves: Manobo is written in the SIL orthography documented
 * in data/manobo/README.md, and a Filipino text-to-speech voice reading that
 * orthography literally gets it wrong in two different ways.
 *
 *   Marks. Stress is an apostrophe BEFORE the stressed syllable ('hilu, hi'lu)
 *   and a glottal stop is a grave accent (bakà) or a dash (agid-id). A TTS
 *   engine has no idea those are letters. It reads the apostrophe as a quote —
 *   pausing, or swallowing the word — and the grave accent as an unknown
 *   character. Sent raw, "wo'hig" does not come out as a word at all.
 *
 *   Vowels. This orthography writes seven vowels, and two of them do not mean
 *   what a Tagalog reader assumes: `o` is a SCHWA and `e` is a true `e` — the
 *   opposite of Cebuano/Tagalog spelling habits. A fil-PH voice pronounces
 *   every `o` as [o], so every schwa in the language comes out wrong.
 *
 * What it deliberately does NOT do: touch anything that is not Manobo. The
 * Manobo a resident actually hears is code-switched with Surigaonon and
 * Bisaya, and the spoken script also carries dates, venues and proper nouns
 * ("Barangay Hall, Bayogo"). Re-spelling those would turn "Bayogo" into "Bayugu".
 * So a word is only transformed when there is positive evidence it was written
 * in this orthography: it carries the marks, or the barangay's own dictionary
 * knows it. Everything else passes through untouched.
 *
 * ── What is lost, and why that is still the right trade ──────────────────
 *
 * Filipino has five vowels; this orthography has seven. Filipino writing has
 * no way to mark a final glottal stop, and no fil-PH voice can be asked for
 * lexical stress through plain text. So three contrasts do not survive:
 *
 *   'hilu "thread" / hi'lu "poison"  — stress; both come out the same
 *   bakà  "jaw"    / baka  "cow"     — final glottal; both come out the same
 *   the schwa `o` merges with `u`
 *
 * These are stated in tests rather than hidden, because the honest position is
 * that a synthesised Filipino voice approximates Manobo and cannot replace a
 * Manobo speaker. A human recording (audio_manobo_path) always outranks this.
 *
 * ── The one judgement call a speaker must confirm ────────────────────────
 *
 * SCHWA_TARGET below. `u` was chosen over `e` for two reasons: mapping the
 * schwa to `e` would collide with the orthography's true `e` and destroy a
 * real contrast, whereas `u` collides only with `u` — and Tagalog and Visayan
 * already treat o~u as near-interchangeable, so a listener re-parses it
 * easily. The dataset supports it too: wo'hig "water" is Tagalog tubig, and
 * so'dà "viand" is ulam. But nobody on this project has heard a fil-PH voice
 * read these aloud, and nobody here speaks Manobo. This constant is the single
 * thing to change if a Manobo speaker says it sounds wrong.
 */
final class ManoboSpeech
{
    /**
     * What the schwa (written `o`) becomes for the voice engine.
     * See the class docblock — this is the one setting needing a speaker's ear.
     */
    private const SCHWA_TARGET = 'u';

    /**
     * Vowel digraphs, longest first so `ae` is never seen as `a` + `e`.
     *
     * `ey` and `iy` are the orthography's long vowels — 'abiy "lip" is noted in
     * the dataset as "a long i sound", not a y-glide, so the glide is dropped
     * rather than spoken.
     */
    private const DIGRAPHS = [
        'ae' => 'a',
        'ue' => 'u',
        'ey' => 'e',
        'iy' => 'i',
    ];

    /** Precomposed accented vowels → [base letter, is a glottal stop]. */
    private const ACCENTS = [
        'à' => ['a', true],  'è' => ['e', true],  'ì' => ['i', true],
        'ò' => ['o', true],  'ù' => ['u', true],
        'á' => ['a', false], 'é' => ['e', false], 'í' => ['i', false],
        'ó' => ['o', false], 'ú' => ['u', false],
        'À' => ['A', true],  'È' => ['E', true],  'Ì' => ['I', true],
        'Ò' => ['O', true],  'Ù' => ['U', true],
        'Á' => ['A', false], 'É' => ['E', false], 'Í' => ['I', false],
        'Ó' => ['O', false], 'Ú' => ['U', false],
    ];

    /** @var array<string,true>|null Mark-insensitive index of dictionary headwords. */
    private static ?array $headwords = null;

    /**
     * Prepare a passage of Manobo for synthesis.
     *
     * Word by word, so the code-switched Surigaonon, the venue and the date
     * that share the sentence are left exactly as they were.
     */
    public static function forSpeech(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }

        // Split on whitespace only, keeping it, so spacing is preserved
        // exactly — the caller's sentence chunks are matched against the page.
        $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        foreach ($parts as $i => $part) {
            if ($part === '' || preg_match('/^\s+$/u', $part)) {
                continue;
            }
            $parts[$i] = self::word($part);
        }

        return implode('', $parts);
    }

    /**
     * Re-spell one token, if it is Manobo at all.
     *
     * Leading and trailing punctuation is peeled off first and put back
     * afterwards, so "wo'hig." keeps its full stop and the sentence still ends.
     */
    public static function word(string $token): string
    {
        // \p{M} keeps combining marks attached to the word. Without it a
        // decomposed "a" + U+0300 splits the grave off as trailing punctuation,
        // and the glottal stop this orthography writes with it is never seen.
        if (!preg_match('/^([^\p{L}\p{N}\p{M}]*)(.*?)([^\p{L}\p{N}\p{M}]*)$/u', $token, $m)) {
            return $token;
        }

        [, $before, $core, $after] = $m;

        // A leading apostrophe is the stress mark, never punctuation: no
        // Filipino or English word begins with one. It has to come off the
        // outside of the token before anything else can recognise the word.
        $before = preg_replace('/[\'\x{2019}]/u', '', $before) ?? $before;

        if ($core === '' || !self::isManobo($core)) {
            return $before . $core . $after;
        }

        return $before . self::respell($core) . $after;
    }

    /**
     * Is this word written in the Manobo orthography?
     *
     * Two kinds of positive evidence, and nothing else counts:
     *
     *   1. It carries a mark this orthography uses and Filipino does not — an
     *      interior apostrophe, a grave accent, or an `ae`/`ue` digraph.
     *   2. The barangay's own dictionary has it, with or without its marks.
     *      This is the 39-entry dataset doing the job it can actually do:
     *      grounding, not translating.
     *
     * A hyphen alone is NOT evidence: Filipino writes "mag-aral" the same way.
     */
    public static function isManobo(string $word): bool
    {
        if (preg_match('/[\p{L}][\'\x{2019}]|^[\'\x{2019}]/u', $word)) {
            return true;
        }
        if (preg_match('/[àèìòùÀÈÌÒÙ]|\x{0300}/u', $word)) {
            return true;
        }
        if (preg_match('/(ae|ue)/iu', $word)) {
            return true;
        }

        return isset(self::headwords()[self::fold($word)]);
    }

    /**
     * Apply the marks and the vowels. Assumes the word is already known Manobo.
     */
    public static function respell(string $word): string
    {
        // 1. Accents. Grave marks a glottal stop; Filipino orthography cannot
        //    write one and no fil-PH voice can produce one on demand, so the
        //    letter drops to its base and the contrast is knowingly lost.
        //    An acute accent is not meaningful in this orthography (it appears
        //    only on a Spanish loan) and simply drops too.
        $word = self::stripAccents($word);

        // 2. Stress apostrophes. Meaningful in writing, unusable in plain-text
        //    synthesis — an engine reads them as quotation marks.
        $word = preg_replace('/[\'\x{2019}]/u', '', $word) ?? $word;

        // 3. A dash marks a medial glottal stop (agid-id) and Filipino spells
        //    that the same way (mag-asawa), so it stays — but never trailing,
        //    where it would be read as a pause instead of a consonant.
        $word = preg_replace('/-+$/u', '', $word) ?? $word;

        // 4. Vowels. Digraphs first, so `ae` is never seen as `a` then `e`.
        foreach (self::DIGRAPHS as $from => $to) {
            $word = self::replaceKeepingCase($word, $from, $to);
        }

        // 5. The schwa. Written `o`, and it is the single most common way a
        //    Filipino voice mispronounces this language.
        $word = self::replaceKeepingCase($word, 'o', self::SCHWA_TARGET);

        return $word;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** Replace case-insensitively while keeping the original's capitalisation. */
    private static function replaceKeepingCase(string $word, string $from, string $to): string
    {
        return (string) preg_replace_callback(
            '/' . preg_quote($from, '/') . '/iu',
            static function (array $m) use ($to): string {
                $first = mb_substr($m[0], 0, 1);
                return $first === mb_strtoupper($first, 'UTF-8')
                    ? mb_strtoupper($to, 'UTF-8')
                    : $to;
            },
            $word
        );
    }

    /** Drop grave and acute accents to their base letters. */
    private static function stripAccents(string $word): string
    {
        // Combining marks first, so a decomposed "a" + U+0300 is handled the
        // same as a precomposed "à" — text pasted from Word arrives either way.
        $word = preg_replace('/\x{0300}|\x{0301}/u', '', $word) ?? $word;

        return strtr($word, array_map(
            static fn (array $spec): string => $spec[0],
            self::ACCENTS
        ));
    }

    /**
     * Mark-insensitive form used to match against the dictionary, mirroring
     * how ManoboDictionary itself matches so a word typed plainly still hits.
     */
    private static function fold(string $word): string
    {
        $word = self::stripAccents(mb_strtolower($word, 'UTF-8'));
        $word = preg_replace('/[\'\x{2019}\-]/u', '', $word) ?? $word;

        return $word;
    }

    /**
     * Every dictionary headword, folded, loaded once per request.
     *
     * Failing to read the dataset is not an error here: it only means the
     * second kind of evidence is unavailable, and words carrying marks are
     * still recognised.
     *
     * @return array<string,true>
     */
    private static function headwords(): array
    {
        if (self::$headwords !== null) {
            return self::$headwords;
        }

        self::$headwords = [];

        try {
            foreach ((new ManoboDictionary())->all() as $entry) {
                $headword = self::fold((string) ($entry['manobo'] ?? ''));
                if ($headword !== '') {
                    self::$headwords[$headword] = true;
                }
            }
        } catch (\Throwable $e) {
            error_log('[ManoboSpeech] dictionary unavailable: ' . $e->getMessage());
        }

        return self::$headwords;
    }

    /** Test seam: forget the cached headwords. */
    public static function flushCache(): void
    {
        self::$headwords = null;
    }
}
