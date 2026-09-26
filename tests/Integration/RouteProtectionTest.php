<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

final class RouteProtectionTest extends TestCase
{
    public function testAdminRouteRequiresAuthAndAdminOrStaffRole(): void
    {
        $routes = require __DIR__ . '/../../routes/web.php';
        $expected = ['GET', '/admin', 'AdminController@dashboard', ['auth', 'role:admin,staff']];
        $this->assertContains($expected, $routes);
    }

    public function testOrdinanceSummarizeApiRouteUsesRateLimit(): void
    {
        $routes = require __DIR__ . '/../../routes/api.php';
        $expected = ['POST', '/api/ai/summarize', 'AIController@summarize', ['auth', 'verified', 'rate-limit']];
        $this->assertContains($expected, $routes);
    }

    public function testAuthAndRoleMiddlewareExist(): void
    {
        $this->assertTrue(class_exists(App\Middleware\AuthMiddleware::class));
        $this->assertTrue(class_exists(App\Middleware\RoleMiddleware::class));
    }
}
