<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentRequest;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * The staff dashboard's Recent Tasks links open /admin/documents?search=<ref>.
 * The search used PostgreSQL-only ILIKE and crashed on MariaDB/MySQL (XAMPP).
 */
class DocumentSearchTest extends TestCase
{
    public function testSearchRunsOnThisDatabaseAndIgnoresCase(): void
    {
        try {
            db();
        } catch (\Throwable $e) {
            $this->markTestSkipped('No database.');
        }

        $all = DocumentRequest::queue('', '', '', '');
        $this->assertIsArray(DocumentRequest::queue('', '', 'no-such-reference-zz', ''), 'search must not throw');

        if ($all === []) {
            $this->markTestSkipped('No document requests to search.');
        }
        $ref = (string) $all[0]['reference_no'];
        $upper = array_column(DocumentRequest::queue('', '', $ref, ''), 'reference_no');
        $lower = array_column(DocumentRequest::queue('', '', strtolower($ref), ''), 'reference_no');

        $this->assertContains($ref, $upper);
        $this->assertSame($upper, $lower, 'search ignores upper/lower case on every database');
    }
}
