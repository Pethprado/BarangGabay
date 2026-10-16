<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use GuzzleHttp\Client;
use PDO;

/**
 * Automatic hazard monitoring for the barangay.
 *
 * Every CHECK_EVERY seconds (driven by site traffic in the background, by
 * tools/hazard-check.php from cron, or by staff pressing "Check now") it
 * reads the official Philippine sources first (OfficialHazards):
 *
 *   - PAGASA public alerts (CAP feed): tropical cyclone, rainfall, flood
 *     and thunderstorm warnings whose area covers the barangay.
 *   - PHIVOLCS latest earthquakes, with the bulletin's tsunami statement.
 *
 * and, as a backup when PAGASA has nothing for that hazard yet, three free
 * international sources, no account or key needed:
 *
 *   - GDACS (UN/EC Global Disaster Alert and Coordination System): tropical
 *     cyclones that are active now, with their current position.
 *   - Open-Meteo: the forecast for the barangay's own coordinates
 *     (daily rain and wind gusts, today and the next two days).
 *   - USGS: earthquakes of magnitude 4.5+ in the past day, with the
 *     tsunami flag.
 *
 * A hazard at warning or danger level is posted as an urgent announcement
 * (English + Filipino), which notifies every verified resident (in-app,
 * email, and SMS when enabled), asks for a safety check-in when it is a
 * danger, and becomes the Hazard Advisory on the resident dashboard. A
 * level increase posts an update; when the hazard passes, the automatic
 * advisory clears itself. A manual advisory written by staff is never
 * replaced by a weaker automatic one.
 *
 * These are early warnings from international data, not official PAGASA or
 * PHIVOLCS bulletins; every post says so and points residents to them.
 */
final class HazardMonitor
{
    public const CHECK_EVERY = 1200;   // 20 minutes
    public const RANK = ['info' => 1, 'warning' => 2, 'danger' => 3];

    /** Madrid, Surigao del Sur (Barangay Bayogo); editable on the admin dashboard. */
    public const DEFAULT_LAT = 9.27;
    public const DEFAULT_LON = 125.96;

    private const GDACS = 'https://www.gdacs.org/gdacsapi/api/events/geteventlist/SEARCH';
    private const OPEN_METEO = 'https://api.open-meteo.com/v1/forecast';
    private const USGS = 'https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/4.5_day.geojson';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 12, 'connect_timeout' => 6, 'http_errors' => false,
            'headers' => ['User-Agent' => 'BarangGabay hazard monitor (barangay portal)']]);
    }

    public static function enabled(): bool
    {
        return (string) Setting::get('hazard_auto_enabled', '1') !== '0';
    }

    /** @return array{0:float,1:float} */
    public static function location(): array
    {
        $lat = (float) Setting::get('hazard_lat', self::DEFAULT_LAT);
        $lon = (float) Setting::get('hazard_lon', self::DEFAULT_LON);

        return [$lat ?: self::DEFAULT_LAT, $lon ?: self::DEFAULT_LON];
    }

    /** Run when the last check is old enough (and only one request does it). */
    public function runIfDue(): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        $last = (int) Setting::get('hazard_last_check_at', 0);
        if (time() - $last < self::CHECK_EVERY) {
            return null;
        }
        // Claim the slot before the slow part so parallel requests skip.
        Setting::set('hazard_last_check_at', (string) time());
        Setting::flush();

        return $this->run();
    }

    /**
     * Check every source, record and post what is new, resolve what passed,
     * and refresh the dashboard advisory.
     *
     * @return array{new:int, updated:int, resolved:int, active:int, errors:list<string>}
     */
    public function run(): array
    {
        [$lat, $lon] = self::location();
        $found  = [];
        $failed = [];
        $errors = [];

        // 1. Official Philippine sources: PAGASA alerts and PHIVOLCS earthquakes.
        foreach (['pagasa' => fn () => $this->pagasa($lat, $lon), 'phivolcs' => fn () => $this->phivolcs($lat, $lon)] as $group => $fetch) {
            try {
                foreach ($fetch() as $hazard) {
                    if (!empty($hazard['cancel'])) {
                        db()->prepare('UPDATE hazard_alerts SET resolved_at = NOW() WHERE source = ? AND external_id = ? AND resolved_at IS NULL')
                            ->execute([$hazard['source'], $hazard['external_id']]);
                        continue;
                    }
                    $found[$hazard['source'] . '|' . $hazard['external_id']] = $hazard;
                }
            } catch (\Throwable $e) {
                $failed[] = $group;
                $errors[] = $group . ': ' . $e->getMessage();
                error_log('[HazardMonitor] ' . $group . ': ' . $e->getMessage());
            }
        }

        // What PAGASA already covers (this run or still active) is not
        // posted again from the international backup sources.
        $officialKinds = [];
        foreach ($found as $h) {
            $officialKinds[$h['kind'] ?? 'other'] = true;
        }
        foreach (self::active() as $row) {
            $d = json_decode((string) ($row['data'] ?? ''), true);
            if ($row['source'] === 'pagasa' && is_array($d)) {
                $officialKinds[$d['kind'] ?? 'other'] = true;
            }
        }
        $phivolcsAnswered = !in_array('phivolcs', $failed, true);

        // 2. International backup: GDACS, Open-Meteo, and USGS when PHIVOLCS did not answer.
        foreach ([
            'typhoon'    => fn () => self::parseTyphoons($this->json(self::GDACS, [
                'eventlist' => 'TC', 'alertlevel' => 'green;orange;red',
                'fromdate' => date('Y-m-d', strtotime('-10 days')), 'todate' => date('Y-m-d', strtotime('+1 day')),
            ]), $lat, $lon),
            'weather'    => fn () => self::parseWeather($this->json(self::OPEN_METEO, [
                'latitude' => $lat, 'longitude' => $lon, 'timezone' => 'Asia/Manila', 'forecast_days' => 3,
                'daily' => 'precipitation_sum,wind_gusts_10m_max',
            ])),
            'earthquake' => fn () => $phivolcsAnswered ? [] : self::parseQuakes($this->json(self::USGS, []), $lat, $lon),
        ] as $group => $fetch) {
            try {
                foreach ($fetch() as $hazard) {
                    $kind = ['typhoon' => 'typhoon', 'rain' => 'rain'][$hazard['source']] ?? 'other';
                    if (isset($officialKinds[$kind])) {
                        continue;   // PAGASA has an official alert for this already
                    }
                    $found[$hazard['source'] . '|' . $hazard['external_id']] = $hazard;
                }
            } catch (\Throwable $e) {
                $failed[] = $group;
                $errors[] = $group . ': ' . $e->getMessage();
                error_log('[HazardMonitor] ' . $group . ': ' . $e->getMessage());
            }
        }

        $summary = ['new' => 0, 'updated' => 0, 'resolved' => 0, 'active' => 0, 'errors' => $errors];
        foreach ($found as $hazard) {
            $outcome = $this->record($hazard);
            if ($outcome === 'new') {
                $summary['new']++;
            } elseif ($outcome === 'updated') {
                $summary['updated']++;
            }
        }
        $summary['resolved'] = $this->resolveGone(array_keys($found), $failed);
        $summary['active']   = count(self::active());
        self::refreshAdvisory();

        Setting::setMany([
            'hazard_last_check_at' => (string) time(),
            'hazard_last_error'    => implode(' | ', $errors),
        ]);
        Setting::flush();

        return $summary;
    }

    // ── Sources (pure functions, tested without the network) ───────────

    /**
     * Active tropical cyclones near the barangay.
     *
     * @return list<array<string,mixed>>
     */
    public static function parseTyphoons(array $geojson, float $lat, float $lon): array
    {
        $out = [];
        foreach ((array) ($geojson['features'] ?? []) as $f) {
            $p = (array) ($f['properties'] ?? []);
            $c = (array) ($f['geometry']['coordinates'] ?? []);
            if (($p['eventtype'] ?? '') !== 'TC' || (string) ($p['iscurrent'] ?? 'false') !== 'true' || count($c) < 2) {
                continue;
            }
            $km = self::distanceKm($lat, $lon, (float) $c[1], (float) $c[0]);
            if ($km > 1500) {
                continue;
            }
            $alert = strtolower((string) ($p['alertlevel'] ?? 'green'));
            $level = $km <= 400 || ($alert === 'red' && $km <= 1000) ? 'danger' : ($km <= 800 ? 'warning' : 'info');
            $wind  = (float) ($p['severitydata']['severity'] ?? 0);
            $name  = ucwords(strtolower((string) preg_replace('/-\d+$/', '', (string) ($p['eventname'] ?? 'Tropical cyclone'))));
            $kind  = self::cycloneKind($wind);
            $dir   = self::compass($lat, $lon, (float) $c[1], (float) $c[0]);
            $kmTxt = number_format(round($km / 10) * 10);

            $out[] = [
                'source' => 'typhoon', 'external_id' => (string) ($p['eventid'] ?? $name), 'level' => $level,
                'title' => "{$kind[0]} {$name} about {$kmTxt} km {$dir[0]} of the barangay",
                'title_fil' => "{$kind[1]} {$name}, mga {$kmTxt} km sa {$dir[1]} ng barangay",
                'summary' => "{$kind[0]} {$name}" . ($wind > 0 ? " (winds up to " . round($wind) . " km/h)" : '') . " is about {$kmTxt} km {$dir[0]} of Barangay Bayogo.",
                'summary_fil' => "Ang {$kind[1]} {$name}" . ($wind > 0 ? " (hangin hanggang " . round($wind) . " km/h)" : '') . " ay mga {$kmTxt} km sa {$dir[1]} ng Barangay Bayogo.",
                'actions' => $level === 'danger'
                    ? ['Stay indoors, away from windows. Secure or bring in loose objects.', 'If you live near the coast, rivers or slopes, go to the nearest evacuation centre when told.', 'Keep phones charged and an emergency kit (water, food, medicine, flashlight) ready.']
                    : ['Prepare an emergency kit: water, food, medicine, flashlight, charged phones.', 'Secure loose objects around the house.', 'Know your nearest evacuation centre and follow barangay announcements.'],
                'actions_fil' => $level === 'danger'
                    ? ['Manatili sa loob ng bahay, malayo sa bintana. Itali o ipasok ang mga bagay na maaaring liparin.', 'Kung malapit kayo sa dagat, ilog o dalisdis, pumunta sa pinakamalapit na evacuation centre kapag sinabihan.', 'I-charge ang telepono at ihanda ang emergency kit (tubig, pagkain, gamot, flashlight).']
                    : ['Ihanda ang emergency kit: tubig, pagkain, gamot, flashlight, naka-charge na telepono.', 'Itali ang mga bagay sa paligid ng bahay na maaaring liparin.', 'Alamin ang pinakamalapit na evacuation centre at sundan ang mga anunsyo ng barangay.'],
                'official' => 'PAGASA',
                'expires_at' => !empty($p['todate']) ? date('Y-m-d H:i:s', strtotime((string) $p['todate'] . ' +12 hours')) : null,
                'data' => ['km' => round($km), 'wind_kmh' => $wind, 'alert' => $alert, 'gdacs_url' => $p['url']['report'] ?? null],
            ];
        }

        return $out;
    }

    /**
     * Heavy rain and strong wind in the barangay's forecast (today + 2 days).
     *
     * @return list<array<string,mixed>>
     */
    public static function parseWeather(array $json): array
    {
        $out   = [];
        $daily = (array) ($json['daily'] ?? []);
        foreach ((array) ($daily['time'] ?? []) as $i => $day) {
            $rain = (float) ($daily['precipitation_sum'][$i] ?? 0);
            $gust = (float) ($daily['wind_gusts_10m_max'][$i] ?? 0);
            $when = date('l, F j', strtotime((string) $day));
            $whenFil = self::filDate((string) $day);
            $expires = date('Y-m-d 23:59:59', strtotime((string) $day));

            if ($rain >= 50) {
                $danger = $rain >= 100;
                $mm = (int) round($rain);
                $out[] = [
                    'source' => 'rain', 'external_id' => 'rain-' . $day, 'level' => $danger ? 'danger' : 'warning',
                    'title' => ($danger ? 'Intense rainfall' : 'Heavy rain') . " expected on {$when}",
                    'title_fil' => ($danger ? 'Matinding ulan' : 'Malakas na ulan') . " sa {$whenFil}",
                    'summary' => "About {$mm} mm of rain is forecast for Barangay Bayogo on {$when}." . ($danger ? ' Flooding and landslides are possible.' : ' Low-lying areas may flood.'),
                    'summary_fil' => "Mga {$mm} mm na ulan ang inaasahan sa Barangay Bayogo sa {$whenFil}." . ($danger ? ' Posible ang pagbaha at pagguho ng lupa.' : ' Maaaring bumaha sa mabababang lugar.'),
                    'actions' => ['Avoid crossing flooded roads and rivers.', 'Families near rivers and slopes: be ready to move to higher ground or the evacuation centre.', 'Clear drainage around your house.'],
                    'actions_fil' => ['Iwasang tumawid sa bahang daan at ilog.', 'Mga pamilyang malapit sa ilog at dalisdis: maging handang lumipat sa mataas na lugar o sa evacuation centre.', 'Linisin ang mga daluyan ng tubig sa paligid ng bahay.'],
                    'official' => 'PAGASA', 'expires_at' => $expires, 'data' => ['rain_mm' => $rain, 'date' => $day],
                ];
            }
            if ($gust >= 62) {
                $danger = $gust >= 89;
                $kmh = (int) round($gust);
                $out[] = [
                    'source' => 'wind', 'external_id' => 'wind-' . $day, 'level' => $danger ? 'danger' : 'warning',
                    'title' => ($danger ? 'Damaging winds' : 'Strong winds') . " expected on {$when}",
                    'title_fil' => ($danger ? 'Mapanganib na hangin' : 'Malakas na hangin') . " sa {$whenFil}",
                    'summary' => "Wind gusts up to {$kmh} km/h are forecast for Barangay Bayogo on {$when}.",
                    'summary_fil' => "Inaasahan ang bugso ng hangin na hanggang {$kmh} km/h sa Barangay Bayogo sa {$whenFil}.",
                    'actions' => ['Secure roofs, signs and loose objects.', 'Fishers: do not go out to sea.', 'Stay away from trees and power lines during strong gusts.'],
                    'actions_fil' => ['Siguraduhin ang bubong, karatula at mga bagay na maaaring liparin.', 'Mga mangingisda: huwag munang pumalaot.', 'Lumayo sa mga puno at kable ng kuryente kapag malakas ang hangin.'],
                    'official' => 'PAGASA', 'expires_at' => $expires, 'data' => ['gust_kmh' => $gust, 'date' => $day],
                ];
            }
        }

        return $out;
    }

    /**
     * Earthquakes felt or dangerous here (USGS, past day).
     *
     * @return list<array<string,mixed>>
     */
    public static function parseQuakes(array $geojson, float $lat, float $lon): array
    {
        $out = [];
        foreach ((array) ($geojson['features'] ?? []) as $f) {
            $p = (array) ($f['properties'] ?? []);
            $c = (array) ($f['geometry']['coordinates'] ?? []);
            if (count($c) < 2) {
                continue;
            }
            $mag     = (float) ($p['mag'] ?? 0);
            $km      = self::distanceKm($lat, $lon, (float) $c[1], (float) $c[0]);
            $tsunami = (int) ($p['tsunami'] ?? 0) === 1;

            $level = null;
            if ($tsunami && $mag >= 6.5 && $km <= 1000) {
                $level = 'danger';
            } elseif ($km <= 300 && $mag >= 6.5) {
                $level = 'danger';
            } elseif ($km <= 300 && $mag >= 5.5) {
                $level = 'warning';
            } elseif ($km <= 150 && $mag >= 4.5) {
                $level = 'info';
            }
            if ($level === null) {
                continue;
            }

            $time  = (int) (((float) ($p['time'] ?? 0)) / 1000) ?: time();
            $at    = date('M j, g:i A', $time);
            $dir   = self::compass($lat, $lon, (float) $c[1], (float) $c[0]);
            $kmTxt = number_format(round($km));
            $m     = number_format($mag, 1);
            $tsunamiNote    = $tsunami && $level === 'danger' ? ' A tsunami may follow: if you are near the shore, move to higher ground now.' : '';
            $tsunamiNoteFil = $tsunami && $level === 'danger' ? ' Maaaring sumunod ang tsunami: kung malapit kayo sa dalampasigan, lumikas na sa mataas na lugar.' : '';

            $out[] = [
                'source' => 'earthquake', 'external_id' => (string) ($f['id'] ?? $p['code'] ?? md5(json_encode($c))), 'level' => $level,
                'title' => "Magnitude {$m} earthquake {$kmTxt} km {$dir[0]} of the barangay",
                'title_fil' => "Lindol na magnitude {$m}, {$kmTxt} km sa {$dir[1]} ng barangay",
                'summary' => "A magnitude {$m} earthquake struck " . (string) ($p['place'] ?? 'nearby') . " at {$at}, about {$kmTxt} km from Barangay Bayogo.{$tsunamiNote}",
                'summary_fil' => "Isang lindol na magnitude {$m} ang tumama sa " . (string) ($p['place'] ?? 'malapit') . " noong {$at}, mga {$kmTxt} km mula sa Barangay Bayogo.{$tsunamiNoteFil}",
                'actions' => ['Expect aftershocks: Drop, Cover and Hold On.', 'Check your house for cracks and gas or electrical damage before going back inside.', 'Report injuries or damage to the barangay.'],
                'actions_fil' => ['Asahan ang mga aftershock: Dumapa, Magtago at Kumapit.', 'Suriin ang bahay kung may bitak at sira sa gas o kuryente bago pumasok muli.', 'Iulat sa barangay ang mga nasaktan o pinsala.'],
                'official' => 'PHIVOLCS', 'expires_at' => date('Y-m-d H:i:s', $time + 86400),
                'data' => ['mag' => $mag, 'km' => round($km), 'tsunami' => $tsunami, 'url' => $p['url'] ?? null],
            ];
        }

        return $out;
    }

    // ── Recording, posting, resolving ───────────────────────────────────

    /** @return 'new'|'updated'|'same' */
    private function record(array $h): string
    {
        $h['data'] = (array) ($h['data'] ?? []) + [
            'title_fil' => $h['title_fil'] ?? $h['title'],
            'official'  => $h['official'] ?? '',
            'kind'      => $h['kind'] ?? (['typhoon' => 'typhoon', 'rain' => 'rain'][$h['source']] ?? 'other'),
        ];
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT * FROM hazard_alerts WHERE source = ? AND external_id = ?');
        $stmt->execute([$h['source'], $h['external_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            try {
                $pdo->prepare('INSERT INTO hazard_alerts (source, external_id, level, title, summary, summary_fil, data, expires_at, detected_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')
                    ->execute([$h['source'], $h['external_id'], $h['level'], mb_substr($h['title'], 0, 255), $h['summary'], $h['summary_fil'],
                               json_encode($h['data']), $h['expires_at']]);
            } catch (\Throwable $e) {
                return 'same';   // another request recorded it first
            }
            $id = (int) $pdo->lastInsertId();
            if (self::RANK[$h['level']] >= self::RANK['warning']) {
                $this->post($id, $h, false);
            }
            return 'new';
        }

        $rose = (self::RANK[$h['level']] ?? 0) > (self::RANK[(string) $row['level']] ?? 0);
        $pdo->prepare('UPDATE hazard_alerts SET level = ?, title = ?, summary = ?, summary_fil = ?, data = ?, expires_at = ?, resolved_at = NULL, updated_at = NOW() WHERE id = ?')
            ->execute([$rose ? $h['level'] : $row['level'], mb_substr($h['title'], 0, 255), $h['summary'], $h['summary_fil'], json_encode($h['data']), $h['expires_at'], $row['id']]);
        if ($rose && self::RANK[$h['level']] >= self::RANK['warning']) {
            $this->post((int) $row['id'], $h, true);
            return 'updated';
        }

        return 'same';
    }

    /** Post the hazard as an urgent announcement and notify residents through the normal path. */
    private function post(int $alertId, array $h, bool $isUpdate): void
    {
        try {
            $pdo    = db();
            $author = (int) $pdo->query("SELECT id FROM users WHERE role IN ('superadmin', 'admin') ORDER BY CASE role WHEN 'superadmin' THEN 0 ELSE 1 END, id LIMIT 1")->fetchColumn();
            if ($author <= 0) {
                return;
            }
            $danger   = $h['level'] === 'danger';
            $prefixEn = ($isUpdate ? 'UPDATE — ' : '') . ($danger ? 'DANGER: ' : 'WARNING: ');
            $prefixFil = ($isUpdate ? 'UPDATE — ' : '') . ($danger ? 'MATINDING BABALA: ' : 'BABALA: ');
            $titleEn  = mb_substr($prefixEn . $h['title'], 0, 255);
            $titleFil = mb_substr($prefixFil . $h['title_fil'], 0, 255);
            $bodyEn   = isset($h['description']) ? self::officialBody($h, false) : self::body($h['summary'], $h['actions'], 'What to do', $h['official'], false);
            $bodyFil  = isset($h['description']) ? self::officialBody($h, true) : self::body($h['summary_fil'], $h['actions_fil'], 'Ano ang gagawin', $h['official'], true);
            $slug     = 'hazard-' . $h['source'] . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $h['external_id'])) . '-' . date('YmdHi');

            self::insertExisting($pdo, 'announcements', [
                'title' => $titleFil, 'title_fil' => $titleFil, 'title_en' => $titleEn,
                'slug' => mb_substr($slug, 0, 255),
                'body' => $bodyFil, 'body_fil' => $bodyFil, 'body_en' => $bodyEn,
                'source_lang' => 'fil', 'en_is_auto' => 0, 'fil_is_auto' => 0,
                'category' => 'safety', 'urgency' => $danger ? 'urgent' : 'important',
                'author_id' => $author, 'status' => 'published', 'published_at' => true,
                'notify_sms' => (string) Setting::get('hazard_auto_sms', '0') === '1' ? 1 : 0,
                'asks_safety_checkin' => $danger ? 1 : 0,
                'is_sample' => 0, 'created_at' => true, 'updated_at' => true,
            ]);
            $annId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE hazard_alerts SET announcement_id = ? WHERE id = ?')->execute([$annId, $alertId]);

            $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = ?');
            $stmt->execute([$annId]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($post)) {
                (new ScheduledPublisher())->dispatch($post, $author);
            }
            \App\Models\AuditLog::record(null, 'hazard.auto_posted', "Automatic {$h['level']} hazard post #{$annId}: {$h['title']}");
        } catch (\Throwable $e) {
            error_log('[HazardMonitor::post] ' . $e->getMessage());
        }
    }

    /** Mark alerts that are no longer detected (by a source that answered) or have expired as resolved. */
    private function resolveGone(array $foundKeys, array $failedGroups): int
    {
        $groupOf = ['typhoon' => 'typhoon', 'rain' => 'weather', 'wind' => 'weather', 'earthquake' => 'earthquake', 'phivolcs' => 'phivolcs', 'pagasa' => 'pagasa'];
        $failedGroups[] = 'pagasa';   // never "gone": a PAGASA alert lasts until it expires or is cancelled
        $count = 0;
        foreach (self::active() as $row) {
            $key     = $row['source'] . '|' . $row['external_id'];
            $expired = !empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time();
            $gone    = !in_array($key, $foundKeys, true) && !in_array($groupOf[$row['source']] ?? '', $failedGroups, true);
            if ($expired || $gone) {
                db()->prepare('UPDATE hazard_alerts SET resolved_at = NOW() WHERE id = ?')->execute([$row['id']]);
                $count++;
            }
        }

        return $count;
    }

    /** @return list<array<string,mixed>> unresolved, unexpired alerts, most severe first */
    public static function active(): array
    {
        try {
            $rows = db()->query('SELECT * FROM hazard_alerts WHERE resolved_at IS NULL ORDER BY detected_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
        $rows = array_values(array_filter($rows, static fn (array $r): bool => empty($r['expires_at']) || strtotime((string) $r['expires_at']) >= time()));
        usort($rows, static fn (array $a, array $b): int => (self::RANK[$b['level']] ?? 0) <=> (self::RANK[$a['level']] ?? 0));

        return $rows;
    }

    /**
     * The dashboard advisory follows the most severe active hazard. It
     * replaces a manual advisory only when it is more severe, and clears
     * itself (only if it set the advisory) when nothing is left.
     */
    public static function refreshAdvisory(): void
    {
        $active   = self::active();
        $isAuto   = (string) Setting::get('hazard_advisory_auto', '0') === '1';
        $current  = trim((string) Setting::get('hazard_advisory_text', ''));
        $curLevel = (string) Setting::get('hazard_advisory_level', 'warning');

        if ($active === []) {
            if ($isAuto && $current !== '') {
                Setting::setMany(['hazard_advisory_text' => '', 'hazard_advisory_fil' => '', 'hazard_advisory_auto' => '0']);
                Setting::flush();
            }
            return;
        }

        $top = $active[0];
        if (!$isAuto && $current !== '' && (self::RANK[$top['level']] ?? 0) <= (self::RANK[$curLevel] ?? 2)) {
            return;   // staff wrote an advisory at least as severe: keep theirs
        }
        $labelEn  = ['info' => 'Notice', 'warning' => 'Warning', 'danger' => 'DANGER'][$top['level']] ?? 'Warning';
        $labelFil = ['info' => 'Paalala', 'warning' => 'Babala', 'danger' => 'MATINDING BABALA'][$top['level']] ?? 'Babala';
        $more     = count($active) > 1 ? ' (+' . (count($active) - 1) . ')' : '';
        Setting::setMany([
            'hazard_advisory_text'  => mb_strimwidth($labelEn . ': ' . $top['summary'] . $more, 0, 300, '…'),
            'hazard_advisory_fil'   => mb_strimwidth($labelFil . ': ' . ($top['summary_fil'] ?: $top['summary']) . $more, 0, 300, '…'),
            'hazard_advisory_level' => $top['level'],
            'hazard_advisory_auto'  => '1',
        ]);
        Setting::flush();
    }

    // ── Official sources ────────────────────────────────────────────────

    /**
     * New PAGASA CAP alerts covering the barangay. Only CAP files not read
     * before are fetched (remembered in hazard_pagasa_seen), oldest first so
     * the latest update of a series is applied last.
     *
     * @return list<array<string,mixed>>
     */
    private function pagasa(float $lat, float $lon): array
    {
        $feed = OfficialHazards::parseFeed($this->text(OfficialHazards::PAGASA_FEED));
        $seen = json_decode((string) Setting::get('hazard_pagasa_seen', '[]'), true);
        $seen = is_array($seen) ? $seen : [];
        $todo = array_values(array_filter($feed, static fn (array $e): bool =>
            !in_array($e['id'], $seen, true)
            && OfficialHazards::feedEntryIsRelevant($e['title'])
            && strtotime($e['updated']) >= strtotime('-3 days')));
        usort($todo, static fn (array $a, array $b): int => strtotime($a['updated']) <=> strtotime($b['updated']));

        $out = [];
        foreach (array_slice($todo, 0, 20) as $entry) {
            $hazard = OfficialHazards::parseCap($this->text($entry['cap']), $lat, $lon);
            if ($hazard !== null && strtotime((string) $hazard['expires_at']) > time()) {
                $out[] = $hazard;
            }
            $seen[] = $entry['id'];
        }
        Setting::set('hazard_pagasa_seen', json_encode(array_slice($seen, -400)));

        return $out;
    }

    /**
     * PHIVOLCS earthquakes of the past day near the barangay; for strong
     * ones the bulletin is opened for its tsunami statement.
     *
     * @return list<array<string,mixed>>
     */
    private function phivolcs(float $lat, float $lon): array
    {
        $quakes = OfficialHazards::parsePhivolcs($this->text(OfficialHazards::PHIVOLCS));
        if ($quakes === []) {
            throw new \RuntimeException('no earthquakes could be read from the PHIVOLCS page');
        }
        $notes = [];
        foreach ($quakes as $q) {
            if ($q['bulletin'] !== '' && $q['mag'] >= 6.0 && $q['time'] >= time() - 86400
                && self::distanceKm($lat, $lon, $q['lat'], $q['lon']) <= 1000 && count($notes) < 3) {
                try {
                    $notes[$q['bulletin']] = OfficialHazards::tsunamiSentence($this->text($q['bulletin']));
                } catch (\Throwable $e) {
                    // the table alone is enough to alert
                }
            }
        }

        return OfficialHazards::quakeHazards($quakes, $lat, $lon, $notes);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * PHIVOLCS's server sends its certificate without the GlobalSign
     * intermediate that links it to a trusted root (browsers fetch it on
     * their own; PHP does not), so HTTPS to it fails with "unable to get
     * local issuer certificate". Verification stays ON: the system's trusted
     * roots plus that one public intermediate (config/certs, issued by
     * GlobalSign Root R3, valid to 2028) are used for PHIVOLCS requests only.
     */
    private static function phivolcsCaBundle(): ?string
    {
        $intermediate = __DIR__ . '/../../config/certs/globalsign-rsa-ov-ssl-ca-2018.pem';
        if (!is_file($intermediate)) {
            return null;
        }
        $locations = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
        $system = '';
        foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile'), getenv('SSL_CERT_FILE') ?: '', $locations['default_cert_file'] ?? '',
                  '/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt'] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                $system = (string) file_get_contents($candidate);
                break;
            }
        }
        if ($system === '') {
            return null;
        }
        $path = sys_get_temp_dir() . '/baranggabay-phivolcs-ca-' . md5($system . filemtime($intermediate)) . '.pem';
        if (!is_file($path)) {
            file_put_contents($path, $system . "\n" . file_get_contents($intermediate));
        }

        return $path;
    }

    private function text(string $url): string
    {
        $options = [];
        if (str_contains((string) parse_url($url, PHP_URL_HOST), 'phivolcs.dost.gov.ph')) {
            $bundle = self::phivolcsCaBundle();
            if ($bundle !== null) {
                $options['verify'] = $bundle;
            }
        }
        $res = $this->http->get($url, $options);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('HTTP ' . $res->getStatusCode() . ' from ' . parse_url($url, PHP_URL_HOST));
        }

        return (string) $res->getBody();
    }

    /**
     * INSERT only the columns this database has (PostgreSQL on Render lacks
     * some MySQL-only migration columns). true as a value means NOW().
     */
    private static function insertExisting(PDO $pdo, string $table, array $row): void
    {
        static $columns = [];
        if (!isset($columns[$table])) {
            $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
            $stmt = $pdo->prepare($isPgsql
                ? 'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?'
                : 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $columns[$table] = array_flip(array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        }
        $names = [];
        $marks = [];
        $args  = [];
        foreach ($row as $name => $value) {
            if (!isset($columns[$table][strtolower($name)])) {
                continue;
            }
            $names[] = $name;
            if ($value === true) {
                $marks[] = 'NOW()';
            } else {
                $marks[] = '?';
                $args[]  = $value;
            }
        }
        $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $marks) . ')')->execute($args);
    }

    private function json(string $url, array $query): array
    {
        $res = $this->http->get($url, ['query' => $query]);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('HTTP ' . $res->getStatusCode());
        }
        $data = json_decode((string) $res->getBody(), true);
        if (!is_array($data)) {
            throw new \RuntimeException('unreadable response');
        }

        return $data;
    }

    /** A PAGASA alert in its own words (description and instruction), with the source credited. */
    private static function officialBody(array $h, bool $fil): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $area = (string) ($h['data']['area'] ?? '');
        $html = '<p><strong>' . $e($fil ? "Opisyal na abiso ng PAGASA para sa {$area}" : "Official PAGASA alert for {$area}") . '</strong></p>';
        foreach (preg_split("/\n\s*\n/", (string) $h['description']) ?: [] as $para) {
            if (trim($para) !== '') {
                $html .= '<p>' . nl2br($e(trim($para))) . '</p>';
            }
        }
        if (trim((string) ($h['instruction'] ?? '')) !== '') {
            $html .= '<p><strong>' . $e($fil ? 'Tagubilin ng PAGASA' : 'PAGASA instruction') . ':</strong> ' . $e(trim((string) $h['instruction'])) . '</p>';
        }
        if (!empty($h['expires_at'])) {
            $html .= '<p>' . $e(($fil ? 'Bisa hanggang ' : 'Valid until ') . date('F j, Y g:i A', strtotime((string) $h['expires_at']))) . '</p>';
        }
        $html .= '<p><em>' . $e($fil
            ? 'Pinagmulan: PAGASA-DOST public alerts (CC BY 4.0), awtomatikong ipinost ng BarangGabay. Sundan ang tagubilin ng barangay.'
            : 'Source: PAGASA-DOST public alerts (CC BY 4.0), posted automatically by BarangGabay. Follow barangay instructions.') . '</em></p>';

        return $html;
    }

    private static function body(string $summary, array $actions, string $heading, string $official, bool $fil): string
    {
        $html = '<p>' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</p><p><strong>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . ':</strong></p><ul>';
        foreach ($actions as $a) {
            $html .= '<li>' . htmlspecialchars($a, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $fromPhivolcs = $official === 'PHIVOLCS' && str_starts_with($summary, $fil ? 'Iniulat ng PHIVOLCS' : 'PHIVOLCS');
        $html .= '</ul><p><em>' . htmlspecialchars($fromPhivolcs
            ? ($fil ? 'Pinagmulan: PHIVOLCS-DOST, awtomatikong ipinost ng BarangGabay. Sundan ang mga bulletin ng PHIVOLCS at ang tagubilin ng barangay.'
                    : 'Source: PHIVOLCS-DOST, posted automatically by BarangGabay. Follow PHIVOLCS bulletins and barangay instructions.')
            : ($fil ? "Awtomatikong babala mula sa BarangGabay batay sa pandaigdigang datos (GDACS, Open-Meteo, USGS). Sundan ang opisyal na abiso ng {$official} at ng barangay."
                    : "Automatic alert from BarangGabay based on international data (GDACS, Open-Meteo, USGS). Follow the official {$official} bulletins and barangay instructions."), ENT_QUOTES, 'UTF-8') . '</em></p>';

        return $html;
    }

    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /** @return array{0:string,1:string} direction from the barangay to the point, English and Filipino */
    private static function compass(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $y = sin(deg2rad($lon2 - $lon1)) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2 - $lon1));
        $deg = fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
        $i   = (int) round($deg / 45) % 8;
        $en  = ['north', 'northeast', 'east', 'southeast', 'south', 'southwest', 'west', 'northwest'];
        $fil = ['hilaga', 'hilagang-silangan', 'silangan', 'timog-silangan', 'timog', 'timog-kanluran', 'kanluran', 'hilagang-kanluran'];

        return [$en[$i], $fil[$i]];
    }

    /** @return array{0:string,1:string} PAGASA category in English and Filipino, from max winds (km/h) */
    private static function cycloneKind(float $wind): array
    {
        return match (true) {
            $wind >= 185 => ['Super Typhoon', 'Super Bagyo'],
            $wind >= 118 => ['Typhoon', 'Bagyong'],
            $wind >= 89  => ['Severe Tropical Storm', 'Malakas na Tropical Storm'],
            $wind >= 62  => ['Tropical Storm', 'Tropical Storm'],
            default      => ['Tropical Depression', 'Tropical Depression'],
        };
    }

    private static function filDate(string $day): string
    {
        $days   = ['Linggo', 'Lunes', 'Martes', 'Miyerkules', 'Huwebes', 'Biyernes', 'Sabado'];
        $months = ['', 'Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
        $t = strtotime($day);

        return $days[(int) date('w', $t)] . ', ' . $months[(int) date('n', $t)] . ' ' . date('j', $t);
    }
}
