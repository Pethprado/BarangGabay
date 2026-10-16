<?php
declare(strict_types=1);

namespace App\Services;

/**
 * The Philippine government's own hazard sources, read for HazardMonitor.
 *
 *  - PAGASA public alerts (https://publicalert.pagasa.dost.gov.ph/feeds/):
 *    an Atom feed of CAP 1.2 alerts (Common Alerting Protocol, licensed
 *    CC BY 4.0): tropical cyclone, rainfall, flood and thunderstorm
 *    warnings, each with the provinces and the map polygons it covers.
 *    An alert counts for the barangay when one of its polygons contains the
 *    barangay's coordinates or its area names Surigao del Sur / Madrid.
 *
 *  - PHIVOLCS latest earthquakes (https://earthquake.phivolcs.dost.gov.ph/):
 *    the official table of Philippine earthquakes (time, position, depth,
 *    magnitude, location, bulletin link).
 *
 * Everything here is parsing; HazardMonitor does the fetching, recording
 * and posting. Parsers are pure functions so they are tested offline.
 */
final class OfficialHazards
{
    public const PAGASA_FEED = 'https://publicalert.pagasa.dost.gov.ph/feeds/';
    public const PHIVOLCS = 'https://earthquake.phivolcs.dost.gov.ph/';

    /** Area names that mean "us" when a CAP alert has no polygon. */
    public const AREA_NAMES = ['surigao del sur', 'madrid', 'bayogo'];

    /** Feed titles worth opening: our region, or national / non-regional alerts (typhoon bulletins). */
    public static function feedEntryIsRelevant(string $title): bool
    {
        $t = strtolower($title);
        if (str_contains($t, 'caraga') || str_contains($t, 'region 13') || str_contains($t, 'surigao')) {
            return true;
        }

        return !str_contains($t, 'region') && !str_contains($t, 'barmm') && !str_contains($t, 'ncr') && !str_contains($t, 'car (');
    }

    /**
     * The CAP links in the PAGASA Atom feed, newest first.
     *
     * @return list<array{id:string, title:string, updated:string, cap:string}>
     */
    public static function parseFeed(string $atom): array
    {
        $xml = @simplexml_load_string($atom);
        if ($xml === false) {
            throw new \RuntimeException('PAGASA feed is not valid XML');
        }
        $out = [];
        foreach ($xml->entry as $entry) {
            $cap = '';
            foreach ($entry->link as $link) {
                if ((string) $link['type'] === 'application/cap+xml' || str_ends_with((string) $link['href'], '.cap')) {
                    $cap = (string) $link['href'];
                }
            }
            if ($cap === '') {
                continue;
            }
            $out[] = ['id' => (string) $entry->id, 'title' => trim((string) $entry->title), 'updated' => (string) $entry->updated, 'cap' => $cap];
        }

        return $out;
    }

    /**
     * One CAP alert → a hazard for the barangay, or null when it does not
     * cover the barangay (or is a test / cancelled message).
     *
     * @return array<string,mixed>|null
     */
    public static function parseCap(string $xml, float $lat, float $lon): ?array
    {
        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            return null;
        }
        $doc->registerXPathNamespace('c', 'urn:oasis:names:tc:emergency:cap:1.2');
        $status  = (string) ($doc->xpath('/c:alert/c:status')[0] ?? '');
        $msgType = (string) ($doc->xpath('/c:alert/c:msgType')[0] ?? '');
        $sent    = (string) ($doc->xpath('/c:alert/c:sent')[0] ?? '');
        if ($status !== '' && $status !== 'Actual') {
            return null;
        }

        foreach ($doc->xpath('/c:alert/c:info') ?: [] as $info) {
            $info->registerXPathNamespace('c', 'urn:oasis:names:tc:emergency:cap:1.2');
            $areas   = [];
            $covered = false;
            foreach ($info->xpath('c:area') ?: [] as $area) {
                $area->registerXPathNamespace('c', 'urn:oasis:names:tc:emergency:cap:1.2');
                $name = trim((string) ($area->xpath('c:areaDesc')[0] ?? ''));
                $hit  = false;
                foreach ($area->xpath('c:polygon') ?: [] as $poly) {
                    if (self::pointInPolygon($lat, $lon, self::polygon((string) $poly))) {
                        $hit = true;
                    }
                }
                foreach (self::AREA_NAMES as $needle) {
                    if ($name !== '' && str_contains(strtolower($name), $needle)) {
                        $hit = true;
                    }
                }
                if ($hit) {
                    $covered = true;
                    $areas[] = $name;
                }
            }
            if (!$covered) {
                continue;
            }

            $event    = trim((string) ($info->xpath('c:event')[0] ?? 'PAGASA alert'));
            $headline = trim((string) ($info->xpath('c:headline')[0] ?? $event));
            $severity = trim((string) ($info->xpath('c:severity')[0] ?? 'Unknown'));
            $urgency  = trim((string) ($info->xpath('c:urgency')[0] ?? 'Unknown'));
            $expires  = trim((string) ($info->xpath('c:expires')[0] ?? ''));
            $desc     = self::cleanText((string) ($info->xpath('c:description')[0] ?? ''));
            $instr    = self::cleanText((string) ($info->xpath('c:instruction')[0] ?? ''));

            $level = match (true) {
                $severity === 'Extreme', $severity === 'Severe' && $urgency === 'Immediate' => 'danger',
                $severity === 'Severe' => 'warning',
                default => 'info',
            };
            $kind = self::kind($event . ' ' . $headline);
            $area = implode(', ', array_unique($areas));

            // What residents need from the description: the forecast and the
            // line about their own province (rivers, towns), not the national
            // weather systems PAGASA opens with.
            $local = [];
            foreach (preg_split('/\n/', $desc) ?: [] as $line) {
                $l = trim($line, " \t+-");
                if ($l === '') {
                    continue;
                }
                $lower = strtolower($l);
                if (str_contains($lower, 'forecast') || array_filter(self::AREA_NAMES, static fn (string $n): bool => str_contains($lower, $n)) !== []) {
                    $local[] = $l;
                }
            }
            $gist = $local !== [] ? implode(' ', array_slice($local, 0, 3)) : self::firstSentences($desc, 2);
            $day  = $sent !== '' ? date('Ymd', strtotime($sent)) : date('Ymd');

            return [
                'source' => 'pagasa',
                // One series per event and day: updates (#2, #3…) refresh it.
                'external_id' => 'pagasa-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($event)), '-') . '-' . $day,
                'level' => $level,
                'kind' => $kind,
                'cancel' => $msgType === 'Cancel',
                'title' => "PAGASA: {$headline}" . ($area !== '' ? " ({$area})" : ''),
                'title_fil' => "PAGASA: {$headline}" . ($area !== '' ? " ({$area})" : ''),
                'summary' => mb_strimwidth("PAGASA {$event} for {$area}. " . $gist, 0, 600, '…'),
                'summary_fil' => mb_strimwidth("Abiso ng PAGASA ({$event}) para sa {$area}. " . $gist, 0, 600, '…'),
                'description' => $desc,
                'instruction' => $instr,
                'actions' => $instr !== '' ? [$instr] : [],
                'actions_fil' => $instr !== '' ? [$instr] : [],
                'official' => 'PAGASA',
                'expires_at' => $expires !== '' ? date('Y-m-d H:i:s', strtotime($expires)) : date('Y-m-d H:i:s', strtotime('+12 hours')),
                'data' => ['event' => $event, 'severity' => $severity, 'urgency' => $urgency, 'area' => $area,
                           'sent' => $sent, 'official' => 'PAGASA', 'credit' => 'PAGASA-DOST (CC BY 4.0)'],
            ];
        }

        return null;
    }

    /**
     * Rows of the PHIVOLCS "latest earthquakes" table.
     *
     * @return list<array{time:int, lat:float, lon:float, depth:float, mag:float, place:string, bulletin:string}>
     */
    public static function parsePhivolcs(string $html): array
    {
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = (string) mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
        }
        $out = [];
        $seen = [];
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $html, $rows);
        foreach ($rows[1] as $row) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells);
            if (count($cells[1]) < 6) {
                continue;
            }
            $text = array_map(static fn (string $c): string => trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), $cells[1]);
            $time = strtotime(str_replace(' - ', ' ', $text[0]) . ' Asia/Manila');
            if ($time === false || !is_numeric($text[1]) || !is_numeric($text[2]) || !is_numeric($text[4])) {
                continue;
            }
            preg_match('/href="([^"]+)"/i', $cells[1][0], $href);
            $bulletin = isset($href[1]) ? self::PHIVOLCS . str_replace('\\', '/', ltrim(html_entity_decode($href[1]), '/')) : '';
            $key = $text[0] . '|' . $text[1] . '|' . $text[2];
            if (isset($seen[$key])) {
                continue;   // the table repeats some rows
            }
            $seen[$key] = true;
            $out[] = ['time' => $time, 'lat' => (float) $text[1], 'lon' => (float) $text[2], 'depth' => (float) $text[3],
                      'mag' => (float) $text[4], 'place' => $text[5], 'bulletin' => $bulletin];
        }

        return $out;
    }

    /**
     * PHIVOLCS earthquakes that matter here (past 24 hours).
     *
     * @param list<array<string,mixed>> $quakes from parsePhivolcs()
     * @param array<string,string> $tsunamiNotes bulletin URL => tsunami sentence, for strong quakes
     * @return list<array<string,mixed>>
     */
    public static function quakeHazards(array $quakes, float $lat, float $lon, array $tsunamiNotes = []): array
    {
        $out = [];
        foreach ($quakes as $q) {
            if ($q['time'] < time() - 86400) {
                continue;
            }
            $km   = HazardMonitor::distanceKm($lat, $lon, $q['lat'], $q['lon']);
            $note = $tsunamiNotes[$q['bulletin']] ?? '';
            $tsunamiThreat = $note !== '' && preg_match('/(warning|evacuat|threat (?:is|to)|advisory)/i', $note) === 1
                && preg_match('/no (?:destructive )?tsunami threat|no tsunami/i', $note) !== 1;

            $level = match (true) {
                $tsunamiThreat && $km <= 1000                    => 'danger',
                $km <= 300 && $q['mag'] >= 6.5                   => 'danger',
                $km <= 300 && $q['mag'] >= 5.5                   => 'warning',
                $km <= 100 && $q['mag'] >= 4.5                   => 'info',
                default                                          => null,
            };
            if ($level === null) {
                continue;
            }
            $m  = number_format($q['mag'], 1);
            $at = date('M j, g:i A', $q['time']);
            $k  = number_format(round($km));
            $out[] = [
                'source' => 'phivolcs', 'external_id' => 'phivolcs-' . date('YmdHi', $q['time']) . '-' . $q['lat'] . '-' . $q['lon'],
                'level' => $level, 'kind' => 'earthquake', 'cancel' => false,
                'title' => "PHIVOLCS: Magnitude {$m} earthquake, {$q['place']}",
                'title_fil' => "PHIVOLCS: Lindol na magnitude {$m}, {$q['place']}",
                'summary' => "PHIVOLCS reports a magnitude {$m} earthquake {$q['place']} at {$at} (depth {$q['depth']} km), about {$k} km from Barangay Bayogo." . ($note !== '' ? ' ' . $note : ''),
                'summary_fil' => "Iniulat ng PHIVOLCS ang lindol na magnitude {$m} sa {$q['place']} noong {$at} (lalim {$q['depth']} km), mga {$k} km mula sa Barangay Bayogo." . ($note !== '' ? ' ' . $note : ''),
                'actions' => $tsunamiThreat
                    ? ['If you are near the shore, move to higher ground or inland now.', 'Do not go to the beach to watch the sea.', 'Follow PHIVOLCS tsunami bulletins and barangay instructions.']
                    : ['Expect aftershocks: Drop, Cover and Hold On.', 'Check your house for cracks and gas or electrical damage before going back inside.', 'Report injuries or damage to the barangay.'],
                'actions_fil' => $tsunamiThreat
                    ? ['Kung malapit kayo sa dalampasigan, lumikas na sa mataas na lugar o papasok sa looban.', 'Huwag pumunta sa tabing-dagat para manood.', 'Sundan ang mga tsunami bulletin ng PHIVOLCS at ang tagubilin ng barangay.']
                    : ['Asahan ang mga aftershock: Dumapa, Magtago at Kumapit.', 'Suriin ang bahay kung may bitak at sira sa gas o kuryente bago pumasok muli.', 'Iulat sa barangay ang mga nasaktan o pinsala.'],
                'official' => 'PHIVOLCS',
                'expires_at' => date('Y-m-d H:i:s', $q['time'] + 86400),
                'data' => ['mag' => $q['mag'], 'km' => round($km), 'tsunami' => $tsunamiThreat, 'url' => $q['bulletin'], 'official' => 'PHIVOLCS'],
            ];
        }

        return $out;
    }

    /** The sentence about tsunami in a PHIVOLCS bulletin page, or ''. */
    public static function tsunamiSentence(string $bulletinHtml): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($bulletinHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (preg_match('/[^.]*tsunami[^.]*\./i', $text, $m) === 1) {
            return mb_strimwidth(trim($m[0]), 0, 300, '…');
        }

        return '';
    }

    /** typhoon | rain | other — used to avoid posting the same hazard from the international backup. */
    public static function kind(string $text): string
    {
        $t = strtolower($text);
        return match (true) {
            (bool) preg_match('/tropical cyclone|typhoon|tropical storm|tropical depression|wind signal|storm surge/', $t) => 'typhoon',
            (bool) preg_match('/rain|flood|thunderstorm/', $t) => 'rain',
            default => 'other',
        };
    }

    /** @return list<array{0:float,1:float}> "lat,lon lat,lon …" → points */
    private static function polygon(string $text): array
    {
        $points = [];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $pair) {
            $xy = explode(',', $pair);
            if (count($xy) === 2 && is_numeric($xy[0]) && is_numeric($xy[1])) {
                $points[] = [(float) $xy[0], (float) $xy[1]];
            }
        }

        return $points;
    }

    /** Ray casting; points are [lat, lon]. */
    public static function pointInPolygon(float $lat, float $lon, array $poly): bool
    {
        $n = count($poly);
        if ($n < 3) {
            return false;
        }
        $inside = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$yi, $xi] = $poly[$i];
            [$yj, $xj] = $poly[$j];
            if ((($yi > $lat) !== ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private static function cleanText(string $text): string
    {
        $text = str_replace(["\r", '**'], ['', ''], $text);
        $text = preg_replace('/^[ \t]*\.[ \t]*$/m', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private static function firstSentences(string $text, int $count): string
    {
        $flat = trim((string) preg_replace('/\s+/', ' ', $text));
        preg_match_all('/[^.!?]+[.!?]+/', $flat, $m);

        return trim(implode(' ', array_slice($m[0], 0, $count))) ?: mb_strimwidth($flat, 0, 300, '…');
    }
}
