<?php
declare(strict_types=1);

use App\Services\ManoboDictionary;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
// CHANGED: the dictionary moved from a CSV file to the manobo_dictionary
// table (migration 030) — this test now needs a real connection. Required
// here rather than in tests/bootstrap.php so every OTHER test keeps running
// with no database at all.
require_once __DIR__ . '/../../config/database.php';

/**
 * @group database
 * Excluded from the default `vendor/bin/phpunit` run — see phpunit.xml.dist's
 * <groups><exclude> comment for why. Run explicitly with:
 *     vendor\bin\phpunit --group database
 */
final class ManoboDictionaryTest extends TestCase
{
    private ManoboDictionary $dictionary;

    protected function setUp(): void
    {
        $this->dictionary = new ManoboDictionary();
    }

    public function testDatasetLoads(): void
    {
        $this->assertGreaterThan(0, $this->dictionary->count());
        $this->assertNotEmpty($this->dictionary->categories());
    }

    public function testEveryEntryHasTheRequiredFields(): void
    {
        $fields = ['manobo', 'english', 'tagalog', 'part_of_speech', 'category', 'notes', 'source'];

        foreach ($this->dictionary->all() as $entry) {
            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $entry);
            }
            $this->assertNotSame('', $entry['manobo']);
            $this->assertNotSame('', $entry['english'], 'Missing English gloss for ' . $entry['manobo']);
            $this->assertNotSame('', $entry['tagalog'], 'Missing Tagalog gloss for ' . $entry['manobo']);
            $this->assertNotSame('', $entry['source'], 'Missing source for ' . $entry['manobo']);
        }
    }

    public function testManoboHeadwordsAreUnique(): void
    {
        $headwords = array_column($this->dictionary->all(), 'manobo');

        $this->assertSame(
            array_values(array_unique($headwords)),
            array_values($headwords),
            'Duplicate Manobo headword in the dataset'
        );
    }

    public function testManoboToEnglish(): void
    {
        $result = $this->dictionary->translate("wo'hig", 'english');

        $this->assertTrue($result['found']);
        $this->assertSame('phrase', $result['match_type']);
        $this->assertSame('water', $result['text']);
    }

    public function testLookupIgnoresStressAndGlottalMarks(): void
    {
        // A resident types plain letters; the dataset stores the marked form.
        $this->assertSame('water', $this->dictionary->translate('wohig', 'english')['text']);
        $this->assertSame('ulam', $this->dictionary->translate('soda', 'tagalog')['text']);
        $this->assertSame('water', $this->dictionary->translate('WOHIG', 'english')['text']);
    }

    public function testEnglishAndTagalogToManobo(): void
    {
        $this->assertSame("wo'hig", $this->dictionary->translate('water', 'manobo')['text']);
        $this->assertSame("wo'hig", $this->dictionary->translate('tubig', 'manobo')['text']);
    }

    public function testInfinitiveMarkerIsOptional(): void
    {
        $this->assertSame("'panow", $this->dictionary->translate('to walk', 'manobo')['text']);
        $this->assertSame("'panow", $this->dictionary->translate('walk', 'manobo')['text']);
    }

    public function testMinimalPairsAreFlaggedAsAmbiguous(): void
    {
        // Stress is phonemic: 'hilu "thread" vs hi'lu "poison".
        $result = $this->dictionary->translate('hilu', 'english');

        $this->assertTrue($result['found']);
        $this->assertTrue($result['ambiguous']);
        $this->assertCount(2, $this->dictionary->lookup('hilu')['entries']);
    }

    public function testWordByWordFallback(): void
    {
        $result = $this->dictionary->translate('wohig sed', 'english');

        $this->assertTrue($result['found']);
        $this->assertSame('word-by-word', $result['match_type']);
        $this->assertSame('water inside', $result['text']);
    }

    public function testUnknownWordsAreReportedNotDropped(): void
    {
        $result = $this->dictionary->translate('wohig zzz', 'english');

        $this->assertTrue($result['found']);
        $this->assertSame(['zzz'], $result['missing']);
        $this->assertStringContainsString('zzz', (string) $result['text']);
    }

    public function testUnknownInputFailsGracefully(): void
    {
        // CHANGED: 'kalabaw' used to be genuinely absent, but the 2026 dataset
        // added "kibow" (carabao) with the Tagalog gloss "Kalabaw" — so this
        // now needs a word that is truly nowhere in either dataset.
        $result = $this->dictionary->translate('zzqqnonexistentword', 'english');

        $this->assertFalse($result['found']);
        $this->assertNull($result['text']);
        $this->assertSame('none', $result['match_type']);
        $this->assertStringContainsString('No translation found', (string) $result['message']);
    }

    public function testEmptyInputFailsGracefully(): void
    {
        $result = $this->dictionary->translate('   ', 'english');

        $this->assertFalse($result['found']);
        $this->assertNull($result['text']);
    }

    public function testLanguageAliases(): void
    {
        $this->assertSame('tubig', $this->dictionary->translate('wohig', 'fil')['text']);
        $this->assertSame("wo'hig", $this->dictionary->translate('water', 'msm')['text']);
    }

    public function testUnsupportedLanguageIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->dictionary->translate('wohig', 'klingon');
    }

    // ── Write API ────────────────────────────────────────────────────
    //
    // CHANGED: these used to run against a throwaway COPY OF THE CSV FILE so
    // the shipped dataset was never touched. There is no file to copy any
    // more, so instead each write test runs inside a transaction that is
    // rolled back in tearDown() — the real manobo_dictionary table, but
    // nothing it does outlives the test.

    private bool $inTransaction = false;

    private function scratchDictionary(): ManoboDictionary
    {
        if (!$this->inTransaction) {
            db()->beginTransaction();
            $this->inTransaction = true;
        }

        return new ManoboDictionary();
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            db()->rollBack();
            $this->inTransaction = false;
            // A ROLLBACK is invisible to ManoboDictionary's in-process cache —
            // without this, a later test in the same PHP process would still
            // see the (now-reverted) write.
            ManoboDictionary::flushCache();
        }
    }

    public function testAddEntryPersistsAndIsImmediatelySearchable(): void
    {
        $dictionary = $this->scratchDictionary();
        $before     = $dictionary->count();

        $dictionary->addEntry([
            'manobo'         => 'tes\'tword',
            'english'        => 'test word',
            'tagalog'        => 'pansubok na salita',
            'part_of_speech' => 'n.',
            'category'       => 'testing',
            'notes'          => 'unverified',
            'source'         => 'LOCAL',
        ]);

        $this->assertSame($before + 1, $dictionary->count());
        $this->assertSame('test word', $dictionary->translate('testword', 'english')['text']);

        // A fresh instance, same connection (same open transaction), must see
        // it too — i.e. it really reached the database, not just this
        // instance's in-memory copy.
        $reloaded = new ManoboDictionary();
        $this->assertNotNull($reloaded->find('tes\'tword'));
    }

    public function testAddEntryRejectsDuplicateHeadword(): void
    {
        $dictionary = $this->scratchDictionary();

        $this->expectException(InvalidArgumentException::class);
        $dictionary->addEntry([
            'manobo'  => "wo'hig",
            'english' => 'water',
            'tagalog' => 'tubig',
        ]);
    }

    public function testAddEntryRequiresAllThreeLanguages(): void
    {
        $dictionary = $this->scratchDictionary();

        $this->expectException(InvalidArgumentException::class);
        $dictionary->addEntry(['manobo' => 'xyz', 'english' => 'thing', 'tagalog' => '']);
    }

    public function testUpdateEntryCorrectsAWord(): void
    {
        $dictionary = $this->scratchDictionary();

        $dictionary->updateEntry("wo'hig", [
            'manobo'         => "wo'hig",
            'english'        => 'water',
            'tagalog'        => 'tubig (naitama)',
            'part_of_speech' => 'n.',
            'category'       => 'nature',
            'notes'          => 'Checked with a speaker.',
            'source'         => 'LOCAL',
        ]);

        $this->assertSame('tubig (naitama)', $dictionary->find("wo'hig")['tagalog']);
        $this->assertSame('Checked with a speaker.', $dictionary->find("wo'hig")['notes']);
    }

    public function testUpdateEntryCanRenameTheHeadword(): void
    {
        $dictionary = $this->scratchDictionary();

        $dictionary->updateEntry("wo'hig", [
            'manobo'  => "wo'híg",
            'english' => 'water',
            'tagalog' => 'tubig',
        ]);

        $this->assertNull($dictionary->find("wo'hig"));
        $this->assertNotNull($dictionary->find("wo'híg"));
    }

    public function testDeleteEntryRemovesTheWord(): void
    {
        $dictionary = $this->scratchDictionary();
        $before     = $dictionary->count();

        $dictionary->deleteEntry("wo'hig");

        $this->assertSame($before - 1, $dictionary->count());
        $this->assertNull($dictionary->find("wo'hig"));
        $this->assertFalse($dictionary->translate("wo'hig", 'english')['found']);
    }

    public function testDeleteUnknownEntryIsRejected(): void
    {
        $dictionary = $this->scratchDictionary();

        $this->expectException(InvalidArgumentException::class);
        $dictionary->deleteEntry('not-a-word');
    }

    // ── ADDED: Trash (soft delete) ──────────────────────────────────────

    public function testDeletedEntryLandsInTrashAndCanBeRestored(): void
    {
        $dictionary = $this->scratchDictionary();

        $dictionary->deleteEntry("wo'hig");
        $this->assertNull($dictionary->find("wo'hig"), 'a trashed word must not appear in normal lookups');

        $trashed = array_values(array_filter(
            $dictionary->trash(),
            static fn (array $e): bool => $e['manobo'] === "wo'hig"
        ));
        $this->assertCount(1, $trashed, 'the deleted word should be sitting in the trash');

        $dictionary->restore((int) $trashed[0]['id']);
        $this->assertNotNull($dictionary->find("wo'hig"), 'restoring should bring it back to normal lookups');
        $this->assertSame('water', $dictionary->find("wo'hig")['english']);
    }

    public function testRestoringAnEntryNotInTrashIsRejected(): void
    {
        $dictionary = $this->scratchDictionary();
        $live       = $dictionary->find("wo'hig");

        $this->expectException(InvalidArgumentException::class);
        $dictionary->restore((int) $live['id']);
    }

    public function testForceDeleteRemovesATrashedEntryPermanently(): void
    {
        $dictionary = $this->scratchDictionary();
        $dictionary->deleteEntry("wo'hig");
        $trashedId = null;
        foreach ($dictionary->trash() as $e) {
            if ($e['manobo'] === "wo'hig") { $trashedId = (int) $e['id']; }
        }
        $this->assertNotNull($trashedId);

        $dictionary->forceDelete($trashedId);

        $this->expectException(InvalidArgumentException::class);
        $dictionary->restore($trashedId);
    }

    // ── ADDED: "Kailangang i-verify" (entries with a note) ──────────────

    public function testNeedsVerificationListsOnlyEntriesWithANote(): void
    {
        $flagged = $this->dictionary->needsVerification();
        $this->assertNotEmpty($flagged, 'the 2026 dataset shipped several notes, e.g. the "e an" entry');

        foreach ($flagged as $entry) {
            $this->assertNotSame('', trim($entry['notes']));
        }

        // Every flagged entry must actually be a live entry (already implied
        // by needsVerification() reading $this->entries, but worth pinning).
        $liveManobo = array_column($this->dictionary->all(), 'manobo');
        foreach ($flagged as $entry) {
            $this->assertContains($entry['manobo'], $liveManobo);
        }
    }

    // ── ADDED: CSV import ────────────────────────────────────────────────

    public function testImportCsvAddsNewWordsAndSkipsExistingHeadwords(): void
    {
        $dictionary = $this->scratchDictionary();
        $before     = $dictionary->count();

        $path = sys_get_temp_dir() . '/manobo_import_test_' . bin2hex(random_bytes(6)) . '.csv';
        file_put_contents(
            $path,
            "manobo,tagalog,english,category\n"
            . "importtestword,pananalitang subok,test word,testing\n"
            // "wo'hig" already exists — must be skipped, not duplicated.
            . "wo'hig,tubig,water,nature\n"
        );

        $result = $dictionary->importCsv($path, 'CSV-IMPORT-TEST');
        unlink($path);

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($before + 1, $dictionary->count());
        $this->assertNotNull($dictionary->find('importtestword'));
        $this->assertSame('CSV-IMPORT-TEST', $dictionary->find('importtestword')['source']);
    }

    // REMOVED: testWritesKeepTheJsonInSync. Every write used to regenerate a
    // sibling JSON file automatically, so "did the write land" and "is the
    // JSON current" were the same question. Now exportJson() is only called
    // on demand (the admin "Export" download) rather than after every write,
    // so there is no longer a JSON file that writes are expected to keep in
    // sync — testAddEntryPersistsAndIsImmediatelySearchable above already
    // covers "did the write land".

    public function testValuesContainingCommasAndQuotesSurviveARoundTrip(): void
    {
        $dictionary = $this->scratchDictionary();

        $dictionary->addEntry([
            'manobo'  => 'quotetest',
            'english' => 'one, two, and "three"',
            'tagalog' => 'isa, dalawa, at "tatlo"',
            'notes'   => 'Has a comma, and "quotes" too.',
        ]);

        // Same open transaction, fresh instance — proves it round-tripped
        // through a real INSERT/SELECT, not just an in-memory copy.
        $reloaded = new ManoboDictionary();
        $entry    = $reloaded->find('quotetest');

        $this->assertSame('one, two, and "three"', $entry['english']);
        $this->assertSame('Has a comma, and "quotes" too.', $entry['notes']);
    }

    public function testSearchMatchesAcrossLanguagesAndCategory(): void
    {
        $this->assertNotEmpty($this->dictionary->search('tubig'));
        $this->assertNotEmpty($this->dictionary->search('water'));
        $this->assertSame(
            count($this->dictionary->byCategory('health')),
            count($this->dictionary->search('', 'health'))
        );
        $this->assertSame(count($this->dictionary->all()), count($this->dictionary->search('')));
    }

    public function testExportJsonProducesAValidSnapshot(): void
    {
        // CHANGED: used to assert the SHIPPED data/manobo/manobo_dictionary.json
        // stayed in sync with the CSV on every write. The database is now the
        // source of truth and exportJson() is only ever called on demand (the
        // admin "Export" download), so there is nothing left for it to drift
        // out of sync WITH — this just checks the snapshot it produces is
        // internally correct.
        $path = sys_get_temp_dir() . '/manobo_export_test_' . bin2hex(random_bytes(6)) . '.json';

        $this->assertTrue($this->dictionary->exportJson($path));
        $data = json_decode((string) file_get_contents($path), true);
        unlink($path);

        $this->assertSame($this->dictionary->count(), $data['entry_count']);
        $this->assertSame(
            array_column($this->dictionary->all(), 'manobo'),
            array_column($data['entries'], 'manobo')
        );
    }

    // ── Query normalisation ──────────────────────────────────────────────

    public function testAnEntryStoredInItsCitationFormIsFoundByAPlainQuery(): void
    {
        // "mother!" is recorded that way because the source marks it as a term
        // of address. Before normalise() stripped surrounding punctuation, a
        // lookup for "mother" missed it — which also meant the word never
        // reached the AI translation prompt.
        $plain = $this->dictionary->lookup('mother');

        $this->assertTrue($plain['found'], '"mother" should reach the "mother!" entry');
        $this->assertContains("i'nay", array_column($plain['entries'], 'manobo'));
    }

    public function testThePunctuatedFormStillMatchesToo(): void
    {
        $this->assertTrue($this->dictionary->lookup('mother!')['found']);
        $this->assertTrue($this->dictionary->lookup('inay!')['found']);
    }

    /** @dataProvider glottalSpellingProvider */
    public function testGlottalMarksRemainPartOfTheSpelling(string $headword): void
    {
        // The hyphen and apostrophe mark a glottal stop in this orthography.
        // Stripping them as "punctuation" would corrupt real headwords, so the
        // normaliser must leave them alone.
        $this->assertTrue(
            $this->dictionary->lookup($headword)['found'],
            "{$headword} must still be findable"
        );
    }

    public static function glottalSpellingProvider(): array
    {
        return [["agid-id"], ["a-ae"], ["a'baga"], ["o'nom"], ["dadu'wa"]];
    }

    public function testEveryHeadwordIsReachableByItsOwnSpelling(): void
    {
        foreach ($this->dictionary->all() as $entry) {
            $this->assertTrue(
                $this->dictionary->lookup($entry['manobo'])['found'],
                "headword {$entry['manobo']} is unreachable"
            );
        }
    }

    public function testEveryEntryIsReachableByItsEnglishGloss(): void
    {
        foreach ($this->dictionary->all() as $entry) {
            // CHANGED: skip a gloss that is pure punctuation once normalised
            // (e.g. the 2026 dataset's "e an" entry, whose English gloss is
            // literally "?" — its own note says "UNCLEAR in PDF, please
            // verify"). Nothing can be "reachable" by a query with no letters
            // left after normalise() strips punctuation; that is not a lookup
            // bug, it is a dataset row waiting on a Manobo speaker.
            if (trim($entry['english'], " \t\n\r\0\x0B.,;:!?\"'()") === '') {
                continue;
            }
            $this->assertTrue(
                $this->dictionary->lookup($entry['english'])['found'],
                "gloss '{$entry['english']}' is unreachable"
            );
        }
    }

    public function testUnrelatedWordsStillDoNotMatch(): void
    {
        foreach (['address', 'status', 'bus', 'zzqq-nonsense'] as $word) {
            $this->assertFalse($this->dictionary->lookup($word)['found'], "{$word} should not match");
        }
    }
}
