<?php
$cookieFile = __DIR__ . '/resident_cookie.txt';

$url = 'https://baranggabay.onrender.com/announcements/babala-storm-surge-at-malakas-na-ulan?lang=mn';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

echo "URL: $url\n";
echo "Length: " . strlen($html) . "\n";
if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1)) {
    echo "H1 Title: " . trim(strip_tags($h1[1])) . "\n";
}
if (preg_match('/class="[^"]*announcement-body[^"]*"[^>]*>(.*?)<\/div>/is', $html, $body)) {
    echo "Body snippet: " . substr(trim(strip_tags($body[1])), 0, 200) . "...\n";
}
