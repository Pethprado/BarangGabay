<?php
$cookieFile = __DIR__ . '/resident_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Visit /login
$ch = curl_init('https://baranggabay.onrender.com/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

// 2. Submit Resident Login
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'email' => 'michelle@baranggabay.ph',
    'password' => 'Resident@1234'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$info = curl_getinfo($ch);

echo "Resident Login HTTP Code: " . $info['http_code'] . "\n";
echo "Effective URL: " . $info['url'] . "\n";

// 3. Check notifications page
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_URL, 'https://baranggabay.onrender.com/notifications');
$notifHtml = curl_exec($ch);
echo "Notifications page title: ";
if (preg_match('/<title>(.*?)<\/title>/', $notifHtml, $t)) echo $t[1] . "\n";

// Check unread count or notification titles
preg_match_all('/<h[3-6][^>]*>(.*?)<\/h[3-6]>/is', $notifHtml, $notifHeadings);
foreach (array_slice($notifHeadings[1], 0, 4) as $h) {
    echo "  Notice: " . trim(strip_tags($h)) . "\n";
}

// 4. Check documents page
curl_setopt($ch, CURLOPT_URL, 'https://baranggabay.onrender.com/documents');
$docHtml = curl_exec($ch);
echo "\nDocuments page title: ";
if (preg_match('/<title>(.*?)<\/title>/', $docHtml, $t)) echo $t[1] . "\n";
preg_match_all('/BRGY-2026-\d+/', $docHtml, $docs);
echo "  Found document references: " . implode(', ', array_unique($docs[0])) . "\n";

// 5. Check evacuation page
curl_setopt($ch, CURLOPT_URL, 'https://baranggabay.onrender.com/evacuation');
$evacHtml = curl_exec($ch);
echo "\nEvacuation centers found: ";
preg_match_all('/Bayogo[^<]+/i', $evacHtml, $evacs);
echo implode(', ', array_slice(array_unique($evacs[0]), 0, 3)) . "\n";
