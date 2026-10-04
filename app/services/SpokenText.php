<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Turns a post into something a speech engine can read out loud.
 *
 * This is the half of the voice reader that must live on the server, for three
 * reasons:
 *
 *   1. The body of an announcement is Quill HTML. Tags have to come off before
 *      anything speaks them, and sentence boundaries have to survive that.
 *   2. Abbreviations have to be expanded. A Filipino voice reading "Brgy."
 *      literally says "brigy", and "No. 2026-014" comes out as the word "no"
 *      followed by a subtraction. Both make a barangay notice sound broken.
 *   3. Chrome's speech engine cuts out after roughly fifteen seconds on a long
 *      utterance. The fix is to queue many short utterances instead of one long
 *      one, so the text has to be split into speakable pieces somewhere.
 *
 * Every piece is returned twice:
 *
 *   'say'  — expanded, emoji-free, what the voice actually pronounces
 *   'find' — the text exactly as it appears on the page, so the player can
 *            locate and highlight the sentence it is currently reading
 *
 * They are separate because the two jobs disagree: the listener wants to hear
 * "Barangay", and the reader watching the screen wants the word "Brgy." lit up
 * where it actually sits.
 *
 * Nothing here calls an API or touches the database. It is pure string work,
 * which is also what makes it cheap enough to run on every page render.
 */
final class SpokenText
{
    /**
     * Ceiling on a single reading, in characters of spoken text.
     *
     * Roughly twenty-five minutes at a normal rate — far longer than any
     * barangay notice, so hitting it means something is wrong (a PDF pasted
     * into a body field, say). Anything past it is dropped and the caller is
     * told, rather than the resident being handed an endless recital.
     */
    public const MAX_CHARS = 6000;

    /**
     * Longest single utterance handed to the speech engine.
     *
     * Short enough that Chrome's ~15 s cutout cannot land inside one, which is
     * the whole point: a chunk that dies mid-sentence is worse than no button.
     */
    private const CHUNK_MAX = 180;

    /**
     * Abbreviations whose full stop is part of the word, not the end of a
     * sentence. Without this list "Brgy. Bayogo" splits into two sentences and
     * the reader pauses in the middle of a place name.
     */
    private const ABBREVIATIONS = [
        'Brgy', 'Bgy', 'Hon', 'Atty', 'Engr', 'Gng', 'Kap', 'Dr', 'Mr', 'Mrs',
        'Ms', 'No', 'Nos', 'Blg', 'Sec', 'Secs', 'Art', 'Sr', 'Jr', 'Prof',
        'Rev', 'etc', 'vs', 'Blk', 'Ave', 'Rd', 'Inc',
    ];

    /** Sentinel standing in for a full stop that must not end a sentence. */
    private const DOT = "\x01";

    /**
     * Build the spoken script for one post.
     *
     * The order of the parts is deliberate and is the caller's decision: a
     * listener needs to know what this is and whether it is urgent before the
     * prose starts, and for an event they need the date, time and venue early
     * rather than four paragraphs in.
     *
     * @param array{
     *     title?:string,
     *     lead?:list<string>,
     *     body?:string,
     *     body_is_html?:bool,
     *     tail?:list<string>,
     *     locale?:string,
     *     label?:string
     * } $parts  'lead' is spoken straight after the title, 'tail' at the very
     *           end; 'label' only names the post in the truncation log line.
     * @return array{
     *     chunks:list<array{say:string,find:string|null,kind:string}>,
     *     chars:int,
     *     truncated:bool
     * }
     */
    public static function script(array $parts): array
    {
        $locale    = (string) ($parts['locale'] ?? 'fil');
        $chunks    = [];
        $chars     = 0;
        $truncated = false;

        /**
         * Append one part of the post, split into speakable pieces.
         *
         * $highlight is false for everything the player cannot point at on
         * screen — the title, the spoken date line, the closing note. Those are
         * things the reader composed, not text sitting in the article body.
         */
        $push = static function (string $raw, string $kind, bool $highlight) use (
            &$chunks, &$chars, &$truncated, $locale
        ): void {
            if ($truncated) {
                return;
            }

            foreach (self::splitForSpeech($raw) as $piece) {
                $say = self::expand($piece, $locale);
                if ($say === '' || !preg_match('/[\p{L}\p{N}]/u', $say)) {
                    continue;                     // punctuation or a stray emoji
                }

                if ($chars + mb_strlen($say) > self::MAX_CHARS) {
                    $truncated = true;
                    return;
                }

                $chars   += mb_strlen($say);
                $chunks[] = [
                    'say'  => $say,
                    'find' => $highlight ? $piece : null,
                    'kind' => $kind,
                ];
            }
        };

        $title = trim((string) ($parts['title'] ?? ''));
        if ($title !== '') {
            $push($title, 'title', false);
        }

        foreach (($parts['lead'] ?? []) as $line) {
            $push((string) $line, 'lead', false);
        }

        $body = (string) ($parts['body'] ?? '');
        if ($body !== '') {
            $body = ($parts['body_is_html'] ?? true) ? self::plain($body) : self::normalise($body);
            $push($body, 'body', true);
        }

        foreach (($parts['tail'] ?? []) as $line) {
            $push((string) $line, 'tail', false);
        }

        if ($truncated) {
            // Worth knowing about: a post this long is almost always a paste
            // accident, and the resident heard only part of it.
            error_log(\sprintf(
                '[SpokenText] reading capped at %d characters for %s',
                self::MAX_CHARS,
                (string) ($parts['label'] ?? 'unknown post')
            ));
        }

        return ['chunks' => $chunks, 'chars' => $chars, 'truncated' => $truncated];
    }

    /**
     * The BCP-47 tag a speech engine should be asked for.
     *
     * Manobo maps to Filipino, and that is a compromise made with open eyes.
     * No provider anywhere offers an Agusan Manobo voice. What a fil-PH voice
     * actually does with Manobo text: the orthography is Latin and largely
     * phonemic and the two languages share most of their vowel and consonant
     * inventory, so the output is broadly intelligible — but the schwa written
     * <e> comes out as Filipino /ɛ/, stress and phrasing follow Filipino
     * rules, and glottal stops land in the wrong places. A Manobo speaker will
     * follow most of it and will also hear immediately that it is not one of
     * them speaking.
     *
     * So this is an approximation, never a Manobo voice, and everything that
     * plays it must say so — see isApproximateVoice(), which callers use to
     * label it. A recording by a person always outranks it.
     */
    public static function speechLang(string $locale): ?string
    {
        return match ($locale) {
            'en'    => 'en-PH',
            'fil'   => 'fil-PH',
            'msm'   => 'fil-PH',   // approximation — must be labelled as one
            default => null,
        };
    }

    /**
     * Whether a synthesised voice for this language is an approximation
     * borrowed from another language rather than the language itself.
     *
     * True only for Manobo. The UI turns this into a visible label beside the
     * play button, in all three languages — not a footnote.
     */
    public static function isApproximateVoice(string $locale): bool
    {
        return $locale === 'msm';
    }

    /**
     * Voice tags to try, best first, for a given locale.
     *
     * Android ships fil-PH; iOS still labels the same voice tl-PH; some desktop
     * builds have neither. The player walks this list and, if it reaches the
     * end with nothing, says so rather than reading Tagalog in an American
     * accent and sounding like nonsense.
     *
     * @return list<string>
     */
    public static function voiceCandidates(string $locale): array
    {
        return match ($locale) {
            'en'    => ['en-PH', 'en-US', 'en-GB', 'en-AU', 'en'],
            'fil'   => ['fil-PH', 'tl-PH', 'fil', 'tl'],
            // Manobo speech is code-switched with Bisaya/Surigaonon (see
            // ManoboAutoTranslator's Bisaya fallback), and a Cebuano voice
            // pronounces that half correctly where a fil-PH voice does not.
            // Still never English — fil-PH remains the fallback, exactly as
            // before, for a device with no Cebuano voice installed.
            'msm'   => ['ceb-PH', 'ceb', 'fil-PH', 'tl-PH', 'fil', 'tl'],
            default => [],
        };
    }

    /**
     * Quill HTML → plain text with its sentence boundaries intact.
     *
     * Block ends become newlines before the tags come off, so a bulleted list
     * is read as separate items instead of one run-on sentence.
     */
    public static function plain(string $html): string
    {
        $text = preg_replace('/<br\b[^>]*>/iu', "\n", $html) ?? $html;
        $text = preg_replace('/<\/(p|div|li|h[1-6]|tr|blockquote|section)\s*>/iu', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::normalise($text);
    }

    /**
     * Put back spaces lost when text was saved with its HTML stripped bare
     * ("Surigao del Sur.Intawa", "ngayonLahat", "2.Mga"): a sentence mark
     * glued to the next capitalised word, and a lowercase word glued to a
     * capitalised one. Decimals ("1.5") and times ("8:00") are untouched
     * because a digit, not a capital, follows the mark.
     */
    public static function repairJoins(string $text): string
    {
        $text = preg_replace('/([.!?;:])(?=\p{Lu}\p{Ll})/u', '$1 ', $text) ?? $text;
        $text = preg_replace('/(\p{Ll})(?=\p{Lu}\p{Ll})/u', '$1 ', $text) ?? $text;
        return $text;
    }

    /**
     * Collapse whitespace the way a browser does when it lays text out.
     *
     * This matters beyond tidiness: the player matches 'find' against the text
     * it reads out of the DOM, and the DOM's own whitespace is collapsed. The
     * two normalisations have to agree or nothing would ever highlight.
     */
    public static function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Non-breaking spaces, plain. Quill emits &nbsp; constantly, and PCRE's
        // \s does not match U+00A0 while JavaScript's does — leave one in and
        // the sentence the player is looking for on the page differs from the
        // sentence it was given by exactly one invisible character, and the
        // highlight silently never appears.
        $text = str_replace(["\u{00A0}", "\u{202F}", "\u{2007}"], ' ', $text);

        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;   // horizontal runs → one space
        $text = preg_replace('/ *\n[ \n]*/u', "\n", $text) ?? $text; // blank lines → one break

        return trim($text);
    }

    /**
     * Split text into sentences, keeping each one exactly as it appears.
     *
     * @return list<string>
     */
    public static function sentences(string $text): array
    {
        $out = [];

        foreach (preg_split('/\n+/u', self::normalise($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Hide the full stops that belong to a word, split, then put them
            // back — PCRE lookbehind is fixed-width, so an "unless it is an
            // abbreviation" rule cannot be expressed in the split pattern.
            $guarded = self::protectDots($line);
            $pieces  = preg_split(
                '/(?:(?<=[.!?…])|(?<=[.!?…]["\')\]]))(?=\s)/u',
                $guarded
            ) ?: [$guarded];

            foreach ($pieces as $piece) {
                $piece = trim(str_replace(self::DOT, '.', $piece));
                if ($piece !== '') {
                    $out[] = $piece;
                }
            }
        }

        return $out;
    }

    /**
     * Sentences, with any over-long one broken at a clause boundary.
     *
     * Every piece returned is still a verbatim run of the normalised source, so
     * the player can find it on the page character for character.
     *
     * @return list<string>
     */
    public static function splitForSpeech(string $text): array
    {
        $out = [];
        foreach (self::sentences($text) as $sentence) {
            foreach (self::softWrap($sentence, self::CHUNK_MAX) as $piece) {
                $out[] = $piece;
            }
        }

        return $out;
    }

    /**
     * Expand what a speech engine would otherwise mangle.
     *
     * Two different treatments, chosen by what a resident actually hears at the
     * barangay hall: an acronym with a well-known full name is spoken in full
     * ("BHW" → "Barangay Health Worker"), while one people pronounce as letters
     * keeps that pronunciation, spelled out so the engine gets it right ("SK" →
     * "Es-Key"). Spelling out MDRRMO letter by letter would be accurate and
     * useless; saying "Barangay Health Worker" for BHW is neither.
     *
     * @param string $locale 'en' speaks English forms; everything else
     *                       (Filipino, and Manobo falling back through it)
     *                       speaks Filipino forms.
     */
    public static function expand(string $text, string $locale = 'fil'): string
    {
        $english = $locale === 'en';

        // Emoji are decoration. Some engines announce them by name, which turns
        // "📢 Anunsyo" into "loudspeaker anunsyo".
        $text = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{20E3}]/u',
            ' ',
            $text
        ) ?? $text;

        // A URL read character by character is pure noise on a phone speaker.
        $text = preg_replace('/\bhttps?:\/\/\S+/iu', $english ? 'a link' : 'isang link', $text) ?? $text;
        $text = preg_replace('/\bwww\.\S+/iu', $english ? 'a link' : 'isang link', $text) ?? $text;

        $replacements = [
            // Titles and places
            '/\bBrgy\.?/iu'  => 'Barangay',
            '/\bBgy\.?/iu'   => 'Barangay',
            '/\bHon\./iu'    => $english ? 'Honorable' : 'Honorable',
            '/\bAtty\./iu'   => $english ? 'Attorney' : 'Attorney',
            '/\bEngr\./iu'   => $english ? 'Engineer' : 'Engineer',
            '/\bGng\./iu'    => $english ? 'Madam' : 'Ginang',
            '/\bKap\./iu'    => $english ? 'Captain' : 'Kapitan',
            '/\bDr\./iu'     => $english ? 'Doctor' : 'Doktor',
            '/\bProf\./iu'   => 'Professor',

            // Barangay and municipal bodies, spoken in full
            '/\bBHWs\b/u'    => $english ? 'Barangay Health Workers' : 'Barangay Health Workers',
            '/\bBHW\b/u'     => 'Barangay Health Worker',
            '/\bBNS\b/u'     => 'Barangay Nutrition Scholar',
            '/\bBPSO\b/u'    => 'Barangay Peacekeeping Action Officer',
            '/\bMDRRMO\b/u'  => 'Municipal Disaster Risk Reduction and Management Office',
            '/\bBDRRMC\b/u'  => 'Barangay Disaster Risk Reduction and Management Council',
            '/\bLGU\b/u'     => 'Local Government Unit',
            '/\bDSWD\b/u'    => 'Department of Social Welfare and Development',
            '/\bDOH\b/u'     => 'Department of Health',
            '/\bDepEd\b/u'   => 'Department of Education',
            '/\bPNP\b/u'     => 'Philippine National Police',
            '/\bBFP\b/u'     => 'Bureau of Fire Protection',
            '/\bRHU\b/u'     => 'Rural Health Unit',
            '/\bBLGU\b/u'    => 'Barangay Local Government Unit',

            // Said as letters, so spelled the way they are said
            '/\bSK\b/u'      => 'Es-Key',
            '/\bCCTV\b/u'    => 'See-See-Tee-Vee',
            '/\bID\b/u'      => 'Ay-Dee',
            '/\b4Ps\b/u'     => $english ? 'Four Peas' : 'Four Peas',

            // Document references
            '/\bNos\.\s*(?=\d)/iu' => $english ? 'Numbers ' : 'Numero ',
            '/\bNo\.\s*(?=\d)/iu'  => $english ? 'Number '  : 'Numero ',
            // "Ordinansa Blg. 2026-014" — the Filipino form, and the one that
            // actually appears on a barangay ordinance.
            '/\bBlg\.\s*(?=\d)/iu' => $english ? 'Number '  : 'Bilang ',
            '/\bSecs\.\s*(?=\d)/iu'=> $english ? 'Sections ' : 'Mga Seksyon ',
            '/\bSec\.\s*(?=\d)/iu' => $english ? 'Section '  : 'Seksyon ',
            '/\bArt\.\s*(?=\d|[IVX])/iu' => $english ? 'Article ' : 'Artikulo ',

            // Small words that are read wrong far more often than not
            '/\betc\.?/iu'   => $english ? 'and so on' : 'at iba pa',
            '/\be\.g\./iu'   => $english ? 'for example' : 'halimbawa',
            '/\bi\.e\./iu'   => $english ? 'that is' : 'ibig sabihin',
            '/\bvs\.?/iu'    => $english ? 'versus' : 'laban sa',
            '/&/u'           => $english ? ' and ' : ' at ',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // Money: the peso sign is silent in most voices.
        $text = preg_replace('/₱\s?([\d,]+(?:\.\d+)?)/u', $english ? '$1 pesos' : '$1 piso', $text) ?? $text;
        $text = preg_replace('/\bPHP\s?([\d,]+(?:\.\d+)?)/iu', $english ? '$1 pesos' : '$1 piso', $text) ?? $text;

        $text = self::expandTimes($text, $english);

        // "Ordinance No. 2026-014" — the hyphen is read as a minus sign by most
        // engines. A comma gives the pause a person would make there instead.
        $text = preg_replace('/\b(\d{4})-(\d{1,4})\b/u', '$1, $2', $text) ?? $text;

        /*
         * Manobo needs one more pass than the others, and it has to be last.
         *
         * Its orthography carries stress apostrophes and glottal-stop accents
         * that a speech engine reads as punctuation, and it writes `o` for a
         * schwa — so a fil-PH voice mispronounces every one of them. This
         * re-spells those words phonetically, and only those words: the
         * Surigaonon, the venue and the date sharing the sentence are left
         * alone. See ManoboSpeech.
         *
         * Speech only. It is applied here, to `say`, and never to `find` —
         * what is on screen keeps its marks, because the marks distinguish
         * different words ('hilu "thread" is not hi'lu "poison").
         */
        if ($locale === 'msm') {
            $text = ManoboSpeech::forSpeech($text);
        }

        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

        return trim($text);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Clock times, said the way they are said out loud.
     *
     * "8:00 AM" becomes "8 AM" (nobody says "eight zero zero"), a real minute
     * value is kept, and a range gets the joining word so "8:00 AM - 10:00 AM"
     * does not come out as a subtraction.
     */
    private static function expandTimes(string $text, bool $english): string
    {
        $text = preg_replace_callback(
            '/\b(\d{1,2}):([0-5]\d)\s*([AaPp])\.?\s?[Mm]\.?\b/u',
            static function (array $m) {
                $meridiem = strtoupper($m[3]) . 'M';
                return $m[2] === '00'
                    ? $m[1] . ' ' . $meridiem
                    : $m[1] . ':' . $m[2] . ' ' . $meridiem;
            },
            $text
        ) ?? $text;

        return preg_replace(
            '/(\d{1,2}(?::[0-5]\d)?\s?[AP]M)\s*[-–—]\s*(\d{1,2}(?::[0-5]\d)?\s?[AP]M)/u',
            $english ? '$1 to $2' : '$1 hanggang $2',
            $text
        ) ?? $text;
    }

    /** Hide full stops that must not be read as the end of a sentence. */
    private static function protectDots(string $line): string
    {
        // "a.m." / "p.m." first: its second dot would otherwise look like the
        // end of a sentence sitting right before a capital letter.
        $line = preg_replace('/\b([AaPp])\.\s?([Mm])\./u', '$1' . self::DOT . '$2' . self::DOT, $line) ?? $line;

        $line = preg_replace_callback(
            '/\b(' . implode('|', self::ABBREVIATIONS) . ')\./iu',
            static fn (array $m): string => $m[1] . self::DOT,
            $line
        ) ?? $line;

        $line = preg_replace('/(\d)\.(\d)/u', '$1' . self::DOT . '$2', $line) ?? $line;  // 1.5, 10.30
        $line = preg_replace('/\b(\p{Lu})\.(?=\s*\p{Lu})/u', '$1' . self::DOT, $line) ?? $line; // J. Cruz

        return $line;
    }

    /**
     * Break one over-long sentence into pieces the engine can finish.
     *
     * Clause boundaries first, because a listener hears a comma as a pause
     * anyway; word boundaries only as a last resort. Pieces are re-joined with
     * a single space, which is exactly what the normalised source looks like,
     * so each piece stays findable on the page.
     *
     * @return list<string>
     */
    private static function softWrap(string $text, int $max): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= $max) {
            return [$text];
        }

        $merged = [];
        $buffer = '';
        foreach (preg_split('/(?<=[,;:])\s+/u', $text) ?: [$text] as $clause) {
            $candidate = $buffer === '' ? $clause : $buffer . ' ' . $clause;
            if ($buffer !== '' && mb_strlen($candidate) > $max) {
                $merged[] = $buffer;
                $buffer   = $clause;
            } else {
                $buffer = $candidate;
            }
        }
        if ($buffer !== '') {
            $merged[] = $buffer;
        }

        $out = [];
        foreach ($merged as $piece) {
            if (mb_strlen($piece) <= $max) {
                $out[] = $piece;
                continue;
            }

            // One clause longer than the cap: fall back to whole words.
            $buffer = '';
            foreach (preg_split('/\s+/u', $piece) ?: [$piece] as $word) {
                $candidate = $buffer === '' ? $word : $buffer . ' ' . $word;
                if ($buffer !== '' && mb_strlen($candidate) > $max) {
                    $out[]  = $buffer;
                    $buffer = $word;
                } else {
                    $buffer = $candidate;
                }
            }
            if ($buffer !== '') {
                $out[] = $buffer;
            }
        }

        return $out;
    }
}
