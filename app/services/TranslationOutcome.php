<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Why a translation or an audio track did not happen — and whether waiting
 * will fix it.
 *
 * Today every failure in this pipeline looks the same from the outside. The
 * free provider returns null whether its daily allowance ran out, the body
 * was too long, or the far end timed out; TranslationService turns all three
 * into `return false`. Staff see a post with no English version and no way
 * to tell whether to wait, shorten the text, or top up an API account.
 *
 * This class is the vocabulary that ends that. It holds no state and touches
 * no database: a reason code, the sentence that explains it, and the one
 * decision that matters operationally — CAN THIS SUCCEED IF WE TRY AGAIN?
 *
 * That last question is the whole point of the retry runner. Getting it
 * wrong in either direction is costly:
 *
 *   - marking a permanent failure retryable makes the runner grind at a post
 *     that can never succeed, spending the daily allowance on it forever;
 *   - marking a temporary failure permanent means a post that would have
 *     filled itself in at midnight never does, and someone has to notice.
 *
 * So the three permanent codes are permanent for a reason that is about the
 * WORLD, not about the code: TEXT_TOO_LONG needs a person to shorten the
 * post, NO_API_KEY needs a person to edit .env, SAME_AS_SOURCE needs a
 * person to correct the source language. No amount of retrying substitutes
 * for any of them.
 */
final class TranslationOutcome
{
    /** It worked. */
    public const OK = 'OK';

    /** The free provider's daily allowance is spent. Resets at midnight UTC. */
    public const QUOTA_EXHAUSTED = 'QUOTA_EXHAUSTED';

    /** Longer than the free provider will accept for one post. */
    public const TEXT_TOO_LONG = 'TEXT_TOO_LONG';

    /** Manobo needs Anthropic, and ANTHROPIC_API_KEY is not set. */
    public const NO_API_KEY = 'NO_API_KEY';

    /** The key is valid but the account has no credit balance. */
    public const NO_CREDITS = 'NO_CREDITS';

    /** The provider answered, but not with a usable translation. */
    public const PROVIDER_ERROR = 'PROVIDER_ERROR';

    /** Asked to translate a post into the language it is already written in. */
    public const SAME_AS_SOURCE = 'SAME_AS_SOURCE';

    /** Title came back but body did not, or the reverse. Nothing was stored. */
    public const PARTIAL_RESULT = 'PARTIAL_RESULT';

    /** No text in this language yet, so there is nothing for the voice to read. */
    public const NO_SOURCE_TEXT = 'NO_SOURCE_TEXT';

    /** No TTS provider configured; the browser voice is standing in. */
    public const NO_TTS_PROVIDER = 'NO_TTS_PROVIDER';

    /** The free translator is switched off in settings. */
    public const PROVIDER_DISABLED = 'PROVIDER_DISABLED';

    /**
     * Every code, so a caller can validate one without hardcoding the list.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::OK,
            self::QUOTA_EXHAUSTED,
            self::TEXT_TOO_LONG,
            self::NO_API_KEY,
            self::NO_CREDITS,
            self::PROVIDER_ERROR,
            self::SAME_AS_SOURCE,
            self::PARTIAL_RESULT,
            self::NO_SOURCE_TEXT,
            self::NO_TTS_PROVIDER,
            self::PROVIDER_DISABLED,
        ];
    }

    public static function isKnown(string $code): bool
    {
        return \in_array($code, self::all(), true);
    }

    /**
     * Will trying again, unchanged, ever work?
     *
     * False here does not mean "broken forever" — it means no retry runner
     * can fix it. A person changes something (the text, the .env file, the
     * source language) and the next save tries again from scratch.
     */
    public static function isRetryable(string $code): bool
    {
        return \in_array($code, [
            self::QUOTA_EXHAUSTED,   // the allowance resets
            self::NO_CREDITS,        // credits get topped up
            self::PROVIDER_ERROR,    // a timeout is not a verdict
            self::PARTIAL_RESULT,    // often a chunk that failed once
        ], true);
    }

    /**
     * When it is worth trying this again, or null for never.
     *
     * QUOTA_EXHAUSTED is the case this system actually hits, and it is not
     * exponential — the allowance resets on a clock, so the right moment is
     * just after midnight UTC and there is nothing to gain by asking sooner.
     * Everything else backs off: 5 minutes, then 20, then 80, capped at a
     * day, so a provider having a bad hour is not hammered.
     *
     * @param int $attempts How many times this has already been tried.
     */
    public static function retryAfter(string $code, int $attempts, ?int $now = null): ?\DateTimeImmutable
    {
        if (!self::isRetryable($code)) {
            return null;
        }

        $now   = $now ?? \time();
        $clock = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('UTC'));

        if ($code === self::QUOTA_EXHAUSTED) {
            // Just after the reset, not exactly on it — a minute of slack
            // avoids racing the provider's own rollover.
            return $clock->modify('tomorrow')->modify('+1 minute');
        }

        $attempts = \max(1, $attempts);
        $minutes  = \min(1440, 5 * (4 ** ($attempts - 1)));

        return $clock->modify('+' . $minutes . ' minutes');
    }

    /**
     * After how many failures to stop trying automatically.
     *
     * A retryable code that has failed this many times has stopped being a
     * transient problem, and a runner that never gives up is a runner that
     * spends the daily allowance on a post nobody is waiting for. The health
     * page still lists it, and a person can still press Translate now.
     */
    public const MAX_ATTEMPTS = 6;

    public static function shouldKeepTrying(string $code, int $attempts): bool
    {
        return self::isRetryable($code) && $attempts < self::MAX_ATTEMPTS;
    }

    /**
     * The t() key for the sentence a staff member reads.
     *
     * Kept as a key rather than a sentence so the message lives in lang/,
     * in both languages, like every other user-facing string in this app.
     */
    public static function messageKey(string $code): string
    {
        return 'translation_reason.' . \strtolower(self::isKnown($code) ? $code : self::PROVIDER_ERROR);
    }

    /** The t() key for what a person should actually DO about it. */
    public static function fixKey(string $code): string
    {
        return 'translation_fix.' . \strtolower(self::isKnown($code) ? $code : self::PROVIDER_ERROR);
    }

    /**
     * The .env key that has to be set, when the fix is a missing provider.
     *
     * Returned as the literal variable name because that is what someone
     * editing the file is looking for. Null when the fix is not a config
     * change.
     */
    public static function envKey(string $code): ?string
    {
        return match ($code) {
            self::NO_API_KEY, self::NO_CREDITS => 'ANTHROPIC_API_KEY',
            self::NO_TTS_PROVIDER              => 'TTS_PROVIDER',
            default                            => null,
        };
    }
}
