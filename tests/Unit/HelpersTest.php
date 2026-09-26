<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

final class HelpersTest extends TestCase
{
    public function testGenerateSlugCreatesHyphenatedLowercaseSlug(): void
    {
        $this->assertSame('barangay-ordinance-title', generate_slug('Barangay Ordinance Title')); 
        $this->assertSame('a-simple-slug', generate_slug('  A Simple   Slug!  '));
    }

    public function testEscapeFunctionEncodesHtmlEntities(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
    }

    public function testAppUrlUsesAppUrlEnvVariable(): void
    {
        $_ENV['APP_URL'] = 'http://localhost/baranggabay';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $this->assertSame('http://localhost/baranggabay/test-path', app_url('test-path'));
    }
}
