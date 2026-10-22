<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

/**
 * Automatic grammar clean-up for what the translator produces.
 *
 * Every machine translation (English, Filipino, Manobo) and every "Draft"
 * button result passes through here before it is stored or shown, so
 * residents read text with its obvious mistakes already fixed.
 *
 * Two layers:
 *   1. Local rules, all three languages, no network: spacing, punctuation,
 *      doubled function words ("ang ang", "the the", "sa sa"), sentence
 *      capitals, English "i" → "I", and the Filipino din/rin, daw/raw,
 *      doon/roon, dito/rito rule.
 *   2. LanguageTool (free public API, no key) for English and Filipino:
 *      grammar, typos and duplicates it is confident about. Manobo has no
 *      grammar checker anywhere, so it gets the local rules only.
 *
 * Conservative on purpose. A wrong "correction" is worse than none, so:
 *   - English spelling is fixed only for ordinary lowercase words a letter or
 *     two away from a real word — never names, places or local terms
 *     (LanguageTool would turn "purok" into "park" and "Bayogo" into "Bayou");
 *   - Filipino spelling suggestions are ignored (they "correct" loanwords
 *     like "hall" into "hal");
 *   - reduplication ("unti unti", "hinay hinay") is left alone; only doubled
 *     articles and particles are removed;
 *   - edits never cross an HTML tag, so formatting is never broken.
 *
 * Never throws: any failure returns the text unchanged (local fixes still
 * apply when LanguageTool is unreachable, which puts it on a short cooldown
 * so saving a post never waits on it twice).
 */
final class GrammarCorrector
{
    public const DEFAULT_URL = 'https://api.languagetool.org/v2/check';

    /** LanguageTool's free API takes 20 KB per request. */
    private const REMOTE_MAX_BYTES = 18000;
    private const COOLDOWN_SECONDS = 300;

    private const REMOTE_LANG = ['en' => 'en-US', 'fil' => 'tl-PH'];

    /** Issue types worth applying automatically (style and regional choices are not). */
    private const REMOTE_TYPES = ['grammar', 'typographical', 'duplication', 'whitespace', 'misspelling'];

    /**
     * Rules whose suggestion is a guess or a matter of taste, or that the
     * local rules do better: sentence capitals (they know "Brgy." is not a
     * sentence end) and din/rin (LanguageTool ignores the KWF exception,
     * "maaari din").
     */
    private const SKIP_RULES = ['ANG_ANG', 'D_WORDS', 'UPPERCASE_SENTENCE_START', 'PUNCTUATION_PARAGRAPH_END',
        'DASH_RULE', 'ARROWS', 'MULTIPLICATION_SIGN'];

    /** Rules outside REMOTE_TYPES that are still safe. */
    private const EXTRA_RULES = [];

    /** Doubled words that are always a mistake (articles and particles, never reduplication). */
    private const FUNCTION_WORDS = [
        'en'  => ['the', 'a', 'an', 'and', 'of', 'to', 'in', 'is', 'are', 'for', 'on', 'at', 'with', 'by', 'from', 'will', 'be'],
        'fil' => ['ang', 'ng', 'sa', 'mga', 'at', 'si', 'ni', 'kay', 'nang', 'para'],
        'msm' => ['ang', 'sa', 'ug', 'og', 'nga', 'si', 'ni', 'kay', 'mga'],
    ];

    /** Words the English spell-checker must never touch. */
    private const LOCAL_TERMS = [
        'barangay', 'barangays', 'brgy', 'purok', 'puroks', 'sitio', 'sitios', 'kagawad', 'kagawads', 'tanod', 'tanods',
        'kapitan', 'punong', 'sangguniang', 'kabataan', 'lupon', 'tagapamayapa', 'bayanihan', 'fiesta', 'cedula',
        'manobo', 'bisaya', 'cebuano', 'tagalog', 'filipino', 'surigaonon', 'bayogo', 'madrid', 'surigao', 'lgu',
        'mdrrmo', 'bdrrmc', 'pagasa', 'phivolcs', 'dswd', 'doh', 'deped', 'philhealth', 'pag-ibig', 'sss', 'gcash',
    ];

    /** Abbreviations after which a full stop does not end the sentence. */
    private const ABBREVIATIONS = [
        'brgy', 'no', 'nos', 'st', 'sto', 'sta', 'mr', 'mrs', 'ms', 'dr', 'engr', 'atty', 'hon', 'gng', 'g', 'bb',
        'e.g', 'i.e', 'etc', 'vs', 'jr', 'sr', 'inc', 'co', 'blk', 'lot', 'ph', 'p', 'pp', 'sec', 'art',
    ];

    /** Block tags read as a paragraph break, so sentence capitals work across them. */
    private const BLOCK_TAG = '/^<\/?(p|div|li|ul|ol|h[1-6]|blockquote|pre|tr|td|th|table|section|article)\b/i';

    private ?Client $client;
    private bool $remoteEnabled;
    private string $url;

    public function __construct(?Client $client = null, ?bool $remoteEnabled = null)
    {
        $this->client = $client;
        $this->url    = (string) (env('LANGUAGETOOL_URL', '') ?: self::DEFAULT_URL);

        // Tests never reach the network unless they hand in a (mocked) client.
        $testing = (string) env('APP_ENV', '') === 'testing';
        $this->remoteEnabled = $remoteEnabled
            ?? ($client !== null || (!$testing && (string) env('GRAMMAR_REMOTE', 'true') !== 'false'));
    }

    /**
     * Correct one text (plain or HTML) in 'en', 'fil' or 'msm'.
     *
     * @return array{text: string, fixes: int}
     */
    public static function fix(string $text, string $lang): array
    {
        return (new self())->correct($text, $lang);
    }

    /**
     * Correct a post's title and body together (one LanguageTool request).
     *
     * @return array{0: string, 1: string, 2: int} [title, body, fixes]
     */
    public static function fixPair(string $lang, string $title, string $body): array
    {
        return (new self())->correctPair($lang, $title, $body);
    }

    /** @return array{text: string, fixes: int} */
    public function correct(string $text, string $lang): array
    {
        [, $body, $fixes] = $this->correctPair($lang, '', $text);
        return ['text' => $body, 'fixes' => $fixes];
    }

    /** @return array{0: string, 1: string, 2: int} */
    public function correctPair(string $lang, string $title, string $body): array
    {
        $lang = self::normaliseLang($lang);
        if ($lang === null || (trim($title) === '' && trim(strip_tags($body)) === '')) {
            return [$title, $body, 0];
        }

        try {
            $pieces = $this->segments($title, $body);

            // Words and spacing first, capitals second: a capital at "ang ang"
            // would otherwise overlap (and block) removing the doubled word.
            $fixes  = $this->apply($pieces, $this->localEdits(self::plain($pieces), $lang));
            $fixes += $this->apply($pieces, $this->capitalEdits(self::plain($pieces), $lang));

            if ($this->remoteEnabled && isset(self::REMOTE_LANG[$lang])) {
                $fixes += $this->apply($pieces, $this->remoteEdits($pieces, $lang));
            }

            [$newTitle, $newBody] = self::join($pieces);

            return [$newTitle, $newBody, $fixes];
        } catch (\Throwable $e) {
            error_log('[GrammarCorrector] ' . $lang . ': ' . $e->getMessage());
            return [$title, $body, 0];
        }
    }

    public static function normaliseLang(string $lang): ?string
    {
        return match (strtolower(trim($lang))) {
            'en', 'en-us', 'english'                      => 'en',
            'fil', 'tl', 'tl-ph', 'filipino', 'tagalog'   => 'fil',
            'msm', 'mn', 'manobo'                         => 'msm',
            default                                       => null,
        };
    }

    // ── Segments ─────────────────────────────────────────────────────────

    /**
     * Split title + body into text pieces and markup pieces.
     *
     * Markup (tags, entities, the title/body divider) carries what it reads as
     * ("as"), so the plain text below has paragraph breaks where the HTML has
     * them — the same plain text LanguageTool checks, offset for offset.
     *
     * @return list<array{kind: string, raw: string, as: string}>
     */
    private function segments(string $title, string $body): array
    {
        $pieces = [];
        foreach ([$title, $body] as $i => $part) {
            if ($i === 1) {
                $pieces[] = ['kind' => 'divider', 'raw' => '', 'as' => "\n\n"];
            }
            if ($part === '') {
                continue;
            }
            if (!preg_match('/<[a-z\/][^>]*>|&[a-z0-9#]+;/i', $part)) {
                $pieces[] = ['kind' => 'text', 'raw' => $part, 'as' => $part];
                continue;
            }
            foreach (preg_split('/(<[^>]*>|&[a-z0-9#]+;)/i', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $chunk) {
                if ($chunk[0] === '<') {
                    $as = preg_match('/^<br\b/i', $chunk) ? "\n" : (preg_match(self::BLOCK_TAG, $chunk) ? "\n\n" : '');
                    $pieces[] = ['kind' => 'markup', 'raw' => $chunk, 'as' => $as];
                } elseif ($chunk[0] === '&' && preg_match('/^&[a-z0-9#]+;$/i', $chunk)) {
                    $pieces[] = ['kind' => 'markup', 'raw' => $chunk, 'as' => html_entity_decode($chunk, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
                } else {
                    $pieces[] = ['kind' => 'text', 'raw' => $chunk, 'as' => $chunk];
                }
            }
        }
        return $pieces;
    }

    /** @param list<array{kind: string, raw: string, as: string}> $pieces */
    private static function plain(array $pieces): string
    {
        return implode('', array_column($pieces, 'as'));
    }

    /**
     * @param list<array{kind: string, raw: string, as: string}> $pieces
     * @return array{0: string, 1: string}
     */
    private static function join(array $pieces): array
    {
        $out = ['', ''];
        $part = 0;
        foreach ($pieces as $p) {
            if ($p['kind'] === 'divider') {
                $part = 1;
                continue;
            }
            $out[$part] .= $p['raw'];
        }
        return $out;
    }

    /**
     * Apply edits (byte offsets into the plain text) to the text pieces.
     *
     * An edit is applied only when it lies inside one text piece; overlapping
     * edits keep the first. Returns how many were applied.
     *
     * @param list<array{kind: string, raw: string, as: string}> $pieces
     * @param list<array{0: int, 1: int, 2: string}> $edits [start, end, replacement]
     */
    private function apply(array &$pieces, array $edits): int
    {
        if ($edits === []) {
            return 0;
        }
        usort($edits, static fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        // Where each text piece sits in the plain text.
        $ranges = [];
        $pos = 0;
        foreach ($pieces as $i => $p) {
            $len = strlen($p['as']);
            if ($p['kind'] === 'text') {
                $ranges[] = [$pos, $pos + $len, $i];
            }
            $pos += $len;
        }

        $byPiece = [];
        $lastEnd = -1;
        foreach ($edits as [$start, $end, $replacement]) {
            if ($start < $lastEnd) {
                continue;   // overlaps an edit already taken
            }
            foreach ($ranges as [$from, $to, $index]) {
                if ($start >= $from && $end <= $to) {
                    $byPiece[$index][] = [$start - $from, $end - $from, $replacement];
                    $lastEnd = max($end, $start + 1);
                    break;
                }
            }
        }

        $applied = 0;
        foreach ($byPiece as $index => $list) {
            $text = $pieces[$index]['raw'];
            foreach (array_reverse($list) as [$s, $e, $r]) {
                if (substr($text, $s, $e - $s) === $r) {
                    continue;
                }
                $text = substr($text, 0, $s) . $r . substr($text, $e);
                $applied++;
            }
            $pieces[$index]['raw'] = $text;
            $pieces[$index]['as']  = $text;
        }

        return $applied;
    }

    // ── Local rules ──────────────────────────────────────────────────────

    /**
     * @return list<array{0: int, 1: int, 2: string}>
     */
    private function localEdits(string $plain, string $lang): array
    {
        $edits = [];
        $add = static function (string $pattern, callable|string $replacement) use ($plain, &$edits): void {
            if (!preg_match_all($pattern, $plain, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                return;
            }
            foreach ($m as $match) {
                [$text, $offset] = $match[0];
                $new = \is_string($replacement) ? $replacement : $replacement($match);
                if ($new !== null && $new !== $text) {
                    $edits[] = [$offset, $offset + strlen($text), $new];
                }
            }
        };

        // Spacing and punctuation.
        $add('/(?<=\S) {2,}(?=\S)/u', ' ');
        // "word ,next" / "word .Next": the space belongs after the mark.
        $add('/(?<=[\p{L}\p{N}]) +([,;.!?]) *(?=\p{L})/u', static fn (array $m): string => $m[1][0] . ' ');
        $add('/(?<=[\p{L}\p{N})"\'’]) +(?=[,;!?](?:\s|$))/u', '');
        $add('/(?<=\p{L}) +(?=\.(?:\s|$))/u', '');
        $add('/,(?=\p{L})/u', ', ');
        $add('/;(?=\p{L})/u', '; ');
        $add('/(?<=\p{Ll}\p{Ll})([.!?])(?=\p{Lu}\p{Ll})/u', static fn (array $m): string => $m[1][0] . ' ');
        // "umaga.maaari" — two words run together — but never a web address
        // ("facebook.com", "gov.ph") or an e-mail.
        $add('/(?<![\p{L}\p{N}@.\/:])(\p{Ll}{3,})\.(\p{Ll}{3,})(?![\p{L}\p{N}.\/@])/u', static function (array $m): ?string {
            if (preg_match('/^(com|net|org|gov|edu|ph|io|ly|info|html?|php|me|co|app)$/', $m[2][0])
                || preg_match('/^(www|http|https)$/', $m[1][0])) {
                return null;
            }
            return $m[1][0] . '. ' . $m[2][0];
        });
        $add('/,{2,}/u', ',');
        $add('/(?<!\.)\.\.(?!\.)/u', '.');
        $add('/\(\s+/u', '(');
        $add('/(?<=\S)\s+\)/u', ')');

        // Doubled articles/particles: "ang ang", "the the", "sa sa".
        $words = implode('|', array_map('preg_quote', array_unique(self::FUNCTION_WORDS[$lang])));
        $add('/(?<![\p{L}\'’-])(' . $words . ')(\s+\1)(?![\p{L}\'’-])/iu', static fn (array $m): string => $m[1][0]);

        // English "i" on its own.
        if ($lang === 'en') {
            $add('/(?<![\p{L}\p{N}\'’.\-\/])i(?=(?:[\'’](?:m|ve|ll|d))?(?![\p{L}\p{N}.\-\/]))/u', 'I');
        }

        // Filipino din/rin, daw/raw, doon/roon, dito/rito, diyan/riyan.
        if ($lang === 'fil') {
            foreach ($this->filipinoDrEdits($plain) as $edit) {
                $edits[] = $edit;
            }
        }

        return $edits;
    }

    /**
     * Capitals: the start of every sentence, and English day/month names
     * ("may" and "march" are left alone — they are also ordinary words).
     *
     * @return list<array{0: int, 1: int, 2: string}>
     */
    private function capitalEdits(string $plain, string $lang): array
    {
        $edits = $this->sentenceStartEdits($plain);

        if ($lang === 'en' && preg_match_all(
            '/(?<![\p{L}\'’-])(monday|tuesday|wednesday|thursday|friday|saturday|sunday|january|february|april|june|july|august|september|october|november|december)(?![\p{L}\'’-])/u',
            $plain, $m, PREG_OFFSET_CAPTURE
        )) {
            foreach ($m[1] as [$word, $offset]) {
                $edits[] = [$offset, $offset + 1, strtoupper($word[0])];
            }
        }

        return $edits;
    }

    /** @return list<array{0: int, 1: int, 2: string}> */
    private function filipinoDrEdits(string $plain): array
    {
        $pairs = ['din' => 'rin', 'daw' => 'raw', 'doon' => 'roon', 'dito' => 'rito', 'diyan' => 'riyan'];
        $toD   = array_flip($pairs);

        if (!preg_match_all('/[\p{L}\p{M}\'’-]+/u', $plain, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $edits = [];
        $tokens = $m[0];
        for ($i = 1, $n = \count($tokens); $i < $n; $i++) {
            [$word, $offset] = $tokens[$i];
            if (!isset($pairs[$word]) && !isset($toD[$word])) {
                continue;   // lowercase particles only
            }
            [$prev, $prevOffset] = $tokens[$i - 1];
            $between = substr($plain, $prevOffset + strlen($prev), $offset - $prevOffset - strlen($prev));
            if (!preg_match('/^[ \t]+$/', $between)) {
                continue;   // punctuation or a line break in between: not one phrase
            }
            $p = mb_strtolower($prev);
            // KWF rule: r after a vowel or w/y; d after a consonant — and d
            // after words ending in -ra/-ri/-re/-ro/-ru/-raw/-ray ("maaari din").
            $wantR = preg_match('/[aeiouwy]$/u', $p) && !preg_match('/r[aeiou]$|r(aw|ay)$/u', $p);
            $fixed = $wantR ? ($pairs[$word] ?? $word) : ($toD[$word] ?? $word);
            if ($fixed !== $word) {
                $edits[] = [$offset, $offset + strlen($word), $fixed];
            }
        }
        return $edits;
    }

    /** @return list<array{0: int, 1: int, 2: string}> */
    private function sentenceStartEdits(string $plain): array
    {
        $edits = [];
        // Start of text, after a paragraph break, or after . ! ? (optionally closed by a quote/bracket).
        if (!preg_match_all('/(?:^|\n|([.!?])["\'’)\]]?[ \t]+)[ \t]*(\p{Ll})/u', $plain, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }
        foreach ($m as $match) {
            [$letter, $offset] = $match[2];

            // The word being capitalised: leave URLs, e-mails and "e.g."-like tokens alone.
            preg_match('/\G[^\s]*/u', $plain, $w, 0, $offset);
            $word = $w[0] ?? $letter;
            if (preg_match('/[.@\/:]\p{L}|^www|^https?/iu', $word)) {
                continue;
            }

            // A full stop after an abbreviation ("Brgy. hall", "No. 5") does not end a sentence.
            if (isset($match[1]) && $match[1][1] >= 0 && $match[1][0] === '.') {
                $before = substr($plain, 0, $match[1][1]);
                if (preg_match('/([\p{L}.]+)$/u', $before, $b)
                    && \in_array(mb_strtolower(rtrim($b[1], '.')), self::ABBREVIATIONS, true)) {
                    continue;
                }
                if (preg_match('/(?:^|\s)\p{L}$/u', $before)) {
                    continue;   // single initial, e.g. "J. dela Cruz"
                }
            }

            $edits[] = [$offset, $offset + strlen($letter), mb_strtoupper($letter)];
        }
        return $edits;
    }

    // ── LanguageTool ─────────────────────────────────────────────────────

    /**
     * @param list<array{kind: string, raw: string, as: string}> $pieces
     * @return list<array{0: int, 1: int, 2: string}>
     */
    private function remoteEdits(array $pieces, string $lang): array
    {
        $plain = self::plain($pieces);
        if (strlen($plain) > self::REMOTE_MAX_BYTES || trim($plain) === '' || ($this->client === null && self::coolingDown())) {
            return [];
        }

        $annotation = [];
        foreach ($pieces as $p) {
            $annotation[] = $p['kind'] === 'text'
                ? ['text' => $p['raw']]
                : ['markup' => $p['raw'] === '' ? '<!-- -->' : $p['raw'], 'interpretAs' => $p['as']];
        }

        try {
            $client   = $this->client ?? new Client(['timeout' => 8, 'connect_timeout' => 4, 'http_errors' => false]);
            $response = $client->post($this->url, [
                'form_params' => [
                    'language'      => self::REMOTE_LANG[$lang],
                    'data'          => json_encode(['annotation' => $annotation], JSON_UNESCAPED_UNICODE),
                    'disabledRules' => implode(',', self::SKIP_RULES),
                ],
                'headers' => ['Accept' => 'application/json'],
            ]);
            if ($response->getStatusCode() !== 200) {
                $this->client === null && self::startCooldown('HTTP ' . $response->getStatusCode());
                return [];
            }
            $data = json_decode((string) $response->getBody(), true);
        } catch (\Throwable $e) {
            $this->client === null && self::startCooldown($e->getMessage());
            return [];
        }

        // LanguageTool's offsets count the annotation as sent — markup and
        // text alike — so locate each text piece in those units and in the
        // plain text (bytes), and translate offsets piece by piece.
        $located = [];
        $unitPos = 0;
        $bytePos = 0;
        foreach ($pieces as $i => $p) {
            $sent  = $p['kind'] === 'text' ? $p['raw'] : $annotation[$i]['markup'];
            $units = self::utf16Offsets($sent);
            $width = (int) array_key_last($units);
            if ($p['kind'] === 'text') {
                $located[] = [$unitPos, $unitPos + $width, $bytePos, $units];
            }
            $unitPos += $width;
            $bytePos += strlen($p['as']);
        }

        $edits = [];
        foreach (($data['matches'] ?? []) as $match) {
            $rule  = (string) ($match['rule']['id'] ?? '');
            $type  = (string) ($match['rule']['issueType'] ?? '');
            $value = $match['replacements'][0]['value'] ?? null;
            if ($value === null || \in_array($rule, self::SKIP_RULES, true) || str_contains($rule, 'QUOTES') || str_contains($rule, 'ELLIPSIS')) {
                continue;
            }
            if (!\in_array($type, self::REMOTE_TYPES, true) && !\in_array($rule, self::EXTRA_RULES, true)) {
                continue;
            }
            $startUnit = (int) ($match['offset'] ?? -1);
            $endUnit   = $startUnit + (int) ($match['length'] ?? 0);
            $start = $end = null;
            foreach ($located as [$uFrom, $uTo, $byteFrom, $units]) {
                if ($startUnit >= $uFrom && $endUnit <= $uTo
                    && isset($units[$startUnit - $uFrom], $units[$endUnit - $uFrom])) {
                    $start = $byteFrom + $units[$startUnit - $uFrom];
                    $end   = $byteFrom + $units[$endUnit - $uFrom];
                    break;
                }
            }
            if ($start === null) {
                continue;   // spans a tag, or offsets we cannot place
            }
            $original = substr($plain, $start, $end - $start);

            // Belt and braces: the text we would replace must be the text
            // LanguageTool flagged, or the fix lands on the wrong words.
            $ctx = $match['context'] ?? [];
            if (isset($ctx['text'], $ctx['offset'], $ctx['length'])
                && mb_substr((string) $ctx['text'], (int) $ctx['offset'], (int) $ctx['length']) !== $original) {
                continue;
            }

            if ($type === 'misspelling' && !$this->safeSpellingFix($lang, $original, (string) $value)) {
                continue;
            }
            $edits[] = [$start, $end, (string) $value];
        }
        return $edits;
    }

    /**
     * Only ordinary lowercase English words, a typo away from the suggestion,
     * and never a local term. Filipino spelling suggestions are never taken.
     */
    private function safeSpellingFix(string $lang, string $original, string $suggestion): bool
    {
        if ($lang !== 'en') {
            return false;
        }
        if (!preg_match('/^[a-z][a-z\'’-]{3,}$/', $original) || !preg_match('/^[a-z][a-z\'’-]+$/', $suggestion)) {
            return false;   // names, places, acronyms, multi-word suggestions
        }
        if (\in_array($original, self::LOCAL_TERMS, true)) {
            return false;
        }
        foreach (FreeTranslationService::protectedTerms() as $term) {
            if (strcasecmp((string) $term, $original) === 0) {
                return false;
            }
        }
        $distance = levenshtein($original, $suggestion);
        return $distance === 1
            || ($distance === 2 && \strlen($original) >= 6 && strncmp($original, $suggestion, 2) === 0);
    }

    /**
     * LanguageTool counts UTF-16 units; map each unit offset to a byte offset.
     *
     * @return array<int, int>
     */
    private static function utf16Offsets(string $plain): array
    {
        $map  = [0 => 0];
        $unit = 0;
        $byte = 0;
        foreach (mb_str_split($plain) as $char) {
            $byte += strlen($char);
            $unit += mb_ord($char) > 0xFFFF ? 2 : 1;
            $map[$unit] = $byte;
        }
        return $map;
    }

    private static function cooldownFile(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'baranggabay_languagetool_cooldown';
    }

    private static function coolingDown(): bool
    {
        $file = self::cooldownFile();
        return is_file($file) && (time() - (int) @filemtime($file)) < self::COOLDOWN_SECONDS;
    }

    private static function startCooldown(string $why): void
    {
        @touch(self::cooldownFile());
        error_log('[GrammarCorrector] LanguageTool unavailable, local rules only for '
            . self::COOLDOWN_SECONDS . 's: ' . $why);
    }
}
