<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FreeTranslationService;
use App\Services\ManoboHybridTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the resident translator fixes of 2026-10-13.
 * None of these touch the network or the database.
 */
class TranslationFixesTest extends TestCase
{
    public function testAgencyAcronymsAreMaskedAndRestored(): void
    {
        $source = 'Ang PAGASA ay nagtaas ng signal; makipag-ugnayan sa MDRRMO o sa DSWD.';
        [$masked, $map] = FreeTranslationService::protectAcronyms($source);

        // "PAGASA" was translated as "Hope" (pag-asa) on a storm warning.
        $this->assertStringNotContainsString('PAGASA', $masked);
        $this->assertCount(3, $map);

        // Engines sometimes change the case of the placeholder.
        $translated = str_replace('ZQX0', 'zqx0', 'ZQX0 has raised the signal; contact ZQX1 or ZQX2.');
        $this->assertSame(
            'PAGASA has raised the signal; contact MDRRMO or DSWD.',
            FreeTranslationService::restoreAcronyms($translated, $map)
        );
    }

    public function testLocalPlaceNamesAreProtected(): void
    {
        // "Bayogo Barangay Hall" came back as "Bay Barangay Hall" — the wrong
        // evacuation centre on a storm warning.
        $source = 'Bukas na ang Bayogo Barangay Hall para sa Purok 1 at Purok 12 ng Surigao del Sur.';
        [$masked, $map] = FreeTranslationService::protectAcronyms($source);

        $this->assertStringNotContainsString('Bayogo', $masked);
        $this->assertStringNotContainsString('Purok', $masked);
        $this->assertContains('Surigao del Sur', $map, 'the longer name wins over "Surigao"');
        $this->assertSame($source, FreeTranslationService::restoreAcronyms($masked, $map));
    }

    public function testAcronymsInsideOtherWordsAreLeftAlone(): void
    {
        [$masked, $map] = FreeTranslationService::protectAcronyms('Ang DAAN at ang SKILLS training.');
        $this->assertSame('Ang DAAN at ang SKILLS training.', $masked);
        $this->assertSame([], $map);
    }

    public function testTenOrMorePlaceholdersRestoreWithoutClashing(): void
    {
        $source = implode(' ', array_fill(0, 12, 'DOH'));
        [$masked, $map] = FreeTranslationService::protectAcronyms($source);
        $this->assertSame($source, FreeTranslationService::restoreAcronyms($masked, $map));
    }

    public function testGarbledMachineBisayaIsRejected(): void
    {
        // Stored as the Manobo of an "APPRECIATION POST" heading.
        $this->assertTrue(ManoboHybridTranslator::looksForeign('SAPALIBOTSAABRAQALḨABĀRĪ'));
        $this->assertTrue(ManoboHybridTranslator::looksForeign('Привет'));
        $this->assertFalse(ManoboHybridTranslator::looksForeign('Maayong buntag sa tanan, Señor Niño.'));
    }

    public function testDisplayTranslationLeavesTextAlreadyInTheTargetLanguage(): void
    {
        $english = 'Your document request has been approved and is ready for pickup at the barangay hall.';
        $this->assertSame($english, translate_for_display($english, 'en'));
        $this->assertSame('12:30 — 4/5', translate_for_display('12:30 — 4/5', 'fil'));
    }

    public function testShortUnclearTextIsLeftAloneWhenAskedToBeSure(): void
    {
        // Event venues: a place name must not be "translated" on a guess.
        $this->assertSame('Bayogo Gym', translate_for_display('Bayogo Gym', 'msm', true));
    }
}
