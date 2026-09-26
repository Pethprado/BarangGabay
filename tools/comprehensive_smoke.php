<?php
$base = 'http://localhost:8000';
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bg_comp_smoke.txt';
@unlink($cookieFile);
function curl($method, $url, $data = null, $cookieFile = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($cookieFile) { curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile); curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); }
    if ($method === 'POST') curl_setopt($ch, CURLOPT_POST, true);
    if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$res, $info];
}

echo "Comprehensive smoke tests starting...\n";
list($html, $info) = curl('GET', $base . '/login', null, $cookieFile);
if ($info['http_code'] !== 200) { echo "GET /login returned " . $info['http_code'] . "\n"; exit(1); }
if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $html, $m)) { echo "No CSRF token found\n"; exit(1); }
$csrf = $m[1];

// login as staff
$loginData = ['email' => 'rosa.santos@baranggabay.ph','password'=>'Staff@1234','csrf_token'=>$csrf];
list($lr, $li) = curl('POST', $base . '/login', $loginData, $cookieFile);
echo "Login HTTP: " . $li['http_code'] . "\n";

// Create announcement
$annData = [
    'title' => 'Automated Test Announcement',
    'body' => '<p>This is a test created by comprehensive smoke test.</p>',
    'category' => 'general',
    'urgency' => 'normal',
    'csrf_token' => $csrf,
    'status' => 'published',
];
list($ar, $ai) = curl('POST', $base . '/admin/announcements', $annData, $cookieFile);
echo "Create announcement HTTP: " . $ai['http_code'] . "\n";

// Check announcements list (resident)
list($listHtml, $listInfo) = curl('GET', $base . '/announcements', null, $cookieFile);
echo "GET /announcements -> " . $listInfo['http_code'] . "\n";
if (strpos($listHtml, 'Automated Test Announcement') !== false) echo "Announcement appears in resident list.\n";

// Upload an ordinance (multipart)
$filePath = __DIR__ . '/test_files/dummy.pdf';
if (!file_exists($filePath)) { echo "Dummy PDF missing\n"; exit(1); }
$post = [
    'title' => 'Automated Upload Ordinance',
    'ordinance_no' => 'Ordinance AUTO-001',
    'description' => 'Uploaded by smoke test',
    'category' => 'Government',
    'enacted_date' => date('Y-m-d'),
    'status' => 'active',
    'csrf_token' => $csrf,
    'file' => new CURLFile($filePath, 'application/pdf', 'dummy.pdf')
];
list($upRes, $upInfo) = curl('POST', $base . '/admin/ordinances', $post, $cookieFile);
echo "Upload ordinance HTTP: " . $upInfo['http_code'] . "\n";

// List ordinances (resident)
list($ordsHtml, $ordsInfo) = curl('GET', $base . '/ordinances', null, $cookieFile);
echo "GET /ordinances -> " . $ordsInfo['http_code'] . "\n";
if (strpos($ordsHtml, 'Automated Upload Ordinance') !== false) echo "Uploaded ordinance appears in list.\n";

// Try viewing ordinance detail - find an ID by searching the list for link pattern /ordinances/{id}
if (preg_match('/\/ordinances\/(\d+)/', $ordsHtml, $m2)) {
    $id = $m2[1];
    list($detailHtml, $detailInfo) = curl('GET', $base . '/ordinances/' . $id, null, $cookieFile);
    echo "GET /ordinances/{$id} -> " . $detailInfo['http_code'] . "\n";
    if (strpos($detailHtml, 'PDF') !== false || strpos($detailHtml, 'ordinance') !== false) echo "Ordinance detail page looks OK.\n";
} else {
    echo "Could not find ordinance ID in list.\n";
}

// Edit announcement - find its id from admin list
list($admAnnHtml, $admAnnInfo) = curl('GET', $base . '/admin/announcements', null, $cookieFile);
if (preg_match('/admin\/announcements\/([0-9]+)\/edit/', $admAnnHtml, $m3)) {
    $annId = $m3[1];
    $updData = ['title' => 'Automated Test Announcement (updated)', 'body' => '<p>Updated</p>', 'category'=>'general','urgency'=>'normal','csrf_token'=>$csrf,'status'=>'published'];
    list($updatRes, $updatInfo) = curl('POST', $base . '/admin/announcements/' . $annId, $updData, $cookieFile);
    echo "Update announcement HTTP: " . $updatInfo['http_code'] . "\n";
    // delete announcement
    list($delRes, $delInfo) = curl('POST', $base . '/admin/announcements/' . $annId . '/delete', ['csrf_token'=>$csrf], $cookieFile);
    echo "Delete announcement HTTP: " . $delInfo['http_code'] . "\n";
} else {
    echo "Could not find admin announcement edit link.\n";
}

echo "Comprehensive smoke tests finished.\n";
?>