<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

class AIService
{
    private ?Client $client = null;
    private string $model = 'claude-sonnet-4-20250514';
    private int $maxTokens = 1024;

    public function __construct()
    {
        $apiKey = env('ANTHROPIC_API_KEY', '');
        if (empty($apiKey)) {
            $this->client = null;
            return;
        }

        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Summarize an ordinance in simple Filipino for community residents.
     * Returns cached summary text (caching handled by AIController).
     */
    public function summarizeOrdinance(int $ordinanceId, string $ordinanceText): string
    {
        if ($this->client === null) {
            $snippet = trim(substr(strip_tags($ordinanceText), 0, 800));
            return $snippet . "\n\n(Automatic summary unavailable — AI key not configured.)";
        }

        $systemPrompt = <<<PROMPT
You are BarangGabay, a friendly community assistant for Barangay Bayogo, Madrid, Surigao del Sur.
Your job is to explain local government ordinances and policies in simple, clear language that any resident can understand — especially senior citizens and indigenous community members.
Always respond in clear Filipino or Taglish. Avoid complex legal terms.
Keep summaries under 250 words. Use bullet points for key points.
Do not add legal advice. End with "Para sa karagdagang impormasyon, makipag-ugnayan sa Barangay Hall."
PROMPT;

        $userPrompt = "Ipaliwanag ang sumusunod na ordinansa sa simpleng Filipino na maiintindihan ng lahat ng residente, lalo na ang mga matanda at katutubong komunidad. Gamitin ang maikling pangungusap:\n\n{$ordinanceText}";

        try {
            $response = $this->client->post('/v1/messages', [
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            /* Unwrap Anthropic's own message, the way translateToManobo()
               already did. Without this the caller saw Guzzle's wall of
               text — "Client error: `POST ...` resulted in a `400 Bad
               Request` response: {"type":"error"...}" — with the one
               sentence that explains the failure buried inside it. */
            throw new \RuntimeException($this->anthropicMessage($e), $e->getCode(), $e);
        }

        $data = json_decode((string) $response->getBody(), true);
        return $data['content'][0]['text'] ?? 'Hindi ma-generate ang buod. Subukan muli mamaya.';
    }

    /** Anthropic's error sentence, dug out of a Guzzle client exception. */
    private function anthropicMessage(\GuzzleHttp\Exception\ClientException $e): string
    {
        $decoded = json_decode((string) $e->getResponse()->getBody(), true);

        return $decoded['error']['message'] ?? $e->getMessage();
    }

    /**
     * What KIND of failure this is — because the honest advice differs.
     *
     * "Please try again later" is correct for a timeout and actively wrong
     * for an empty credit balance: it sends a resident to tap a button that
     * cannot succeed, for as long as the balance stays at zero. The caller
     * uses this to pick a message and to decide whether to offer a retry at
     * all.
     *
     * @return 'billing'|'rate_limit'|'auth'|'network'|'unknown'
     */
    public static function classifyFailure(\Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'credit balance')
            || str_contains($message, 'billing')
            || str_contains($message, 'quota')) {
            return 'billing';
        }
        if (str_contains($message, 'rate limit')
            || str_contains($message, 'overloaded')
            || str_contains($message, '429')) {
            return 'rate_limit';
        }
        if (str_contains($message, 'authentication')
            || str_contains($message, 'invalid x-api-key')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'permission')) {
            return 'auth';
        }
        if (str_contains($message, 'curl error')
            || str_contains($message, 'could not resolve')
            || str_contains($message, 'timed out')
            || str_contains($message, 'connect')) {
            return 'network';
        }

        return 'unknown';
    }

    /** Is retrying this failure capable of succeeding? */
    public static function isRetryable(\Throwable $e): bool
    {
        return \in_array(self::classifyFailure($e), ['rate_limit', 'network'], true);
    }

    /**
     * Shared system prompt for both Manobo translation paths below (the
     * on-demand "🌐 Translate" widget and the auto-translate-on-publish
     * flow), so the two can never drift into different dialects or styles
     * again — that drift is what caused the mismatched "Western Bukidnon
     * Manobo" labelling elsewhere in this codebase.
     *
     * Targets the Manobo actually spoken by indigenous residents of
     * Barangay Bayogo, Madrid, Surigao del Sur —
     * the variety catalogued locally as Agusan Manobo (ISO 639-3 "msm";
     * see data/manobo/README.md and ManoboDictionary) — NOT the unrelated
     * Western Bukidnon Manobo (mbb) spoken in Bukidnon province. That
     * distinction now holds across the whole codebase: the locale code, the
     * dictionary, this prompt and the method names all say msm.
     *
     * @param string $vocabHint Optional extra lines of community-verified
     *                          vocabulary from communityVocabHint().
     */
    private function manoboSystemPrompt(string $vocabHint = ''): string
    {
        $base = <<<'PROMPT'
You are a specialist AI translator for the Manobo spoken by indigenous residents of
Barangay Bayogo, Madrid, Surigao del Sur, Caraga Region,
Philippines (locally catalogued as Agusan Manobo).

HOW PEOPLE HERE ACTUALLY TALK — this is the most important instruction: nobody in
this community speaks "textbook" pure Manobo. Everyday Manobo speech is naturally
code-switched with Surigaonon (the Visayan language native to this province — not
the same as Cebuano/Boholano Bisaya, though closely related) and with general
Bisaya, especially for governance terms, modern vocabulary, greetings, discourse
particles ("man", "gani", "bitaw", "unya", "kuan"), and numbers. Translate the way a
real Manobo resident or elder of Bayogo would actually say it out loud —
never a "purified", Manobo-only version.

VOCABULARY REFERENCE (Manobo / Surigaonon / Bisaya blend used in this area):

People & Family:
tao/mga tao = wata/katawhan | bata/anak = bata/anak | matanda = tigulang/lakay
lalaki = lalaki/bana | babae = babaye/asawa | pamilya = pamilya/panimalay
ama = amahan/tatay | ina = inahan/nanay | kapatid = igsoon | kaibigan = amigo/higala

Place & Nature:
bahay = balay | barangay = banuwa/banwa | komunidad = katilingban
lugar = dapit/lugar | bundok = bukid/bulukbulukon | ilog = suba
dagat = dagat | lupa = yuta/duta | langit = langit | ulan = ulan

Time:
araw = aldew/adlaw | gabi = gabii | umaga = buntag | tanghali = ugto
hapon = hapon | buwan = bulan | taon = tuig | ngayon = karon | bukas = ugma

Governance & Announcements:
opisyal = opisyal/pangulo | kapitan = kapitan/lider | batas = balaod
ordinansa = kasugoan | programa = programa/plano | serbisyo = serbisyo/tabang
tulong = tabang/suporta | kalusugan = kahimsog/panglawas
anunsyo = balita/kasayuran | impormasyon = impormasyon/kasayuran
abiso = pasabot/paalala | paalala = pag-aman/pamahandi
kaganapan = hitabo/okasyon | pagtitipon = tigum/miting
patakaran = lagda/balaod | dokumento = papeles/dokumento

Actions:
pumunta = adto/moadto | kumain = kaon/mokaon | uminom = inom/moinom
makinig = paminaw/maminaw | basahin = basa/mabasa | tulungan = tabangan
sumali = apil/moapil | dumalo = motambong | magrehistro = pagparehistro

Descriptive:
libre = libre/walay bayad | mahalaga = importante/hinungdan
kailangan = kinahanglan | lahat = tanan | ilan = pila
huwag = ayaw/dili | oo = oo/hu-o | hindi = dili/dae
agad = dayon/dali | mahal = hinigugma

Common Surigaonon/Bisaya discourse particles to weave in naturally (these have no
direct Manobo equivalent and real speakers use them as-is):
man, gani, bitaw, unya, kuan, kay, dira, diha, kadto, karon

Translation rules:
1. Blend freely: use a Manobo word where you know one, Surigaonon for local
   Visayan-origin vocabulary and discourse particles, and general Bisaya/Cebuano to
   fill any remaining gap. This mixing is intentional and CORRECT — do not "clean it
   up" into a single language.
2. Prioritise Surigaonon over generic Cebuano Bisaya when the two differ (Surigaonon
   is the everyday lingua franca in this part of Surigao del Sur); plain Bisaya is a
   fine fallback when you are unsure of the Surigaonon form.
3. Keep proper nouns unchanged: names of people, places, organisations, dates,
   times, and numbers already written as digits (e.g. "8", "2026"). Numbers
   written out as words (e.g. "eight", "walo") are NOT proper nouns — translate
   them into the local blend like any other word.
4. Do not invent Manobo vocabulary. Use a Manobo word only when it is confirmed in
   the vocabulary notes appended below this prompt (when present), or when you are
   genuinely confident it is real Agusan Manobo rather than a guess, a different
   Manobo variant, or Tagalog/Cebuano dressed up to look like Manobo. When you are
   not sure a concept has a real Manobo word, say it in Surigaonon or Bisaya
   instead — a correct Bisaya word the community will recognise is better than an
   invented-sounding Manobo one.
5. Write naturally, in flowing sentences — the way it would actually be spoken —
   not a robotic word-for-word gloss.
6. Output ONLY the translation, starting immediately with the first word. No labels,
   no notes, no explanations, no language name.
PROMPT;

        if ($vocabHint !== '') {
            $base .= "\n\n" . $vocabHint;
        }

        return $base;
    }

    /**
     * Look up words from $text against the barangay's own community-curated
     * dictionaries — Manobo first (ManoboDictionary — msm, admin-editable at
     * /admin/manobo), then Bisaya (BisayaDictionary, /admin/bisaya) for
     * whatever Manobo did not cover — and return them as extra prompt
     * context, so vocabulary the community has already reviewed actually
     * shapes the AI's output instead of the model only using its own
     * guesses. Returns '' if neither dictionary can be read or nothing
     * matches.
     *
     * CHANGED: used to attest Manobo only. Rule 4's "do not invent Manobo
     * vocabulary" asks the model to fall back to Bisaya when it is not sure
     * of a real Manobo word — that fallback is only as good as the Bisaya
     * the model happens to know unless it is ALSO handed the community's
     * own reviewed Bisaya entries, the same way Manobo already was.
     */
    private function communityVocabHint(string $text): string
    {
        $manobo = $this->attestedVocabulary($text);
        $bisaya = $this->attestedBisayaVocabulary($text, $manobo);

        if ($manobo === [] && $bisaya === []) {
            return '';
        }

        $hint = '';
        if ($manobo !== []) {
            $hint .= "COMMUNITY-VERIFIED VOCABULARY (collected from local Manobo speakers via the "
                . "barangay's own dictionary — use these exact words wherever they match):\n"
                . implode("\n", $manobo);
        }
        if ($bisaya !== []) {
            $hint .= ($hint !== '' ? "\n\n" : '')
                . "COMMUNITY-REVIEWED BISAYA VOCABULARY (for concepts with no Manobo entry above — "
                . "use these rather than guessing your own Bisaya/Cebuano wording):\n"
                . implode("\n", $bisaya);
        }

        return $hint;
    }

    /**
     * Which of this text's words the barangay's own dictionary actually has.
     *
     * Public because the count is worth recording: it is the difference
     * between a translation grounded in attested vocabulary and one the model
     * produced entirely from memory. data/manobo/README.md is blunt about why
     * that matters — model-invented Manobo tends to come out as Cebuano,
     * Tagalog or a different Manobo variant, and nobody using the app is
     * positioned to catch it. With 39 entries most sentences will match
     * nothing, and that is itself the honest signal.
     *
     * @return list<string> "gloss = manobo (notes)" lines, capped at 25.
     */
    public function attestedVocabulary(string $text): array
    {
        try {
            $dictionary = new ManoboDictionary();
        } catch (\Throwable) {
            return [];
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $seen = [];
        $hits = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 3 || isset($seen[$word])) {
                continue;
            }
            $seen[$word] = true;

            $result = $dictionary->lookup($word);

            // Real sentences carry plurals ("announcements", "ordinances")
            // while the dictionary stores singulars, so retry the singular
            // before giving up. Mirrors what the MB language switch already
            // does for UI labels (helpers.php::manobo_word), so a word a
            // speaker adds behaves the same in both places.
            if (empty($result['entries']) && \function_exists('manobo_singular')) {
                $singular = manobo_singular($word);
                if ($singular !== null && !isset($seen[$singular])) {
                    $seen[$singular] = true;
                    $result = $dictionary->lookup($singular);
                }
            }

            foreach ($result['entries'] ?? [] as $entry) {
                $gloss = $entry['tagalog'] !== '' ? $entry['tagalog'] : $entry['english'];
                if ($gloss === '' || $entry['manobo'] === '') {
                    continue;
                }
                $line = $gloss . ' = ' . $entry['manobo'];
                if (!empty($entry['notes'])) {
                    $line .= ' (' . $entry['notes'] . ')';
                }
                $hits[$line] = true;
            }

            if (\count($hits) >= 25) {
                break;
            }
        }

        return array_slice(array_keys($hits), 0, 25);
    }

    /**
     * The Bisaya counterpart to attestedVocabulary(): which of this text's
     * words the barangay's reviewed Bisaya dictionary (BisayaDictionary,
     * /admin/bisaya) has, so the model's Bisaya fallback is grounded too,
     * not just its Manobo. Skips any gloss $exclude already covers, so a
     * word Manobo already answered for is not ALSO handed a Bisaya
     * alternative that could contradict it.
     *
     * @param  list<string> $exclude Manobo hint lines already produced,
     *                                as "gloss = manobo" — only the gloss
     *                                (left of " = ") is used to exclude.
     * @return list<string> "gloss = bisaya" lines, capped at 25.
     */
    public function attestedBisayaVocabulary(string $text, array $exclude = []): array
    {
        try {
            $dictionary = new BisayaDictionary();
        } catch (\Throwable) {
            return [];
        }

        $excludedGlosses = [];
        foreach ($exclude as $line) {
            $gloss = trim((string) strstr($line, ' = ', true));
            if ($gloss !== '') {
                $excludedGlosses[mb_strtolower($gloss, 'UTF-8')] = true;
            }
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $seen = [];
        $hits = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 3 || isset($seen[$word]) || isset($excludedGlosses[$word])) {
                continue;
            }
            $seen[$word] = true;

            $result = $dictionary->lookup($word);

            if (empty($result['entries']) && \function_exists('manobo_singular')) {
                $singular = manobo_singular($word);
                if ($singular !== null && !isset($seen[$singular]) && !isset($excludedGlosses[$singular])) {
                    $seen[$singular] = true;
                    $result = $dictionary->lookup($singular);
                }
            }

            foreach ($result['entries'] ?? [] as $entry) {
                $gloss = $entry['tagalog'] !== '' ? $entry['tagalog'] : $entry['english'];
                if ($gloss === '' || $entry['bisaya'] === '' || isset($excludedGlosses[mb_strtolower($gloss, 'UTF-8')])) {
                    continue;
                }
                $line = $gloss . ' = ' . $entry['bisaya'];
                if (!empty($entry['notes'])) {
                    $line .= ' (' . $entry['notes'] . ')';
                }
                $hits[$line] = true;
            }

            if (\count($hits) >= 25) {
                break;
            }
        }

        return array_slice(array_keys($hits), 0, 25);
    }

    /**
     * Translate text to the Manobo spoken in Bayogo, Madrid, Surigao del Sur, for
     * indigenous residents there — naturally code-switched with Surigaonon
     * and Bisaya, the way it is actually spoken locally (see
     * manoboSystemPrompt()). Text is truncated to 4 000 characters before
     * the API call.
     */
    public function translateToManobo(string $text): string
    {
        if ($this->client === null) {
            return 'Pasensya na, hindi available ang AI translation service. Mangyaring subukan muli mamaya.';
        }

        $truncated    = mb_substr(strip_tags($text), 0, 4000);
        $systemPrompt = $this->manoboSystemPrompt($this->communityVocabHint($truncated));

        try {
            $response = $this->client->post('/v1/messages', [
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => 2000,
                    'system'     => $systemPrompt,
                    'messages'   => [
                        ['role' => 'user', 'content' => "Isaling ito sa Manobo:\n\n{$truncated}"],
                    ],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Extract Anthropic's JSON error body for a clear user-facing message.
            $body    = (string) $e->getResponse()->getBody();
            $decoded = json_decode($body, true);
            $msg     = $decoded['error']['message'] ?? $e->getMessage();
            throw new \RuntimeException($msg, $e->getCode(), $e);
        }

        $data = json_decode((string) $response->getBody(), true);
        return $data['content'][0]['text'] ?? 'Hindi ma-isagawa ang pagsasalin. Subukan muli mamaya.';
    }

    /**
     * Translate a content title + body into local Manobo — the same variety
     * and naturally code-switched style as translateToManobo() /
     * manoboSystemPrompt() — in a single API call. Returns
     * ['title' => string, 'body' => string] on success. Throws
     * \RuntimeException (with Anthropic's error message) on failure.
     *
     * Was called translateToWesternBukidnonManobo() until this rename. That
     * name was not cosmetic: Western Bukidnon Manobo (mbb) is a different
     * language, spoken hundreds of kilometres away in Bukidnon province, and
     * the prompt had already been corrected to target the Agusan Manobo (msm)
     * this barangay actually speaks. Leaving the old name meant the codebase
     * still told a reader — and the next person to write a prompt — that the
     * translation target was mbb. data/manobo/README.md flags exactly this
     * mismatch; the name now matches the dictionary, the locale code and the
     * prompt.
     *
     * The caller is responsible for stripping HTML before passing $body.
     */
    public function translatePostToManobo(string $title, string $body): array
    {
        if ($this->client === null) {
            throw new \RuntimeException('ANTHROPIC_API_KEY not configured. Add it to .env to enable auto-translation.');
        }

        $cleanTitle = mb_substr(trim(strip_tags($title)), 0, 500);
        $cleanBody  = mb_substr(trim(strip_tags($body)),  0, 3500);

        $systemPrompt = $this->manoboSystemPrompt(
            $this->communityVocabHint($cleanTitle . ' ' . $cleanBody)
        );

        $userPrompt = "Translate both the TITLE and BODY below.\n"
            . "Respond with ONLY a JSON object in exactly this format — no markdown, no extra text:\n"
            . "{\"title\":\"<translated title>\",\"body\":\"<translated body>\"}\n\n"
            . "TITLE: {$cleanTitle}\n\n"
            . "BODY:\n{$cleanBody}";

        try {
            $response = $this->client->post('/v1/messages', [
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => 3000,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => $userPrompt]],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errBody = (string) $e->getResponse()->getBody();
            $decoded = json_decode($errBody, true);
            $msg     = $decoded['error']['message'] ?? $e->getMessage();
            throw new \RuntimeException($msg, $e->getCode(), $e);
        }

        $data    = json_decode((string) $response->getBody(), true);
        $rawText = trim($data['content'][0]['text'] ?? '');

        // Strip optional markdown code fences Claude sometimes adds
        $rawText = preg_replace('/^```json\s*/i', '', $rawText);
        $rawText = preg_replace('/\s*```$/', '', trim($rawText));

        $parsed = json_decode($rawText, true);
        if (is_array($parsed) && isset($parsed['title'], $parsed['body'])) {
            return ['title' => trim((string) $parsed['title']), 'body' => trim((string) $parsed['body'])];
        }

        // Fallback if JSON parsing fails: use the raw output as body
        return ['title' => $cleanTitle, 'body' => $rawText];
    }

    /**
     * Translate a post's title and body into plain English.
     *
     * The counterpart to translatePostToManobo(). Staff write posts
     * in Filipino, so without this the header's EN button had no English copy
     * of the content to show and quietly fell back to Filipino.
     *
     * Deliberately asks for plain, simple English rather than a literal
     * rendering: the audience is residents reading a barangay notice, not
     * translators comparing texts.
     *
     * @return array{title:string, body:string}
     */
    public function translateToEnglish(string $title, string $body): array
    {
        if ($this->client === null) {
            throw new \RuntimeException('ANTHROPIC_API_KEY not configured. Add it to .env to enable auto-translation.');
        }

        $cleanTitle = mb_substr(trim(strip_tags($title)), 0, 500);
        $cleanBody  = mb_substr(trim(strip_tags($body)),  0, 3500);

        $systemPrompt = <<<PROMPT
        You translate barangay announcements, events and ordinances from Filipino
        (or Taglish) into clear, simple English for residents of Barangay Bayogo,
        Madrid, Surigao del Sur.

        Rules:
        - Use plain, everyday English. A resident with basic English should
          understand it on one reading. Short sentences.
        - Keep every fact exactly as given: dates, times, places, names, amounts,
          phone numbers and ordinance numbers must not change.
        - Keep proper nouns as they are (Barangay Hall, Bayogo, Madrid, names of
          people and offices). Do not translate them.
        - Do not add, explain, summarise or omit anything. Translate only.
        - If a passage is already in English, leave it as it is.
        PROMPT;

        $userPrompt = "Translate both the TITLE and BODY below into English.\n"
            . "Respond with ONLY a JSON object in exactly this format — no markdown, no extra text:\n"
            . "{\"title\":\"<translated title>\",\"body\":\"<translated body>\"}\n\n"
            . "TITLE: {$cleanTitle}\n\n"
            . "BODY:\n{$cleanBody}";

        try {
            $response = $this->client->post('/v1/messages', [
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => 3000,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => $userPrompt]],
                ],
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errBody = (string) $e->getResponse()->getBody();
            $decoded = json_decode($errBody, true);
            $msg     = $decoded['error']['message'] ?? $e->getMessage();
            throw new \RuntimeException($msg, $e->getCode(), $e);
        }

        $data    = json_decode((string) $response->getBody(), true);
        $rawText = trim($data['content'][0]['text'] ?? '');

        $rawText = preg_replace('/^```json\s*/i', '', $rawText);
        $rawText = preg_replace('/\s*```$/', '', trim($rawText));

        $parsed = json_decode($rawText, true);
        if (is_array($parsed) && isset($parsed['title'], $parsed['body'])) {
            return ['title' => trim((string) $parsed['title']), 'body' => trim((string) $parsed['body'])];
        }

        // Unparseable response: return nothing rather than storing the model's
        // raw output as if it were a translation. The caller treats an empty
        // result as "not translated" and leaves the Filipino original in place.
        return ['title' => '', 'body' => ''];
    }

    /**
     * Community Q&A — answer resident questions using recent announcements as context.
     */
    public function chat(string $question, array $recentPosts): string
    {
        if ($this->client === null) {
            return "Pasensya na, kasalukuyang hindi available ang AI service. Mangyaring makipag-ugnayan sa Barangay Hall para sa karagdagang impormasyon.";
        }
        $context = implode("\n\n", array_map(static fn($post) => "### {$post['title']}\n{$post['body']}", array_slice($recentPosts, 0, 5)));

        $systemPrompt = <<<PROMPT
You are BarangGabay, a helpful assistant for residents of Barangay Bayogo, Madrid.
Answer questions based only on the barangay information provided below.
If the answer is not in the provided context, say:
"Pasensya na, wala akong impormasyon tungkol diyan. Mangyaring makipag-ugnayan sa Barangay Hall."
Be friendly, concise, and use simple Filipino or Taglish.
Keep answers under 150 words.

BARANGAY CONTEXT:
{$context}
PROMPT;

        $response = $this->client->post('/v1/messages', [
            'json' => [
                'model' => $this->model,
                'max_tokens' => 512,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $question],
                ],
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        return $data['content'][0]['text'] ?? 'Hindi ko masagot ang iyong tanong sa ngayon.';
    }
}