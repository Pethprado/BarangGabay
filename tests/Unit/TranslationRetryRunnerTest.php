<?php
declare(strict_types=1);

use App\Services\TranslationRetryRunner;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The runner works unattended, overnight, on posts nobody is looking at.
 *
 * That is what makes it useful — a post whose English failed because the
 * free translator's daily allowance ran out fills itself in after the reset,
 * with no staff member awake to notice. It is also what makes it dangerous:
 * anything it gets wrong, it gets wrong quietly and at scale.
 *
 * So the rule that outranks everything else gets its own tests. A staff
 * member who hand-wrote the English while the machine was failing must not
 * come back to find it replaced by a machine translation.
 */
final class TranslationRetryRunnerTest extends TestCase
{
    /**
     * @param array<string,mixed> $row
     * @dataProvider ownership
     */
    public function testWhoWroteALanguageIsReadFromTheAutoFlag(
        string $case,
        array  $row,
        string $lang,
        bool   $byPerson
    ): void {
        $this->assertSame(
            $byPerson,
            TranslationRetryRunner::wasWrittenByAPerson($row, $lang),
            $case
        );
    }

    /** @return array<string, array{0:string, 1:array<string,mixed>, 2:string, 3:bool}> */
    public static function ownership(): array
    {
        return [
            'staff typed the English' => [
                'a filled field with the flag clear was typed by a person',
                ['title_en' => 'Free vaccine tomorrow', 'body_en' => 'At the hall.', 'en_is_auto' => 0],
                'en',
                true,
            ],
            'the machine produced the English' => [
                'a filled field with the flag set may be replaced by a better attempt',
                ['title_en' => 'Free vaccine tomorrow', 'body_en' => 'At the hall.', 'en_is_auto' => 1],
                'en',
                false,
            ],
            'nothing there at all' => [
                'an empty field is not "written by a person"',
                ['title_en' => '', 'body_en' => '', 'en_is_auto' => 0],
                'en',
                false,
            ],
            'only the title was typed' => [
                'half a translation still counts as a person having been here',
                ['title_en' => 'Free vaccine tomorrow', 'body_en' => '', 'en_is_auto' => 0],
                'en',
                true,
            ],
            'only the body was typed' => [
                'the body alone counts too',
                ['title_en' => '', 'body_en' => 'At the hall.', 'en_is_auto' => 0],
                'en',
                true,
            ],
            'whitespace is not text' => [
                'a field holding spaces is empty',
                ['title_en' => '   ', 'body_en' => "\n\t", 'en_is_auto' => 0],
                'en',
                false,
            ],
            'the flag is missing entirely' => [
                'an older row with no flag column defaults to "a person wrote it" — the safe side',
                ['title_en' => 'Free vaccine tomorrow'],
                'en',
                true,
            ],
        ];
    }

    /**
     * Manobo is the trap. The locale is 'msm' everywhere a reader can see
     * it, and the columns are title_manobo / body_manobo / manobo_is_auto.
     * Looking for title_msm finds nothing, reads as "no translation", and
     * hands a hand-written Manobo post to the machine to overwrite.
     */
    public function testManoboIsReadFromTheManoboColumnsNotTheLocaleCode(): void
    {
        $handWritten = [
            'title_manobo'   => 'Libre no bakuna',
            'body_manobo'    => 'Diri to barangay hall.',
            'manobo_is_auto' => 0,
        ];

        $this->assertTrue(
            TranslationRetryRunner::wasWrittenByAPerson($handWritten, 'msm'),
            'hand-written Manobo was not recognised, so the runner would overwrite it'
        );

        $machine = $handWritten;
        $machine['manobo_is_auto'] = 1;

        $this->assertFalse(TranslationRetryRunner::wasWrittenByAPerson($machine, 'msm'));
    }

    /**
     * Events and ordinances call the body `description`. Reading the wrong
     * key does not error — it finds nothing, concludes no person has been
     * here, and overwrites their text.
     */
    public function testTheBodyFieldNameIsHonouredPerContentType(): void
    {
        $event = [
            'title_en'          => '',
            'description_en'    => 'Fiesta on the 24th.',
            'en_is_auto'        => 0,
        ];

        $this->assertTrue(
            TranslationRetryRunner::wasWrittenByAPerson($event, 'en', 'description'),
            'staff text in description_en was missed'
        );
        $this->assertFalse(
            TranslationRetryRunner::wasWrittenByAPerson($event, 'en', 'body'),
            'reading the wrong column should find nothing — this asserts the parameter matters'
        );
    }

    public function testTheSweepIsSmallEnoughToSitInsideAPageLoad(): void
    {
        $this->assertGreaterThan(0, TranslationRetryRunner::SWEEP_BATCH);
        $this->assertLessThanOrEqual(
            5,
            TranslationRetryRunner::SWEEP_BATCH,
            'each item is an HTTP call to a translation service and this runs inside somebody\'s page load'
        );
        $this->assertGreaterThan(
            TranslationRetryRunner::SWEEP_BATCH,
            TranslationRetryRunner::CLI_BATCH,
            'a scheduled run has nobody waiting and should take a bigger bite'
        );
    }

    /**
     * The opportunistic trigger must keep the guards the existing sweep has.
     *
     * Dropping any one of them turns a catch-up into a tax on page loads:
     * without the GET check it fires during a form POST somebody is waiting
     * on; without the throttle it fires on every request; without the
     * try/catch a translation failure takes down the page that triggered it.
     */
    public function testTheOpportunisticSweepIsGuardedLikeTheExistingOne(): void
    {
        $index = (string) file_get_contents(\dirname(__DIR__, 2) . '/public/index.php');

        $at = strpos($index, 'TranslationRetryRunner');
        $this->assertNotFalse($at, 'the retry sweep is not wired into public/index.php at all');

        // The block it lives in, from the enclosing GET guard.
        $guard = strrpos(substr($index, 0, $at), "\$_SERVER['REQUEST_METHOD'] === 'GET'");
        $this->assertNotFalse($guard, 'the sweep is not inside the GET-only guard');

        $block = substr($index, $guard, $at - $guard + 400);

        /*
         * Both halves of the throttle, not just the name.
         *
         * A first version of this asserted only that the session key
         * appeared somewhere in the block — which stayed green when the
         * READ was replaced by a literal 0 and only the WRITE was left. The
         * sweep then fired on every single page load while the test still
         * passed, which is the exact failure a guard test exists to stop.
         */
        $this->assertMatchesRegularExpression(
            '~\$lastRetry\s*=\s*\(int\)\s*\(\$_SESSION\[[\'"]bg_translation_retry_at[\'"]\]\s*\?\?\s*0\)~',
            $block,
            'the sweep does not read its last-run time from the session, so the throttle is dead'
        );
        $this->assertMatchesRegularExpression(
            '~\$_SESSION\[[\'"]bg_translation_retry_at[\'"]\]\s*=\s*time\(\)~',
            $block,
            'the sweep never records that it ran, so the throttle can never hold'
        );
        $this->assertMatchesRegularExpression(
            '~time\(\)\s*-\s*\$lastRetry\s*>=\s*\$retryEvery~',
            $block,
            'the two halves are not actually compared'
        );
        $this->assertStringContainsString('try {', $block);
        $this->assertStringContainsString(
            'catch (\Throwable',
            $block,
            'an unwrapped sweep lets a translation failure take down the page that triggered it'
        );
    }

    /**
     * The scheduled entry point must refuse to run from a browser.
     *
     * It takes a --limit and would otherwise be an unauthenticated way for
     * anyone to spend the barangay's daily translation allowance.
     */
    public function testTheScheduledScriptRefusesWebRequests(): void
    {
        $tool = (string) file_get_contents(\dirname(__DIR__, 2) . '/tools/retry-translations.php');

        $this->assertStringContainsString("PHP_SAPI !== 'cli'", $tool);
        $this->assertStringContainsString('403', $tool);

        // And it must document how to schedule it, since that is the point.
        $this->assertStringContainsString('Task Scheduler', $tool);
    }
}
