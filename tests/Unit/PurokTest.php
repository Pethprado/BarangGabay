<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Purok;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Puroks page: counts per purok and for the barangay, adding residents to a
 * purok, and registering a resident in person. Test rows are removed again.
 */
class PurokTest extends TestCase
{
    private const NAME = 'zz-purok-unit-test';

    protected function tearDown(): void
    {
        db()->prepare('DELETE FROM users WHERE full_name LIKE ?')->execute([self::NAME . '%']);
    }

    private function membersIn(string $purok): int
    {
        foreach (Purok::summary() as $row) {
            if ($row['name'] === $purok) {
                return $row['total'];
            }
        }

        return 0;
    }

    public function testEveryOfficialPurokIsListedEvenWhenEmpty(): void
    {
        $names = array_column(Purok::summary(), 'name');
        foreach (barangay_subdivisions() as $purok) {
            $this->assertContains($purok, $names);
        }
    }

    public function testRegisteringWithoutEmailCountsInThePurokAndBarangay(): void
    {
        $before      = $this->membersIn('Purok 7');
        $totalBefore = Purok::totals()['total'];

        $made = Purok::createResident(['full_name' => self::NAME . ' A', 'purok' => 'Purok 7', 'phone' => '09171234567']);

        $this->assertNull($made['password'], 'no email: nobody gets a password');
        $this->assertTrue(Purok::isPlaceholderEmail($made['email']));
        $this->assertSame($before + 1, $this->membersIn('Purok 7'));
        $this->assertSame($totalBefore + 1, Purok::totals()['total']);

        $member = array_values(array_filter(Purok::members('Purok 7'), static fn ($m) => (int) $m['id'] === $made['id']));
        $this->assertSame('verified', $member[0]['status'] ?? null);
        $this->assertNotContains(
            $made['email'],
            array_column(\App\Models\User::allVerifiedResidentEmails(), 'email'),
            'placeholder addresses never get email blasts'
        );
    }

    public function testRegisteringWithEmailGivesATemporaryPasswordThatWorks(): void
    {
        $email = 'zz-purok-' . bin2hex(random_bytes(3)) . '@example.com';
        $made  = Purok::createResident(['full_name' => self::NAME . ' B', 'purok' => 'Purok 6', 'email' => $email]);

        $this->assertNotNull($made['password']);
        $hash = (string) db()->query('SELECT password_hash FROM users WHERE id = ' . $made['id'])->fetchColumn();
        $this->assertTrue(password_verify($made['password'], $hash));
    }

    public function testAssignMovesResidentsAndRemoveLeavesThemWithoutAPurok(): void
    {
        $made = Purok::createResident(['full_name' => self::NAME . ' C', 'purok' => 'Purok 5']);

        $this->assertContains($made['id'], array_map('intval', array_column(Purok::candidates('Purok 4', self::NAME), 'id')));
        $this->assertSame(1, Purok::assign([$made['id']], 'Purok 4'));
        $this->assertContains($made['id'], array_map('intval', array_column(Purok::members('Purok 4'), 'id')));
        $this->assertNotContains($made['id'], array_map('intval', array_column(Purok::candidates('Purok 4', self::NAME), 'id')));

        Purok::assign([$made['id']], '');
        $this->assertContains($made['id'], array_map('intval', array_column(Purok::members(''), 'id')));
    }

    public function testAssignNeverTouchesStaffAccounts(): void
    {
        $staffId = (int) db()->query("SELECT id FROM users WHERE role <> 'resident' ORDER BY id LIMIT 1")->fetchColumn();
        $this->assertSame(0, Purok::assign([$staffId], 'Purok 3'));
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $made = Purok::createResident(['full_name' => self::NAME . ' Dalisay', 'purok' => 'Purok 2']);
        $this->assertContains($made['id'], array_map('intval', array_column(Purok::members('Purok 2', 'DALISAY'), 'id')));
    }
}
