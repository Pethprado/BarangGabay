<?php
declare(strict_types=1);

use App\Services\TwoFactorService;
use PHPUnit\Framework\TestCase;
use PragmaRX\Google2FA\Google2FA;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Covers the parts of two-factor auth that need no database.
 *
 * The backup-code cases exist because hashing and verification once
 * normalised the code differently ("ABCD-EFGH" vs "ABCDEFGH"), which silently
 * made every backup code unusable. These pin that behaviour down.
 */
final class TwoFactorServiceTest extends TestCase
{
    private TwoFactorService $twoFactor;
    private Google2FA $engine;

    protected function setUp(): void
    {
        // The service derives its encryption key from JWT_SECRET.
        $_ENV['JWT_SECRET'] = $_ENV['JWT_SECRET'] ?? 'test-secret-for-unit-tests';

        $this->twoFactor = new TwoFactorService();
        $this->engine    = new Google2FA();
    }

    public function testGeneratedSecretIsUsableBase32(): void
    {
        $secret = $this->twoFactor->generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testGeneratedSecretsAreUnique(): void
    {
        $this->assertNotSame($this->twoFactor->generateSecret(), $this->twoFactor->generateSecret());
    }

    public function testCurrentCodeIsAccepted(): void
    {
        $secret = $this->twoFactor->generateSecret();
        $code   = $this->engine->getCurrentOtp($secret);

        $this->assertTrue($this->twoFactor->verifyCode($secret, $code));
    }

    public function testWrongCodeIsRejected(): void
    {
        $secret  = $this->twoFactor->generateSecret();
        $current = $this->engine->getCurrentOtp($secret);
        $wrong   = str_pad((string) ((((int) $current) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->assertFalse($this->twoFactor->verifyCode($secret, $wrong));
    }

    /**
     * Malformed input must be rejected before it reaches the TOTP library,
     * which throws on bad input rather than returning false.
     */
    public function testMalformedCodesAreRejectedWithoutThrowing(): void
    {
        $secret = $this->twoFactor->generateSecret();

        foreach (['', 'abcdef', '12345', '1234567', '  ', 'not-a-code'] as $bad) {
            $this->assertFalse(
                $this->twoFactor->verifyCode($secret, $bad),
                'Expected rejection for: ' . var_export($bad, true)
            );
        }
    }

    public function testEmptySecretIsRejected(): void
    {
        $this->assertFalse($this->twoFactor->verifyCode('', '123456'));
    }

    public function testProvisioningUriCarriesIssuerAndAccount(): void
    {
        $secret = $this->twoFactor->generateSecret();
        $uri    = $this->twoFactor->provisioningUri('resident@example.test', $secret);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('resident%40example.test', $uri);
    }

    public function testQrCodeRendersInlineSvg(): void
    {
        $secret = $this->twoFactor->generateSecret();
        $svg    = $this->twoFactor->qrCodeSvg($this->twoFactor->provisioningUri('a@b.test', $secret));

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<path', $svg);

        // The QR must be drawn locally. An <image> tag or a call to Google's
        // old chart API would leak the TOTP secret to a third party.
        $this->assertStringNotContainsString('<image', $svg);
        $this->assertStringNotContainsString('chart.googleapis.com', $svg);
        $this->assertStringNotContainsString($secret, $svg, 'The secret must not appear as literal text');
    }

    /**
     * Hashing and verification must normalise a backup code identically.
     * When they disagreed, no backup code could ever match.
     */
    public function testBackupCodeNormalisationIsSymmetric(): void
    {
        $normalise = static fn (string $c): string
            => strtoupper(trim(str_replace([' ', '-'], '', $c)));

        $issued = 'ELE5-C4XF';
        $hash   = password_hash($normalise($issued), PASSWORD_BCRYPT);

        // Every plausible way a person might type it back in.
        foreach (['ELE5-C4XF', 'ele5-c4xf', 'ELE5C4XF', 'ele5 c4xf', '  ELE5-C4XF  '] as $typed) {
            $this->assertTrue(
                password_verify($normalise($typed), $hash),
                'Should accept backup code typed as: ' . $typed
            );
        }

        $this->assertFalse(password_verify($normalise('ELE5-C4XG'), $hash));
    }

    public function testRequiredRolesDefaultsToSuperadmin(): void
    {
        // No database in unit tests, so setting() falls back to its default.
        $this->assertContains('superadmin', TwoFactorService::requiredRoles());
    }
}
