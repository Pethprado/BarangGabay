<?php
declare(strict_types=1);

use App\Services\ErrorRemedy;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Pins what the error log claims it can repair.
 *
 * The risk this guards is not a crash — it is a false success. An error marked
 * resolved because a button ran leaves the list looking clean while the bug is
 * still live, and nobody re-reads a resolved row. So the assertions here are
 * mostly about restraint: that the unfixable stay unfixable, that a repair
 * cannot be pointed outside the uploads folder, and that "verified" is never
 * set by anything except an actual re-check.
 */
final class ErrorRemedyTest extends TestCase
{
    /** @return array<string,mixed> */
    private function log(string $message, string $type = 'PDOException', string $file = ''): array
    {
        return ['id' => 1, 'type' => $type, 'message' => $message, 'file' => $file, 'line' => 1];
    }

    // ── Recognition ──────────────────────────────────────────────────────────

    /**
     * The exact error sitting in the barangay's log when this was built.
     * Worth pinning as itself rather than as a paraphrase.
     */
    public function testTheRealLoggedErrorIsRecognised(): void
    {
        $dx = ErrorRemedy::diagnose($this->log(
            "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'related_id' in 'where clause'"
        ));

        $this->assertSame('db_missing_column', $dx['code']);
        $this->assertSame('related_id', $dx['subject']);
        $this->assertTrue($dx['fixable']);
    }

    public function testAMissingTableIsRecognisedAndNamed(): void
    {
        $dx = ErrorRemedy::diagnose($this->log(
            "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'baranggabay.post_audio' doesn't exist"
        ));

        $this->assertSame('db_missing_table', $dx['code']);
        $this->assertSame('post_audio', $dx['subject'], 'The database prefix must be stripped');
        $this->assertTrue($dx['fixable']);
    }

    public function testAMissingUploadFolderIsRecognised(): void
    {
        $dx = ErrorRemedy::diagnose($this->log(
            'Could not create the voice upload directory: /public/uploads/voice',
            'RuntimeException'
        ));

        $this->assertSame('missing_directory', $dx['code']);
        $this->assertSame('uploads/voice', $dx['subject']);
        $this->assertTrue($dx['fixable']);
    }

    // ── Restraint: what must NOT be claimed as fixable ───────────────────────

    /**
     * An unpaid API bill is not an engineering problem, and a wrench that
     * pretended to fix it would be lying to the one person who can pay it.
     */
    public function testBillingAndConfigurationAreDiagnosedButNotFixable(): void
    {
        $cases = [
            'ai_no_credit'     => 'Your credit balance is too low to access the Anthropic API.',
            'ai_no_key'        => 'ANTHROPIC_API_KEY not configured. Add it to .env to enable auto-translation.',
            'tts_unconfigured' => 'No text-to-speech provider is configured.',
            'permissions'      => 'fopen(/var/x): Permission denied',
            'network'          => 'cURL error 28: Operation timed out',
        ];

        foreach ($cases as $expected => $message) {
            $dx = ErrorRemedy::diagnose($this->log($message, 'RuntimeException'));

            $this->assertSame($expected, $dx['code'], $message);
            $this->assertFalse($dx['fixable'], "{$expected} must never offer a repair button");
        }
    }

    public function testAnUnrecognisedErrorIsNotFixable(): void
    {
        $dx = ErrorRemedy::diagnose($this->log('Something nobody has seen before', 'LogicException'));

        $this->assertSame('unknown', $dx['code']);
        $this->assertFalse($dx['fixable']);
        $this->assertNotSame('', $dx['explain'], 'Even an unknown error gets a starting point');
    }

    /** An error from a deleted one-off script is history, not a fault. */
    public function testAnErrorFromADeletedFileIsCalledOut(): void
    {
        $dx = ErrorRemedy::diagnose(
            $this->log('Anything at all', 'Exception', 'C:/xampp/htdocs/BarangGabay/verify_scheduling.php')
        );

        $this->assertSame('stale_script', $dx['code']);
        $this->assertSame('verify_scheduling.php', $dx['subject']);
        $this->assertFalse($dx['fixable']);
    }

    public function testAnErrorFromAFileThatStillExistsIsNotCalledStale(): void
    {
        $dx = ErrorRemedy::diagnose(
            $this->log('Anything at all', 'Exception', __FILE__)
        );

        $this->assertNotSame('stale_script', $dx['code']);
    }

    // ── Every diagnosis is written, not improvised ───────────────────────────

    /**
     * t() returns the key itself when a string is missing, so an untranslated
     * diagnosis would render as "remedy.db_missing_table.title" on the page.
     */
    public function testEveryDiagnosisHasRealTextInBothLanguages(): void
    {
        $messages = [
            "Unknown column 'x' in 'where clause'",
            "Table 'db.y' doesn't exist",
            'Could not create the upload directory: /public/uploads/events',
            'credit balance is too low',
            'ANTHROPIC_API_KEY not configured',
            'No text-to-speech provider is configured',
            'Permission denied',
            'cURL error 7: Connection refused',
            'Totally unrecognised',
        ];

        foreach (['en', 'fil'] as $locale) {
            set_locale($locale);

            foreach ($messages as $message) {
                $dx = ErrorRemedy::diagnose($this->log($message));

                $this->assertStringNotContainsString('remedy.', $dx['title'], "{$locale}: {$message}");
                $this->assertStringNotContainsString('remedy.', $dx['explain'], "{$locale}: {$message}");
                $this->assertNotEmpty($dx['steps'], "{$locale}: {$message} should suggest something");

                foreach ($dx['steps'] as $step) {
                    $this->assertStringNotContainsString('remedy.', $step);
                }
            }
        }

        set_locale('fil');
    }

    /** The captured name must reach the reader, not sit unused in a template. */
    public function testTheDiagnosisNamesTheThingThatIsMissing(): void
    {
        set_locale('en');

        $dx = ErrorRemedy::diagnose($this->log("Table 'baranggabay.sms_logs' doesn't exist"));
        $this->assertStringContainsString('sms_logs', $dx['explain']);

        set_locale('fil');
    }

    // ── A repair cannot be aimed outside the uploads folder ──────────────────

    /**
     * The folder remedy is the only one that writes to disk, and the path it
     * writes to comes out of an exception message. Escaping public/uploads
     * must be impossible regardless of what that message contains.
     */
    public function testTheFolderRemedyRefusesAnythingOutsideUploads(): void
    {
        $remedy = new ErrorRemedy();

        $hostile = [
            'failed to open stream: No such file or directory in uploads/../../windows',
            'upload directory uploads/..%2f..%2fetc',
        ];

        foreach ($hostile as $message) {
            $dx = ErrorRemedy::diagnose($this->log($message, 'RuntimeException'));

            if ($dx['code'] !== 'missing_directory') {
                continue;          // not recognised at all is also a safe answer
            }

            $result = $remedy->apply($this->log($message, 'RuntimeException'));

            $this->assertFalse(
                $result['verified'],
                'A path outside uploads/ must never be reported as repaired'
            );
        }

        // And nothing escaped onto disk.
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../../public/windows');
        $this->assertDirectoryDoesNotExist(__DIR__ . '/../../windows');
    }

    /** A diagnosis with no remedy must not report that one ran. */
    public function testApplyDoesNothingForAnUnfixableError(): void
    {
        $result = (new ErrorRemedy())->apply(
            $this->log('Your credit balance is too low to access the Anthropic API.', 'RuntimeException')
        );

        $this->assertSame('ai_no_credit', $result['code']);
        $this->assertFalse($result['attempted'], 'Nothing should have been run');
        $this->assertFalse($result['verified'], 'Nothing was fixed, so nothing may be claimed');
        $this->assertNotEmpty($result['detail'], 'It should still say what to do');
    }
}
