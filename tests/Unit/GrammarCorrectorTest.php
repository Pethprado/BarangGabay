<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GrammarCorrector;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Grammar clean-up of translator output. Local rules run with the network
 * off; LanguageTool is a mocked client, so nothing leaves the machine.
 */
class GrammarCorrectorTest extends TestCase
{
    private function local(): GrammarCorrector
    {
        return new GrammarCorrector(null, false);
    }

    // ── Local rules ─────────────────────────────────────────────────────

    public function testEnglishSpacingCapitalsAndDoubledWords(): void
    {
        $out = $this->local()->correct('the hall is closed .residents must attend the the meeting ,i will be there on monday.', 'en');

        $this->assertSame('The hall is closed. Residents must attend the meeting, I will be there on Monday.', $out['text']);
        $this->assertGreaterThan(0, $out['fixes']);
    }

    public function testAbbreviationsAndWebAddressesAreNotSentenceEnds(): void
    {
        $text = 'Visit Brgy. hall or see facebook.com/bayogo for details.';
        $this->assertSame($text, $this->local()->correct($text, 'en')['text']);
    }

    public function testFilipinoDinRinFollowsTheKwfRule(): void
    {
        $out = $this->local()->correct('Pupunta din kami doon. Ang kapitan rito ay maaari din sumama.', 'fil');

        // r after a vowel; d after a consonant; d kept after -ri ("maaari din").
        $this->assertSame('Pupunta rin kami roon. Ang kapitan dito ay maaari din sumama.', $out['text']);
    }

    public function testManoboGetsLocalFixesButReduplicationIsKept(): void
    {
        $out = $this->local()->correct('ang ang tubig sa sa purok mawala ugma .hinay hinay kamo,salamat', 'msm');

        $this->assertSame('Ang tubig sa purok mawala ugma. Hinay hinay kamo, salamat', $out['text']);
    }

    public function testHtmlIsNeverBroken(): void
    {
        $html = '<p>water  will be cut in <strong>purok 2</strong> .</p><p>thank you &amp; stay safe</p>';
        $out  = $this->local()->correct($html, 'en');

        $this->assertSame('<p>Water will be cut in <strong>purok 2</strong> .</p><p>Thank you &amp; stay safe</p>', $out['text']);
    }

    public function testTitleAndBodyAreCorrectedSeparately(): void
    {
        [$title, $body, $fixes] = (new GrammarCorrector(null, false))->correctPair('fil', 'pulong sa purok', '<p>pupunta din ang kapitan.</p>');

        $this->assertSame('Pulong sa purok', $title);
        $this->assertSame('<p>Pupunta rin ang kapitan.</p>', $body);
        $this->assertSame(3, $fixes);
    }

    public function testUnknownLanguageAndEmptyTextAreLeftAlone(): void
    {
        $this->assertSame('the the', $this->local()->correct('the the', 'xx')['text']);
        $this->assertSame('', $this->local()->correct('', 'en')['text']);
    }

    // ── LanguageTool (mocked) ───────────────────────────────────────────

    /**
     * @param list<array<string,mixed>> $matches
     * @param array<int,mixed> $history
     */
    private function remote(array $matches, array &$history): GrammarCorrector
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['matches' => $matches]))]));
        $stack->push(Middleware::history($history));

        return new GrammarCorrector(new Client(['handler' => $stack, 'http_errors' => false]), true);
    }

    /** A LanguageTool match, with offsets counted over the annotation as sent. */
    private static function match(string $sent, string $flagged, string $fix, string $type, string $rule = 'X'): array
    {
        $offset = mb_strpos($sent, $flagged);
        return [
            'offset'       => $offset,
            'length'       => mb_strlen($flagged),
            'replacements' => [['value' => $fix]],
            'rule'         => ['id' => $rule, 'issueType' => $type],
            'context'      => ['text' => $sent, 'offset' => $offset, 'length' => mb_strlen($flagged)],
        ];
    }

    public function testRemoteFixLandsOnTheRightWordAfterTagsAndEntities(): void
    {
        // What GrammarCorrector sends: divider markup, then the HTML pieces.
        $sent = '<!-- --><p>Rock &amp; roll is <em>verry</em> loud. He go home.</p>';
        $history = [];
        $out = $this->remote([
            self::match($sent, 'verry', 'very', 'misspelling', 'MORFOLOGIK_RULE_EN_US'),
            self::match($sent, 'go', 'goes', 'grammar', 'HE_VERB_AGR'),
        ], $history)->correct('<p>Rock &amp; roll is <em>verry</em> loud. He go home.</p>', 'en');

        $this->assertSame('<p>Rock &amp; roll is <em>very</em> loud. He goes home.</p>', $out['text']);

        $form = [];
        parse_str((string) $history[0]['request']->getBody(), $form);
        $this->assertSame('en-US', $form['language']);
    }

    public function testRemoteNeverTouchesNamesLocalTermsOrFilipinoSpelling(): void
    {
        $text = 'Residents of purok 3 in Bayogo met.';
        $sent = '<!-- -->' . $text;
        $history = [];
        $out = $this->remote([
            self::match($sent, 'purok', 'park', 'misspelling'),
            self::match($sent, 'Bayogo', 'Bayou', 'misspelling'),
            self::match($sent, 'met', 'meet', 'style'),
        ], $history)->correct($text, 'en');
        $this->assertSame($text, $out['text']);

        $fil  = 'Sarado ang hall bukas.';
        $history = [];
        $out = $this->remote([self::match('<!-- -->' . $fil, 'hall', 'hal', 'misspelling', 'MORFOLOGIK_RULE_TL')], $history)
            ->correct($fil, 'fil');
        $this->assertSame($fil, $out['text']);
        $form = [];
        parse_str((string) $history[0]['request']->getBody(), $form);
        $this->assertSame('tl-PH', $form['language']);
    }

    public function testManoboNeverCallsLanguageTool(): void
    {
        $history = [];
        $this->remote([], $history)->correct('maayo nga buntag', 'msm');
        $this->assertCount(0, $history);
    }

    public function testRemoteFailureFallsBackToLocalRules(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(429, [], 'Too many requests')]));
        $out   = (new GrammarCorrector(new Client(['handler' => $stack, 'http_errors' => false]), true))
            ->correct('the the hall', 'en');

        $this->assertSame('The hall', $out['text']);
    }
}
