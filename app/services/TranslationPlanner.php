<?php
declare(strict_types=1);

namespace App\Services;

/**
 * What will happen to this post when you press Save — before you press it.
 *
 * Every problem this whole feature exists to fix is discoverable BEFORE the
 * save, and none of them was surfaced there:
 *
 *   - the body is longer than the free provider accepts, so English will
 *     never be produced no matter how many times it is re-saved;
 *   - Manobo needs an Anthropic key that is not set, so the MN button will
 *     do nothing for residents and nobody will be told why;
 *   - the source language is about to be recorded wrong, which means the
 *     post gets "translated" into the language it is already in and a
 *     resident tapping FIL is shown English under a Filipino badge.
 *
 * Each of those costs a staff member a wasted save and a confused guess.
 * All three are answerable from the text in the form.
 *
 * ── Why this is computed on the server ──────────────────────────────────
 *
 * It would be easy to count characters in JavaScript and call that the cap
 * check. It would also be wrong: FreeTranslationService splits on SENTENCE
 * boundaries, so a post can sit well under the advertised character count
 * and still need more than the twelve requests it is allowed. The only
 * honest predicate is the real chunker, and the real chunker is here.
 *
 * The same goes for the source language: LanguageGuess and the order of
 * authority in resolveSourceLang() are the things that will actually decide
 * it on save, so the panel asks them rather than re-implementing them in a
 * second language where they can drift.
 */
class TranslationPlanner
{
    /**
     * @param string $sourceOverride 'fil' | 'en' | 'auto' | ''
     * @return array{
     *   source: array{lang:string, decidedBy:string, overridden:bool},
     *   length: array{characters:int, chunks:int, maxChunks:int, overCap:bool},
     *   languages: list<array{lang:string, will:string, reason:string, fix:?string, envKey:?string}>,
     *   providers: list<array{name:string, ready:bool, envKey:?string}>
     * }
     */
    public static function plan(
        string $title,
        string $body,
        string $sourceOverride = 'auto',
        string $existingSource = 'fil'
    ): array {
        $plainBody = \trim(\strip_tags($body));
        $combined  = \trim($title . "\n" . $plainBody);

        // ── 1. Which language is this written in? ────────────────────────
        $sourceOverride = \strtolower(\trim($sourceOverride));
        $explicit       = \in_array($sourceOverride, ['fil', 'en'], true);

        // The real function, not a copy of its rules.
        $source = TranslationService::resolveSourceLang(
            $sourceOverride,
            $title,
            $plainBody,
            $existingSource
        );

        $detected = LanguageGuess::detectOrNull($combined);

        $decidedBy = $explicit
            ? 'chosen'
            : ($detected !== null ? 'detected' : 'default');

        /*
         * Is the staff member's choice fighting the text?
         *
         * Their choice always wins — they read the post and a word counter
         * did not. But when the two disagree it is worth saying so, because
         * the commonest cause is a caption pasted from an English source
         * into a form left on Filipino, and the consequence is silent: the
         * post is "translated" into the language it is already in.
         */
        $disagrees = $explicit && $detected !== null && $detected !== $source;

        // ── 2. Is it too long for the free provider? ─────────────────────
        $free   = new FreeTranslationService();
        $chunks = $free->chunkCount($combined);
        $over   = $chunks > FreeTranslationService::maxChunks();

        // ── 3. Which providers are actually usable right now? ────────────
        $freeOn   = (int) setting('free_translation_enabled', 1) === 1;
        $hasGloss = \function_exists('manobo_gloss_for');

        /*
         * A key that is set is not the same as a key that works.
         *
         * Whether ANTHROPIC_API_KEY exists is readable from .env; whether
         * its account still has credits cannot be known without spending
         * one. Reporting "Manobo will be generated" on the strength of the
         * key alone is a promise that breaks on every save for an empty
         * account — which is the state this barangay has actually been in.
         *
         * The recorded outcomes settle it. If Anthropic came back "no
         * credits" within the last day, the next call will too, and the
         * panel says so instead of guessing.
         */
        $keySet    = !empty(env('ANTHROPIC_API_KEY', ''));
        $outOfCash = self::looksOutOfCredits($keySet);
        $hasKey    = self::anthropicUsable($keySet, $outOfCash);

        $providers = [
            ['name' => 'mymemory',  'ready' => $freeOn,   'envKey' => null],
            ['name' => 'anthropic', 'ready' => $hasKey,   'envKey' => 'ANTHROPIC_API_KEY',
             'reason' => $outOfCash ? TranslationOutcome::NO_CREDITS
                       : ($keySet ? null : TranslationOutcome::NO_API_KEY)],
            ['name' => 'dictionary','ready' => $hasGloss, 'envKey' => null],
        ];

        // ── 4. So what happens to each language? ─────────────────────────
        $other     = $source === 'fil' ? 'en' : 'fil';
        $languages = [];

        // The source language is simply kept — nothing is generated.
        $languages[] = [
            'lang'   => $source,
            'will'   => 'source',
            'reason' => 'source',
            'fix'    => null,
            'envKey' => null,
        ];

        // The other of the fil/en pair.
        $languages[] = self::forOther($other, $combined, $freeOn, $over, $hasKey);

        // Manobo.
        $languages[] = self::forManobo($hasKey, $hasGloss, $outOfCash);

        return [
            'source' => [
                'lang'       => $source,
                'decidedBy'  => $decidedBy,
                'overridden' => $disagrees,
                'detected'   => $detected,
            ],
            'length' => [
                'characters' => \mb_strlen($combined),
                'chunks'     => $chunks,
                'maxChunks'  => FreeTranslationService::maxChunks(),
                'overCap'    => $over,
            ],
            'languages' => $languages,
            'providers' => $providers,
        ];
    }

    /**
     * Is Anthropic actually usable?
     *
     * Pulled out as its own function with no side effects so it can be
     * tested directly. A version of this written inline as
     * `$keySet && !$outOfCash` passed a test that only checked the source
     * file mentioned seenRecently() — because a mutation to
     * `false && seenRecently(...)` left the words in place while making
     * the call dead. Checking for the presence of a string cannot tell
     * live code from dead code; running the function can.
     */
    public static function anthropicUsable(bool $keySet, bool $outOfCredits): bool
    {
        return $keySet && !$outOfCredits;
    }

    /**
     * Does the record say the account is empty?
     *
     * Separated from the decision above so the decision stays pure. This
     * one touches the database; seenRecently() returns false if it cannot,
     * which errs toward optimism — the plan may then promise a translation
     * that fails, but a broken lookup must not make the panel claim a
     * provider is down.
     */
    public static function looksOutOfCredits(bool $keySet): bool
    {
        return $keySet && \App\Models\TranslationAttempt::seenRecently(
            TranslationOutcome::NO_CREDITS
        );
    }

    /**
     * @return array{lang:string, will:string, reason:string, fix:?string, envKey:?string}
     */
    private static function forOther(
        string $lang,
        string $combined,
        bool   $freeOn,
        bool   $over,
        bool   $hasKey
    ): array {
        if (\trim($combined) === '') {
            return self::skip($lang, TranslationOutcome::NO_SOURCE_TEXT);
        }

        /* Anthropic is tried first and can do this even when the free
           provider is off or the post is too long for it — so a key that
           works keeps this language available either way. Whether the
           ACCOUNT has credits cannot be known without spending one, which
           is why this says "will try" rather than promising. */
        if ($over && !$hasKey) {
            return self::skip($lang, TranslationOutcome::TEXT_TOO_LONG);
        }
        if (!$freeOn && !$hasKey) {
            return self::skip($lang, TranslationOutcome::PROVIDER_DISABLED);
        }

        return [
            'lang'   => $lang,
            'will'   => $over ? 'maybe' : 'generate',
            'reason' => $over ? TranslationOutcome::TEXT_TOO_LONG : TranslationOutcome::OK,
            'fix'    => $over ? TranslationOutcome::fixKey(TranslationOutcome::TEXT_TOO_LONG) : null,
            'envKey' => null,
        ];
    }

    /** @return array{lang:string, will:string, reason:string, fix:?string, envKey:?string} */
    private static function forManobo(bool $hasKey, bool $hasGloss, bool $outOfCash = false): array
    {
        if ($hasKey) {
            return ['lang' => 'msm', 'will' => 'generate', 'reason' => TranslationOutcome::OK,
                    'fix' => null, 'envKey' => null];
        }

        /* Anthropic is unavailable — either no key at all, or a key whose
           account is empty. The two need different fixes, so they are named
           differently rather than both becoming "no key": telling someone
           to set a variable that is already set would send them looking in
           the wrong file. */
        $why = $outOfCash ? TranslationOutcome::NO_CREDITS : TranslationOutcome::NO_API_KEY;

        /* The barangay's own dictionary can still gloss it — word by word,
           and only where it covers enough of the post. A real possibility
           rather than a promise, so it says "maybe". */
        if ($hasGloss) {
            return ['lang' => 'msm', 'will' => 'maybe', 'reason' => $why,
                    'fix' => TranslationOutcome::fixKey($why),
                    'envKey' => TranslationOutcome::envKey($why)];
        }

        return self::skip('msm', $why);
    }

    /** @return array{lang:string, will:string, reason:string, fix:string, envKey:?string} */
    private static function skip(string $lang, string $code): array
    {
        return [
            'lang'   => $lang,
            'will'   => 'skip',
            'reason' => $code,
            'fix'    => TranslationOutcome::fixKey($code),
            'envKey' => TranslationOutcome::envKey($code),
        ];
    }
}
