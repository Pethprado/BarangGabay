<?php
declare(strict_types=1);

use App\Services\BisayaDictionary;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Lighter than ManoboDictionaryTest by design: this class deliberately
 * mirrors ManoboDictionary's contract (see BisayaDictionary's own docblock),
 * so the exhaustive matching-algorithm coverage already lives there. This
 * file checks the things that are actually different or new: the bisaya
 * column name, that "ang"/"mga" are NOT stripped as articles, and the
 * write/trash/verify/import API against the real (migration 030) table.
 *
 * @group database
 * Excluded from the default `vendor/bin/phpunit` run — see phpunit.xml.dist's
 * <groups><exclude> comment. Run explicitly with:
 *     vendor\bin\phpunit --group database
 */
final class BisayaDictionaryTest extends TestCase
{
    private BisayaDictionary $dictionary;
    private bool $inTransaction = false;

    protected function setUp(): void
    {
        $this->dictionary = new BisayaDictionary();
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            db()->rollBack();
            $this->inTransaction = false;
            BisayaDictionary::flushCache();
        }
    }

    private function scratchDictionary(): BisayaDictionary
    {
        if (!$this->inTransaction) {
            db()->beginTransaction();
            $this->inTransaction = true;
        }

        return new BisayaDictionary();
    }

    public function testDatasetLoads(): void
    {
        $this->assertGreaterThan(0, $this->dictionary->count());
        $this->assertNotEmpty($this->dictionary->categories());
    }

    public function testEveryEntryHasTheRequiredFields(): void
    {
        foreach ($this->dictionary->all() as $entry) {
            foreach (BisayaDictionary::FIELDS as $field) {
                $this->assertArrayHasKey($field, $entry);
            }
            $this->assertNotSame('', $entry['bisaya']);
        }
    }

    public function testBisayaToEnglish(): void
    {
        $result = $this->dictionary->translate('ordinansa', 'english');

        $this->assertTrue($result['found']);
        $this->assertSame('ordinance', $result['text']);
    }

    public function testEnglishAndTagalogToBisaya(): void
    {
        $this->assertSame('ordinansa', $this->dictionary->translate('ordinance', 'bisaya')['text']);
        $this->assertSame('ordinansa', $this->dictionary->translate('ordinansa', 'bisaya')['text']);
    }

    public function testCaseIsIgnored(): void
    {
        $this->assertSame('ordinance', $this->dictionary->translate('ORDINANSA', 'english')['text']);
    }

    public function testUnknownInputFailsGracefully(): void
    {
        $result = $this->dictionary->translate('zzqqnonexistentword', 'english');

        $this->assertFalse($result['found']);
        $this->assertNull($result['text']);
    }

    public function testSearchMatchesAcrossLanguagesAndCategory(): void
    {
        $this->assertNotEmpty($this->dictionary->search('ordinansa'));
        $this->assertSame(
            count($this->dictionary->byCategory('civic')),
            count($this->dictionary->search('', 'civic'))
        );
        $this->assertSame(count($this->dictionary->all()), count($this->dictionary->search('')));
    }

    public function testUnsupportedLanguageIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->dictionary->translate('ordinansa', 'klingon');
    }

    // ── Write API ────────────────────────────────────────────────────

    public function testAddEntryPersistsAndIsImmediatelySearchable(): void
    {
        $dictionary = $this->scratchDictionary();
        $before     = $dictionary->count();

        $dictionary->addEntry([
            'bisaya'  => 'pananglitanx',
            'english' => 'example',
            'tagalog' => 'halimbawa',
            'category' => 'testing',
            'source'  => 'LOCAL',
        ]);

        $this->assertSame($before + 1, $dictionary->count());
        $this->assertSame('example', $dictionary->translate('pananglitanx', 'english')['text']);
    }

    public function testAddEntryRequiresAllThreeLanguages(): void
    {
        $dictionary = $this->scratchDictionary();

        $this->expectException(InvalidArgumentException::class);
        $dictionary->addEntry(['bisaya' => 'xyz', 'english' => 'thing', 'tagalog' => '']);
    }

    public function testUpdateEntryCorrectsAWord(): void
    {
        $dictionary = $this->scratchDictionary();

        $dictionary->updateEntry('ordinansa', [
            'bisaya'  => 'ordinansa',
            'english' => 'ordinance',
            'tagalog' => 'ordinansa (naitama)',
        ]);

        $this->assertSame('ordinansa (naitama)', $dictionary->find('ordinansa')['tagalog']);
    }

    public function testDeletedEntryLandsInTrashAndCanBeRestored(): void
    {
        $dictionary = $this->scratchDictionary();

        $dictionary->deleteEntry('ordinansa');
        $this->assertNull($dictionary->find('ordinansa'));

        $trashed = array_values(array_filter(
            $dictionary->trash(),
            static fn (array $e): bool => $e['bisaya'] === 'ordinansa'
        ));
        $this->assertCount(1, $trashed);

        $dictionary->restore((int) $trashed[0]['id']);
        $this->assertNotNull($dictionary->find('ordinansa'));
    }

    public function testForceDeleteRemovesATrashedEntryPermanently(): void
    {
        $dictionary = $this->scratchDictionary();
        $dictionary->deleteEntry('ordinansa');

        $trashedId = null;
        foreach ($dictionary->trash() as $e) {
            if ($e['bisaya'] === 'ordinansa') { $trashedId = (int) $e['id']; }
        }
        $this->assertNotNull($trashedId);

        $dictionary->forceDelete($trashedId);

        $this->expectException(InvalidArgumentException::class);
        $dictionary->restore($trashedId);
    }

    public function testImportCsvAddsNewWordsAndSkipsExistingHeadwords(): void
    {
        $dictionary = $this->scratchDictionary();
        $before     = $dictionary->count();

        $path = sys_get_temp_dir() . '/bisaya_import_test_' . bin2hex(random_bytes(6)) . '.csv';
        file_put_contents(
            $path,
            "bisaya,tagalog,english,category\n"
            . "importtestword,pananalitang subok,test word,testing\n"
            . "ordinansa,ordinansa,ordinance,civic\n" // already exists — must be skipped
        );

        $result = $dictionary->importCsv($path, 'CSV-IMPORT-TEST');
        unlink($path);

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($before + 1, $dictionary->count());
    }
}
