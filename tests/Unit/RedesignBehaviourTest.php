<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Behaviour the UI redesign added beyond appearance:
 *   - the five password rules the registration checklist shows are the same
 *     five the server enforces;
 *   - the back-office EN / FIL / MN switch changes labels only, never which
 *     language version of a post staff are shown.
 */
final class RedesignBehaviourTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['bg_is_back_office'] = false;
        unset($_SESSION['bo_locale']);
        set_locale('fil');
        parent::tearDown();
    }

    public function testStrongPasswordPassesEveryRule(): void
    {
        $this->assertSame([], AuthController::passwordRuleFailures('Bayogo@2026'));
    }

    /** @dataProvider weakPasswords */
    public function testEachMissingRuleIsReported(string $password, string $rule): void
    {
        $this->assertContains($rule, AuthController::passwordRuleFailures($password));
    }

    public static function weakPasswords(): array
    {
        return [
            'too short'    => ['Ab1!',        'length'],
            'no uppercase' => ['bayogo@2026', 'upper'],
            'no lowercase' => ['BAYOGO@2026', 'lower'],
            'no number'    => ['Bayogo@abcd', 'number'],
            'no symbol'    => ['Bayogo2026x', 'symbol'],
        ];
    }

    public function testBackOfficeLocaleDefaultsToEnglish(): void
    {
        unset($_SESSION['bo_locale']);
        $this->assertSame('en', back_office_locale());
    }

    public function testBackOfficeSwitchTranslatesLabelsButNotContentLocale(): void
    {
        $GLOBALS['bg_is_back_office'] = true;
        set_back_office_locale('fil');

        $this->assertSame('Mga Anunsyo', t('admin_nav.announcements'), 'Labels follow the back-office switch');
        $this->assertSame('en', current_locale(), 'Content stays the original in the back office');
    }

    public function testBackOfficeChoiceDoesNotLeakIntoResidentPages(): void
    {
        set_locale('en');
        set_back_office_locale('fil');
        $GLOBALS['bg_is_back_office'] = false;

        $this->assertSame('Announcements', t('admin_nav.announcements'));
    }

    public function testUnknownBackOfficeLocaleIsIgnored(): void
    {
        set_back_office_locale('xx');
        $this->assertSame('en', back_office_locale());
    }
}
