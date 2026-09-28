<?php
$cookieFile = __DIR__ . '/resident_cookie.txt';
$ch = curl_init('https://baranggabay.onrender.com/announcements/babala-storm-surge-at-malakas-na-ulan');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

if (preg_match('/data-cached="([^"]*)"/', $html, $m)) {
    echo "DATA-CACHED LENGTH: " . strlen($m[1]) . "\n";
    echo "DATA-CACHED CONTENT:\n" . substr(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), 0, 300) . "...\n";
} else {
    echo "No data-cached found.\n";
}
