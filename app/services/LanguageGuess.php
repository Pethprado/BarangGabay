<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Is this text Filipino or English?
 *
 * Exists because the whole translation pipeline hangs off one answer. A post
 * is translated INTO the languages it was not written in, so getting the
 * source wrong does not produce a bad translation — it produces none at all,
 * silently. An English post recorded as Filipino is translated English→English
 * and never gets a Filipino version, so a resident who taps FIL is shown the
 * English original under a Filipino badge with nothing to explain it. That was
 * the reported bug, and it came from source_lang defaulting to 'fil' for a
 * caption imported from an English Facebook post.
 *
 * Deliberately a function-word count, not a language model:
 *
 *   - The markers are closed-class words — articles, markers, pronouns,
 *     conjunctions. Nobody writes a barangay notice without them, and they do
 *     not drift the way vocabulary does.
 *   - It is honest about not knowing. A two-word title, or a Taglish notice
 *     that genuinely mixes both, returns low confidence, and callers keep
 *     whatever the staff member chose rather than overriding it.
 *   - Nothing here reaches the network. It runs on every save, and a save must
 *     not wait on a third party to decide what language it is in.
 *
 * It only ever chooses between Filipino and English. Manobo is not a candidate:
 * no post is authored in it here, and Manobo text is produced from the barangay
 * dictionary rather than detected. See TranslationService.
 */
final class LanguageGuess
{
    /**
     * Below this, the guess is not worth acting on.
     *
     * Set from the shape of real barangay notices: a genuinely bilingual one
     * lands near 0.5 and must NOT flip a staff member's explicit choice, while
     * a wholly English or wholly Filipino notice clears 0.75 easily.
     */
    public const MIN_CONFIDENCE = 0.62;

    /**
     * Tagalog/Filipino function words. Weighted where a word is unambiguous:
     * "at" and "sa" also occur in English text, "ang" and "mga" never do.
     *
     * @var array<string,int>
     */
    private const FIL_MARKERS = [
        'ang' => 3, 'mga' => 3, 'ng' => 3, 'nang' => 2, 'sa' => 1, 'ay' => 2,
        'ni' => 2, 'si' => 2, 'kay' => 2, 'para' => 1, 'dahil' => 2, 'upang' => 2,
        'kung' => 2, 'hindi' => 3, 'may' => 1, 'mula' => 2, 'tungkol' => 3,
        'ito' => 2, 'iyon' => 2, 'ating' => 3, 'natin' => 3, 'namin' => 3,
        'ninyo' => 3, 'kayo' => 2, 'tayo' => 2, 'ako' => 2, 'niya' => 3,
        'nila' => 3, 'po' => 2, 'lamang' => 3, 'din' => 1, 'rin' => 2,
        'bawat' => 3, 'lahat' => 2, 'isang' => 3, 'pagkatapos' => 3,
        'maaari' => 3, 'dapat' => 2, 'walang' => 3, 'ginanap' => 3,
        'magkaroon' => 3, 'kasama' => 2, 'pati' => 2, 'habang' => 3,
    ];

    /**
     * English function words. Same principle in reverse — "the", "of" and
     * "and" carry the load.
     *
     * @var array<string,int>
     */
    private const EN_MARKERS = [
        'the' => 3, 'of' => 3, 'and' => 2, 'to' => 2, 'in' => 2, 'is' => 2,
        'are' => 2, 'was' => 2, 'were' => 2, 'for' => 2, 'with' => 2,
        'that' => 2, 'this' => 2, 'these' => 2, 'those' => 2, 'we' => 2,
        'our' => 2, 'you' => 1, 'your' => 2, 'will' => 2, 'would' => 2,
        'from' => 2, 'have' => 2, 'has' => 2, 'had' => 2, 'been' => 3,
        'on' => 1, 'at' => 1, 'by' => 2, 'as' => 2, 'it' => 1, 'its' => 2,
        'their' => 2, 'there' => 2, 'which' => 3, 'who' => 2, 'all' => 1,
        'each' => 2, 'every' => 2, 'because' => 3, 'while' => 2,
        'during' => 3, 'through' => 3, 'together' => 2, 'thank' => 1,
    ];

    /**
     * What language is this?
     *
     * @return array{lang:string, confidence:float, fil:int, en:int}
     *         `lang` is 'fil' or 'en'; `confidence` runs 0.5 (a tie) to 1.0.
     *         An empty or wordless string returns Filipino at 0.0 — no signal,
     *         so the caller falls back to its own default.
     */
    public static function detect(string $text): array
    {
        $words = self::words($text);

        $fil = 0;
        $en  = 0;

        foreach ($words as $word) {
            $fil += self::FIL_MARKERS[$word] ?? 0;
            $en  += self::EN_MARKERS[$word]  ?? 0;
        }

        $total = $fil + $en;

        if ($total === 0) {
            return ['lang' => 'fil', 'confidence' => 0.0, 'fil' => 0, 'en' => 0];
        }

        $lang  = $fil >= $en ? 'fil' : 'en';
        $share = max($fil, $en) / $total;

        return ['lang' => $lang, 'confidence' => round($share, 3), 'fil' => $fil, 'en' => $en];
    }

    /**
     * The language to use, or null when the text does not say clearly enough.
     *
     * Callers treat null as "keep what you already had". Returning a coin-flip
     * would be worse than returning nothing: it would silently overwrite a
     * choice a staff member made on purpose.
     */
    public static function detectOrNull(string $text): ?string
    {
        $guess = self::detect($text);

        return $guess['confidence'] >= self::MIN_CONFIDENCE ? $guess['lang'] : null;
    }

    /**
     * Words, lowercased, with the decoration stripped.
     *
     * Two passes matter here and both came from real content:
     *
     *   - HTML is removed, because bodies arrive as Quill markup and a tag
     *     soup of <p> and <strong> is not evidence of any language.
     *   - Styled Unicode is folded to ASCII. Facebook captions are full of the
     *     mathematical-bold block ("𝐂𝐞𝐥𝐞𝐛𝐫𝐚𝐭𝐢𝐧𝐠" is U+1D400-and-friends, not
     *     the letters C-e-l-e-b), and without this every marker in such a post
     *     goes uncounted — which is exactly the post that was misfiled as
     *     Filipino.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($ascii) && trim($ascii) !== '') {
            $text = $ascii;
        }

        $text = mb_strtolower($text, 'UTF-8');

        return preg_split('/[^a-z]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
