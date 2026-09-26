<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Cleans up a caption pasted out of Facebook.
 *
 * This is the tier that always works. No API, no key, no platform
 * cooperation — a staff member selects the caption, copies it, pastes it here.
 * Facebook cannot break it and it costs nothing, which is why it gets the same
 * care as the clever paths rather than being the fallback nobody polished.
 *
 * What actually arrives when you copy from Facebook, and what each rule is for:
 *
 *   "… See more"        the truncation control, copied along with the text
 *   "See translation"   the same, on a post in another language
 *   #Barangay #Bayogo   a block of hashtags at the end, which is a tagging
 *                       convention on Facebook and just noise in a notice —
 *                       so they come out of the body and are offered as tags
 *   soft line breaks    Facebook keeps paragraph breaks as single newlines and
 *                       copying often doubles or triples them
 *   bare URLs           pasted as plain text, and a notice nobody can click is
 *                       a notice with a missing link
 *
 * Everything here is a pure string transform. The result still goes through
 * HTMLPurifier before it is stored, like any other body text.
 */
final class SocialText
{
    /** Cap on how many hashtags are offered back as tags. */
    private const MAX_TAGS = 12;

    /**
     * Clean one pasted caption.
     *
     * @return array{
     *     text:string, html:string, tags:list<string>,
     *     removed:list<string>, links:list<string>
     * }  `text` is the cleaned plain text, `html` the same with paragraphs and
     *    links, ready for the Quill editor. `removed` names what was stripped,
     *    so the panel can say what it did instead of silently rewriting.
     */
    public static function clean(string $raw): array
    {
        $removed = [];

        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = self::stripInvisible($text);

        $before = $text;
        $text   = self::unstyleUnicode($text);
        if ($text !== $before) {
            $removed[] = 'styled_unicode';
        }

        // 1. Interface artefacts copied along with the caption.
        $before = $text;
        // [ \t]* rather than \s* on the tail: "See more" sits at the end of a
        // line, and a \s* would swallow the newline after it and weld the next
        // line on. That merge then hid the like/comment counters from the rule
        // below, which only matches a counter alone on its own line.
        $text = preg_replace(
            '/(?:\.{3}|…)?[ \t]*\b(?:See more|See More|Показать ещё|Tingnan pa|See translation|See Translation|Translated from [A-Za-z]+)\b[ \t]*/u',
            ' ',
            $text
        ) ?? $text;
        if ($text !== $before) {
            $removed[] = 'see_more';
        }

        // Reaction/comment counters that come with a full-post copy.
        $before = $text;
        // The trailing newline goes with it. Removing only the text would
        // leave a blank line where the counter was, and a blank line is a
        // paragraph break — so a deleted "248 likes" would still split the
        // caption in two.
        $text = preg_replace(
            '/^[ \t]*\d[\d,.]*[ \t]*(?:likes?|comments?|shares?|reactions?|views?)[ \t]*(?:\n|$)/im',
            '',
            $text
        ) ?? $text;
        if ($text !== $before) {
            $removed[] = 'counters';
        }

        // 2. Trailing hashtag block → tags.
        [$text, $tags] = self::liftTrailingHashtags($text);
        if ($tags !== []) {
            $removed[] = 'hashtags';
        }

        // 3. Whitespace. Facebook's copy tends to arrive with runs of blank
        //    lines; a barangay notice wants paragraphs, not gaps.
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        $links = self::findLinks($text);

        return [
            'text'    => $text,
            'html'    => self::toHtml($text),
            'tags'    => $tags,
            'removed' => $removed,
            'links'   => $links,
        ];
    }

    /**
     * Turn cleaned text into the HTML the Quill editor expects.
     *
     * Escaped first, then the few tags we want are added — never the other way
     * round. The input is something a stranger wrote on Facebook.
     */
    public static function toHtml(string $text): string
    {
        $paragraphs = preg_split('/\n{2,}/u', trim($text)) ?: [];
        $out        = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $safe = e($paragraph);
            $safe = self::linkify($safe);
            $safe = nl2br($safe, false);

            $out[] = '<p>' . $safe . '</p>';
        }

        return implode("\n", $out);
    }

    /**
     * Hashtags at the END of a caption are tagging; hashtags inside a sentence
     * are part of the sentence ("join the #BrigadaEskwela cleanup"). Only the
     * trailing block is lifted, which is the difference between tidying a post
     * and rewriting somebody's words.
     *
     * @return array{0:string, 1:list<string>}
     */
    public static function liftTrailingHashtags(string $text): array
    {
        $tags = [];

        // Walk backwards over trailing lines that are nothing but hashtags.
        $lines = explode("\n", rtrim($text));
        while ($lines !== []) {
            $last = trim((string) end($lines));

            if ($last === '') {
                array_pop($lines);
                continue;
            }
            if (!preg_match('/^(?:#[\p{L}\p{N}_]+[\s,]*)+$/u', $last)) {
                break;
            }

            preg_match_all('/#([\p{L}\p{N}_]+)/u', $last, $found);
            $tags = array_merge($found[1], $tags);
            array_pop($lines);
        }

        // Also the common one-liner: caption text then hashtags on the same
        // final line, e.g. "Salamat po! #Bayogo #Barangay".
        if ($lines !== []) {
            $last = (string) end($lines);
            if (preg_match('/^(.*?\S)\s+((?:#[\p{L}\p{N}_]+[\s,]*){2,})$/u', $last, $m)) {
                preg_match_all('/#([\p{L}\p{N}_]+)/u', $m[2], $found);
                $tags        = array_merge($found[1], $tags);
                $lines[array_key_last($lines)] = $m[1];
            }
        }

        $tags = array_values(array_unique(array_map(
            static fn (string $tag): string => mb_substr(trim($tag), 0, 40),
            $tags
        )));

        return [implode("\n", $lines), array_slice($tags, 0, self::MAX_TAGS)];
    }

    /**
     * Bare URLs in the text, in the order they appear.
     *
     * @return list<string>
     */
    public static function findLinks(string $text): array
    {
        preg_match_all('#\bhttps?://[^\s<>"\']+#i', $text, $m);

        return array_values(array_unique(array_map(
            static fn (string $url): string => rtrim($url, '.,;:!?)'),
            $m[0]
        )));
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Link up bare URLs in text that has ALREADY been escaped.
     *
     * Operating on escaped text is deliberate: the URL cannot contain a quote
     * or an angle bracket by the time it gets here, so the anchor it becomes
     * cannot break out of its own attribute.
     */
    private static function linkify(string $escaped): string
    {
        return (string) preg_replace(
            '#\b(https?://[^\s<]+?)(?=[.,;:!?)]*(?:\s|$))#i',
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow">$1</a>',
            $escaped
        );
    }

    /**
     * Strip the zero-width and directional characters social platforms sprinkle
     * through copied text. They are invisible, they survive a paste, and they
     * quietly break every pattern below if left in.
     */
    /**
     * Fold "𝐟𝐚𝐧𝐜𝐲 𝐭𝐞𝐱𝐭" back to ordinary letters.
     *
     * Facebook captions are full of the Unicode mathematical alphanumerics —
     * "𝐂𝐞𝐥𝐞𝐛𝐫𝐚𝐭𝐢𝐧𝐠" is the bold block at U+1D400, not the letters C-e-l-e-b.
     * People use it because Facebook has no bold button. It looks like text
     * and behaves like nothing:
     *
     *   - the slug generator saw no letters at all and produced an empty slug,
     *     so the post's link was dead (fixed separately, and it should never
     *     have had to be);
     *   - searching for "Celebrating" does not match it;
     *   - the translation service answers HTTP 504 for it and translates the
     *     very same sentence perfectly once folded;
     *   - screen readers and the voice reader skip it entirely, which on a
     *     barangay notice is the whole point of the post lost.
     *
     * So it is folded once, here, as the caption comes in — not patched around
     * at each of the four places it breaks. iconv's transliteration does the
     * mapping; it is the same one generate_slug() relies on. The visual bold
     * is lost, which is the correct trade: Quill has real bold, and a notice
     * that can be read matters more than one that is styled.
     */
    public static function unstyleUnicode(string $text): string
    {
        // Cheap guard: the mathematical alphanumerics live in one contiguous
        // block, so most captions can skip the conversion entirely.
        if (preg_match('/[\x{1D400}-\x{1D7FF}\x{FF21}-\x{FF5A}]/u', $text) !== 1) {
            return $text;
        }

        $out = '';

        // Character by character, so only the styled runs are touched and
        // every accented letter, curly quote and emoji elsewhere in the
        // caption survives exactly as written.
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $code = mb_ord($char, 'UTF-8');

            if ($code === false
                || !(($code >= 0x1D400 && $code <= 0x1D7FF) || ($code >= 0xFF21 && $code <= 0xFF5A))) {
                $out .= $char;
                continue;
            }

            $plain = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $char);

            // A styled character with no ASCII equivalent is dropped rather
            // than left in place: leaving it would defeat the whole point.
            $out .= is_string($plain) ? $plain : '';
        }

        return $out;
    }

    private static function stripInvisible(string $text): string
    {
        return (string) preg_replace(
            '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u',
            '',
            $text
        );
    }
}
