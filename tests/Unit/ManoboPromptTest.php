<?php
declare(strict_types=1);

use App\Services\AIService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

/**
 * The Manobo translation prompt, and the community-vocabulary injection that
 * feeds it.
 *
 * Two things are worth pinning here:
 *
 *   1. The prompt targets the Manobo actually spoken around Bayogo, Madrid
 *      and explicitly permits code-switching with Surigaonon and Bisaya. An
 *      earlier version of this codebase aimed at Western Bukidnon Manobo — an
 *      unrelated language spoken hundreds of kilometres away — so the target
 *      variety is asserted rather than assumed.
 *
 *   2. Words a Manobo speaker adds at /admin/manobo must actually reach the
 *      model. Without that, curating the dictionary would improve the lookup
 *      pages while leaving AI translations exactly as they were.
 *
 * Both methods under test are private (they are prompt-building internals, not
 * API), so they are reached by reflection. No network call is made anywhere in
 * this file.
 */
final class ManoboPromptTest extends TestCase
{
    private function call(string $method, array $args = []): string
    {
        $service    = new AIService();
        $reflection = new ReflectionMethod(AIService::class, $method);
        $reflection->setAccessible(true);

        return (string) $reflection->invokeArgs($service, $args);
    }

    // ── The prompt itself ────────────────────────────────────────────────

    public function testPromptTargetsTheLocalVariety(): void
    {
        $prompt = $this->call('manoboSystemPrompt');

        // The barangay the system serves, so the model is told where "here"
        // is. This moved from Lanuza to Bayogo, Madrid — the assertion names
        // the current place rather than any place, because naming the WRONG
        // locality is exactly the failure this test exists to catch.
        $this->assertStringContainsString('Bayogo', $prompt);
        $this->assertStringContainsString('Madrid', $prompt);
        $this->assertStringContainsString('Surigao del Sur', $prompt);

        // The language, which did NOT move. Agusan Manobo is the variety the
        // bundled dictionary documents; that citation is independent of which
        // barangay runs this system.
        $this->assertStringContainsString('Agusan Manobo', $prompt);
    }

    public function testPromptDoesNotTargetWesternBukidnonManobo(): void
    {
        // Regression guard. Western Bukidnon Manobo (mbb) is a different
        // language from a different province; targeting it here would produce
        // words no resident of Bayogo speaks.
        $this->assertStringNotContainsString('Western Bukidnon', $this->call('manoboSystemPrompt'));
    }

    /**
     * The whole codebase must name one Manobo.
     *
     * The prompt was corrected long before the method name was, so for a while
     * the code told a reader the target was mbb while actually asking for msm.
     * The next person to touch this would have believed the name.
     */
    public function testTheCodebaseNoLongerReferencesTheWrongVariety(): void
    {
        $this->assertTrue(
            method_exists(\App\Services\AIService::class, 'translatePostToManobo'),
            'The post translator should be named for the language it targets'
        );
        $this->assertFalse(
            method_exists(\App\Services\AIService::class, 'translateToWesternBukidnonManobo'),
            'The mbb-named method must not come back'
        );
        $this->assertFileDoesNotExist(
            __DIR__ . '/../../lang/mbb.php',
            'lang/mbb.php was an empty file for a language nobody here speaks'
        );
        $this->assertArrayNotHasKey('mbb', available_locales());
    }

    public function testPromptForbidsAPurifiedManoboOnlyTranslation(): void
    {
        $prompt = $this->call('manoboSystemPrompt');

        $this->assertStringContainsString('never a "purified", Manobo-only version', $prompt);
        $this->assertStringContainsString('code-switched', $prompt);
        $this->assertStringContainsString('Surigaonon', $prompt);
    }

    public function testPromptCarriesTheDiscourseParticlesRealSpeakersUse(): void
    {
        $prompt = $this->call('manoboSystemPrompt');

        foreach (['man', 'gani', 'bitaw', 'unya', 'kuan'] as $particle) {
            $this->assertStringContainsString($particle, $prompt);
        }
    }

    public function testPromptKeepsTheVocabularyReferenceTable(): void
    {
        $prompt = $this->call('manoboSystemPrompt');

        $this->assertStringContainsString('VOCABULARY REFERENCE', $prompt);
        $this->assertStringContainsString('balay', $prompt,   'place vocabulary');
        $this->assertStringContainsString('kasugoan', $prompt, 'governance vocabulary');
        $this->assertStringContainsString('tabang', $prompt,   'action vocabulary');
    }

    public function testPromptStatesAllSixTranslationRules(): void
    {
        // CHANGED: was five rules; rule 3 was split (numbers-as-digits stay,
        // numbers-as-words get translated) and a new rule 4 ("do not invent
        // Manobo vocabulary") was added — see the two tests below.
        $prompt = $this->call('manoboSystemPrompt');

        foreach (['1.', '2.', '3.', '4.', '5.', '6.'] as $rule) {
            $this->assertStringContainsString($rule, $prompt);
        }
        $this->assertStringContainsString('Prioritise Surigaonon', $prompt);
        $this->assertStringContainsString('Keep proper nouns unchanged', $prompt);
        $this->assertStringContainsString('Output ONLY the translation', $prompt);
    }

    public function testPromptTranslatesSpelledOutNumbersButNotDigits(): void
    {
        // Regression guard: this rule used to say "keep numbers unchanged"
        // outright, which told the model to leave "eight" as "eight" —
        // exactly the leftover-English complaint the rule was meant to
        // prevent elsewhere in the app.
        $prompt = $this->call('manoboSystemPrompt');

        $this->assertStringContainsString('numbers already written as digits', $prompt);
        $this->assertStringContainsString('written out as words', $prompt);
        $this->assertStringContainsString('into the local blend', $prompt);
    }

    public function testPromptForbidsInventingManoboVocabulary(): void
    {
        // Deliberately does NOT check for the literal "COMMUNITY-VERIFIED
        // VOCABULARY" heading here — that string is reserved for when a
        // hint is actually injected (see testTheHintIsActuallyAppendedTo
        // TheSystemPrompt, which checks the PLAIN prompt has none of it).
        $prompt = $this->call('manoboSystemPrompt');

        $this->assertStringContainsString('Do not invent Manobo vocabulary', $prompt);
        $this->assertStringContainsString('vocabulary notes appended below', $prompt);
    }

    // ── Community vocabulary injection ───────────────────────────────────

    public function testTextWithNoDictionaryWordsAddsNoHint(): void
    {
        $this->assertSame('', $this->call('communityVocabHint', ['zzqq wwvv xxyy']));
    }

    public function testADictionaryWordInTheTextBecomesPromptContext(): void
    {
        // "shoulder" is in the bundled dataset as a'baga / balikat.
        $hint = $this->call('communityVocabHint', ['My shoulder hurts today']);

        if ($hint === '') {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }

        $this->assertStringContainsString('COMMUNITY-VERIFIED VOCABULARY', $hint);
        $this->assertStringContainsString('baga', $hint, 'the Manobo headword reaches the prompt');
    }

    public function testTheHintIsActuallyAppendedToTheSystemPrompt(): void
    {
        $hint = $this->call('communityVocabHint', ['My shoulder hurts today']);

        if ($hint === '') {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }

        $withHint = $this->call('manoboSystemPrompt', [$hint]);
        $plain    = $this->call('manoboSystemPrompt');

        $this->assertStringContainsString($hint, $withHint, 'curated words must reach the model');
        $this->assertStringNotContainsString('COMMUNITY-VERIFIED', $plain);
        $this->assertGreaterThan(mb_strlen($plain), mb_strlen($withHint));
    }

    public function testBisayaFallsBackIntoTheHintWhenManoboHasNoEntry(): void
    {
        // ADDED: "ordinansa" is a Bisaya-dictionary word (migration 030) with
        // no Manobo equivalent — the hint should ground the model's Bisaya
        // fallback the same way it already grounds Manobo.
        $hint = $this->call('communityVocabHint', ['Basahin ang ordinansa bago ang miting']);

        if ($hint === '') {
            $this->markTestSkipped('Neither dataset available in this environment.');
        }

        $this->assertStringContainsString('COMMUNITY-REVIEWED BISAYA VOCABULARY', $hint);
        $this->assertStringContainsString('ordinansa', $hint);
    }

    public function testBisayaHintExcludesWordsManoboAlreadyAnswered(): void
    {
        // A word Manobo already covers should not ALSO get a Bisaya
        // suggestion that could contradict it — "tubig" (water) is
        // attested in Manobo as wo'hig. Both methods are public (their own
        // docblocks explain why — the count is worth recording), so no
        // reflection needed here.
        $service    = new AIService();
        $manoboHits = $service->attestedVocabulary('May sapat na tubig ba?');

        if ($manoboHits === []) {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }

        $bisayaHits = $service->attestedBisayaVocabulary('May sapat na tubig ba?', $manoboHits);

        foreach ($bisayaHits as $line) {
            $this->assertStringNotContainsStringIgnoringCase('tubig = ', $line);
        }
    }

    public function testAPluralInTheTextStillFindsTheSingularEntry(): void
    {
        // Real sentences carry plurals while the dictionary stores singulars.
        // Without the singular retry, "eyes" in a sentence never reached the
        // model even though "eye" is in the dataset.
        $hint = $this->call('communityVocabHint', ['Check the eyes of the child']);

        if ($this->call('communityVocabHint', ['Check the eye of the child']) === '') {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }

        $this->assertNotSame('', $hint, 'a plural should still produce a vocabulary hint');
        $this->assertStringContainsString('mata', $hint);
    }

    public function testACitationFormEntryReachesThePromptFromAPlainWord(): void
    {
        // The dataset records "mother!" as a term of address; a sentence says
        // "mother". Both must land on the same entry.
        $hint = $this->call('communityVocabHint', ['Bring your mother tomorrow']);

        if ($hint === '') {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }

        $this->assertStringContainsString('nay', $hint);
    }

    public function testVeryShortTokensAreIgnoredWhenBuildingTheHint(): void
    {
        // Two-letter tokens would match noise rather than vocabulary.
        $this->assertSame('', $this->call('communityVocabHint', ['a an is of to']));
    }

    public function testTheHintIsBoundedSoThePromptCannotRunAway(): void
    {
        // Feed the whole dictionary back in; the hint must still be capped.
        // CHANGED: the hint now has two independently-capped sections —
        // Manobo (attestedVocabulary) and Bisaya (attestedBisayaVocabulary),
        // 25 lines each — since feeding the SAME text can attest words in
        // both dictionaries, the combined ceiling is 50, not 25.
        $dictionary = new App\Services\ManoboDictionary();
        $everyWord  = implode(' ', array_column($dictionary->all(), 'english'));

        $hint  = $this->call('communityVocabHint', [$everyWord]);
        $lines = array_filter(explode("\n", $hint), static fn (string $l): bool => str_contains($l, ' = '));

        $this->assertLessThanOrEqual(50, count($lines), 'hint is capped at 25 entries per dictionary');
    }

    // ── Offline fallback gloss ───────────────────────────────────────────

    /**
     * @return array<string,mixed>|null
     */
    private function gloss(string $text)
    {
        $controller = new App\Controllers\AIController();
        $method     = new ReflectionMethod(App\Controllers\AIController::class, 'offlineGloss');
        $method->setAccessible(true);

        return $method->invoke($controller, $text);
    }

    public function testOfflineGlossProducesManoboForASentenceItPartlyKnows(): void
    {
        $gloss = $this->gloss('May tubig at gamot para sa ubo. Dalhin ang bata sa Barangay Hall.');

        if ($gloss === null) {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }

        $this->assertTrue($gloss['offline'], 'must be flagged as a gloss, never as a translation');
        $this->assertStringContainsString("wo'hig", $gloss['translation']);
        $this->assertGreaterThanOrEqual(2, $gloss['matched']);
        $this->assertGreaterThan($gloss['matched'], $gloss['total']);
    }

    public function testOfflineGlossReportsWhatItCouldNotTranslate(): void
    {
        // CHANGED: this sentence used to leave a word or two uncovered, but
        // offlineGloss() now also falls back to the Bisaya dictionary (see
        // ManoboAutoTranslator), and between the two "May tubig at gamot
        // para sa ubo" now resolves in full — a genuine coverage
        // improvement, not a regression. A made-up word guarantees this
        // still exercises the "report what's missing" path.
        $gloss = $this->gloss('May tubig at gamot para sa ubo, zzqqnonexistentword.');

        if ($gloss === null) {
            $this->markTestSkipped('Manobo dataset unavailable in this environment.');
        }

        $this->assertIsArray($gloss['missing']);
        $this->assertNotEmpty($gloss['missing'], 'the resident must see what is not covered');
        $this->assertContains('zzqqnonexistentword', $gloss['missing']);
    }

    public function testOfflineGlossRefusesTextItCannotHelpWith(): void
    {
        // Nothing matched — an unchanged sentence is worse than an honest error.
        $this->assertNull($this->gloss('Zzqq wwvv xxyy nnmm pplk jjhh kkll.'));
    }

    public function testOfflineGlossSuppressesASingleStrayMatchInALongText(): void
    {
        // One hit buried in a long announcement is noise, not a reading aid.
        $long = 'tubig ' . str_repeat('lorem ipsum dolor sit amet consectetur ', 6);

        $gloss = $this->gloss($long);
        if ($gloss !== null) {
            $this->assertGreaterThanOrEqual(2, $gloss['matched'], 'a lone match should not qualify');
        }
        $this->addToAssertionCount(1);
    }

    // ── Behaviour with no API key configured ─────────────────────────────

    public function testTranslationDegradesGracefullyWithoutAnApiKey(): void
    {
        if (!empty($_ENV['ANTHROPIC_API_KEY'])) {
            $this->markTestSkipped('An API key is configured; this asserts the no-key path.');
        }

        $result = (new AIService())->translateToManobo('Kumusta ka?');

        $this->assertNotSame('', $result);
        $this->assertStringContainsString('AI translation service', $result);
    }
}
