<?php
declare(strict_types=1);
// Disposable CI database only. Confirm upgrade rejects POST without backup acknowledgement.
$cookie=tempnam(sys_get_temp_dir(),'pizza-upgrade-ci');
register_shutdown_function(static fn()=>@unlink($cookie));
function hit(string $path,?array $post=null): array {
 global $cookie; $ch=curl_init('http://127.0.0.1:8081/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>20]);
 if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
 $raw=curl_exec($ch);if($raw===false)throw new RuntimeException('HTTP: '.curl_error($ch));
 $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$split=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
 return [$status,substr($raw,0,$split),substr($raw,$split)];
}
[$status,$h,$body]=hit('auth.php');
if($status!==200||!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))throw new RuntimeException('Missing administrator login CSRF');
[$status]=hit('auth.php',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'AdminPassword-2026!']);
if($status!==302)throw new RuntimeException('Administrator login failed');
[$status,$h,$body]=hit('upgrade.php');
if($status!==200||!str_contains($body,'name="backup_confirmed"')||!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$token))throw new RuntimeException('Upgrade preflight unavailable');
[$status,$h,$body]=hit('upgrade.php',['csrf'=>$token[1]]);
if(!str_contains($body,'Confirm a completed database'))throw new RuntimeException('Unchecked upgrade POST was not rejected');
[$status,$h,$body]=hit('upgrade.php',['csrf'=>$token[1],'backup_confirmed'=>'yes']);
if($status!==200||str_contains($body,'Upgrade stopped;'))throw new RuntimeException('Idempotent confirmed upgrade failed');
echo "PASS: admin login, backup acknowledgement refusal, and confirmed upgrade on disposable MySQL\n";
