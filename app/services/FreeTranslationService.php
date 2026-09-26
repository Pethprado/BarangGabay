<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

/**
 * Filipino → English machine translation that costs nothing.
 *
 * Exists because the Anthropic path needs credits the barangay does not have,
 * which left every EN translation to be typed by hand. MyMemory is free, needs
 * no API key, and handles Tagalog well enough for barangay notices:
 *
 *   "May libreng bakuna bukas sa Barangay Hall mula alas otso ng umaga..."
 *   → "There will be a free vaccine tomorrow at Barangay Hall from 8am..."
 *
 * Deliberate limits, all learned from probing the real API:
 *
 *   - 500 characters per request, hard. Anything longer comes back as the
 *     literal string "QUERY LENGTH LIMIT EXCEEDED", which would otherwise be
 *     stored as if it were a translation. Text is split on sentence
 *     boundaries and reassembled.
 *   - The free tier is a daily character allowance per IP. A cap on chunks
 *     per post stops one long ordinance from consuming the whole day's
 *     allowance in a single save.
 *   - Every failure returns null. A translation that cannot be produced must
 *     leave the Filipino original in place, never a partial or an error
 *     string.
 *
 * NOTE ON PRIVACY: this sends the post's text to a third-party service.
 * That is acceptable here because announcements, events and ordinances are
 * public notices by nature — but it is why it is behind a setting the
 * barangay can switch off, and why nothing else in the system uses it.
 */
class FreeTranslationService
{
    /** MyMemory's hard per-request limit. Kept below it for multibyte safety. */
    private const MAX_CHUNK_BYTES = 450;

    /** Most chunks one post may spend. Roughly 10 sentences — plenty for a notice. */
    private const MAX_CHUNKS = 12;

    /** Courtesy pause between requests, in microseconds. */
    private const PAUSE_US = 250000;

    /** Longer pause before a retry — a 504 means the far end needs a moment. */
    private const RETRY_PAUSE_US = 900000;

    private Client $client;

    /**
     * Why the last call returned null.
     *
     * Every failure path in this class returns null, which is the right
     * contract — a partial or wrong translation must never reach the
     * database. But it left the CALLER unable to distinguish "the daily
     * allowance is spent, try again tomorrow" from "this post is too long,
     * shorten it" from "the far end timed out". All three became
     * `return false` in TranslationService and then silence on the screen.
     *
     * So the reason is recorded here as it happens, and read afterwards.
     * Nothing about the return values changes: callers that do not care are
     * unaffected, and the one that does can now say something true.
     *
     * @var array{code:string, message:string}|null
     */
    private ?array $lastFailure = null;

    /** The reason for the most recent null, or null if the last call worked. */
    public function lastFailure(): ?array
    {
        return $this->lastFailure;
    }

    /** The reason code alone, defaulting to a generic provider error. */
    public function lastReasonCode(): string
    {
        return $this->lastFailure['code'] ?? TranslationOutcome::PROVIDER_ERROR;
    }

    /** Record why this attempt is about to return null. */
    private function fail(string $code, string $message): null
    {
        $this->lastFailure = ['code' => $code, 'message' => $message];

        return null;
    }

    /**
     * The theoretical ceiling: every request filled to the byte.
     *
     * Useful as a headline number and useless as a test. Text is split on
     * SENTENCE boundaries, so a sentence that will not fit in the space
     * left ends the chunk early — real prose averages well under 450 bytes
     * per request, and a post of 5 000 characters can easily need more than
     * twelve. Showing this figure as "your limit" would tell staff they are
     * fine and then refuse the post after they save, which is the exact
     * experience this work exists to end.
     *
     * Use wouldExceedCap() to decide anything. This is for display, next to
     * the word "about".
     */
    public static function maxCharacters(): int
    {
        return self::MAX_CHUNK_BYTES * self::MAX_CHUNKS;
    }

    /** How many requests this text would actually cost. */
    public function chunkCount(string $text): int
    {
        return count($this->chunk(trim(strip_tags($text))));
    }

    /** The requests one post may spend. */
    public static function maxChunks(): int
    {
        return self::MAX_CHUNKS;
    }

    /**
     * Would this post be refused as too long?
     *
     * The honest predicate, asked the same way translate() asks it — by
     * chunking the real text. The pre-save panel calls this, so the warning
     * a staff member sees before saving is the same decision the service
     * makes after.
     */
    public function wouldExceedCap(string $text): bool
    {
        return $this->chunkCount($text) > self::MAX_CHUNKS;
    }

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout'     => 20,
            'http_errors' => false,
            'headers'     => ['User-Agent' => 'BarangGabay/1.0 (barangay notice board)'],
        ]);
    }

    /**
     * Translate Filipino text into English, or null if it cannot be done.
     *
     * @param string $text Plain text. HTML is stripped by the caller.
     */
    public function toEnglish(string $text): ?string
    {
        return $this->translate($text, 'fil', 'en');
    }

    /** Translate English text into Filipino, or null if it cannot be done. */
    public function toFilipino(string $text): ?string
    {
        return $this->translate($text, 'en', 'fil');
    }

    /**
     * Translate between Filipino and English in either direction.
     *
     * Staff write posts in whichever language they are comfortable with, so
     * the translation has to run both ways — a post written in English needs
     * a Filipino version just as much as the reverse.
     *
     * @param string $from 'fil' | 'en'
     * @param string $to   'fil' | 'en'
     */
    public function translate(string $text, string $from, string $to): ?string
    {
        $this->lastFailure = null;

        $codes = ['fil' => 'tl', 'en' => 'en'];
        if (!isset($codes[$from], $codes[$to])) {
            return $this->fail(
                TranslationOutcome::PROVIDER_ERROR,
                "Unsupported language pair {$from}->{$to}"
            );
        }
        if ($from === $to) {
            /* Asked to translate a post into the language it is already in.
               Always a sign that source_lang is wrong, and the reason a post
               can look "translated" while the reader gets the same text
               under a different flag. */
            return $this->fail(
                TranslationOutcome::SAME_AS_SOURCE,
                "Source and target are both {$from}"
            );
        }

        $text = trim(strip_tags($text));
        if ($text === '') {
            return $this->fail(TranslationOutcome::NO_SOURCE_TEXT, 'Nothing to translate');
        }

        // Styled Unicode is folded before anything is sent. The API answers
        // HTTP 504 for "𝐂𝐞𝐥𝐞𝐛𝐫𝐚𝐭𝐢𝐧𝐠 𝐂𝐮𝐥𝐭𝐮𝐫𝐞" and translates "Celebrating
        // Culture" correctly on the first try — the text is identical to a
        // reader and completely different to a machine. Only what leaves this
        // method is changed; the post keeps whatever staff stored.
        $text = SocialText::unstyleUnicode($text);

        $chunks = $this->chunk($text);
        if ($chunks === []) {
            return $this->fail(TranslationOutcome::NO_SOURCE_TEXT, 'Nothing to translate');
        }
        if (count($chunks) > self::MAX_CHUNKS) {
            /* Not retryable, and that is the important part: no amount of
               waiting shortens a post. A runner that kept picking this up
               would spend the daily allowance on something that can only be
               fixed by a person editing the text. */
            return $this->fail(
                TranslationOutcome::TEXT_TOO_LONG,
                sprintf(
                    '%d characters needs %d requests; the cap is %d (about %d characters).',
                    mb_strlen($text), count($chunks), self::MAX_CHUNKS, self::maxCharacters()
                )
            );
        }

        $langPair = $codes[$from] . '|' . $codes[$to];

        $out = [];
        foreach ($chunks as $chunk) {
            $piece = $this->translateChunk($chunk, $langPair);
            if ($piece === null) {
                // Partial output would be a mix of two languages presented as
                // one translation. Better to have none. translateChunk() has
                // already recorded which kind of failure it was.
                return null;
            }
            $out[] = $piece;
            usleep(self::PAUSE_US);
        }

        $joined = trim(implode(' ', $out));

        if ($joined === '') {
            return $this->fail(TranslationOutcome::PROVIDER_ERROR, 'Provider returned empty text');
        }

        return $joined;
    }

    /**
     * One chunk, with one retry for a transient failure.
     *
     * The English→Filipino direction returns HTTP 504 often enough to matter —
     * not a refusal, just the upstream engine timing out. Because a post is
     * stored in a language only if every chunk of it came back, a single 504
     * in the middle of a notice throws away the whole translation and staff
     * see "no translation could be produced" for a service that is working.
     *
     * Only 5xx and transport errors are retried, and only once. A refusal, a
     * quota message or a bad language pair will say the same thing twice, so
     * asking again would just spend the allowance faster.
     */
    private function translateChunk(string $chunk, string $langPair = 'tl|en'): ?string
    {
        $result = $this->requestChunk($chunk, $langPair);

        if ($result === null) {
            usleep(self::RETRY_PAUSE_US);
            $result = $this->requestChunk($chunk, $langPair);
        }

        return $result;
    }

    /** One request. Returns null on any error, quota exhaustion or refusal. */
    private function requestChunk(string $chunk, string $langPair): ?string
    {
        try {
            $res = $this->client->get('https://api.mymemory.translated.net/get', [
                'query' => ['q' => $chunk, 'langpair' => $langPair, 'mt' => '1'],
            ]);
        } catch (\Throwable $e) {
            error_log('[FreeTranslationService] request failed: ' . $e->getMessage());
            return $this->fail(TranslationOutcome::PROVIDER_ERROR, $e->getMessage());
        }

        if ($res->getStatusCode() !== 200) {
            error_log('[FreeTranslationService] HTTP ' . $res->getStatusCode() . ' for ' . $langPair);
            return $this->fail(
                TranslationOutcome::PROVIDER_ERROR,
                'HTTP ' . $res->getStatusCode() . ' for ' . $langPair
            );
        }

        $data = json_decode((string) $res->getBody(), true);
        if (!is_array($data)) {
            return $this->fail(TranslationOutcome::PROVIDER_ERROR, 'Response was not JSON');
        }

        // The HTTP status is 200 even when the API refuses; the real outcome
        // is in responseStatus, and the refusal text is put in the SAME field
        // as a successful translation. Both have to be checked.
        if ((int) ($data['responseStatus'] ?? 0) !== 200) {
            $detail = (string) ($data['responseDetails'] ?? 'unknown');
            error_log('[FreeTranslationService] refused: ' . $detail);

            return $this->fail(self::classifyRefusal($detail), $detail);
        }

        $translated = trim((string) ($data['responseData']['translatedText'] ?? ''));
        if ($translated === '') {
            return $this->fail(TranslationOutcome::PROVIDER_ERROR, 'Empty translation returned');
        }

        // Guard against the refusal strings ever reaching the database.
        // These arrive with a 200 status in the same field a translation
        // would use, so the text itself is the only thing that gives them
        // away — and "DAILY LIMIT" here is the daily allowance running out
        // mid-post, which is the single most common reason a language goes
        // missing in this barangay.
        $upper = mb_strtoupper($translated);
        foreach (['QUERY LENGTH LIMIT', 'MYMEMORY WARNING', 'INVALID LANGUAGE', 'DAILY LIMIT'] as $marker) {
            if (str_contains($upper, $marker)) {
                error_log('[FreeTranslationService] refusal text returned: ' . $translated);

                return $this->fail(self::classifyRefusal($translated), $translated);
            }
        }

        if (self::looksLikeMarkup($translated)) {
            error_log('[FreeTranslationService] discarded markup output: ' . mb_substr($translated, 0, 80));
            return $this->fail(
                TranslationOutcome::PROVIDER_ERROR,
                'Provider returned its own markup: ' . mb_substr($translated, 0, 120)
            );
        }

        if (!self::looksLikeLanguage($translated)) {
            error_log('[FreeTranslationService] discarded nonsense output: ' . mb_substr($translated, 0, 80));
            return $this->fail(
                TranslationOutcome::PROVIDER_ERROR,
                'Provider returned non-language output: ' . mb_substr($translated, 0, 120)
            );
        }

        $this->lastFailure = null;

        return $translated;
    }

    /**
     * Read MyMemory's refusal and decide whether waiting will help.
     *
     * The whole point of the retry runner rests on this one distinction.
     * The allowance messages ("YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR
     * TODAY", "MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS")
     * mean a post will translate itself tonight with nobody watching. The
     * length messages mean it never will until someone shortens it.
     *
     * Matched case-insensitively on the phrases the API actually sends,
     * with a generic provider error as the safe default — an unrecognised
     * refusal is retried a few times and then left alone, rather than being
     * assumed permanent and abandoned.
     */
    public static function classifyRefusal(string $detail): string
    {
        $upper = mb_strtoupper($detail);

        foreach (['ALL AVAILABLE FREE TRANSLATIONS', 'DAILY LIMIT', 'QUOTA', 'MYMEMORY WARNING'] as $marker) {
            if (str_contains($upper, $marker)) {
                return TranslationOutcome::QUOTA_EXHAUSTED;
            }
        }

        foreach (['QUERY LENGTH LIMIT', 'TOO LONG', 'MAX LENGTH'] as $marker) {
            if (str_contains($upper, $marker)) {
                return TranslationOutcome::TEXT_TOO_LONG;
            }
        }

        /* Only a refusal that really means "these are the same language".
           "'FIL' IS AN INVALID SOURCE LANGUAGE" reads similar and is not:
           it means the code we sent was rejected, which is our bug, not a
           wrongly-recorded source language. Mapping it here would have told
           staff to go and correct a source language that was fine. */
        if (str_contains($upper, 'SAME LANGUAGE')) {
            return TranslationOutcome::SAME_AS_SOURCE;
        }

        return TranslationOutcome::PROVIDER_ERROR;
    }

    /**
     * Is this the API's own page leaking out instead of a translation?
     *
     * Asked to put an English title into Filipino, MyMemory answered:
     *
     *   {{app['fromLang']['value']}} -&gt; {{app['toLang']['value']}}
     *
     * — an unrendered template from its own web front end, returned with a 200
     * status and responseStatus 200. It was stored as the Filipino title, so
     * residents reading in Filipino got that as the headline of a barangay
     * notice. looksLikeLanguage() passes it happily: it is mostly letters.
     *
     * The markers are ones no barangay notice contains. A title with a double
     * brace in it is not a title, whatever else is true about it.
     */
    public static function looksLikeMarkup(string $text): bool
    {
        foreach (['{{', '}}', '</', '/>', '<?', '?>'] as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }

        // Array subscripting — ['fromLang'] — is code, not prose.
        return preg_match('/\[\s*[\'"][^\'"]*[\'"]\s*\]/', $text) === 1;
    }

    /**
     * Does this output look like words, or like the API giving up?
     *
     * Fed a title in styled Unicode — the mathematical-bold letters Facebook
     * captions are full of — MyMemory returned "< < < < < < < …" with a 200
     * status and responseStatus 200. It was stored, and residents reading in
     * English saw a headline of angle brackets while the admin form showed the
     * real title. Nothing above catches that: it is not a refusal string, it
     * is not empty, and the API insists it succeeded.
     *
     * The test is simply whether the result contains words. Real translations
     * are mostly letters and digits in any language; a string that is almost
     * entirely punctuation is not a translation of anything.
     *
     * Deliberately permissive — a wrong translation is not this function's
     * business to judge, and refusing something legitimate would leave
     * residents with no English at all. Only the plainly broken is dropped.
     */
    public static function looksLikeLanguage(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        // Ignore whitespace; a run of spaces says nothing either way.
        $visible = preg_replace('/\s+/u', '', $text) ?? '';
        if ($visible === '') {
            return false;
        }

        $words = preg_match_all('/[\p{L}\p{N}]/u', $visible);
        if ($words === 0) {
            return false;                      // "< < < <" lands here
        }

        return ($words / mb_strlen($visible)) >= 0.3;
    }

    /**
     * Split text into request-sized pieces, preferring sentence boundaries.
     *
     * A sentence that is itself too long is split on word boundaries rather
     * than mid-word, so each piece is still translatable on its own.
     *
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $chunks  = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            if (strlen($sentence) > self::MAX_CHUNK_BYTES) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current  = '';
                }
                foreach ($this->splitOnWords($sentence) as $piece) {
                    $chunks[] = $piece;
                }
                continue;
            }

            $candidate = $current === '' ? $sentence : $current . ' ' . $sentence;
            if (strlen($candidate) <= self::MAX_CHUNK_BYTES) {
                $current = $candidate;
            } else {
                $chunks[] = $current;
                $current  = $sentence;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** @return list<string> */
    private function splitOnWords(string $sentence): array
    {
        $pieces  = [];
        $current = '';

        foreach (preg_split('/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (strlen($candidate) <= self::MAX_CHUNK_BYTES) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $pieces[] = $current;
                }
                // A single word longer than the limit cannot be helped; send
                // it alone and let the API decide.
                $current = $word;
            }
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
