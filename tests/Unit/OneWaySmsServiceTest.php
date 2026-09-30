<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OneWaySmsService;
use PHPUnit\Framework\TestCase;

final class OneWaySmsServiceTest extends TestCase
{
    private OneWaySmsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OneWaySmsService();
    }

    public function testCleanPhoneNormalizesPhilippineNumbers(): void
    {
        $this->assertSame('639171234567', $this->service->cleanPhone('09171234567'));
        $this->assertSame('639171234567', $this->service->cleanPhone('9171234567'));
        $this->assertSame('639171234567', $this->service->cleanPhone('+639171234567'));
        $this->assertSame('639171234567', $this->service->cleanPhone('639171234567'));
        $this->assertSame('639171234567', $this->service->cleanPhone('6309171234567'));
    }

    public function testCleanPhoneRejectsInvalidNumbers(): void
    {
        $this->assertNull($this->service->cleanPhone('12345'));
        $this->assertNull($this->service->cleanPhone('invalid_phone'));
        $this->assertNull($this->service->cleanPhone(''));
    }
}
