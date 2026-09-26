<?php
declare(strict_types=1);

use App\Services\FreeTranslationService;
use App\Services\TranslationOutcome;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The one decision the whole retry story rests on.
 *
 * Until now every failure in the translation pipeline looked the same: the
 * free provider returned null whether its daily allowance was spent, the
 * body was too long, or the far end timed out, and TranslationService
 * turned all three into `return false`. A post saved with no English
 * version and no explanation anywhere.
 *
 * Splitting them apart is only useful if the split is RIGHT, and getting it
 * wrong is expensive in both directions:
 *
 *   - treat a permanent failure as retryable, and the runner grinds at a
 *     post that can never succeed, spending the daily allowance on it;
 *   - treat a temporary one as permanent, and a post that would have filled
 *     itself in at midnight never does.
 *
 * So this file is mostly about which codes are retryable and why.
 */
final class TranslationOutcomeTest extends TestCase
{
    public function testEveryCodeIsKnownToItself(): void
    {
        foreach (TranslationOutcome::all() as $code) {
            $this->assertTrue(TranslationOutcome::isKnown($code), "{$code} is not in all()");
        }

        $this->assertFalse(TranslationOutcome::isKnown('MADE_UP'));
    }

    /**
     * The three permanent codes are permanent for reasons about the WORLD,
     * not about the code: shortening a post, editing .env, and correcting a
     * source language all need a person. No retry substitutes for any.
     *
     * @dataProvider retryability
     */
    public function testRetryabilityMatchesWhatAWaitCanActuallyFix(string $code, bool $retryable): void
    {
        $this->assertSame(
            $retryable,
            TranslationOutcome::isRetryable($code),
            $retryable
                ? "{$code} can succeed later and should be retried"
                : "{$code} cannot be fixed by waiting — retrying it burns the daily allowance"
        );
    }

    /** @return array<string, array{0:string, 1:bool}> */
    public static function retryability(): array
    {
        return [
            'the allowance resets overnight'    => [TranslationOutcome::QUOTA_EXHAUSTED, true],
            'credits can be topped up'          => [TranslationOutcome::NO_CREDITS,      true],
            'a timeout is not a verdict'        => [TranslationOutcome::PROVIDER_ERROR,  true],
            'one chunk failed, try again'       => [TranslationOutcome::PARTIAL_RESULT,  true],

            'only a person can shorten a post'  => [TranslationOutcome::TEXT_TOO_LONG,   false],
            'only a person can edit .env'       => [TranslationOutcome::NO_API_KEY,      false],
            'only a person can fix the source'  => [TranslationOutcome::SAME_AS_SOURCE,  false],
            'no text means nothing to work on'  => [TranslationOutcome::NO_SOURCE_TEXT,  false],
            'a missing voice service is config' => [TranslationOutcome::NO_TTS_PROVIDER, false],
            'a switched-off provider is a choice' => [TranslationOutcome::PROVIDER_DISABLED, false],
            'success is not retried'            => [TranslationOutcome::OK,              false],
        ];
    }

    public function testPermanentFailuresGetNoRetryTime(): void
    {
        foreach ([TranslationOutcome::TEXT_TOO_LONG,
                  TranslationOutcome::NO_API_KEY,
                  TranslationOutcome::SAME_AS_SOURCE] as $code) {
            $this->assertNull(
                TranslationOutcome::retryAfter($code, 1),
                "{$code} was given a retry time; the runner would loop on it forever"
            );
        }
    }

    /**
     * The allowance resets on a clock, not after a delay.
     *
     * Backing off five minutes from a quota failure asks a provider that
     * has already said no twelve more times before midnight, for nothing.
     */
    public function testAQuotaFailureWaitsForTheDailyReset(): void
    {
        $now  = \mktime(14, 30, 0, 9, 23, 2026);
        $when = TranslationOutcome::retryAfter(TranslationOutcome::QUOTA_EXHAUSTED, 1, $now);

        $this->assertNotNull($when);
        $this->assertSame('00:01', $when->format('H:i'), 'should land just after midnight UTC');
        $this->assertGreaterThan($now, $when->getTimestamp());
        $this->assertLessThanOrEqual($now + 86400 + 120, $when->getTimestamp(), 'never more than a day out');
    }

    public function testBackoffGrowsAndIsCapped(): void
    {
        $now  = \time();
        $last = 0;

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $when = TranslationOutcome::retryAfter(TranslationOutcome::PROVIDER_ERROR, $attempt, $now);
            $this->assertNotNull($when);

            $minutes = (int) round(($when->getTimestamp() - $now) / 60);
            $this->assertGreaterThanOrEqual($last, $minutes, 'backoff went backwards');
            $this->assertLessThanOrEqual(1440, $minutes, 'backoff exceeded one day');
            $last = $minutes;
        }
    }

    public function testTheRunnerEventuallyGivesUp(): void
    {
        $this->assertTrue(
            TranslationOutcome::shouldKeepTrying(TranslationOutcome::PROVIDER_ERROR, 1)
        );
        $this->assertFalse(
            TranslationOutcome::shouldKeepTrying(
                TranslationOutcome::PROVIDER_ERROR,
                TranslationOutcome::MAX_ATTEMPTS
            ),
            'a runner that never gives up spends the allowance on a post nobody is waiting for'
        );
        $this->assertFalse(
            TranslationOutcome::shouldKeepTrying(TranslationOutcome::TEXT_TOO_LONG, 1),
            'a permanent failure must never be kept trying, at any attempt count'
        );
    }

    public function testTheEnvKeyIsNamedWhereTheFixIsAConfigChange(): void
    {
        $this->assertSame('ANTHROPIC_API_KEY', TranslationOutcome::envKey(TranslationOutcome::NO_API_KEY));
        $this->assertSame('ANTHROPIC_API_KEY', TranslationOutcome::envKey(TranslationOutcome::NO_CREDITS));
        $this->assertSame('TTS_PROVIDER',      TranslationOutcome::envKey(TranslationOutcome::NO_TTS_PROVIDER));
        $this->assertNull(TranslationOutcome::envKey(TranslationOutcome::TEXT_TOO_LONG));
    }

    /**
     * MyMemory answers HTTP 200 for a refusal and puts the refusal text in
     * the same field a translation would use, so the string is the only
     * thing that distinguishes "come back tomorrow" from "this will never
     * work".
     *
     * @dataProvider refusals
     */
    public function testRefusalsAreReadCorrectly(string $detail, string $expected): void
    {
        $this->assertSame($expected, FreeTranslationService::classifyRefusal($detail));
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function refusals(): array
    {
        return [
            'the allowance, as the API words it' => [
                'MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY',
                TranslationOutcome::QUOTA_EXHAUSTED,
            ],
            'the allowance, other wording' => [
                'You used all available free translations for today.',
                TranslationOutcome::QUOTA_EXHAUSTED,
            ],
            'too long' => [
                'QUERY LENGTH LIMIT EXCEEDED. MAX ALLOWED QUERY : 500 CHARS',
                TranslationOutcome::TEXT_TOO_LONG,
            ],
            'genuinely the same language' => [
                'SOURCE AND TARGET ARE THE SAME LANGUAGE',
                TranslationOutcome::SAME_AS_SOURCE,
            ],
            /* Reads like a source-language problem and is not: the code we
               sent was rejected. Classifying it as SAME_AS_SOURCE would send
               staff to correct a source language that was already right. */
            'a rejected language code is our bug, not theirs' => [
                "'FIL' IS AN INVALID SOURCE LANGUAGE",
                TranslationOutcome::PROVIDER_ERROR,
            ],
            'anything unrecognised is retried a few times, not abandoned' => [
                'Something nobody has seen before',
                TranslationOutcome::PROVIDER_ERROR,
            ],
        ];
    }

    /**
     * The provider now says why it returned null.
     *
     * These three paths need no network: they are refused before any
     * request is made, which is also why they are the cheapest to assert.
     */
    public function testTheProviderReportsWhyItRefused(): void
    {
        $free = new FreeTranslationService();

        $this->assertNull($free->translate('Kumusta po', 'fil', 'fil'));
        $this->assertSame(TranslationOutcome::SAME_AS_SOURCE, $free->lastReasonCode());

        $free = new FreeTranslationService();
        $this->assertNull($free->translate('   ', 'fil', 'en'));
        $this->assertSame(TranslationOutcome::NO_SOURCE_TEXT, $free->lastReasonCode());

        $free = new FreeTranslationService();
        $long = str_repeat('Ito ay isang mahabang pangungusap tungkol sa barangay. ', 200);
        $this->assertNull($free->translate($long, 'fil', 'en'));
        $this->assertSame(TranslationOutcome::TEXT_TOO_LONG, $free->lastReasonCode());
    }

    /**
     * The warning shown BEFORE saving must agree with the refusal that
     * happens after it.
     *
     * The first version of this test compared the text length against
     * maxCharacters(), and failed — correctly. That number is 450 × 12, an
     * every-request-filled-to-the-byte ceiling, while chunking happens on
     * sentence boundaries and wastes whatever does not fit. A post can sit
     * comfortably under the advertised character count and still need more
     * than twelve requests.
     *
     * So the predicate is wouldExceedCap(), which chunks the real text the
     * same way translate() does. Anything else tells staff they are fine
     * and then refuses the post after they press save.
     */
    public function testThePreSaveWarningAgreesWithTheActualRefusal(): void
    {
        $free = new FreeTranslationService();

        $short = 'May libreng bakuna bukas sa Barangay Hall mula alas otso ng umaga.';
        $this->assertFalse($free->wouldExceedCap($short));

        $long = str_repeat('Ito ay isang mahabang pangungusap tungkol sa barangay. ', 200);
        $this->assertTrue($free->wouldExceedCap($long));

        // And the prediction matches what translate() then does.
        $this->assertNull($free->translate($long, 'fil', 'en'));
        $this->assertSame(TranslationOutcome::TEXT_TOO_LONG, $free->lastReasonCode());
    }

    public function testTheAdvertisedCeilingIsNeverLowerThanWhatIsAllowed(): void
    {
        $free = new FreeTranslationService();

        // A post right at the headline figure may or may not fit, but the
        // figure itself must not understate the limit — a warning that
        // fires early is still a warning that is wrong.
        $this->assertGreaterThanOrEqual(
            FreeTranslationService::maxChunks() * 100,
            FreeTranslationService::maxCharacters()
        );
        $this->assertSame(0, $free->chunkCount('   '));
    }

    public function testEveryCodeHasAReasonAndAFixInBothLanguages(): void
    {
        $en  = require \dirname(__DIR__, 2) . '/lang/en.php';
        $fil = require \dirname(__DIR__, 2) . '/lang/fil.php';

        foreach (TranslationOutcome::all() as $code) {
            $key = \strtolower($code);

            foreach ([['en', $en], ['fil', $fil]] as [$name, $lang]) {
                $this->assertArrayHasKey($key, $lang['translation_reason'], "{$name}.php has no reason for {$code}");
                $this->assertArrayHasKey($key, $lang['translation_fix'], "{$name}.php has no fix for {$code}");
                $this->assertNotSame('', trim($lang['translation_fix'][$key]));
            }
        }
    }

    /**
     * The fix text for a config problem must name the .env key, because
     * that is the literal string someone editing the file searches for.
     */
    public function testTheFixNamesTheEnvKey(): void
    {
        $en = require \dirname(__DIR__, 2) . '/lang/en.php';

        foreach ([TranslationOutcome::NO_API_KEY, TranslationOutcome::NO_CREDITS] as $code) {
            $this->assertStringContainsString(
                'ANTHROPIC_API_KEY',
                $en['translation_fix'][\strtolower($code)],
                "the fix for {$code} does not name the variable to set"
            );
        }

        $this->assertStringContainsString(
            'TTS_PROVIDER',
            $en['translation_fix'][\strtolower(TranslationOutcome::NO_TTS_PROVIDER)]
        );
    }
}
