<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

final class CsrfBehaviorTest extends TestCase
{
    private function runRunner(string $mode): array
    {
        $php = escapeshellarg(PHP_BINARY);
        $script = escapeshellarg(__DIR__ . '/csrf_runner.php');
        $output = shell_exec("$php $script $mode 2>&1");
        $jsonStart = strpos($output, '{');
        $json = $jsonStart !== false ? substr($output, $jsonStart) : '';
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->fail('Could not parse CSRF runner output: ' . $output);
        }
        return $data;
    }

    public function testCsrfMiddlewareAllowsValidToken(): void
    {
        $result = $this->runRunner('valid');
        $this->assertSame('passed', $result['status']);
        $this->assertContains($result['code'], [false, 200]);
    }

    public function testCsrfMiddlewareRejectsInvalidToken(): void
    {
        $result = $this->runRunner('invalid');
        $this->assertSame(403, $result['code']);
    }
}
