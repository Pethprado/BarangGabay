<?php
$cookieFile = __DIR__ . '/render_cookie.txt';
function getUrl($url, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    return curl_exec($ch);
}

echo "--- Announcements on Render ---\n";
$annHtml = getUrl('https://baranggabay.onrender.com/admin/announcements', $cookieFile);
preg_match_all('/<td class="[^"]*fw-semibold[^"]*">(.*?)<\/td>/is', $annHtml, $annTitles);
foreach ($annTitles[1] as $t) {
    echo " • " . trim(strip_tags($t)) . "\n";
}

echo "\n--- Events on Render ---\n";
$evHtml = getUrl('https://baranggabay.onrender.com/admin/events', $cookieFile);
preg_match_all('/<td class="[^"]*fw-semibold[^"]*">(.*?)<\/td>/is', $evHtml, $evTitles);
foreach ($evTitles[1] as $t) {
    echo " • " . trim(strip_tags($t)) . "\n";
}

echo "\n--- Ordinances on Render ---\n";
$ordHtml = getUrl('https://baranggabay.onrender.com/admin/ordinances', $cookieFile);
preg_match_all('/<td class="[^"]*fw-semibold[^"]*">(.*?)<\/td>/is', $ordHtml, $ordTitles);
foreach ($ordTitles[1] as $t) {
    echo " • " . trim(strip_tags($t)) . "\n";
}

echo "\n--- Residents on Render ---\n";
$resHtml = getUrl('https://baranggabay.onrender.com/admin/residents', $cookieFile);
preg_match_all('/<td class="[^"]*fw-semibold[^"]*">(.*?)<\/td>/is', $resHtml, $resNames);
foreach ($resNames[1] as $n) {
    echo " • " . trim(strip_tags($n)) . "\n";
}
