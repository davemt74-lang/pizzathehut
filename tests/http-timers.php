<?php
declare(strict_types=1);
// Real authenticated HTTP + disposable MySQL integration for timer state.
$name=getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci';
if($name!=='pizzalab_ci')throw new RuntimeException('Refusing non-CI database.');
$db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=pizzalab_ci;charset=utf8mb4',getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cookie=tempnam(sys_get_temp_dir(),'timer-http-');register_shutdown_function(static fn()=>@unlink($cookie));
function requestTimer(string $path,?array $post=null,?string $csrf=null):array{
 global $cookie;
 $c=curl_init('http://127.0.0.1:8081/'.$path);
 $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie];
 if($post!==null){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=$csrf===null?http_build_query($post):json_encode($post);if($csrf!==null)$options[CURLOPT_HTTPHEADER]=['X-CSRF-Token: '.$csrf,'Content-Type: application/json'];}
 curl_setopt_array($c,$options);$raw=curl_exec($c);if($raw===false)throw new RuntimeException(curl_error($c));
 $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);$headers=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);return [$status,substr($raw,$headers)];
}
function must(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
[$s]=requestTimer('api.php?action=kitchenTimers');must($s===302||$s===401||$s===403,'Unauthenticated timer read exposed.');
[$s,$body]=requestTimer('auth.php');must($s===200&&preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m),'Login token missing.');
[$s]=requestTimer('auth.php',['csrf'=>$m[1],'email'=>'chef@example.test','password'=>'TestPassword-2026!']);must($s===302,'Timer chef login failed.');
[$s,$html]=requestTimer('agent_chat.php');must($s===200&&preg_match('/const CSRF=("[a-f0-9]+");/',$html,$m),'Chat token missing.');$csrf=json_decode($m[1],true);
$recipe=(int)$db->query('SELECT id FROM recipes LIMIT 1')->fetchColumn();
$db->prepare('INSERT INTO evidence (recipe_id,label) VALUES (?,?)')->execute([$recipe,'Timer CI pizza']);$pizza=(int)$db->lastInsertId();
$post=static function(array $data)use($csrf):array{$res=requestTimer('api.php',$data,$csrf);return [$res[0],json_decode($res[1],true)?:[]];};
$base=['action'=>'cookActivity','evidence_id'=>$pizza,'event_type'=>'timer_started','source'=>'manual','client_event_id'=>'ci_timer_start_000001','target_seconds'=>120];
[$s,$x]=$post($base);must($s===200&&$x['timer']['status']==='running','Timer start failed.');
[$s,$same]=$post($base);must($s===200&&$same['existing']===true&&$same['id']===$x['id'],'Start replay duplicated.');
[$s]=$post([...$base,'client_event_id'=>'ci_timer_start_000002']);must($s===409,'Second start was allowed.');
[$s,$running]=requestTimer('api.php?action=kitchenTimers');$data=json_decode($running,true);must($s===200&&count(array_filter($data['timers'],fn($t)=>$t['evidence_id']===$pizza))===1,'Timer did not restore from server.');
$event=static fn(string $type,string $id)=>['action'=>'cookActivity','evidence_id'=>$pizza,'event_type'=>$type,'source'=>'manual','client_event_id'=>$id];
[$s,$pause]=$post($event('timer_paused','ci_timer_pause_00001'));must($s===200&&$pause['timer']['status']==='paused','Pause failed.');
[$s,$resume]=$post($event('timer_resumed','ci_timer_resume_0001'));must($s===200&&$resume['timer']['status']==='running','Resume failed.');
[$s,$stop]=$post($event('timer_stopped','ci_timer_stop_000001'));must($s===200&&$stop['timer']['status']==='stopped','Stop failed.');
[$s,$reset]=$post($event('timer_reset','ci_timer_reset_00001'));must($s===200&&$reset['timer']['elapsed_ms']===0,'Reset failed.');
[$s,$list]=requestTimer('api.php?action=kitchenTimers');must($s===200&&count(array_filter((json_decode($list,true)['timers']??[]),fn($t)=>$t['evidence_id']===$pizza))===0,'Stopped timer still active.');
echo "PASS: authenticated timer transitions, replay, duplicate prevention, persistence, reload and listing\n";
