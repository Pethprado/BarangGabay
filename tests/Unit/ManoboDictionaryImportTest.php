<?php
declare(strict_types=1);

namespace Tests\Unit;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/database.php';

use App\Services\DocumentParserService;
use App\Services\ManoboDictionary;
use App\Services\ManoboHybridTranslator;
use PHPUnit\Framework\TestCase;

/**
 * @group database
 */
class ManoboDictionaryImportTest extends TestCase
{
    private ManoboDictionary $dictionary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dictionary = new ManoboDictionary();
    }

    public function testCsvParsing(): void
    {
        $csvContent = "Manobo,English,Tagalog,Bisaya\n" .
                     "tuyaw,crazy person,taong lito,buwang\n" .
                     "kuntaan,today / now,ngayon,karon\n";
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'mn_csv_') . '.csv';
        file_put_contents($tmpFile, $csvContent);

        $parser = new DocumentParserService();
        $parsed = $parser->parseFile($tmpFile, 'test.csv');
        @unlink($tmpFile);

        $this->assertNotEmpty($parsed['entries']);
        $this->assertCount(2, $parsed['entries']);
        $this->assertSame('tuyaw', $parsed['entries'][0]['manobo']);
        $this->assertSame('crazy person', $parsed['entries'][0]['english']);
        $this->assertSame('taong lito', $parsed['entries'][0]['tagalog']);
    }

    public function testBatchImportAndUndo(): void
    {
        $batchItems = [
            [
                'manobo'         => 'test_word_unit_' . uniqid(),
                'english'        => 'unit test word meaning',
                'tagalog'        => 'kahulugan ng salita',
                'bisaya'         => 'pasabot sa pulong',
                'category'       => 'general',
                'part_of_speech' => 'noun',
                'notes'          => 'Unit test entry',
                'action'         => 'import',
            ]
        ];

        $filename = 'unit_test_doc.docx';
        $result = $this->dictionary->importBatch($batchItems, $filename, 'docx', 1);

        $this->assertSame(1, $result['added']);
        $this->assertNotEmpty($result['batch_id']);

        $dup = $this->dictionary->findDuplicate($batchItems[0]['manobo'], 'unit test word meaning', 'kahulugan ng salita');
        $this->assertNotNull($dup);

        // Undo batch import
        $undoResult = $this->dictionary->undoImport($result['batch_id'], 1);
        $this->assertSame(1, $undoResult['deleted']);

        $dupAfterUndo = $this->dictionary->findDuplicate($batchItems[0]['manobo'], 'unit test word meaning', 'kahulugan ng salita');
        $this->assertNull($dupAfterUndo);
    }

    public function testHybridTranslatorRecognizesNewEntryImmediately(): void
    {
        $uniqueManobo = 'minatay_' . rand(1000, 9999);
        $uniqueEnglish = 'unique_concept_' . rand(1000, 9999);

        // Translate before adding
        $translator = new ManoboHybridTranslator();
        $transBefore = $translator->translate('This is a ' . $uniqueEnglish);
        $this->assertStringNotContainsString($uniqueManobo, $transBefore['translation']);

        // Add and approve entry
        $id = $this->dictionary->addEntry([
            'manobo'         => $uniqueManobo,
            'english'        => $uniqueEnglish,
            'tagalog'        => $uniqueEnglish,
            'bisaya'         => '',
            'category'       => 'general',
            'part_of_speech' => 'noun',
            'notes'          => 'Unit test approved entry',
        ]);
        $this->dictionary->approve($id, 1);

        // Translate after adding
        $transAfter = $translator->translate('This is a ' . $uniqueEnglish);
        $this->assertStringContainsString($uniqueManobo, $transAfter['translation']);

        // Cleanup
        $this->dictionary->deleteEntry($uniqueManobo);
        $this->dictionary->forceDelete($id);
    }
}
