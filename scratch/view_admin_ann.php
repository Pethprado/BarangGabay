<?php
$cookieFile = __DIR__ . '/render_cookie.txt';
$ch = curl_init('https://baranggabay.onrender.com/admin/announcements');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

echo "Length: " . strlen($html) . "\n";
if (preg_match('/<title>(.*?)<\/title>/', $html, $t)) echo "Title: " . $t[1] . "\n";

// Look for table or cards
preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows);
echo "TR count: " . count($rows[0]) . "\n";
foreach (array_slice($rows[0], 0, 5) as $i => $r) {
    echo "Row $i: " . trim(preg_replace('/\s+/', ' ', strip_tags($r))) . "\n";
}
