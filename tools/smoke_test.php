<?php
/**
 * Simple smoke test: login and request admin + AI endpoints.
 */
$base = 'http://localhost:8000';
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bg_smoke_cookies.txt';
@unlink($cookieFile);
function curl_get($url, $cookieFile) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
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

echo "Starting smoke tests...\n";
list($html, $info) = curl_get($base . '/login', $cookieFile);
if ($info['http_code'] !== 200) {
    echo "GET /login returned HTTP " . $info['http_code'] . "\n";
    exit(1);
}
// extract csrf token
$csrf = null;
if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $html, $m)) {
    $csrf = $m[1];
}
if (!$csrf) {
    // try inline JS token
    if (preg_match('/csrfToken:\s*' . "'" . '([a-f0-9]+)' . "'" . '/i', $html, $m2)) {
        $csrf = $m2[1];
    }
}
if (!$csrf) {
    echo "Could not find CSRF token on /login page\n";
    exit(1);
}
echo "CSRF token found: $csrf\n";
// perform login
$loginData = [
    'email' => 'admin@baranggabay.ph',
    'password' => 'Admin@1234',
    'csrf_token' => $csrf,
];
list($loginRes, $loginInfo) = curl_post($base . '/login', $loginData, $cookieFile);
echo "POST /login -> HTTP " . $loginInfo['http_code'] . "\n";
if ($loginInfo['http_code'] >= 400) {
    echo "Login failed.\n";
    exit(1);
}
// request admin dashboard
list($adminHtml, $adminInfo) = curl_get($base . '/admin', $cookieFile);
echo "GET /admin -> HTTP " . $adminInfo['http_code'] . "\n";
if ($adminInfo['http_code'] === 200) {
    echo "Admin dashboard accessible.\n";
} else {
    echo "Admin dashboard not accessible (maybe middleware).\n";
}
// request ordinance upload page
list($upHtml, $upInfo) = curl_get($base . '/admin/ordinances/upload', $cookieFile);
echo "GET /admin/ordinances/upload -> HTTP " . $upInfo['http_code'] . "\n";
// AI endpoints (summarize)
$aiData = ['ordinance_id' => 1, 'csrf_token' => $csrf];
list($aiRes, $aiInfo) = curl_post($base . '/api/ai/summarize', $aiData, $cookieFile, ['Accept: application/json']);
echo "POST /api/ai/summarize -> HTTP " . $aiInfo['http_code'] . "\n";
if ($aiInfo['http_code'] === 200) {
    echo "AI summarize response: \n" . substr($aiRes,0,800) . "\n";
} else {
    echo "AI summarize failed or returned non-200.\n";
}
// AI chat
$chatData = ['question' => 'What are the important announcements?', 'csrf_token' => $csrf];
list($chatRes, $chatInfo) = curl_post($base . '/api/ai/chat', $chatData, $cookieFile, ['Accept: application/json']);
echo "POST /api/ai/chat -> HTTP " . $chatInfo['http_code'] . "\n";
if ($chatInfo['http_code'] === 200) {
    echo "AI chat response: \n" . substr($chatRes,0,800) . "\n";
} else {
    echo "AI chat failed or returned non-200.\n";
}

echo "Smoke tests completed.\n";
?>