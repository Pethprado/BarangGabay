<?php
$cookieFile = __DIR__ . '/render_cookie.txt';
$ch = curl_init('https://baranggabay.onrender.com/superadmin/errors?q=003AB340');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

preg_match('/<pre[^>]*>(.*?)<\/pre>/is', $html, $pre);
if (!empty($pre[1])) {
    echo "Stack trace:\n" . html_entity_decode(strip_tags($pre[1])) . "\n";
} else {
    preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $html, $tds);
    foreach ($tds[1] as $td) {
        $t = trim(strip_tags($td));
        if (strlen($t) > 0 && strlen($t) < 300) echo "  TD: " . $t . "\n";
    }
}
