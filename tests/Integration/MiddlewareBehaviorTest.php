<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

final class MiddlewareBehaviorTest extends TestCase
{
    private function runRunner(string $mode): array
    {
        $php = 'C:\\xampp\\php\\php.exe';
        $script = __DIR__ . '/middleware_runner.php';
        $output = shell_exec("\"$php\" \"$script\" $mode 2>&1");
        $json = trim(substr($output, strrpos($output, '{')));
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->fail('Could not parse middleware runner output: ' . $output);
        }
        return $data;
    }

    public function testAuthMiddlewareRedirectsGuest(): void
    {
        $result = $this->runRunner('auth');
        $this->assertSame('pending', $result['status'] ?? '');
        $this->assertSame(302, $result['code']);
    }

    public function testRoleMiddlewareDeniesResident(): void
    {
        $result = $this->runRunner('role-deny');
        $this->assertSame('pending', $result['status'] ?? '');
        $this->assertSame(403, $result['code']);
    }

    public function testRoleMiddlewareAllowsStaff(): void
    {
        $result = $this->runRunner('role-allow');
        $this->assertSame('allowed', $result['status'] ?? '');
    }
}
