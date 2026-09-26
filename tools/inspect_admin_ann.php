<?php
$base='http://localhost:8000';
$cookie=sys_get_temp_dir().DIRECTORY_SEPARATOR.'bg_inspect_admin.txt';@unlink($cookie);
function curlg($url,$cookie){$ch=curl_init();curl_setopt($ch, CURLOPT_URL,$url);curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);curl_setopt($ch, CURLOPT_COOKIEJAR,$cookie);curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie);$r=curl_exec($ch);$i=curl_getinfo($ch);curl_close($ch);return[$r,$i];}
list($h,$i)=curlg($base.'/login',$cookie);preg_match('/name="csrf_token" value="([a-f0-9]+)"/i',$h,$m);$csrf=$m[1]??null;echo "csrf:$csrf\n";list($lr,$li)=curlg($base.'/login', $cookie); // to set cookie
// do post login
$ch=curl_init();curl_setopt($ch, CURLOPT_URL,$base.'/login');curl_setopt($ch, CURLOPT_POST,true);curl_setopt($ch, CURLOPT_POSTFIELDS,['email'=>'admin@baranggabay.ph','password'=>'Admin@1234','csrf_token'=>$csrf]);curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);curl_setopt($ch, CURLOPT_COOKIEJAR,$cookie);curl_setopt($ch, CURLOPT_COOKIEFILE,$cookie);$lr=curl_exec($ch);curl_close($ch);
list($adm,$ai)=curlg($base.'/admin/announcements',$cookie);echo "HTTP:".$ai['http_code']."\n";echo substr($adm,0,3000);
?>