<?php
declare(strict_types=1);

/**
 * Run the automatic hazard check now (typhoons, heavy rain, strong wind,
 * earthquakes) and print what it found.
 *
 *   php tools/hazard-check.php           check now (ignores the 20-minute pause)
 *   php tools/hazard-check.php --dry     only show what the sources report; post nothing
 *
 * The portal also runs this by itself in the background from normal page
 * visits; this script is for a cron job or for testing.
 */

require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../config/database.php';

use App\Services\HazardMonitor;
use GuzzleHttp\Client;

[$lat, $lon] = HazardMonitor::location();
echo "Location: {$lat}, {$lon}\n";

if (in_array('--dry', $argv, true)) {
    $http = new Client(['timeout' => 15, 'http_errors' => false]);
    $get  = static fn (string $url, array $q): array => json_decode((string) $http->get($url, ['query' => $q])->getBody(), true) ?: [];
    $found = array_merge(
        HazardMonitor::parseTyphoons($get('https://www.gdacs.org/gdacsapi/api/events/geteventlist/SEARCH', ['eventlist' => 'TC', 'alertlevel' => 'green;orange;red', 'fromdate' => date('Y-m-d', strtotime('-10 days')), 'todate' => date('Y-m-d', strtotime('+1 day'))]), $lat, $lon),
        HazardMonitor::parseWeather($get('https://api.open-meteo.com/v1/forecast', ['latitude' => $lat, 'longitude' => $lon, 'timezone' => 'Asia/Manila', 'forecast_days' => 3, 'daily' => 'precipitation_sum,wind_gusts_10m_max'])),
        HazardMonitor::parseQuakes($get('https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/4.5_day.geojson', []), $lat, $lon)
    );
    echo $found === [] ? "No hazard near the barangay right now.\n" : '';
    foreach ($found as $h) {
        echo strtoupper($h['level']), ': ', $h['title'], "\n   ", $h['summary'], "\n";
    }
    exit(0);
}

$summary = (new HazardMonitor())->run();
echo json_encode($summary, JSON_PRETTY_PRINT), "\n";
