<?php
$base = 'http://localhost:8000';
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bg_comp_smoke.txt';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base . '/ordinances');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
echo "HTTP: " . $info['http_code'] . "\n";
echo substr($res,0,3000);
?>