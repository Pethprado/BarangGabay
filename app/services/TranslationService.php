<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\TranslationAttempt;
use App\Models\TranslationLog;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Ordinance;

/**
 * Auto-translation helper — called from admin controllers after content is published.
 * All methods fail silently: if the API is unavailable the admin save still succeeds
 * and the resident-side widget will retry on first page view.
 */
class TranslationService
{
    /**
     * Generate and cache a Manobo translation immediately after admin publishes content.
     *
     * @param string $contentType  'announcement' | 'event' | 'ordinance'
     * @param int    $contentId    The saved record's ID
     * @param string $plainText    Plain-text representation of the content (title + body)
     * @param int|null $userId     Admin user ID for attribution
     */
    public static function autoTranslate(
        string $contentType,
        int    $contentId,
        string $plainText,
        ?int   $userId = null
    ): void {
        // Skip if no API key configured
        if (empty(env('ANTHROPIC_API_KEY', ''))) return;

        // Skip if a valid translation already exists in the cache
        if (TranslationLog::findCached($contentType, $contentId)) return;

        $plainText = \trim(\strip_tags($plainText));
        if (\strlen($plainText) < 3) return;

        try {
            $ai          = new AIService();
            $translation = $ai->translateToManobo($plainText);

            // Only cache a real translation — ignore fallback/error strings
            if (\str_contains($translation, 'hindi available') ||
                \str_contains($translation, 'translation service')) {
                return;
            }

            TranslationLog::create($userId, $contentType, $contentId, $plainText, $translation);
        } catch (\Throwable) {
            // Fail silently — resident-side widget will trigger translation on first view
        }
    }

    /**
     * Translate a content record's title + body to Manobo (may halong Bisaya/Surigaonon) and persist the
     * result directly in the content table (title_manobo / body_manobo or description_manobo).
     *
     * Called automatically after admin creates or updates a post.
     * Returns true on success, false when the API is unavailable or the call fails.
     * The post is always saved — a failure here is non-fatal.
     *
     * @param  string $contentType  'announcement' | 'event' | 'ordinance'
     * @param  int    $contentId    Primary key of the saved record
     * @param  string $title        English title
     * @param  string $body         English body / description (may be HTML — will be stripped)
     */
    public static function autoTranslatePost(
        string $contentType,
        int    $contentId,
        string $title,
        string $body
    ): bool {
        $source = $title . "\n" . $body;

        if (empty(env('ANTHROPIC_API_KEY', ''))) {
            /* Recorded rather than returned silently. This is the single
               most common reason MN is missing, and the fix is one line in
               .env — but nothing in the system ever said so, so staff were
               left to conclude that Manobo "just does not work".

               Not fatal on its own: autoTranslateFrom() falls through to the
               dictionary gloss next, which may still succeed and will
               overwrite this row with OK. */
            TranslationAttempt::record(
                $contentType, $contentId, 'msm', TranslationOutcome::NO_API_KEY,
                'text', 'ANTHROPIC_API_KEY is not set', 'anthropic', $source
            );

            return false;
        }

        try {
            $ai = new AIService();

            /*
             * How much of this translation is grounded in vocabulary the
             * barangay has actually verified, versus produced from the model's
             * own memory. Recorded rather than assumed: data/manobo/README.md
             * is explicit that model-invented Manobo tends to come out as
             * Cebuano, Tagalog or a different Manobo variant, and that nobody
             * using the app is positioned to catch it. With 39 dictionary
             * entries most posts will ground nothing at all — which is exactly
             * the signal worth having in the log.
             */
            $attested = $ai->attestedVocabulary($title . ' ' . $body);
            error_log(\sprintf(
                '[TranslationService] %s #%d Manobo: %d word(s) from the barangay dictionary; '
                . 'the rest is model output and is stored unreviewed (manobo_is_auto=1).',
                $contentType,
                $contentId,
                \count($attested)
            ));

            $result = $ai->translatePostToManobo($title, $body);

            if (empty($result['title']) && empty($result['body'])) {
                TranslationAttempt::record(
                    $contentType, $contentId, 'msm', TranslationOutcome::PROVIDER_ERROR,
                    'text', 'Anthropic returned no Manobo text', 'anthropic', $source
                );

                return false;
            }

            $t = $result['title'] ?: '';
            $b = $result['body']  ?: '';

            switch ($contentType) {
                case 'announcement':
                    Announcement::updateManobo($contentId, $t, $b);
                    break;
                case 'event':
                    Event::updateManobo($contentId, $t, $b);
                    break;
                case 'ordinance':
                    Ordinance::updateManobo($contentId, $t, $b);
                    break;
                default:
                    TranslationAttempt::record(
                        $contentType, $contentId, 'msm', TranslationOutcome::PROVIDER_ERROR,
                        'text', 'Unknown content type ' . $contentType, 'anthropic', $source
                    );
                    return false;
            }

            TranslationAttempt::recordOk($contentType, $contentId, 'msm', 'text', 'anthropic', $source);

            return true;
        } catch (\Throwable $e) {
            error_log('[TranslationService] autoTranslatePost failed (' . $contentType . ' #' . $contentId . '): ' . $e->getMessage());

            // An empty credit balance is the case here, and it is retryable:
            // credits get topped up and the post fills itself in afterwards.
            TranslationAttempt::record(
                $contentType,
                $contentId,
                'msm',
                AIService::classifyFailure($e) === 'billing'
                    ? TranslationOutcome::NO_CREDITS
                    : TranslationOutcome::PROVIDER_ERROR,
                'text',
                $e->getMessage(),
                'anthropic',
                $source
            );

            return false;
        }
    }

    /**
     * Translate a record's title + body into English and store it alongside
     * the Filipino original, so the header's EN button has real content to
     * show instead of falling back to Filipino.
     *
     * Same contract as autoTranslatePost(): never throws, returns false when
     * the API is unavailable, and the post is saved either way. That matters
     * here in particular — at the time of writing the Anthropic account has no
     * credits, so this returns false on every call and staff rely on the
     * manual English fields instead. It starts working on its own the moment
     * credits exist, with no code change.
     *
     * @param string $contentType 'announcement' | 'event' | 'ordinance'
     */
    public static function autoTranslatePostToEnglish(
        string $contentType,
        int    $contentId,
        string $title,
        string $body
    ): bool {
        $t = $b = '';

        /* Every exit from here now records WHY, so a missing English version
           can be explained instead of guessed at. $reason carries the last
           thing that went wrong; $provider says who was asked. */
        $reason   = TranslationOutcome::PROVIDER_ERROR;
        $detail   = null;
        $provider = null;

        // 1. Anthropic, when the account has credits. Best quality.
        if (!empty(env('ANTHROPIC_API_KEY', ''))) {
            $provider = 'anthropic';
            try {
                $result = (new AIService())->translateToEnglish($title, $body);
                $t = (string) ($result['title'] ?? '');
                $b = (string) ($result['body']  ?? '');
            } catch (\Throwable $e) {
                error_log('[TranslationService] Anthropic EN failed (' . $contentType
                    . ' #' . $contentId . '): ' . $e->getMessage());
                // "no credits" and "bad key" need different fixes from staff.
                $reason = AIService::classifyFailure($e) === 'billing'
                    ? TranslationOutcome::NO_CREDITS
                    : TranslationOutcome::PROVIDER_ERROR;
                $detail = $e->getMessage();
            }
        }

        // 2. Free provider. This is the path that actually runs today, since
        //    the Anthropic account has no credits. Off by default only if the
        //    barangay has switched it off — it sends text to a third party,
        //    which is fine for public notices but should stay their choice.
        if ($t === '' && $b === '') {
            if ((int) setting('free_translation_enabled', 1) !== 1) {
                $reason = TranslationOutcome::PROVIDER_DISABLED;
                $detail = 'free_translation_enabled is off';
            } else {
                $provider = 'mymemory';
                $free     = new FreeTranslationService();
                $t        = (string) ($free->toEnglish($title) ?? '');
                $b        = (string) ($free->toEnglish($body)  ?? '');

                if ($free->lastFailure() !== null) {
                    $reason = $free->lastReasonCode();
                    $detail = $free->lastFailure()['message'] ?? null;
                }
            }
        }

        if (!self::isCompleteTranslation($title, $body, $t, $b)) {
            /* One half came back and the other did not. Distinguished from a
               clean failure because the fix is different: a partial is
               usually one chunk that timed out and is worth retrying, while
               the reason already set above may be permanent. */
            if ($t !== '' || $b !== '') {
                $reason = TranslationOutcome::PARTIAL_RESULT;
                $detail = 'Only part of the post came back; nothing was stored.';
            }

            TranslationAttempt::record(
                $contentType, $contentId, 'en', $reason, 'text', $detail, $provider, $title . "\n" . $body
            );

            return false;
        }

        $stored = self::storeEnglish($contentType, $contentId, $t, $b, true);

        TranslationAttempt::record(
            $contentType,
            $contentId,
            'en',
            $stored ? TranslationOutcome::OK : TranslationOutcome::PROVIDER_ERROR,
            'text',
            $stored ? null : 'Translated, but the database write failed.',
            $provider,
            $title . "\n" . $body
        );

        // Urgent announcements hold their machine English until a person has
        // checked it. Everything else publishes immediately, as before — the
        // gate exists for the case where a wrong translation is dangerous,
        // not as a general slowdown. Manobo is never gated: it is glossed
        // from the barangay's own dictionary and has no alternative source.
        if ($stored && $contentType === 'announcement' && self::isUrgentAnnouncement($contentId)) {
            \App\Models\Announcement::setEnReviewState($contentId, \App\Models\Announcement::REVIEW_PENDING);
        }

        return $stored;
    }

    /**
     * Did every part of the post survive translation?
     *
     * A post is stored in one language or it is not stored in that language at
     * all. Half of one is worse than none: the title translated but the body
     * not produces a Filipino headline over an English article, which reads as
     * a broken page rather than a missing translation — and it defeats the
     * notice on the resident side, which can only say "no translation yet" for
     * a field that is genuinely empty.
     *
     * This is a real failure mode, not a hypothetical. The free service caps
     * how much text one post may spend, so a short title goes through while a
     * long body is refused, and the two results arrive independently.
     *
     * A field that was empty to begin with is allowed to stay empty — some
     * ordinances carry no description.
     */
    private static function isCompleteTranslation(
        string $sourceTitle,
        string $sourceBody,
        string $title,
        string $body
    ): bool {
        if (\trim($sourceTitle) !== '' && \trim($title) === '') {
            return false;
        }

        if (\trim(\strip_tags($sourceBody)) !== '' && \trim($body) === '') {
            return false;
        }

        return \trim($title) !== '' || \trim($body) !== '';
    }

    /**
     * Which languages this post can actually be read in, and which of those a
     * machine produced.
     *
     * The admin list shows this per post. It has to be visible because it is
     * routinely incomplete and not because anything is broken: the free
     * translation service has a daily per-IP allowance, so a post saved after
     * it runs out is saved correctly with no translation attached. Silence
     * there reads as success, and staff only find out when a resident tells
     * them. Manobo is missing more often than not — it needs either Anthropic
     * credits or hand-typed fields.
     *
     * @param array<string,mixed> $row       A content row.
     * @param string              $bodyField 'body' for announcements,
     *                                       'description' for events and
     *                                       ordinances.
     * @return array{
     *     source:string,
     *     fil:array{has:bool, machine:bool},
     *     en:array{has:bool, machine:bool},
     *     manobo:array{has:bool, machine:bool}
     * }
     */
    public static function statusFor(array $row, string $bodyField = 'body'): array
    {
        $source = \in_array($row['source_lang'] ?? 'fil', ['fil', 'en'], true)
            ? (string) $row['source_lang']
            : 'fil';

        $filled = static function (string $suffix) use ($row, $bodyField): bool {
            return \trim((string) ($row['title_' . $suffix] ?? '')) !== ''
                || \trim((string) ($row[$bodyField . '_' . $suffix] ?? '')) !== '';
        };

        $machine = static fn (string $flag): bool => (int) ($row[$flag] ?? 0) === 1;

        return [
            'source' => $source,
            // The language a post was written in is always readable, and it
            // was written by a person — never label the original "machine".
            'fil' => $source === 'fil'
                ? ['has' => true, 'machine' => false]
                : ['has' => $filled('fil'), 'machine' => $machine('fil_is_auto')],
            'en' => $source === 'en'
                ? ['has' => true, 'machine' => false]
                : ['has' => $filled('en'), 'machine' => $machine('en_is_auto')],
            'manobo' => ['has' => $filled('manobo'), 'machine' => $machine('manobo_is_auto')],
        ];
    }

    /**
     * Which translations may be regenerated for an existing row.
     *
     * Text a person typed is never regenerated over. That rule is why the
     * *_is_auto flags exist: a filled field with the flag clear was written by
     * a staff member and is left exactly alone, while a filled field the
     * machine produced may be replaced by a better attempt.
     *
     * @param array<string,mixed> $row
     * @return array{other:bool, manobo:bool}
     */
    public static function regenerable(array $row, string $bodyField = 'body'): array
    {
        $status = self::statusFor($row, $bodyField);
        $other  = $status['source'] === 'fil' ? 'en' : 'fil';

        return [
            'other'  => !$status[$other]['has'] || $status[$other]['machine'],
            'manobo' => !$status['manobo']['has'] || $status['manobo']['machine'],
        ];
    }

    /**
     * Settle which language a post was written in.
     *
     * The single most consequential value in this service. A post is
     * translated INTO the languages it was not written in, so a wrong answer
     * does not produce a bad translation — it produces none, silently. An
     * English post recorded as Filipino is "translated" English→English, never
     * gets a Filipino version, and a resident who taps FIL is shown English
     * under a Filipino badge. That was the reported bug, and its cause was
     * this value defaulting to 'fil' for a caption imported from an English
     * Facebook post.
     *
     * Order of authority, highest first:
     *
     *   1. What the staff member explicitly chose. Never overridden. They read
     *      the post; a word counter did not.
     *   2. What the text itself says, when it says so clearly.
     *   3. What the post already had — on an edit, an unrelated change must
     *      not silently re-file the language.
     *   4. Filipino, the language most notices here are written in.
     *
     * @param string|null $posted   $_POST['source_lang'] — 'fil', 'en',
     *                              'auto', or absent.
     * @param string      $existing What the record already says, for an edit.
     */
    public static function resolveSourceLang(
        ?string $posted,
        string  $title,
        string  $body,
        string  $existing = 'fil'
    ): string {
        $posted = \strtolower(\trim((string) $posted));

        if ($posted === 'fil' || $posted === 'en') {
            return $posted;
        }

        $detected = LanguageGuess::detectOrNull(\trim($title . "\n" . $body));
        if ($detected !== null) {
            return $detected;
        }

        return \in_array($existing, ['fil', 'en'], true) ? $existing : 'fil';
    }

    /**
     * Translate a post into every language it was NOT written in.
     *
     * The one entry point the controllers call. Which translations are needed
     * depends entirely on the source language:
     *
     *   written in Filipino → English (service) + Manobo (dictionary gloss)
     *   written in English  → Filipino (service) + Manobo (dictionary gloss)
     *
     * Manobo is glossed from whichever language the post was written in — the
     * community dictionaries index both Tagalog and English, so no round-trip
     * through a third language is needed.
     *
     * Manual text always wins: a caller passes only the fields the staff
     * member left blank.
     *
     * @param string $sourceLang 'fil' | 'en'
     * @return array{other:bool, manobo:bool} which translations were produced
     */
    public static function autoTranslateFrom(
        string $contentType,
        int    $contentId,
        string $title,
        string $body,
        string $sourceLang,
        bool   $needOther = true,
        bool   $needManobo = true
    ): array {
        $sourceLang = \in_array($sourceLang, ['fil', 'en'], true) ? $sourceLang : 'fil';
        $result     = ['other' => false, 'manobo' => false];

        if ($needOther) {
            $result['other'] = $sourceLang === 'fil'
                ? self::autoTranslatePostToEnglish($contentType, $contentId, $title, $body)
                : self::autoTranslatePostToFilipino($contentType, $contentId, $title, $body);
        }

        if ($needManobo) {
            // Manobo and Bisaya hybrid translation:
            // APPROVED MANOBO DATASET -> BISAYA FALLBACK -> ORIGINAL ONLY FOR PROTECTED CONTENT
            $result['manobo'] = self::autoTranslatePostToManobo($contentType, $contentId, $title, $body, $sourceLang);
        }

        return $result;
    }

    /**
     * Reusable translation service entry point.
     * e.g. TranslationService::translate($text, 'mn')
     */
    public static function translate(string $text, string $target = 'mn', string $from = 'auto'): array
    {
        if ($target === 'mn' || $target === 'msm') {
            $translator = new ManoboHybridTranslator();
            return $translator->translate($text, $from);
        }

        $free = new FreeTranslationService();
        $source = $from === 'auto' ? (LanguageGuess::detectOrNull($text) ?? 'fil') : $from;
        $out = $free->translate($text, $source, $target);
        return [
            'success'         => $out !== null,
            'translation'     => $out ?? $text,
            'language'        => $target,
            'source_lang'     => $source,
            'manoboMatches'   => 0,
            'bisayaFallbacks' => 0,
            'provenance'      => [],
            'cached'          => false,
        ];
    }

    /**
     * Translate an English post into Filipino using the free service.
     *
     * The mirror of autoTranslatePostToEnglish(). Same review gate applies:
     * on an urgent announcement the machine's Filipino is held back until a
     * person confirms it, because a reversed instruction is just as dangerous
     * in Filipino as it is in English.
     */
    public static function autoTranslatePostToFilipino(
        string $contentType,
        int    $contentId,
        string $title,
        string $body
    ): bool {
        $source = $title . "\n" . $body;

        if ((int) setting('free_translation_enabled', 1) !== 1) {
            TranslationAttempt::record(
                $contentType, $contentId, 'fil', TranslationOutcome::PROVIDER_DISABLED,
                'text', 'free_translation_enabled is off', null, $source
            );
            return false;
        }

        $free = new FreeTranslationService();
        $t    = (string) ($free->toFilipino($title) ?? '');
        $b    = (string) ($free->toFilipino($body)  ?? '');

        if (!self::isCompleteTranslation($title, $body, $t, $b)) {
            $reason = $free->lastReasonCode();
            $detail = $free->lastFailure()['message'] ?? null;

            if ($t !== '' || $b !== '') {
                $reason = TranslationOutcome::PARTIAL_RESULT;
                $detail = 'Only part of the post came back; nothing was stored.';
            }

            TranslationAttempt::record(
                $contentType, $contentId, 'fil', $reason, 'text', $detail, 'mymemory', $source
            );

            return false;
        }

        $stored = self::storeFilipino($contentType, $contentId, $t, $b, true);

        TranslationAttempt::record(
            $contentType,
            $contentId,
            'fil',
            $stored ? TranslationOutcome::OK : TranslationOutcome::PROVIDER_ERROR,
            'text',
            $stored ? null : 'Translated, but the database write failed.',
            'mymemory',
            $source
        );

        if ($stored && $contentType === 'announcement' && self::isUrgentAnnouncement($contentId)) {
            \App\Models\Announcement::setEnReviewState($contentId, \App\Models\Announcement::REVIEW_PENDING);
        }

        return $stored;
    }

    /**
     * Write a Filipino translation, recording whether a machine produced it.
     *
     * @param bool $isAuto true for machine output, false for text a person wrote.
     */
    public static function storeFilipino(
        string $contentType,
        int    $contentId,
        string $title,
        string $body,
        bool   $isAuto
    ): bool {
        try {
            switch ($contentType) {
                case 'announcement':
                    Announcement::updateFilipino($contentId, $title, $body);
                    break;
                case 'event':
                    Event::updateFilipino($contentId, $title, $body);
                    break;
                case 'ordinance':
                    Ordinance::updateFilipino($contentId, $title, $body);
                    break;
                default:
                    return false;
            }
            self::flagAuto($contentType, $contentId, 'fil_is_auto', $isAuto);

            if (!$isAuto && $contentType === 'announcement') {
                \App\Models\Announcement::setEnReviewState($contentId, \App\Models\Announcement::REVIEW_NONE);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[TranslationService] storeFilipino failed (' . $contentType
                . ' #' . $contentId . '): ' . $e->getMessage());
            return false;
        }
    }

    /** Whether an announcement is marked urgent. False for anything else. */
    private static function isUrgentAnnouncement(int $id): bool
    {
        try {
            $stmt = db()->prepare('SELECT urgency FROM announcements WHERE id = ?');
            $stmt->execute([$id]);

            return (string) $stmt->fetchColumn() === 'urgent';
        } catch (\Throwable $e) {
            error_log('[TranslationService] isUrgentAnnouncement failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Write an English translation, recording whether a machine produced it.
     *
     * @param bool $isAuto true for machine output, false for text a person wrote.
     */
    public static function storeEnglish(
        string $contentType,
        int    $contentId,
        string $title,
        string $body,
        bool   $isAuto
    ): bool {
        try {
            switch ($contentType) {
                case 'announcement':
                    Announcement::updateEnglish($contentId, $title, $body);
                    break;
                case 'event':
                    Event::updateEnglish($contentId, $title, $body);
                    break;
                case 'ordinance':
                    Ordinance::updateEnglish($contentId, $title, $body);
                    break;
                default:
                    return false;
            }
            self::flagAuto($contentType, $contentId, 'en_is_auto', $isAuto);

            if (!$isAuto && $contentType === 'announcement') {
                \App\Models\Announcement::setEnReviewState($contentId, \App\Models\Announcement::REVIEW_NONE);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[TranslationService] storeEnglish failed (' . $contentType
                . ' #' . $contentId . '): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fill the Manobo translation using the Manobo and Bisaya Hybrid Translator.
     *
     * Priority: APPROVED MANOBO DATASET -> BISAYA FALLBACK -> ORIGINAL ONLY FOR PROTECTED CONTENT
     */
    public static function autoTranslatePostToManobo(
        string $contentType,
        int    $contentId,
        string $title,
        string $body,
        string $sourceLang = 'fil'
    ): bool {
        $needTitle = trim($title) !== '';
        $needBody  = trim(strip_tags($body)) !== '';

        $titleGloss = null;
        $bodyGloss  = null;

        $source = $title . "\n" . $body;

        try {
            $translator = new ManoboHybridTranslator();
            if ($needTitle) {
                $tRes = $translator->translate($title, $sourceLang);
                $titleGloss = !empty($tRes['translation']) ? $tRes['translation'] : null;
            }
            if ($needBody) {
                $bRes = $translator->translate($body, $sourceLang);
                $bodyGloss = !empty($bRes['translation']) ? $bRes['translation'] : null;
            }

            if (($needTitle && $titleGloss === null) || ($needBody && $bodyGloss === null)) {
                TranslationAttempt::record(
                    $contentType, $contentId, 'msm', TranslationOutcome::PARTIAL_RESULT, 'text',
                    'The barangay dictionary covered only part of this post, so nothing was stored.',
                    'hybrid_translator', $source
                );

                return false;
            }

            if ($titleGloss === null && $bodyGloss === null) {
                TranslationAttempt::record(
                    $contentType, $contentId, 'msm', TranslationOutcome::PARTIAL_RESULT, 'text',
                    'Too few words in this post could be translated.',
                    'hybrid_translator', $source
                );

                return false;
            }

            $t = $titleGloss ?? '';
            $b = $bodyGloss  ?? '';

            switch ($contentType) {
                case 'announcement':
                    Announcement::updateManobo($contentId, $t, $b);
                    break;
                case 'event':
                    Event::updateManobo($contentId, $t, $b);
                    break;
                case 'ordinance':
                    Ordinance::updateManobo($contentId, $t, $b);
                    break;
                default:
                    TranslationAttempt::record(
                        $contentType, $contentId, 'msm', TranslationOutcome::PROVIDER_ERROR,
                        'text', 'Unknown content type ' . $contentType, 'hybrid_translator', $source
                    );
                    return false;
            }
            self::flagAuto($contentType, $contentId, 'manobo_is_auto', true);

            TranslationAttempt::recordOk($contentType, $contentId, 'msm', 'text', 'hybrid_translator', $source);

            return true;
        } catch (\Throwable $e) {
            error_log('[TranslationService] autoTranslatePostToManobo failed ('
                . $contentType . ' #' . $contentId . '): ' . $e->getMessage());

            TranslationAttempt::record(
                $contentType, $contentId, 'msm', TranslationOutcome::PROVIDER_ERROR,
                'text', $e->getMessage(), 'hybrid_translator', $source
            );

            return false;
        }
    }

    /**
     * Set or clear one of the "this was machine generated" flags.
     *
     * The column name never comes from user input — callers pass one of two
     * literals — so interpolating it here is safe, and there is no way to
     * parameterise a column name in SQL anyway.
     */
    public static function flagAuto(string $contentType, int $contentId, string $column, bool $isAuto): void
    {
        if (!\in_array($column, ['en_is_auto', 'fil_is_auto', 'manobo_is_auto'], true)) {
            return;
        }
        $table = match ($contentType) {
            'announcement' => 'announcements',
            'event'        => 'events',
            'ordinance'    => 'ordinances',
            default        => null,
        };
        if ($table === null) {
            return;
        }

        try {
            db()->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?")
                ->execute([$isAuto ? 1 : 0, $contentId]);
        } catch (\Throwable $e) {
            error_log('[TranslationService] flagAuto failed: ' . $e->getMessage());
        }
    }
}