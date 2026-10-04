<?php
declare(strict_types=1);

use App\Services\VoiceResolver;
use App\Services\VoiceText;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * Voice dataset pipeline — the pure parts (no database):
 * normalisation shared by every module, and the longest-match planner the
 * Resident Voice Reader and the admin Interactive Voice Tester both use.
 */
final class VoicePipelineTest extends TestCase
{
    /** @return array<string,array<string,mixed>> */
    private function map(string ...$keys): array
    {
        $map = [];
        foreach ($keys as $i => $key) {
            $map[$key] = ['audio_url' => "http://x/voice/audio/{$i}", 'sample_id' => $i, 'speaker' => ''];
        }
        return $map;
    }

    public function testNormalizeFoldsCasePunctuationAndWhitespace(): void
    {
        $this->assertSame('madjow no masim', VoiceText::normalize('  Madjow no   masim! '));
        $this->assertSame('madjow no masim', VoiceText::normalize('<p>MADJOW, no masim.</p>'));
    }

    public function testNormalizeKeepsManoboSpelling(): void
    {
        // Inner apostrophes/hyphens and accented letters are part of the word.
        $this->assertSame("a'baga", VoiceText::normalize("A'baga"));
        $this->assertSame("a'baga", VoiceText::normalize("A\u{2019}baga"));   // curly apostrophe folds
        $this->assertSame('pag-ampo', VoiceText::normalize('Pag-ampo'));
        $this->assertSame('súlod', VoiceText::normalize('Súlod'));
        // Edge punctuation is not.
        $this->assertSame('baga', VoiceText::normalize("'baga-"));
    }

    public function testTokensAndWordDetection(): void
    {
        $this->assertSame(['ania', '2026', 'tibo'], VoiceText::tokens('Ania, 2026 — tibo.'));
        $this->assertTrue(VoiceText::isWord('tibo'));
        $this->assertFalse(VoiceText::isWord('2026'));
    }

    public function testPhraseRecordingBeatsSingleWords(): void
    {
        $map  = $this->map('madjow', 'no', 'masim', 'madjow no masim');
        $plan = VoiceResolver::plan(VoiceText::tokens('Madjow no masim'), $map, 3);

        $this->assertCount(1, $plan);
        $this->assertSame('recorded', $plan[0]['type']);
        $this->assertSame('madjow no masim', $plan[0]['text']);
    }

    public function testMissingWordsAreGroupedBetweenRecordings(): void
    {
        $map  = $this->map('madjow no masim', 'tibo');
        $plan = VoiceResolver::plan(VoiceText::tokens('Madjow no masim kaniyo duon tibo'), $map, 3);

        $this->assertSame(['recorded', 'missing', 'recorded'], array_column($plan, 'type'));
        $this->assertSame('kaniyo duon', $plan[1]['text']);
        $this->assertSame(['kaniyo', 'duon'], VoiceResolver::missingWords($plan));
        $this->assertTrue(VoiceResolver::hasRecording($plan));
    }

    public function testFragmentOfAPhraseDoesNotMatchThePhrase(): void
    {
        // Only "madjow no masim" is recorded; "madjow" alone must stay missing.
        $plan = VoiceResolver::plan(VoiceText::tokens('madjow kaniyo'), $this->map('madjow no masim'), 3);

        $this->assertSame([['type' => 'missing', 'text' => 'madjow kaniyo']], $plan);
        $this->assertFalse(VoiceResolver::hasRecording($plan));
    }

    public function testEmptyMapReadsEverythingWithFallback(): void
    {
        $plan = VoiceResolver::plan(['a', 'b'], [], 1);
        $this->assertSame([['type' => 'missing', 'text' => 'a b']], $plan);
    }

    public function testNumbersAreNotCountedAsMissingVocabulary(): void
    {
        $plan = VoiceResolver::plan(VoiceText::tokens('tibo 25'), [], 1);
        $this->assertSame(['tibo'], VoiceResolver::missingWords($plan));
    }
}
