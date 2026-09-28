<?php
$cookieFile = __DIR__ . '/render_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

$loginUrl = 'https://baranggabay.onrender.com/login?as=staff';
$ch = curl_init($loginUrl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$html = curl_exec($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';
echo "Got CSRF: " . substr($csrf, 0, 16) . "...\n";

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'email' => 'admin@baranggabay.ph',
    'password' => 'Admin@1234'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$info = curl_getinfo($ch);

echo "Status Code: " . $info['http_code'] . "\n";
echo "Final URL: " . $info['url'] . "\n";
if (preg_match('/class="[^"]*alert[^"]*"[^>]*>(.*?)<\/div>/is', $res, $alert)) {
    echo "Found alert: " . trim(strip_tags($alert[1])) . "\n";
} else {
    echo "No alert found. Looking for 'Mali' or 'Error' or 'Hindi':\n";
    if (preg_match_all('/<div class="[^"]*invalid-feedback[^"]*">(.*?)<\/div>/is', $res, $invalids)) {
        foreach ($invalids[1] as $inv) echo "  invalid: " . trim(strip_tags($inv)) . "\n";
    }
}


