<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\HazardMonitor;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';

/**
 * Automatic hazard monitoring: what counts as a hazard for Barangay Bayogo,
 * and the full path from a detected typhoon to an urgent announcement,
 * resident notifications and the dashboard advisory (with mocked sources).
 */
class HazardMonitorTest extends TestCase
{
    private const LAT = 9.27;
    private const LON = 125.96;

    /** @var array<string,mixed> */
    private array $savedSettings = [];

    protected function setUp(): void
    {
        foreach (['hazard_advisory_text', 'hazard_advisory_fil', 'hazard_advisory_level', 'hazard_advisory_auto', 'hazard_auto_sms', 'hazard_last_check_at', 'hazard_last_error', 'hazard_lat', 'hazard_lon'] as $k) {
            $this->savedSettings[$k] = Setting::get($k, '');
        }
    }

    protected function tearDown(): void
    {
        $pdo = db();
        $ids = $pdo->query("SELECT announcement_id FROM hazard_alerts WHERE external_id LIKE 'zz-test%' AND announcement_id IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $pdo->prepare("DELETE FROM notifications WHERE related_id = ? AND related_type = 'announcement'")->execute([$id]);
            $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        }
        $pdo->exec("DELETE FROM hazard_alerts WHERE external_id LIKE 'zz-test%'");
        Setting::setMany(array_map('strval', $this->savedSettings));
        Setting::flush();
    }

    private static function typhoonFeed(float $lat, float $lon, string $alert = 'Orange', float $wind = 150): array
    {
        return ['features' => [[
            'geometry' => ['coordinates' => [$lon, $lat]],
            'properties' => ['eventtype' => 'TC', 'eventid' => 'zz-test-tc', 'eventname' => 'TESTBAGYO-26', 'iscurrent' => 'true',
                             'alertlevel' => $alert, 'todate' => date('Y-m-d\TH:i:s', strtotime('+2 days')), 'severitydata' => ['severity' => $wind]],
        ]]];
    }

    public function testTyphoonLevelsByDistance(): void
    {
        $near = HazardMonitor::parseTyphoons(self::typhoonFeed(9.3, 128.7), self::LAT, self::LON);          // ~300 km east
        $mid  = HazardMonitor::parseTyphoons(self::typhoonFeed(9.3, 131.9), self::LAT, self::LON);          // ~650 km
        $far  = HazardMonitor::parseTyphoons(self::typhoonFeed(9.3, 138.0), self::LAT, self::LON);          // ~1300 km
        $gone = HazardMonitor::parseTyphoons(self::typhoonFeed(20.0, -108.8), self::LAT, self::LON);        // Mexico

        $this->assertSame('danger', $near[0]['level']);
        $this->assertStringContainsString('east', $near[0]['title']);
        $this->assertStringContainsString('Typhoon Testbagyo', $near[0]['title']);
        $this->assertSame('warning', $mid[0]['level']);
        $this->assertSame('info', $far[0]['level']);
        $this->assertSame([], $gone);
    }

    public function testWeatherThresholds(): void
    {
        $out = HazardMonitor::parseWeather(['daily' => [
            'time' => ['2026-10-17', '2026-10-18', '2026-10-19'],
            'precipitation_sum' => [12, 65, 130],
            'wind_gusts_10m_max' => [30, 70, 95],
        ]]);
        $levels = array_map(static fn ($h) => $h['source'] . ':' . $h['level'], $out);
        $this->assertSame(['rain:warning', 'wind:warning', 'rain:danger', 'wind:danger'], $levels);
        $this->assertStringContainsString('Sabado, Oktubre 17', HazardMonitor::parseWeather(['daily' => ['time' => ['2026-10-17'], 'precipitation_sum' => [80], 'wind_gusts_10m_max' => [0]]])[0]['title_fil']);
    }

    public function testEarthquakesNearOrTsunami(): void
    {
        $q = static fn (float $lat, float $lon, float $mag, int $tsunami = 0) => ['features' => [[
            'id' => 'zz-test-eq', 'geometry' => ['coordinates' => [$lon, $lat, 10]],
            'properties' => ['mag' => $mag, 'place' => 'Test place', 'time' => time() * 1000, 'tsunami' => $tsunami],
        ]]];
        $this->assertSame('warning', HazardMonitor::parseQuakes($q(9.0, 126.5, 5.8), self::LAT, self::LON)[0]['level']);
        $this->assertSame('danger', HazardMonitor::parseQuakes($q(12.0, 131.0, 7.0, 1), self::LAT, self::LON)[0]['level'], 'tsunami-capable quake within 1000 km');
        $this->assertStringContainsString('higher ground', HazardMonitor::parseQuakes($q(12.0, 131.0, 7.0, 1), self::LAT, self::LON)[0]['summary']);
        $this->assertSame([], HazardMonitor::parseQuakes($q(35.0, 139.0, 6.0), self::LAT, self::LON), 'Japan M6: not ours');
    }

    public function testATyphoonIsPostedNotifiedAndShownThenClearsItself(): void
    {
        Setting::setMany(['hazard_advisory_text' => '', 'hazard_advisory_auto' => '0', 'hazard_auto_sms' => '0', 'hazard_lat' => (string) self::LAT, 'hazard_lon' => (string) self::LON]);
        Setting::flush();
        $calm = ['daily' => ['time' => [date('Y-m-d')], 'precipitation_sum' => [1], 'wind_gusts_10m_max' => [10]]];

        $monitor = new HazardMonitor(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(self::typhoonFeed(9.3, 128.7))),
            new Response(200, [], json_encode($calm)),
            new Response(200, [], json_encode(['features' => []])),
        ])), 'http_errors' => false]));
        $summary = $monitor->run();

        $this->assertSame(1, $summary['new']);
        $alert = db()->query("SELECT * FROM hazard_alerts WHERE external_id = 'zz-test-tc'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('danger', $alert['level']);
        $this->assertNotEmpty($alert['announcement_id'], 'posted as an announcement');

        $post = db()->query('SELECT * FROM announcements WHERE id = ' . (int) $alert['announcement_id'])->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('published', $post['status']);
        $this->assertSame('urgent', $post['urgency']);
        $this->assertSame('safety', $post['category']);
        $this->assertStringContainsString('MATINDING BABALA', $post['title']);
        $this->assertStringContainsString('DANGER', (string) $post['title_en']);
        $this->assertNotNull($post['notified_at'], 'residents were notified');
        $notified = (int) db()->query("SELECT COUNT(*) FROM notifications WHERE related_type = 'announcement' AND related_id = " . (int) $post['id'])->fetchColumn();
        $verified = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND status = 'verified'")->fetchColumn();
        $this->assertGreaterThanOrEqual($verified, $notified, 'every verified resident got an in-app notification');

        Setting::flush();
        $this->assertSame('1', (string) Setting::get('hazard_advisory_auto'));
        $this->assertSame('danger', (string) Setting::get('hazard_advisory_level'));
        $this->assertStringContainsString('Testbagyo', (string) Setting::get('hazard_advisory_text'));
        $this->assertStringContainsString('MATINDING BABALA', (string) Setting::get('hazard_advisory_fil'));

        // The storm leaves: the alert resolves and the automatic advisory clears.
        $after = new HazardMonitor(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['features' => []])),
            new Response(200, [], json_encode($calm)),
            new Response(200, [], json_encode(['features' => []])),
        ])), 'http_errors' => false]));
        $this->assertSame(1, $after->run()['resolved']);
        Setting::flush();
        $this->assertSame('', (string) Setting::get('hazard_advisory_text'));
    }

    public function testAFailedSourceDoesNotClearItsAlerts(): void
    {
        $calm = ['daily' => ['time' => [date('Y-m-d')], 'precipitation_sum' => [1], 'wind_gusts_10m_max' => [10]]];
        db()->exec("INSERT INTO hazard_alerts (source, external_id, level, title, summary, detected_at, updated_at) VALUES ('typhoon', 'zz-test-keep', 'info', 'T', 'S', NOW(), NOW())");
        $monitor = new HazardMonitor(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(503, [], 'down'),
            new Response(200, [], json_encode($calm)),
            new Response(200, [], json_encode(['features' => []])),
        ])), 'http_errors' => false]));
        $summary = $monitor->run();

        $this->assertNotEmpty($summary['errors']);
        $this->assertNull(db()->query("SELECT resolved_at FROM hazard_alerts WHERE external_id = 'zz-test-keep'")->fetchColumn() ?: null);
    }

    public function testAManualAdvisoryIsNotReplacedByAWeakerAutomaticOne(): void
    {
        Setting::setMany(['hazard_advisory_text' => 'Staff: road closed at Purok 3', 'hazard_advisory_level' => 'danger', 'hazard_advisory_auto' => '0']);
        Setting::flush();
        db()->exec("INSERT INTO hazard_alerts (source, external_id, level, title, summary, detected_at, updated_at) VALUES ('rain', 'zz-test-rain', 'warning', 'Rain', 'Heavy rain', NOW(), NOW())");
        HazardMonitor::refreshAdvisory();
        Setting::flush();
        $this->assertSame('Staff: road closed at Purok 3', (string) Setting::get('hazard_advisory_text'));
    }
}
