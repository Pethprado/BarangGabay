<?php
$cookieFile = __DIR__ . '/render_cookie.txt';

function printRows($title, $url, $cookieFile) {
    echo "\n=== $title ===\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($ch);
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows);
    echo "Total rows: " . count($rows[0]) . "\n";
    foreach (array_slice($rows[0], 0, 6) as $i => $r) {
        echo "  Row $i: " . trim(preg_replace('/\s+/', ' ', strip_tags($r))) . "\n";
    }
}

printRows("EVENTS", "https://baranggabay.onrender.com/admin/events", $cookieFile);
printRows("ORDINANCES", "https://baranggabay.onrender.com/admin/ordinances", $cookieFile);
printRows("RESIDENTS", "https://baranggabay.onrender.com/admin/residents", $cookieFile);
