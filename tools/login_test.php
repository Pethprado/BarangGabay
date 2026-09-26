<?php
// tools/login_test.php
// Performs login POST and then fetches protected page to verify session
$base = 'http://localhost:8000';
$jar = tempnam(sys_get_temp_dir(), 'cj');

function get($url, $jar) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

function post($url, $jar, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

// Step 1: GET login to obtain CSRF
list($res, $info) = get($base . '/login', $jar);
echo "GET HTTP_CODE: " . $info['http_code'] . "\n";
echo "GET HEADERS SNIPPET:\n" . substr($res, 0, 400) . "\n";
if (!preg_match('/name="csrf_token" value="([^"]+)"/', $res, $m)) {
    echo "Failed to extract CSRF token\n";
    exit(1);
}
$token = $m[1];

// Step 2: POST credentials
list($res2, $info2) = post($base . '/login', $jar, [
    'email' => 'admin@baranggabay.ph',
    'password' => 'Admin@1234',
    'csrf_token' => $token,
]);

echo "POST HTTP_CODE: " . $info2['http_code'] . "\n";
echo "POST BODY SNIPPET:\n" . substr($res2, 0, 800) . "\n";

// Step 3: GET protected page
list($res3, $info3) = get($base . '/announcements', $jar);
echo "Protected page HTTP_CODE: " . $info3['http_code'] . "\n";

if (strpos($res3, 'Announcements') !== false) {
    echo "Session active: found Announcements in protected page.\n";
} else {
    echo "Session may not be active: Announcements not found.\n";
}

@unlink($jar);
