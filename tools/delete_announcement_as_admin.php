<?php
$base = 'http://localhost:8000';
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bg_delete_admin.txt';
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

list($html, $info) = curl('GET', $base . '/login', null, $cookieFile);
if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/i', $html, $m)) { echo "CSRF not found\n"; exit(1); }
$csrf = $m[1];
$loginData = ['email'=>'admin@baranggabay.ph','password'=>'Admin@1234','csrf_token'=>$csrf];
list($lr,$li) = curl('POST', $base . '/login', $loginData, $cookieFile);
echo "Admin login HTTP: " . $li['http_code'] . "\n";
list($admHtml,$admInfo) = curl('GET', $base . '/admin/announcements', null, $cookieFile);
if (preg_match('/admin\/announcements\/([0-9]+)\/edit.*?Automated Test Announcement/si', $admHtml, $m2)) {
    $id = $m2[1];
    echo "Found announcement id: $id\n";
    list($del, $delInfo) = curl('POST', $base . '/admin/announcements/' . $id . '/delete', ['csrf_token'=>$csrf], $cookieFile);
    echo "Delete HTTP: " . $delInfo['http_code'] . "\n";
    echo substr($del,0,400) . "\n";
} else {
    echo "Could not find admin announcement by exact phrase; trying relaxed match...\n";
    if (preg_match('/admin\/announcements\/([0-9]+)\/edit/si', $admHtml, $m3)) {
        $id = $m3[1];
        echo "Using first announcement id: $id\n";
        list($del, $delInfo) = curl('POST', $base . '/admin/announcements/' . $id . '/delete', ['csrf_token'=>$csrf], $cookieFile);
        echo "Delete HTTP: " . $delInfo['http_code'] . "\n";
    } else {
        echo "No announcement edit links found.\n";
    }
}
?>