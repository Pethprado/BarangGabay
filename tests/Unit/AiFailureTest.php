<?php
declare(strict_types=1);

use App\Services\AIService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * A failed AI call has to say something true.
 *
 * For nine days this system answered every failure with "AI service
 * unavailable. Please try again later." and a Try again button. The actual
 * cause, logged nowhere, was a zero credit balance — so the advice was
 * wrong, the button could not succeed, and nothing recorded why.
 *
 * Three rules come out of that, and these are them:
 *
 *   1. Every failure is classified. "Unknown" is allowed; unclassified is
 *      not, because the message and the retry both hang off the class.
 *   2. Only failures that can pass on a second attempt are retryable. A
 *      billing or configuration problem is not fixed by tapping again.
 *   3. Every class has its own sentence, in both languages.
 */
final class AiFailureTest extends TestCase
{
    /**
     * Real messages, as the API and the HTTP stack actually phrase them.
     * Taken from this project's own error log rather than invented, so the
     * classifier is tested against what it will really be handed.
     *
     * @return array<string, array{0:string, 1:string, 2:bool}>
     */
    public static function failures(): array
    {
        return [
            'no credits (the one that was live)' => [
                'Your credit balance is too low to access the Anthropic API. '
                . 'Please go to Plans & Billing to upgrade or purchase credits.',
                'billing',
                false,
            ],
            'connection refused' => [
                'cURL error 28: Failed to connect to api.anthropic.com port 443 after 21036 ms: '
                . "Couldn't connect to server",
                'network',
                true,
            ],
            'rate limited' => [
                'Number of request tokens has exceeded your per-minute rate limit',
                'rate_limit',
                true,
            ],
            'overloaded' => ['Overloaded', 'rate_limit', true],
            'bad key'    => ['invalid x-api-key', 'auth', false],
            'no permission' => [
                'Your API key does not have permission to use the specified resource',
                'auth',
                false,
            ],
            'dns failure' => ['cURL error 6: Could not resolve host: api.anthropic.com', 'network', true],
            'something new' => ['A brand new failure nobody has seen', 'unknown', false],
        ];
    }

    /** @dataProvider failures */
    public function testFailuresAreClassified(string $message, string $expected, bool $retryable): void
    {
        $this->assertSame(
            $expected,
            AIService::classifyFailure(new RuntimeException($message)),
            "misclassified: {$message}"
        );
    }

    /** @dataProvider failures */
    public function testOnlyRecoverableFailuresOfferARetry(string $message, string $expected, bool $retryable): void
    {
        $this->assertSame(
            $retryable,
            AIService::isRetryable(new RuntimeException($message)),
            $retryable
                ? "a retry should be offered for: {$message}"
                : "a retry is offered for something that cannot succeed: {$message}"
        );
    }

    /**
     * An unrecognised failure must not be treated as retryable.
     *
     * Defaulting to "sure, try again" is how the original bug read to
     * everyone: plausible, and wrong for the case that was actually
     * happening. Unknown means unknown.
     */
    public function testAnUnknownFailureIsNotAssumedRecoverable(): void
    {
        $this->assertFalse(AIService::isRetryable(new RuntimeException('???')));
    }

    public function testEveryClassHasASentenceInBothLanguages(): void
    {
        $en  = require \dirname(__DIR__, 2) . '/lang/en.php';
        $fil = require \dirname(__DIR__, 2) . '/lang/fil.php';

        foreach (['billing', 'rate_limit', 'auth', 'network', 'unknown'] as $kind) {
            $this->assertArrayHasKey("err_{$kind}", $en['ai_summary'], "en.php has no ai_summary.err_{$kind}");
            $this->assertArrayHasKey("err_{$kind}", $fil['ai_summary'], "fil.php has no ai_summary.err_{$kind}");
        }

        $this->assertArrayHasKey('fallback_label', $en['ai_summary']);
        $this->assertArrayHasKey('fallback_label', $fil['ai_summary']);
    }

    /**
     * The message for a billing failure must not tell anyone to try again.
     *
     * This is the specific sentence that was wrong, so it gets its own
     * assertion rather than resting on a reviewer noticing.
     */
    public function testTheBillingMessageDoesNotPromiseARetry(): void
    {
        $en  = require \dirname(__DIR__, 2) . '/lang/en.php';
        $fil = require \dirname(__DIR__, 2) . '/lang/fil.php';

        $this->assertStringNotContainsStringIgnoringCase(
            'try again',
            $en['ai_summary']['err_billing'],
            'the billing message sends the reader back to a button that cannot work'
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'subukan muli',
            $fil['ai_summary']['err_billing']
        );
    }

    /**
     * The controller must log the failure and offer the ordinance text.
     *
     * Both were missing, and the missing log is why the cause could only be
     * guessed at from the screen.
     */
    public function testTheSummariseHandlerLogsAndFallsBack(): void
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/controllers/AIController.php'
        );

        $at = strpos($source, 'public function summarize()');
        $this->assertNotFalse($at, 'summarize() not found');

        // The method body, up to the next method declaration.
        $next = strpos($source, 'private function isStaff', $at);
        $body = substr($source, $at, ($next !== false ? $next : strlen($source)) - $at);

        $this->assertStringContainsString('error_log(', $body, 'a failed summary is still logged nowhere');
        $this->assertStringContainsString('classifyFailure', $body, 'the failure is not classified');
        $this->assertStringContainsString("'retryable'", $body, 'the client is not told whether a retry can work');
        $this->assertStringContainsString("'fallback'", $body, 'nothing is offered in place of the summary');
    }
}
