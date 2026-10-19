<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The Wiktionary Bisaya word list (data/bisaya/bisaya_wiktionary.csv) is
 * imported straight into bisaya_dictionary, so its shape is pinned here:
 * every row usable, attributed, and free of the mistakes that would hurt
 * the MN translator (Tagalog function words, false friends).
 */
class BisayaWiktionaryDatasetTest extends TestCase
{
    private const CSV = __DIR__ . '/../../data/bisaya/bisaya_wiktionary.csv';

    /** @return list<array<string,string>> */
    private function rows(): array
    {
        $handle = fopen(self::CSV, 'rb');
        $header = fgetcsv($handle);
        $rows   = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $line);
        }
        fclose($handle);
        return $rows;
    }

    public function testEveryRowIsUsableAndAttributed(): void
    {
        $rows = $this->rows();
        $this->assertGreaterThan(5000, count($rows));
        foreach ($rows as $row) {
            $this->assertNotSame('', trim($row['bisaya']));
            $this->assertNotSame('', trim($row['english']), $row['bisaya']);
            $this->assertSame('WIKTIONARY-CEB', $row['source']);
            $this->assertStringContainsString('Wiktionary', $row['notes']);
        }
    }

    public function testNoTagalogFunctionWordsOrFalseFriends(): void
    {
        $byWord = [];
        foreach ($this->rows() as $row) {
            $byWord[$row['bisaya']] = $row;
            foreach (array_filter(explode(',', $row['tagalog'])) as $tagalog) {
                $this->assertNotContains($tagalog, ['na', 'ay', 'ang', 'ng', 'sa', 'at', 'si', 'ni', 'mga'], $row['bisaya']);
            }
        }
        // Spelled like Tagalog, different meaning: must not be in this list.
        $this->assertArrayNotHasKey('damo', $byWord);
        // Known-good pairings stay paired.
        $this->assertStringContainsString('kalayaan', $byWord['kagawasan']['tagalog']);
        $this->assertSame('liog', $byWord['liog']['bisaya']);
    }

    public function testDoesNotRepeatTheHandWrittenWords(): void
    {
        $handle = fopen(__DIR__ . '/../../data/bisaya/bisaya_dictionary.csv', 'rb');
        fgetcsv($handle);
        $curated = [];
        while (($line = fgetcsv($handle)) !== false) {
            $curated[mb_strtolower(trim($line[0]))] = true;
        }
        fclose($handle);

        foreach ($this->rows() as $row) {
            $this->assertArrayNotHasKey(mb_strtolower($row['bisaya']), $curated, $row['bisaya']);
        }
    }
}
