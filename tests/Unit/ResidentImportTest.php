<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ResidentImport;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Importing residents from a list: columns in English or Filipino, tidy
 * names and puroks, purok headings, duplicates kept out. Test residents
 * ("Zzimport …") are removed again.
 */
class ResidentImportTest extends TestCase
{
    protected function tearDown(): void
    {
        db()->exec("DELETE FROM users WHERE full_name LIKE '%Zzimport%'");
    }

    public function testFilipinoHeadersNamesPuroksAndPhones(): void
    {
        $a = ResidentImport::analyse([
            ['No.', 'Pangalan', 'Purok', 'Contact No.', 'Kasarian', 'Kaarawan'],
            ['1', 'ZZIMPORT, ANDRES B.', '4', '0917-555-0001', 'M', '05/02/1980'],
            ['2', 'Bea Zzimport', 'P-5', '+63 917 555 0002', 'F', ''],
            ['3', 'Carlo Zzimport', 'Purok 99', '12345', '', ''],
            ['Total', '', '', '', '', ''],
        ]);

        $this->assertTrue($a['header']);
        $this->assertCount(3, $a['rows'], 'the Total line is skipped');
        [$andres, $bea, $carlo] = $a['rows'];
        $this->assertSame('Andres B. Zzimport', $andres['full_name']);
        $this->assertSame(['Andres', 'B.', 'Zzimport'], [$andres['first_name'], $andres['middle_name'], $andres['last_name']]);
        $this->assertSame('Purok 4', $andres['purok']);
        $this->assertSame('09175550001', $andres['phone']);
        $this->assertSame('male', $andres['sex']);
        $this->assertSame('1980-05-02', $andres['date_of_birth']);
        $this->assertSame('Purok 5', $bea['purok']);
        $this->assertSame('09175550002', $bea['phone']);
        $this->assertSame('', $carlo['purok'], 'Purok 99 is not a barangay purok');
        $this->assertSame('', $carlo['phone']);
        $this->assertCount(2, $carlo['issues']);
    }

    public function testPurokHeadingsAndDefaultPurok(): void
    {
        $a = ResidentImport::analyse([
            ['Dina Zzimport'],
            ['PUROK 2'],
            ['Elmer Zzimport', '09175550004'],
            ['Page 1 of 1'],
        ], 'Purok 6');

        $this->assertFalse($a['header']);
        $this->assertSame('Purok 6', $a['rows'][0]['purok'], 'default purok before any heading');
        $this->assertSame('Purok 2', $a['rows'][1]['purok'], 'heading wins');
        $this->assertSame('09175550004', $a['rows'][1]['phone']);
        $this->assertCount(2, $a['rows']);
    }

    public function testExistingResidentsAndRepeatsAreFlagged(): void
    {
        $existing = (string) db()->query("SELECT full_name FROM users WHERE role = 'resident' ORDER BY id LIMIT 1")->fetchColumn();
        if ($existing === '') {
            $this->markTestSkipped('No resident in this database.');
        }
        $a = ResidentImport::analyse([['Name'], [mb_strtoupper($existing)], ['Fely Zzimport'], ['FELY ZZIMPORT']]);
        $this->assertSame('duplicate', $a['rows'][0]['status'], 'same person, different capitals');
        $this->assertSame('new', $a['rows'][1]['status']);
        $this->assertSame('duplicate', $a['rows'][2]['status'], 'twice in the same file');
    }

    public function testImportCreatesVerifiedResidentsInTheirPurok(): void
    {
        $a = ResidentImport::analyse([['Name', 'Purok', 'Phone'], ['Gina Zzimport', 'Purok 3', '09175550009'], ['Hector Zzimport', '', '']]);
        $result = ResidentImport::import($a['rows'], [0 => 'Purok 3', 1 => 'Purok 7'], 0);

        $this->assertSame(2, $result['created']);
        $rows = db()->query("SELECT full_name, zone, status, role, phone, email FROM users WHERE full_name LIKE '%Zzimport' ORDER BY full_name")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame(['Purok 3', 'Purok 7'], array_column($rows, 'zone'), 'the purok chosen on the preview');
        $this->assertSame(['verified', 'verified'], array_column($rows, 'status'));
        $this->assertSame('09175550009', $rows[0]['phone']);
        $this->assertStringEndsWith('@' . \App\Models\Purok::NO_EMAIL_DOMAIN, (string) $rows[1]['email']);

        $again = ResidentImport::import($a['rows'], [0 => 'Purok 3'], 0);
        $this->assertSame(0, $again['created'], 'a "new" row registered since the preview is skipped');
    }

    public function testReadsCsvWithSemicolonsAndRejectsOldExcel(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, "Name;Purok\nIvy Zzimport;Purok 1\n");
        $rows = ResidentImport::read($path, 'list.csv');
        unlink($path);
        $this->assertSame(['Ivy Zzimport', 'Purok 1'], $rows[1]);

        $this->expectException(\RuntimeException::class);
        ResidentImport::read(__FILE__, 'list.xls');
    }

    public function testTemplateHasTheRecognisedColumns(): void
    {
        $this->assertStringContainsString('Name,Purok,Phone,Address,Email,Sex,"Civil status",Birthdate', ResidentImport::templateCsv());
    }
}
