<?php
$base = 'http://localhost:8000';
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bg_smoke_cookies_staff.txt';
@unlink($cookieFile);
function curl_get($url, $cookieFile) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}
function curl_post($url, $data, $cookieFile, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

echo "Starting staff smoke tests...\n";
list($html, $info) = curl_get($base . '/login', $cookieFile);
if ($info['http_code'] !== 200) {
    echo "GET /login returned HTTP " . $info['http_code'] . "\n";
    exit(1);
}
$csrf = null;
if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $html, $m)) $csrf = $m[1];
if (!$csrf && preg_match('/csrfToken:\s*' . "'" . '([a-f0-9]+)' . "'" . '/i', $html, $m2)) $csrf = $m2[1];
if (!$csrf) {
    echo "Could not find CSRF token on /login page\n";
    exit(1);
}
echo "CSRF token found: $csrf\n";
$loginData = [
    'email' => 'rosa.santos@baranggabay.ph',
    'password' => 'Staff@1234',
    'csrf_token' => $csrf,
];
list($loginRes, $loginInfo) = curl_post($base . '/login', $loginData, $cookieFile);
echo "POST /login -> HTTP " . $loginInfo['http_code'] . "\n";
list($adminHtml, $adminInfo) = curl_get($base . '/admin', $cookieFile);
echo "GET /admin -> HTTP " . $adminInfo['http_code'] . "\n";
if ($adminInfo['http_code'] === 200) echo "Admin dashboard accessible for staff.\n";
else echo "Admin dashboard not accessible for staff.\n";
list($upHtml, $upInfo) = curl_get($base . '/admin/ordinances/upload', $cookieFile);
echo "GET /admin/ordinances/upload -> HTTP " . $upInfo['http_code'] . "\n";
// AI summarize
$aiData = ['ordinance_id' => 1, 'csrf_token' => $csrf];
list($aiRes, $aiInfo) = curl_post($base . '/api/ai/summarize', $aiData, $cookieFile, ['Accept: application/json']);
echo "POST /api/ai/summarize -> HTTP " . $aiInfo['http_code'] . "\n";
if ($aiInfo['http_code'] === 200) echo "AI summarize OK.\n";
else echo "AI summarize failed.\n";
// AI chat
$chatData = ['question' => 'Anong mga anunsyo ngayon?', 'csrf_token' => $csrf];
list($chatRes, $chatInfo) = curl_post($base . '/api/ai/chat', $chatData, $cookieFile, ['Accept: application/json']);
echo "POST /api/ai/chat -> HTTP " . $chatInfo['http_code'] . "\n";
if ($chatInfo['http_code'] === 200) echo "AI chat OK.\n";
else echo "AI chat failed.\n";

echo "Staff smoke tests completed.\n";
?>