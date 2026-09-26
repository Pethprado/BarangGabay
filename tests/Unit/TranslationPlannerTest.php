<?php
declare(strict_types=1);

use App\Services\FreeTranslationService;
use App\Services\TranslationOutcome;
use App\Services\TranslationPlanner;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The plan panel is only worth having if it agrees with what Save does.
 *
 * A panel that says "English will be generated" followed by a post with no
 * English version is worse than no panel: it teaches staff to stop reading
 * it. So the tests here are mostly about the two places where a plausible
 * shortcut would make it lie.
 *
 * Reads and writes nothing except through the planner, which needs a
 * database for its credit-evidence check — so the assertions that would
 * touch it are in the end-to-end probe instead. What is tested here is the
 * reasoning that does not.
 */
final class TranslationPlannerTest extends TestCase
{
    private function source(): string
    {
        $src = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/services/TranslationPlanner.php'
        );

        // Comments out, so a rule that was commented away cannot pass by
        // still being present as prose.
        $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);

        return (string) preg_replace('#(?<!:)//[^\n]*#', '', $src);
    }

    /**
     * The cap check must use the real chunker, not a character count.
     *
     * Text is split on SENTENCE boundaries, so a post can sit well under
     * the advertised character limit and still need more than the twelve
     * requests it is allowed. Counting characters would tell staff they
     * are fine and let the save fail anyway — the exact experience this
     * whole feature exists to end.
     */
    public function testTheCapCheckUsesTheRealChunker(): void
    {
        $src = $this->source();

        $this->assertStringContainsString(
            'chunkCount(',
            $src,
            'the planner does not chunk the text, so its cap warning is a guess'
        );
        $this->assertStringContainsString('maxChunks()', $src);
    }

    /**
     * The source language must come from resolveSourceLang(), not from a
     * second copy of its rules.
     *
     * That function is the order of authority — staff choice, then the
     * text, then what the row already said, then Filipino. A planner that
     * re-implemented it would be free to disagree with the save that
     * follows, and the disagreement would show up as a post translated
     * into the language it is already in.
     */
    public function testTheSourceLanguageComesFromTheOneImplementation(): void
    {
        $this->assertStringContainsString(
            'TranslationService::resolveSourceLang(',
            $this->source(),
            'the planner decides the source language on its own terms'
        );
    }

    /**
     * And it must actually USE the answer.
     *
     * The structural check above stayed green when the call was reduced to
     * dead code inside a `'fil' ?: …` expression — present in the file,
     * never evaluated. These run the real function and compare its answer
     * with resolveSourceLang()'s, which is the only thing that matters.
     *
     * @dataProvider sourceCases
     */
    public function testThePlanAgreesWithTheSaveAboutTheSourceLanguage(
        string $title,
        string $body,
        string $override,
        string $because
    ): void {
        $expected = \App\Services\TranslationService::resolveSourceLang(
            $override,
            $title,
            $body,
            'fil'
        );

        $plan = TranslationPlanner::plan($title, $body, $override);

        $this->assertSame($expected, $plan['source']['lang'], $because);
    }

    /** @return array<string, array{0:string,1:string,2:string,3:string}> */
    public static function sourceCases(): array
    {
        $english  = 'There will be a free vaccination tomorrow at the Barangay Hall '
                  . 'from eight in the morning until five in the afternoon.';
        $filipino = 'May libreng bakuna bukas sa Barangay Hall mula alas otso ng umaga '
                  . 'hanggang alas singko ng hapon.';

        return [
            'English text, left on auto' => [
                'Free vaccination tomorrow', $english, 'auto',
                'the text should decide when nobody has chosen',
            ],
            'Filipino text, left on auto' => [
                'Libreng bakuna bukas', $filipino, 'auto',
                'the text should decide when nobody has chosen',
            ],
            'English text, staff chose Filipino' => [
                'Free vaccination tomorrow', $english, 'fil',
                'the staff choice outranks the text — they read the post',
            ],
            'Filipino text, staff chose English' => [
                'Libreng bakuna bukas', $filipino, 'en',
                'the staff choice outranks the text',
            ],
        ];
    }

    /**
     * A staff choice that contradicts the text is flagged, not overruled.
     *
     * Their choice wins — but an English caption filed as Filipino is
     * "translated" into the language it is already in, and a resident
     * tapping FIL is shown English under a Filipino badge. That was the
     * reported bug this whole panel exists to make visible.
     */
    public function testAContradictedSourceChoiceIsFlagged(): void
    {
        $english = 'There will be a free vaccination tomorrow at the Barangay Hall '
                 . 'from eight in the morning until five in the afternoon.';

        $wrong = TranslationPlanner::plan('Free vaccination tomorrow', $english, 'fil');
        $this->assertSame('fil', $wrong['source']['lang'], 'the choice must still win');
        $this->assertTrue($wrong['source']['overridden'], 'the disagreement was not reported');
        $this->assertSame('en', $wrong['source']['detected']);

        $right = TranslationPlanner::plan('Free vaccination tomorrow', $english, 'en');
        $this->assertFalse($right['source']['overridden'], 'a correct choice must not be nagged about');
    }

    /**
     * A key that exists is not a key that works.
     *
     * Whether ANTHROPIC_API_KEY is set can be read from .env; whether its
     * account has credits cannot be known without spending one. Promising
     * Manobo on the strength of the variable alone breaks on every save
     * for an empty account — the state this barangay has been in for weeks.
     *
     * Run, not read. An earlier version of this test only checked that the
     * source file mentioned seenRecently(), and stayed green when the call
     * was neutered to `false && seenRecently(...)`: the words were still
     * there and the check was dead. Searching a file for a string cannot
     * distinguish live code from a comment or a short-circuited expression.
     *
     * @dataProvider anthropicStates
     */
    public function testAnEmptyAccountIsNotTreatedAsAWorkingKey(
        bool   $keySet,
        bool   $outOfCredits,
        bool   $usable,
        string $because
    ): void {
        $this->assertSame(
            $usable,
            TranslationPlanner::anthropicUsable($keySet, $outOfCredits),
            $because
        );
    }

    /** @return array<string, array{0:bool,1:bool,2:bool,3:string}> */
    public static function anthropicStates(): array
    {
        return [
            'key set, credits available' => [
                true, false, true, 'a working key should be used',
            ],
            'key set, account empty' => [
                true, true, false,
                'a key with no credits must not be promised — it fails on every save',
            ],
            'no key at all' => [
                false, false, false, 'there is nothing to call',
            ],
            'no key, and stale evidence of no credits' => [
                false, true, false, 'still nothing to call',
            ],
        ];
    }

    /**
     * The evidence lookup only runs when there is a key to doubt, and a
     * failed lookup errs toward optimism rather than reporting a provider
     * as down.
     */
    public function testTheCreditCheckIsSkippedWhenThereIsNoKey(): void
    {
        $this->assertFalse(
            TranslationPlanner::looksOutOfCredits(false),
            'with no key there is no account to be empty'
        );
    }

    /**
     * With a key, the lookup must actually happen.
     *
     * The assertion above passes whether or not it does — with no key the
     * expression short-circuits either way — so it did not notice the
     * lookup being neutered to `false && seenRecently(...)`.
     *
     * Proving it behaviourally needs a database, and this suite
     * deliberately has none: it runs in milliseconds and on a machine with
     * no MySQL, which is worth more than covering one line. Wiring the DB
     * in would also mean every test run replays the migrations.
     *
     * So the guard here is structural — the conjunction must be live, with
     * the key on the left and the real lookup on the right. It is weaker
     * than running the code and it is honest about that; the behavioural
     * proof is in the end-to-end probe, which writes a NO_CREDITS row and
     * watches the plan change from "generate" to "maybe".
     */
    public function testTheCreditLookupIsNotShortCircuited(): void
    {
        $src = $this->source();

        $at = strpos($src, 'function looksOutOfCredits');
        $this->assertNotFalse($at, 'looksOutOfCredits() is gone');

        $body = substr($src, $at, 400);

        $this->assertMatchesRegularExpression(
            '~return\s+\$keySet\s*&&\s*\\\\?App\\\\Models\\\\TranslationAttempt::seenRecently\(~',
            $body,
            'the credit lookup is not reached — a constant on the left of && makes it dead code, '
            . 'and the panel goes back to promising translations that fail on every save'
        );
    }

    /**
     * The two Anthropic problems need different fixes, so they must not
     * collapse into one message. Telling someone to set a variable that is
     * already set sends them to the wrong file.
     */
    public function testAMissingKeyAndAnEmptyAccountAreDistinguished(): void
    {
        $en = require \dirname(__DIR__, 2) . '/lang/en.php';

        $noKey    = $en['translation_fix'][\strtolower(TranslationOutcome::NO_API_KEY)];
        $noCredit = $en['translation_fix'][\strtolower(TranslationOutcome::NO_CREDITS)];

        $this->assertNotSame($noKey, $noCredit);
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $noKey);
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $noCredit);
        $this->assertStringContainsStringIgnoringCase('credit', $noCredit);
    }

    /**
     * Every "will" the planner can return needs a sentence, in both
     * languages — a panel that renders "will_maybe" as a bare key is worse
     * than a panel that says nothing.
     */
    public function testEveryOutcomeTheePanelCanShowIsTranslated(): void
    {
        $en  = require \dirname(__DIR__, 2) . '/lang/en.php';
        $fil = require \dirname(__DIR__, 2) . '/lang/fil.php';

        foreach (['source', 'generate', 'maybe', 'skip'] as $will) {
            $this->assertArrayHasKey("will_{$will}", $en['translation_plan']);
            $this->assertArrayHasKey("will_{$will}", $fil['translation_plan']);
        }

        foreach (['how_chosen', 'how_detected', 'how_default', 'mismatch', 'too_long_warning'] as $key) {
            $this->assertArrayHasKey($key, $en['translation_plan'], "en.php has no {$key}");
            $this->assertArrayHasKey($key, $fil['translation_plan'], "fil.php has no {$key}");
        }
    }

    /**
     * The mismatch warning must name both languages, or it is advice
     * nobody can act on.
     */
    public function testTheMismatchWarningNamesBothLanguages(): void
    {
        foreach ([require \dirname(__DIR__, 2) . '/lang/en.php',
                  require \dirname(__DIR__, 2) . '/lang/fil.php'] as $lang) {
            $msg = $lang['translation_plan']['mismatch'];
            $this->assertStringContainsString(':chosen', $msg);
            $this->assertStringContainsString(':detected', $msg);
        }
    }

    /** The endpoint is a POST behind auth, like every other admin action. */
    public function testThePlanEndpointIsProtected(): void
    {
        $routes = require \dirname(__DIR__, 2) . '/routes/web.php';
        $found  = false;

        foreach ($routes as $route) {
            if (($route[1] ?? '') !== '/admin/translation-plan') { continue; }
            $found = true;
            $this->assertSame('POST', $route[0]);
            $this->assertContains('auth', $route[3] ?? []);
            $this->assertContains('role:admin,staff', $route[3] ?? []);
        }

        $this->assertTrue($found, '/admin/translation-plan is not routed');

        $controller = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/controllers/TranslationHealthController.php'
        );
        $controller = (string) preg_replace('#/\*.*?\*/#s', '', $controller);
        $controller = (string) preg_replace('#(?<!:)//[^\n]*#', '', $controller);

        $at   = strpos($controller, 'function plan()');
        $body = substr($controller, (int) $at, 900);

        $this->assertStringContainsString('check_csrf()', $body, 'the plan endpoint has no CSRF check');
        $this->assertStringContainsString('mb_substr', $body,
            'the input is unbounded — this runs on every pause in typing');
    }

    /**
     * The panel is advice. A failed request must leave the form exactly as
     * it was, because blocking a barangay notice over a cosmetic feature
     * would be the wrong trade.
     */
    public function testTheePanelNeverBlocksTheForm(): void
    {
        $partial = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/shared/_translation-plan.php'
        );

        $this->assertStringContainsString('catch', $partial, 'a failed fetch is not handled');
        $this->assertStringContainsString('setTimeout', $partial,
            'the request is not debounced, so it fires on every keystroke');
    }

    /** The advertised ceiling and the enforced limit must stay related. */
    public function testTheAdvertisedCeilingMatchesTheEnforcedLimit(): void
    {
        $free = new FreeTranslationService();

        $this->assertSame(0, $free->chunkCount(''));
        $this->assertGreaterThan(0, FreeTranslationService::maxChunks());
        $this->assertGreaterThan(
            FreeTranslationService::maxChunks(),
            FreeTranslationService::maxCharacters(),
            'the character ceiling should exceed the request count it is derived from'
        );
    }
}
