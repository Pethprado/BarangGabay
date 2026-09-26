<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

final class RoutesTest extends TestCase
{
    public function testWebRoutesReturnArray(): void
    {
        $routes = require __DIR__ . '/../../routes/web.php';
        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
        $this->assertContains(['GET', '/', 'ResidentController@home', []], $routes);
    }

    public function testApiRoutesReturnArray(): void
    {
        $routes = require __DIR__ . '/../../routes/api.php';
        $this->assertIsArray($routes);
        $this->assertNotEmpty($routes);
        $this->assertContains(['POST', '/api/ai/summarize', 'AIController@summarize', ['auth', 'verified', 'rate-limit']], $routes);
    }

    public function testRouteParametersUseCurlyBraces(): void
    {
        $routes = require __DIR__ . '/../../routes/web.php';
        $this->assertTrue(in_array(['GET', '/announcements/{slug}', 'AnnouncementController@show', ['auth', 'verified']], $routes, true));
    }
}
