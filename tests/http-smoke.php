<?php
declare(strict_types=1);
$base='http://127.0.0.1:8081/';
$cookie=tempnam(sys_get_temp_dir(),'pizza-ci-cookie-');
register_shutdown_function(static fn()=>@unlink($cookie));
function request(string $path,?array $post=null):array{
 global $base,$cookie;
 $c=curl_init($base.$path);
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_HEADER=>true]);
 if($post!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));}
 $raw=curl_exec($c);if($raw===false)throw new RuntimeException('HTTP failure: '.curl_error($c));
 $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);
 $length=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);
 $headers=substr($raw,0,$length);$body=substr($raw,$length);curl_close($c);
 return[$status,$headers,$body];
}
[$status,$header,$body]=request('auth.php');
if($status!==200||!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))throw new RuntimeException('Login or CSRF unavailable');
[$status,$h]=request('auth.php',['csrf'=>$m[1],'email'=>'chef@example.test','password'=>'TestPassword-2026!']);
if($status!==302||!str_contains($h,'Location: index.php'))throw new RuntimeException('Team member login failed');
[$status,$h]=request('index.php');
if($status!==302||!str_contains($h,'agent_chat.php'))throw new RuntimeException('Missing chat-first redirect');
[$status,$h,$html]=request('agent_chat.php');
if($status!==200)throw new RuntimeException('Team chat page unavailable');
foreach(['id="chatMic"','id="chatCamera"','id="chatNavToggle"','id="chatAccountToggle"','id="question"'] as $tag)
 if(!str_contains($html,$tag))throw new RuntimeException('Missing chat control: '.$tag);
[$status,$h,$json]=request('api.php?action=batchCoachHistory&scope=all');
if($status!==200||!is_array(json_decode($json,true)))throw new RuntimeException('Team chat history failure');
[$status]=request('api.php?action=teamUsers');
if($status!==403)throw new RuntimeException('Team accessed admin-only account list');
[$status,$h,$body]=request('pizza_tracker.php?embedded=1');
if($status!==200||!str_contains($h,'SAMEORIGIN'))throw new RuntimeException('Same-origin embedded tracker unavailable');
echo "PASS: authenticated team login, CSRF, chat controls, role isolation and camera embed\n";
