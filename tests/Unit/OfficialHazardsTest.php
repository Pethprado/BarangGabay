<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OfficialHazards;
use PHPUnit\Framework\TestCase;

/**
 * Reading PAGASA's CAP alerts and PHIVOLCS's earthquake table, with
 * samples in the exact shape those sites publish.
 */
class OfficialHazardsTest extends TestCase
{
    private const LAT = 9.27;
    private const LON = 125.96;

    private static function cap(string $event, string $severity, string $urgency, string $areaDesc, string $polygon, string $msgType = 'Alert'): string
    {
        $expires = date('c', strtotime('+12 hours'));
        $sent    = date('c');

        return <<<XML
<?xml version="1.0"?>
<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><identifier>x</identifier><sender>PAGASA-DOST</sender><sent>{$sent}</sent><status>Actual</status><msgType>{$msgType}</msgType><scope>Public</scope>
<info><category>Met</category><event>{$event}</event><urgency>{$urgency}</urgency><severity>{$severity}</severity><certainty>Likely</certainty><expires>{$expires}</expires>
<headline>{$event}</headline><description>Heavy rains expected.
 + **Surigao Del Sur** - Rivers and its tributaries particularly Cantilan, Carac-An.</description>
<instruction>People living near the mountain slopes and in the low lying areas are advised to take precautionary measures.</instruction>
<area><areaDesc>{$areaDesc}</areaDesc><polygon>{$polygon}</polygon></area></info></alert>
XML;
    }

    /** Roughly the Surigao del Sur polygon from PAGASA's feed (contains Madrid). */
    private const SDS = '7.94,126.37 8.84,125.88 9.18,125.76 9.48,125.92 9.41,125.89 9.23,126.05 9.30,126.16 9.08,126.20 8.64,126.09 8.09,126.44 7.94,126.37';

    public function testAlertCoveringTheBarangayByPolygon(): void
    {
        $h = OfficialHazards::parseCap(self::cap('Heavy Rainfall Warning', 'Severe', 'Expected', 'Caraga coast', self::SDS), self::LAT, self::LON);

        $this->assertNotNull($h);
        $this->assertSame('pagasa', $h['source']);
        $this->assertSame('warning', $h['level']);
        $this->assertSame('rain', $h['kind']);
        $this->assertStringContainsString('PAGASA', $h['title']);
        $this->assertStringNotContainsString('**', $h['description'], 'markdown removed');
        $this->assertStringContainsString('precautionary', $h['instruction']);
    }

    public function testAlertCoveringTheProvinceByName(): void
    {
        $h = OfficialHazards::parseCap(self::cap('Tropical Cyclone Wind Signal No. 2', 'Extreme', 'Immediate', 'Surigao Del Sur', '0,0 0,1 1,1 0,0'), self::LAT, self::LON);
        $this->assertSame('danger', $h['level']);
        $this->assertSame('typhoon', $h['kind']);
    }

    public function testAlertsElsewhereAreIgnored(): void
    {
        $manila = '14.4,120.9 14.8,120.9 14.8,121.2 14.4,121.2 14.4,120.9';
        $this->assertNull(OfficialHazards::parseCap(self::cap('Heavy Rainfall Warning', 'Severe', 'Expected', 'Metro Manila', $manila), self::LAT, self::LON));
    }

    public function testModerateFloodAdvisoryIsANoticeAndCancelIsRecognised(): void
    {
        $this->assertSame('info', OfficialHazards::parseCap(self::cap('General Flood Advisory (Moderate)', 'Moderate', 'Future', 'Surigao Del Sur', self::SDS), self::LAT, self::LON)['level']);
        $this->assertTrue(OfficialHazards::parseCap(self::cap('Heavy Rainfall Warning', 'Severe', 'Expected', 'Surigao Del Sur', self::SDS, 'Cancel'), self::LAT, self::LON)['cancel']);
    }

    public function testFeedEntriesForOurRegionOrNational(): void
    {
        $this->assertTrue(OfficialHazards::feedEntryIsRelevant('GFA #5 - Region 13 (Caraga)'));
        $this->assertTrue(OfficialHazards::feedEntryIsRelevant('Tropical Cyclone Bulletin #4 for Typhoon "Ofel"'));
        $this->assertFalse(OfficialHazards::feedEntryIsRelevant('GFA #5 - BARMM (Bangsamoro Autonomous Region of Muslim Mindanao)'));
        $this->assertFalse(OfficialHazards::feedEntryIsRelevant('GFA #3 - Region 4-B (MIMAROPA)'));

        $atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><id>urn:uuid:1</id><title>GFA #5 - Region 13 (Caraga)</title><updated>2026-10-16T16:38:22+08:00</updated>'
              . '<link type="application/cap+xml" href="https://publicalert.pagasa.dost.gov.ph/output/gfa/1.cap"/></entry></feed>';
        $this->assertSame('https://publicalert.pagasa.dost.gov.ph/output/gfa/1.cap', OfficialHazards::parseFeed($atom)[0]['cap']);
    }

    public function testPhivolcsTableAndLevels(): void
    {
        $row = static fn (string $when, string $lat, string $lon, string $mag, string $place) =>
            '<tr><td><a href="2026_Earthquake_Information\\October\\b.html">' . $when . '</a></td><td>' . $lat . '</td><td>' . $lon . '</td><td>010</td><td>' . $mag . '</td><td>' . $place . '</td></tr>';
        $now  = date('d F Y - h:i A');
        $html = '<table>' . $row($now, '09.10', '126.30', '5.8', '040 km N 80° E of Tandag City (Surigao Del Sur)')
              . $row($now, '09.10', '126.30', '5.8', '040 km N 80° E of Tandag City (Surigao Del Sur)')   // the site repeats rows
              . $row($now, '05.93', '126.25', '2.1', 'Far away') . '</table>';

        $quakes = OfficialHazards::parsePhivolcs($html);
        $this->assertCount(2, $quakes, 'duplicates dropped');
        $this->assertStringContainsString('2026_Earthquake_Information/October/b.html', $quakes[0]['bulletin']);

        $haz = OfficialHazards::quakeHazards($quakes, self::LAT, self::LON);
        $this->assertCount(1, $haz);
        $this->assertSame('warning', $haz[0]['level']);
        $this->assertStringContainsString('PHIVOLCS', $haz[0]['title']);

        $tsunami = OfficialHazards::quakeHazards($quakes, self::LAT, self::LON, [$quakes[0]['bulletin'] => 'Tsunami Warning: residents of coastal areas are advised to evacuate.']);
        $this->assertSame('danger', $tsunami[0]['level']);
        $none = OfficialHazards::quakeHazards($quakes, self::LAT, self::LON, [$quakes[0]['bulletin'] => 'No destructive tsunami threat exists based on available data.']);
        $this->assertSame('warning', $none[0]['level']);
    }

    public function testPointInPolygon(): void
    {
        $square = [[9.0, 125.0], [10.0, 125.0], [10.0, 126.0], [9.0, 126.0]];
        $this->assertTrue(OfficialHazards::pointInPolygon(9.27, 125.96, $square));
        $this->assertFalse(OfficialHazards::pointInPolygon(14.6, 121.0, $square));
    }
}
