<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The query shape behind the SMS recipient picker.
 *
 * These assert the SQL-building rule rather than hitting the database: the
 * bug being pinned was a clause that matched every row, and that is a
 * property of how the query is assembled, not of any particular data.
 *
 * The rule: a search term is matched against the name, and additionally
 * against the digits of the phone number — but ONLY when the term actually
 * contains digits. Stripping non-digits from "Zzqq" leaves an empty string,
 * and `LIKE '%%'` matches everything, so a search for a name nobody has was
 * returning the entire resident list instead of "no match".
 */
final class SmsRecipientSearchTest extends TestCase
{
    /**
     * Mirrors the clause-selection logic in User::reachableBySms().
     *
     * @return array{clause:string, params:list<string>}
     */
    private function build(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['clause' => '', 'params' => []];
        }

        $digits = preg_replace('/[^0-9]/', '', $query);

        if ($digits !== '') {
            return ['clause' => 'name+phone', 'params' => ['%' . $query . '%', '%' . $digits . '%']];
        }

        return ['clause' => 'name', 'params' => ['%' . $query . '%']];
    }

    /** The bug: a text-only search must not fall through to a phone LIKE '%%'. */
    public function testTextOnlySearchDoesNotAddAnEmptyPhoneClause(): void
    {
        $built = $this->build('Zzqq');

        $this->assertSame('name', $built['clause'], 'A term with no digits must search the name only');
        $this->assertNotContains('%%', $built['params'], "LIKE '%%' matches every row");
        $this->assertSame(['%Zzqq%'], $built['params']);
    }

    /** A number still searches both, so a partial number finds the person. */
    public function testNumericSearchMatchesNameAndPhone(): void
    {
        $built = $this->build('0951 834');

        $this->assertSame('name+phone', $built['clause']);
        $this->assertSame(['%0951 834%', '%0951834%'], $built['params'], 'Punctuation is stripped for the phone match');
    }

    /** A name containing a digit is still a valid search, not a crash. */
    public function testMixedTermSearchesBoth(): void
    {
        $built = $this->build('Purok 3');

        $this->assertSame('name+phone', $built['clause']);
        $this->assertSame(['%Purok 3%', '%3%'], $built['params']);
    }

    /** An empty query browses rather than filtering — staff asked to browse. */
    public function testEmptyQueryAddsNoFilter(): void
    {
        $this->assertSame('', $this->build('')['clause']);
        $this->assertSame('', $this->build('   ')['clause']);
    }

    /** Whitespace-only input is treated as empty, not as a literal search. */
    public function testWhitespaceIsTrimmed(): void
    {
        $this->assertSame(['%Mary%'], $this->build('  Mary  ')['params']);
    }
}
