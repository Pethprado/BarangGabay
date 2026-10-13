<?php
$baseUrl = 'https://baranggabay.onrender.com';
$adminCookie = __DIR__ . '/admin_live_cookie.txt';
$residentCookie = __DIR__ . '/resident_live_cookie.txt';

function req($url, $post = null, $cookieFile = null, $follow = true) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
    }
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $response, 'info' => $info];
}

// 1. Fetch admin residents page
$resPage = req($baseUrl . '/admin/residents?status=verified', null, $adminCookie);

// Parse email and ID of first verified resident
preg_match_all('/href="[^"]*\/admin\/residents\/(\d+)"[^>]*>.*?<\/a>/s', $resPage['body'], $m);
preg_match_all('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $resPage['body'], $emails);

echo "Found resident IDs: " . implode(', ', array_slice($m[1] ?? [], 0, 5)) . "\n";
echo "Found emails: " . implode(', ', array_slice($emails[1] ?? [], 0, 5)) . "\n";

$targetId = $m[1][0] ?? null;
$targetEmail = null;

foreach ($emails[1] as $em) {
    if (!str_contains($em, 'admin') && !str_contains($em, 'staff')) {
        $targetEmail = $em;
        break;
    }
}

if ($targetId && $targetEmail) {
    echo "Resetting password for resident ID $targetId ($targetEmail)...\n";
    // Get CSRF from admin page
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resPage['body'], $csrfM);
    $csrf = $csrfM[1] ?? '';

    $newPass = 'Resident123!';
    $resetRes = req($baseUrl . "/admin/residents/{$targetId}/reset-password", [
        'csrf_token' => $csrf,
        'new_password' => $newPass,
        'new_password_confirmation' => $newPass
    ], $adminCookie);

    echo "Reset HTTP Status: " . $resetRes['info']['http_code'] . "\n";

    // 2. Now login as this verified resident!
    if (file_exists($residentCookie)) unlink($residentCookie);
    $resLoginPage = req($baseUrl . '/login', null, $residentCookie);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLoginPage['body'], $resCsrfM);
    $resCsrf = $resCsrfM[1] ?? '';

    echo "Logging in as resident $targetEmail ...\n";
    $loginRes = req($baseUrl . '/login', [
        'csrf_token' => $resCsrf,
        'email' => $targetEmail,
        'password' => $newPass
    ], $residentCookie);

    echo "Resident Login HTTP: " . $loginRes['info']['http_code'] . "\n";
    echo "Resident Login Effective URL: " . $loginRes['info']['url'] . "\n";

    // 3. GET /feedback as resident!
    echo "\n================ GET /feedback AS VERIFIED RESIDENT ================\n";
    $fbRes = req($baseUrl . '/feedback', null, $residentCookie);
    echo "Feedback HTTP Code: " . $fbRes['info']['http_code'] . "\n";
    echo "Feedback Effective URL: " . $fbRes['info']['url'] . "\n";

    if (preg_match('/Error ID:\s*([A-Z0-9]+)/i', $fbRes['body'], $errMatch)) {
        echo "\n>>> ERROR ID DETECTED: " . $errMatch[1] . " <<<\n";
    } else {
        echo "Feedback page loaded successfully!\n";
        echo "Page Title / Header snippet: " . substr(strip_tags($fbRes['body']), 0, 400) . "\n";
    }

    // 4. Test submitting a feedback!
    echo "\n================ TESTING POST /feedback ================\n";
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $fbRes['body'], $fbCsrfM);
    $fbCsrf = $fbCsrfM[1] ?? '';

    $postRes = req($baseUrl . '/feedback', [
        'csrf_token' => $fbCsrf,
        'message' => 'Test feedback from automated verification runner ' . date('Y-m-d H:i:s')
    ], $residentCookie);

    echo "POST Feedback HTTP Code: " . $postRes['info']['http_code'] . "\n";
    echo "POST Feedback Effective URL: " . $postRes['info']['url'] . "\n";
    if (preg_match('/Error ID:\s*([A-Z0-9]+)/i', $postRes['body'], $errMatch2)) {
        echo "\n>>> POST FEEDBACK ERROR ID: " . $errMatch2[1] . " <<<\n";
    } else {
        echo "POST Feedback snippet: " . substr(strip_tags($postRes['body']), 0, 300) . "\n";
    }
}
