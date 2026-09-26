<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Whole-text Manobo auto-translate for the resident-facing "MN" button.
 *
 * Distinct from ManoboDictionary::translate(), which only tries the ENTIRE
 * input as one phrase and then falls through to single words. This class
 * segments an arbitrary block of text (a whole announcement, or whatever a
 * resident typed) into a run of phrase/word matches, longest phrase first,
 * so a multi-word saying is translated as one saying instead of being
 * shredded into single-word glosses next to it.
 *
 * Matching rules (all case-insensitive, punctuation-insensitive):
 *   1. Longest matching PHRASE first, scanning left to right — "Magandang
 *      umaga" becomes "Madjow no masim" as one unit before "umaga" alone
 *      ever gets a chance to match something else.
 *   2. A dictionary value listing alternatives with "," or "/" — "Sandali /
 *      Maghintay", "one, two" — matches EITHER alternative on its own.
 *   3. Falls to single words when no longer phrase matches.
 *   4. FALLBACK: a word/phrase missing from Manobo is looked up in the
 *      Bisaya dictionary before being given up on.
 *   5. Still not found anywhere: the original word is kept, unchanged.
 *
 * Original punctuation and capitalisation are preserved in the output —
 * capitalisation via manobo_match_case() (already used by the UI-chrome
 * gloss functions below), punctuation and spacing by leaving every
 * non-word span exactly as it was.
 *
 * Both dictionaries are already cached in-process by ManoboDictionary /
 * BisayaDictionary (see their $cache statics); this class builds its own
 * phrase-lookup map from them once per request and reuses it for every call.
 */
final class ManoboAutoTranslator
{
    private const MAX_PHRASE_WORDS = 8;

    /** @var array<string,string>|null normalised phrase => manobo headword */
    private static ?array $manoboPhrases = null;

    /** @var array<string,string>|null normalised phrase => bisaya headword */
    private static ?array $bisayaPhrases = null;

    /**
     * Translate a block of text, tagging each piece with where it came from.
     *
     * @return array{
     *     success: bool,
     *     segments: list<array{text:string, display:string, source:string}>,
     *     matched_manobo: int,
     *     matched_bisaya: int,
     *     unmatched: int
     * }
     */
    public function translateBlock(string $text): array
    {
        if (trim($text) === '') {
            return ['success' => false, 'segments' => [], 'matched_manobo' => 0, 'matched_bisaya' => 0, 'unmatched' => 0];
        }

        $manoboPhrases = self::manoboPhrases();
        $bisayaPhrases = self::bisayaPhrases();

        // Split into words vs. everything between them (spaces, punctuation),
        // keeping every delimiter so the output can be reassembled exactly.
        // Apostrophes stay attached to a word (contractions, Manobo glottal
        // marks); everything else that is not a letter/number splits it.
        $parts = preg_split('/([^\p{L}\p{N}\x27\x{2019}]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        // Word tokens live at even indexes, delimiters at odd indexes — pull
        // the words into their own list so longest-match can look ahead
        // across them without caring about the punctuation between.
        $words = [];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0 && $part !== '') {
                $words[] = $part;
            }
        }

        $segments      = [];
        $matchedManobo = 0;
        $matchedBisaya = 0;
        $unmatched     = 0;

        $wi = 0;      // index into $words
        $pi = 0;      // index into $parts
        $total = \count($words);

        while ($pi < \count($parts)) {
            if ($pi % 2 === 1) {
                // A delimiter — passed through untouched.
                if ($parts[$pi] !== '') {
                    $segments[] = ['text' => $parts[$pi], 'display' => $parts[$pi], 'source' => 'text'];
                }
                $pi++;
                continue;
            }
            if ($parts[$pi] === '') {
                $pi++;
                continue;
            }

            // Longest phrase first: try the biggest window of remaining
            // words, shrinking until something matches or only one is left.
            $maxLen = min(self::MAX_PHRASE_WORDS, $total - $wi);
            $found  = false;

            for ($len = $maxLen; $len >= 1; $len--) {
                $span = \array_slice($words, $wi, $len);
                $key  = self::normalise(implode(' ', $span));
                if ($key === '') {
                    continue;
                }

                $original = implode(' ', $span);

                if (isset($manoboPhrases[$key])) {
                    $segments[] = [
                        'text'    => $original,
                        'display' => manobo_match_case($original, $manoboPhrases[$key]),
                        'source'  => 'manobo',
                    ];
                    $matchedManobo++;
                    $found = true;
                } elseif (isset($bisayaPhrases[$key])) {
                    $segments[] = [
                        'text'    => $original,
                        'display' => manobo_match_case($original, $bisayaPhrases[$key]),
                        'source'  => 'bisaya',
                    ];
                    $matchedBisaya++;
                    $found = true;
                }

                if ($found) {
                    // Advance past every word/delimiter this span consumed.
                    for ($k = 0; $k < $len; $k++) {
                        $wi++;
                        $pi++;                              // the word itself
                        if ($k < $len - 1 && isset($parts[$pi])) {
                            $pi++;                           // the delimiter between words in the span
                        }
                    }
                    break;
                }
            }

            if (!$found) {
                // Not in either dictionary at any length — keep it, flagged.
                $segments[] = ['text' => $words[$wi], 'display' => $words[$wi], 'source' => 'none'];
                $unmatched++;
                $wi++;
                $pi++;
            }
        }

        return [
            'success'        => true,
            'segments'       => $segments,
            'matched_manobo' => $matchedManobo,
            'matched_bisaya' => $matchedBisaya,
            'unmatched'      => $unmatched,
        ];
    }

    /** Render translateBlock()'s segments back into one plain string. */
    public function render(array $result): string
    {
        $out = '';
        foreach ($result['segments'] as $segment) {
            $out .= $segment['display'];
        }

        return $out;
    }

    /** Drop the cached phrase maps — call after either dictionary changes. */
    public static function flushCache(): void
    {
        self::$manoboPhrases = null;
        self::$bisayaPhrases = null;
    }

    // ── Internals ────────────────────────────────────────────────────────

    /** @return array<string,string> */
    private static function manoboPhrases(): array
    {
        if (self::$manoboPhrases !== null) {
            return self::$manoboPhrases;
        }

        self::$manoboPhrases = [];
        try {
            foreach ((new ManoboDictionary())->all() as $entry) {
                self::indexEntry(self::$manoboPhrases, $entry, $entry['manobo'] ?? '');
            }
        } catch (\Throwable $e) {
            error_log('[ManoboAutoTranslator] manobo dataset unavailable: ' . $e->getMessage());
        }

        return self::$manoboPhrases;
    }

    /** @return array<string,string> */
    private static function bisayaPhrases(): array
    {
        if (self::$bisayaPhrases !== null) {
            return self::$bisayaPhrases;
        }

        self::$bisayaPhrases = [];
        try {
            foreach ((new BisayaDictionary())->all() as $entry) {
                self::indexEntry(self::$bisayaPhrases, $entry, $entry['bisaya'] ?? '');
            }
        } catch (\Throwable $e) {
            error_log('[ManoboAutoTranslator] bisaya dataset unavailable: ' . $e->getMessage());
        }

        return self::$bisayaPhrases;
    }

    /**
     * Index one dictionary entry's tagalog + english glosses, splitting on
     * "," and "/" so an alternative like "Sandali / Maghintay" answers to
     * either "sandali" or "maghintay" on its own — rule (c) of the matching
     * spec. First entry wins on a collision, same convention as the rest of
     * this codebase's dictionary maps.
     *
     * @param array<string,string> $map
     * @param array<string,string> $entry
     */
    private static function indexEntry(array &$map, array $entry, string $headword): void
    {
        $headword = trim($headword);
        if ($headword === '') {
            return;
        }

        foreach (['tagalog', 'english'] as $field) {
            $value = (string) ($entry[$field] ?? '');
            foreach (preg_split('~[,/]~u', $value) ?: [] as $sense) {
                $key = self::normalise($sense);
                if ($key !== '') {
                    $map[$key] ??= $headword;
                }
            }
        }
    }

    /** Case-insensitive, punctuation-and-space-insensitive match key. */
    private static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/\s*\([^)]*\)/u', '', $text) ?? $text;   // drop "(asides)"
        $text = trim($text, " \t\n\r\0\x0B.,;:!?\"'“”()[]");
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }
}
