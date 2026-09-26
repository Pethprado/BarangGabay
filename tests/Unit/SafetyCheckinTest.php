<?php
declare(strict_types=1);

use App\Models\SafetyCheckin;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The safety check-in's two promises.
 *
 *   1. It stops asking. An advisory that has aged out must not keep the
 *      dashboard asking "are you safe?" about a storm that passed weeks ago,
 *      and must not keep counting people as unaccounted for.
 *
 *   2. What a NEIGHBOUR sees is counts, and only counts. The barangay gets
 *      names, phone numbers and addresses from needingAttention(), because
 *      a tanod is about to walk to a house. A resident gets four integers,
 *      because knowing four people on your street have not answered is
 *      enough to make you knock and discloses nothing about who they are.
 *
 * These run without a database: the guards below either return before any
 * query, or are assertions about the SQL itself. The full round trip —
 * tapping, correcting, and the roll-up reading it back — is exercised
 * against the running site separately.
 */
final class SafetyCheckinTest extends TestCase
{
    private function modelSource(): string
    {
        return (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/models/SafetyCheckin.php'
        );
    }

    /** The body of one method, by name. */
    private function methodBody(string $name): string
    {
        $src = $this->modelSource();
        $at  = strpos($src, 'function ' . $name . '(');
        $this->assertNotFalse($at, "method {$name}() not found");

        $open = strpos($src, '{', $at);
        $this->assertNotFalse($open);

        $depth = 1;
        $i     = $open + 1;
        while ($i < \strlen($src) && $depth > 0) {
            if ($src[$i] === '{')      { $depth++; }
            elseif ($src[$i] === '}')  { $depth--; }
            $i++;
        }
        return substr($src, $open, $i - $open);
    }

    // ── 1. It stops asking ───────────────────────────────────────────────

    public function testTheAskingWindowIsBounded(): void
    {
        $this->assertGreaterThan(0, SafetyCheckin::ASKS_FOR_DAYS);
        $this->assertLessThanOrEqual(
            30,
            SafetyCheckin::ASKS_FOR_DAYS,
            'a check-in that keeps asking for a month is asking about a storm nobody remembers'
        );
    }

    public function testTheDashboardQueryUsesThatWindow(): void
    {
        $body = $this->methodBody('openForUser');

        /* The constant is concatenated into the SQL, so the source reads
           `INTERVAL " . self::ASKS_FOR_DAYS . " DAY`. Asserting on the
           interpolated text would only pass by accident; asserting that the
           query is written in terms of the constant is the real invariant —
           change the constant and the window moves with it. */
        $this->assertMatchesRegularExpression(
            '~INTERVAL\s*"\s*\.\s*self::ASKS_FOR_DAYS\s*\.\s*"\s*DAY~',
            $body,
            'openForUser() does not bound itself by ASKS_FOR_DAYS, so the card would ask forever'
        );
        $this->assertStringContainsString(
            "a.status = 'published'",
            $body,
            'a draft advisory must not ask anyone for a check-in'
        );
        $this->assertStringContainsString(
            'asks_safety_checkin = 1',
            $body,
            'only an advisory that asked for a check-in may prompt for one'
        );
    }

    // ── 2. Counts only ───────────────────────────────────────────────────

    /**
     * The columns a resident-facing query may not select.
     *
     * Listed as the actual column names rather than a vague "no personal
     * data", so the test fails on the specific mistake: someone adding
     * u.full_name to the pulse to make the card friendlier.
     */
    public function testThePurokPulseSelectsNoPersonalColumns(): void
    {
        $body = $this->methodBody('purokPulse');

        foreach (['full_name', 'phone', 'address', 'email', 'id_photo_url'] as $column) {
            $this->assertStringNotContainsString(
                $column,
                $body,
                "purokPulse() reaches for {$column}. It is shown to neighbours, so it carries counts only — "
                . 'names and numbers belong to needingAttention(), behind /admin/safety.'
            );
        }
    }

    public function testThePurokPulseReturnsOnlyIntegersAndThePurokName(): void
    {
        $body = $this->methodBody('purokPulse');

        // The returned array literal, not the query.
        $this->assertMatchesRegularExpression(
            "~'purok'\s*=>\s*\\\$purok~",
            $body,
            'the pulse should echo back the purok it was asked about'
        );

        foreach (['residents', 'answered', 'safe', 'needs_help', 'silent'] as $field) {
            $this->assertMatchesRegularExpression(
                "~'{$field}'\s*=>\s*\(int\)~",
                $body,
                "{$field} must be cast to int on the way out"
            );
        }
    }

    public function testThePulseRefusesToRunWithoutAPurokOrAnAdvisory(): void
    {
        // Both return before any query, so this needs no database.
        $this->assertNull(SafetyCheckin::purokPulse('', 1), 'no purok means there are no neighbours to count');
        $this->assertNull(SafetyCheckin::purokPulse('   ', 1), 'whitespace is not a purok');
        $this->assertNull(SafetyCheckin::purokPulse(null, 1));
        $this->assertNull(SafetyCheckin::purokPulse('Purok 1', 0), 'a check-in always answers a specific advisory');
        $this->assertNull(SafetyCheckin::purokPulse('Purok 1', -3));
    }

    // ── 3. The form contract did not change ──────────────────────────────

    /**
     * The dashboard card posts to the SAME endpoint the announcement page
     * does, with the same fields. Two forms that disagree about the shape of
     * a request is how one of them quietly stops working.
     */
    public function testTheDashboardCardPostsTheExistingContract(): void
    {
        $page = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/resident/home.php'
        );

        /* Scoped to the safety form itself, not the whole page. The dashboard
           carries several forms, so searching the file for name="csrf_token"
           passes even when THIS form has lost it — the assertion would be
           satisfied by somebody else's token. */
        $start = strpos($page, 'action="<?= e(route(\'safety-checkin/');
        $this->assertNotFalse($start, 'the safety form is not on the dashboard');

        $open = strrpos(substr($page, 0, $start), '<form');
        $this->assertNotFalse($open);

        $end = strpos($page, '</form>', $start);
        $this->assertNotFalse($end);

        $form = substr($page, $open, $end - $open);

        $this->assertStringContainsString('name="csrf_token"', $form, 'this form must carry its own CSRF token');
        $this->assertStringContainsString('method="post"', $form);
        $this->assertStringContainsString('name="status" value="safe"', $form);
        $this->assertStringContainsString('name="status" value="needs_help"', $form);
        $this->assertStringContainsString('name="note"', $form);
        $this->assertStringContainsString('maxlength="255"', $form, 'the note column is VARCHAR(255)');
    }

    /**
     * The card must render on an ordinary day, not only during a storm.
     *
     * It first shipped wrapped in a single `if ($safetyAdvisory)`, so with
     * nothing being asked the dashboard showed nothing at all — and the
     * feature could not be found by anyone who was not already in an
     * emergency. That is exactly backwards: where to evacuate is learned
     * before the storm, not during it.
     */
    public function testTheCardHasAQuietStateForWhenNothingIsAsked(): void
    {
        $page = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/app/views/resident/home.php'
        );

        $this->assertStringContainsString(
            'safety-card--quiet',
            $page,
            'the dashboard has no quiet state, so the safety card vanishes whenever no advisory is live'
        );
        $this->assertStringContainsString(
            'if (empty($safetyAdvisory)):',
            $page,
            'the quiet state should be the branch taken when nothing is asking'
        );
        $this->assertStringContainsString(
            'safety-quiet__centre',
            $page,
            'the quiet state should name the evacuation centre — otherwise it is a card that says nothing'
        );
    }

    /**
     * Every label on the card goes through t(), in both languages.
     *
     * A hardcoded English string on an emergency card is the one place a
     * missing translation actually costs something.
     */
    public function testEveryLabelOnTheCardIsTranslated(): void
    {
        $en  = require \dirname(__DIR__, 2) . '/lang/en.php';
        $fil = require \dirname(__DIR__, 2) . '/lang/fil.php';

        $keys = [
            'card_eyebrow', 'card_read', 'change_answer', 'note_saved',
            'pulse_title', 'pulse_none', 'pulse_counts', 'pulse_silent',
            'pulse_all_in', 'pulse_help', 'pulse_knock', 'pulse_private',
            'pulse_no_purok', 'pulse_set',
            'quiet_title', 'quiet_help', 'quiet_centre_label', 'quiet_no_centre',
        ];

        /* Terms that are the same word in both languages. "Safety check-in"
           is what this is called here — the Filipino strings around it use
           the English phrase too — so an identical value is the translation,
           not a missing one. Anything NOT on this list that comes out
           identical almost certainly got copied and never translated. */
        $loanwords = ['card_eyebrow'];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $en['safety'], "safety.{$key} missing from en.php");
            $this->assertArrayHasKey($key, $fil['safety'], "safety.{$key} missing from fil.php");
            $this->assertNotSame('', trim($fil['safety'][$key]), "safety.{$key} is blank in fil.php");

            if (\in_array($key, $loanwords, true)) { continue; }

            $this->assertNotSame(
                $en['safety'][$key],
                $fil['safety'][$key],
                "safety.{$key} is identical in both files — it was probably copied, not translated"
            );
        }
    }
}
