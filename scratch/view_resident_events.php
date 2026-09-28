<?php
$cookieFile = __DIR__ . '/resident_cookie.txt';
$ch = curl_init('https://baranggabay.onrender.com/events');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

if (preg_match('/<title>(.*?)<\/title>/', $html, $t)) echo "Title: " . $t[1] . "\n";
if (preg_match('/Error ID:\s*([A-F0-9]+)/i', $html, $eid)) {
    echo "Error ID: " . $eid[1] . "\n";
}
echo "HTML body preview:\n" . substr(strip_tags($html), 0, 800) . "\n";


