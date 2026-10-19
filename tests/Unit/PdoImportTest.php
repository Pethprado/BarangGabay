<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Inside a namespace, a bare `PDO::` resolves to e.g. App\Middleware\PDO and
 * dies with "Class not found" — only when that line runs. That took down
 * every rate-limited AI endpoint (ordinance "Simplify", AI chat). Every
 * namespaced file that writes `PDO::` must import it or write `\PDO::`.
 */
class PdoImportTest extends TestCase
{
    public function testNamespacedFilesImportPdo(): void
    {
        $root = \dirname(__DIR__, 2) . '/app';
        $bad  = [];
        $it   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (!preg_match('/^namespace\s/m', $code) || preg_match('/^use\s+PDO\s*;/m', $code)) {
                continue;
            }
            if (preg_match('/(?<![\\\\\w])PDO::/', $code)) {
                $bad[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        $this->assertSame([], $bad, 'Add `use PDO;` (or write \\PDO::) in: ' . implode(', ', $bad));
    }
}
