<?php
declare(strict_types=1);

use App\Services\SecretBox;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/** Payment keys saved from the superadmin page are encrypted at rest. */
final class SecretBoxTest extends TestCase
{
    private ?string $jwt = null;

    protected function setUp(): void
    {
        $this->jwt = $_ENV['JWT_SECRET'] ?? null;
        $_ENV['JWT_SECRET'] = 'unit-test-server-secret';
    }

    protected function tearDown(): void
    {
        if ($this->jwt === null) { unset($_ENV['JWT_SECRET']); } else { $_ENV['JWT_SECRET'] = $this->jwt; }
    }

    public function testRoundTripAndCiphertextHidesTheKey(): void
    {
        $stored = SecretBox::encrypt('sk_test_abcdefghijklmnop');
        $this->assertStringStartsWith('sbx1:', $stored);
        $this->assertStringNotContainsString('sk_test_', $stored);
        $this->assertSame('sk_test_abcdefghijklmnop', SecretBox::decrypt($stored));
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $stored = SecretBox::encrypt('sk_test_abcdefghijklmnop');
        $this->assertSame('', SecretBox::decrypt(substr($stored, 0, -4) . 'AAAA'));
    }

    public function testDifferentServerSecretCannotDecrypt(): void
    {
        $stored = SecretBox::encrypt('sk_test_abcdefghijklmnop');
        $_ENV['JWT_SECRET'] = 'another-server';
        $this->assertSame('', SecretBox::decrypt($stored));
    }
}
