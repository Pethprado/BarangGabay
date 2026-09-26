<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Guards the login entry points: Resident, Staff, and an unadvertised system
 * door. Each admits only its own roles.
 *
 * Two properties hold the whole design together, and both are pinned below.
 *
 *   The gate can only refuse. A door is consulted after the password has been
 *   verified, to decide whether that account is turned away. There is no value
 *   a client can put in the query string that turns a resident account into a
 *   staff session — the role is still read from the user record, and the one
 *   authentication endpoint is still shared.
 *
 *   The gate cannot be used to enumerate. Because it runs after the password
 *   check, every door answers "wrong email or password" to anyone who does not
 *   already hold working credentials. Moving that check earlier — looking an
 *   email up and rejecting it by role before testing the password — would turn
 *   the staff page into a free "is this a staff account?" oracle. That is the
 *   specific regression testGateRunsOnlyAfterThePasswordIsVerified() exists to
 *   catch, because it would look like a harmless reordering in a diff.
 */
final class LoginEntryPointsTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    /** Call a private method on AuthController. */
    private function call(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod(AuthController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(new AuthController(), $args);
    }

    private function constant(string $name): mixed
    {
        return (new ReflectionClass(AuthController::class))->getConstant($name);
    }

    // ── One endpoint ─────────────────────────────────────────────────────────

    /**
     * Separate forms per role would triple the surface to protect and secure
     * nothing, since anyone can open any of those URLs anyway.
     */
    public function testThereIsExactlyOneLoginEndpoint(): void
    {
        $routes = array_merge(
            require __DIR__ . '/../../routes/web.php',
            require __DIR__ . '/../../routes/api.php'
        );

        $gets = $posts = [];
        foreach ($routes as [$method, $path, $handler, $middleware]) {
            if (!str_contains(strtolower($path), 'login')) {
                continue;
            }
            if ($method === 'GET')  { $gets[]  = $path . ' → ' . $handler; }
            if ($method === 'POST') { $posts[] = $path . ' → ' . $handler; }
        }

        $this->assertSame(['/login → AuthController@showLogin'], $gets);
        $this->assertSame(['/login → AuthController@login'], $posts);
    }

    // ── The whitelist ────────────────────────────────────────────────────────

    public function testKnownEntriesResolveToThemselves(): void
    {
        foreach (['resident', 'staff', 'system'] as $entry) {
            $this->assertSame($entry, $this->call('loginEntry', [$entry]));
        }
    }

    /**
     * Anything unrecognised falls back to the default door. Note in particular
     * that a role name is NOT an entry: ?as=superadmin selects nothing.
     */
    public function testAnythingElseFallsBackToTheDefaultEntry(): void
    {
        $hostile = [
            'admin', 'superadmin', 'root',          // role names are not doors
            '', ' ', 'Resident ', 'STAFF',          // whitespace / casing
            '../system', 'staff&as=system',
            '<script>alert(1)</script>',
            null, 42, [], new stdClass(),
        ];

        foreach ($hostile as $value) {
            $entry = $this->call('loginEntry', [$value]);
            $this->assertContains(
                $entry,
                ['resident', 'staff', 'system'],
                'loginEntry must only ever return a known key'
            );
        }

        // The ones that are not simply a case variant land on the default.
        $this->assertSame('resident', $this->call('loginEntry', ['superadmin']));
        $this->assertSame('resident', $this->call('loginEntry', ['<script>alert(1)</script>']));
        $this->assertSame('resident', $this->call('loginEntry', [null]));
        $this->assertSame('resident', $this->call('loginEntry', [['staff']]));
    }

    /** Case and stray whitespace are tolerated rather than 404-ing a staff member. */
    public function testEntryMatchingIsForgivingAboutCaseAndSpacing(): void
    {
        $this->assertSame('staff', $this->call('loginEntry', ['  STAFF ']));
    }

    // ── Nothing raw reaches the page ─────────────────────────────────────────

    /**
     * The redirect a failed sign-in comes back to is rebuilt from the
     * whitelist, so a crafted ?as= cannot be reflected into a Location header.
     */
    public function testTheFailureRedirectIsRebuiltNotEchoed(): void
    {
        $cases = [
            'staff'                      => '/login?as=staff',
            'system'                     => '/login?as=system',
            'resident'                   => '/login',
            'superadmin'                 => '/login',
            "staff\r\nSet-Cookie: x=1"   => '/login',
            'https://evil.example/'      => '/login',
        ];

        foreach ($cases as $raw => $expected) {
            $_GET['as'] = $raw;
            $this->assertSame($expected, $this->call('loginRedirectPath'));
        }

        $_GET = [];
        $this->assertSame('/login', $this->call('loginRedirectPath'));
    }

    // ── Which door admits whom ───────────────────────────────────────────────

    /**
     * The full matrix, written out rather than generated, so a change to any
     * single cell has to be made deliberately.
     */
    public function testEachDoorAdmitsOnlyItsOwnRoles(): void
    {
        $expected = [
            //            resident  staff  admin  superadmin
            'resident' => [true,    false, false, false],
            'staff'    => [false,   true,  true,  true],
            'system'   => [false,   false, false, true],
        ];

        $roles = ['resident', 'staff', 'admin', 'superadmin'];

        foreach ($expected as $entry => $row) {
            foreach ($roles as $i => $role) {
                $this->assertSame(
                    $row[$i],
                    $this->call('entryAdmits', [$entry, $role]),
                    "The '{$entry}' door and the '{$role}' role"
                );
            }
        }
    }

    /** An unknown role is admitted nowhere — fail closed, not open. */
    public function testAnUnknownRoleIsAdmittedNowhere(): void
    {
        foreach (['resident', 'staff', 'system'] as $entry) {
            $this->assertFalse($this->call('entryAdmits', [$entry, 'guest']));
            $this->assertFalse($this->call('entryAdmits', [$entry, '']));
        }
    }

    /**
     * The "use that page instead" link must only ever name a door the page
     * already advertises, or a rejection would disclose the system entry.
     */
    public function testTheSuggestedDoorIsNeverTheUnadvertisedOne(): void
    {
        foreach (['resident', 'staff', 'admin', 'superadmin', 'anything-else'] as $role) {
            $suggested = $this->call('entryForRole', [$role]);

            $this->assertContains($suggested, $this->constant('PUBLIC_ENTRIES'));
            $this->assertNotSame('system', $suggested);
        }

        $this->assertSame('resident', $this->call('entryForRole', ['resident']));
        $this->assertSame('staff',    $this->call('entryForRole', ['superadmin']));
    }

    /**
     * The gate must sit after the password check.
     *
     * Before it, the staff page would answer "this page is for staff only" to
     * any address typed into it — telling a stranger which accounts are staff
     * accounts without a password. After it, only someone who already has
     * working credentials can tell the doors apart.
     */
    public function testGateRunsOnlyAfterThePasswordIsVerified(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/controllers/AuthController.php');

        $passwordCheck = strpos($source, '!password_verify($password, $user[\'password_hash\'])');
        $roleGate      = strpos($source, '!$this->entryAdmits($entry,');

        $this->assertIsInt($passwordCheck, 'The password check should still be here');
        $this->assertIsInt($roleGate, 'The entry gate should still be here');
        $this->assertGreaterThan(
            $passwordCheck,
            $roleGate,
            'The entry gate must never be evaluated before the password'
        );
    }

    /**
     * A wrong door is not a wrong password, and must not spend an account's
     * lockout budget — otherwise clicking the wrong link three times would
     * lock someone out of the right one.
     */
    public function testAWrongDoorDoesNotCountTowardTheLockout(): void
    {
        $lockoutReasons = (new ReflectionClass(\App\Models\LoginAttempt::class))
            ->getConstant('LOCKOUT_REASONS');

        $this->assertNotContains(\App\Models\LoginAttempt::REASON_WRONG_ENTRY, $lockoutReasons);

        // But a genuinely wrong password still does.
        $this->assertContains(\App\Models\LoginAttempt::REASON_BAD_CREDENTIALS, $lockoutReasons);
    }

    // ── The superadmin door is not advertised ────────────────────────────────

    /**
     * A public link labelled "Super Admin" would point every scanner at the
     * highest-value account and buy nothing — that account already signs in
     * from the ordinary form.
     */
    public function testTheSystemEntryIsNeverLinkedFromThePage(): void
    {
        $public = $this->constant('PUBLIC_ENTRIES');

        $this->assertSame(['resident', 'staff'], $public);
        $this->assertNotContains('system', $public);

        // It still has to exist as a reachable door.
        $this->assertArrayHasKey('system', $this->constant('LOGIN_ENTRIES'));
    }

    public function testTheLoginViewNeverNamesTheSuperadminRole(): void
    {
        $view = (string) file_get_contents(__DIR__ . '/../../app/views/auth/login.php');

        $this->assertStringNotContainsString('superadmin', $view);
        $this->assertStringNotContainsString('as=system', $view);
    }

    // ── No field may influence the role ──────────────────────────────────────

    /**
     * The only hidden input on the sign-in form is the CSRF token.
     *
     * A source-level assertion because that is exactly the shape of the
     * mistake being guarded: a reviewer skimming a diff would not blink at one
     * more hidden input, and a role carried in the form would be invisible
     * until someone edited it in dev tools.
     */
    public function testTheFormCarriesNoHiddenInputButTheCsrfToken(): void
    {
        $view = (string) file_get_contents(__DIR__ . '/../../app/views/auth/login.php');

        preg_match_all('/<input[^>]*type="hidden"[^>]*>/i', $view, $matches);

        $this->assertCount(1, $matches[0], 'Exactly one hidden input is expected');
        $this->assertStringContainsString('name="csrf_token"', $matches[0][0]);
    }

    public function testEveryEntryPostsToTheSameEndpoint(): void
    {
        $view = (string) file_get_contents(__DIR__ . '/../../app/views/auth/login.php');

        // One form, and its action is built from route('login').
        $this->assertSame(1, substr_count($view, '<form method="post"'));
        $this->assertStringContainsString("\$formAction = route('login')", $view);
    }

    /**
     * The redirect after a successful sign-in is decided in one place, from
     * the database row — not from the entry point, and not duplicated.
     */
    public function testTheRoleUsedForRedirectComesFromTheUserRecord(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/controllers/AuthController.php');

        $this->assertSame(
            1,
            substr_count($source, 'private function completeLogin'),
            'completeLogin must remain the single place a session is populated'
        );
        $this->assertStringContainsString("\$_SESSION['role']      = \$user['role'];", $source);

        // Nothing in the controller may read a role out of the request.
        foreach (["\$_POST['role']", "\$_GET['role']", "\$_REQUEST['role']"] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }
}
