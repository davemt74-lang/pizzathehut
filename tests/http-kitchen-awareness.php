<?php
declare(strict_types=1);
// Disposable MySQL 8 + real HTTP only. Do not run against an installed kitchen.
if((getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci')!=='pizzalab_ci')throw new RuntimeException('Refusing non-CI database.');
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=pizzalab_ci;charset=utf8mb4',
    getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$cookie=tempnam(sys_get_temp_dir(),'kitchen-aware-');register_shutdown_function(static fn()=>@unlink($cookie));
function kitchenAwareHttp(string $url,?array $post=null,?string $csrf=null):array{
 global $cookie;
 $c=curl_init('http://127.0.0.1:8081/'.$url);
 $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie];
 if($post!==null){
   $opts[CURLOPT_POST]=true;
   $opts[CURLOPT_POSTFIELDS]=$csrf===null?http_build_query($post):json_encode($post,JSON_THROW_ON_ERROR);
   if($csrf!==null)$opts[CURLOPT_HTTPHEADER]=['X-CSRF-Token: '.$csrf,'Content-Type: application/json'];
 }
 curl_setopt_array($c,$opts);
 $body=curl_exec($c);if($body===false)throw new RuntimeException(curl_error($c));
 $code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);$len=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
 return [$code,substr($body,$len)];
}
function awareAssert(bool $yes,string $msg):void{if(!$yes)throw new RuntimeException($msg);}
[$code]=kitchenAwareHttp('api.php?action=chatKitchenAwareness');
awareAssert(in_array($code,[302,401,403],true),'Unauthenticated kitchen snapshot is exposed');
[$code,$html]=kitchenAwareHttp('auth.php');
awareAssert($code===200&&preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m),'Login CSRF missing');
[$code]=kitchenAwareHttp('auth.php',['email'=>'chef@example.test','password'=>'TestPassword-2026!','csrf'=>$m[1]]);
awareAssert($code===302,'Chef sign-in failed');
[$code,$html]=kitchenAwareHttp('agent_chat.php');
awareAssert($code===200&&preg_match('/const CSRF=("[a-f0-9]+");/',$html,$m),'Agent Chat CSRF missing');
$csrf=json_decode($m[1],true);
$chef=(int)$pdo->query("SELECT id FROM users WHERE email='chef@example.test'")->fetchColumn();
$recipe=(int)$pdo->query('SELECT id FROM recipes ORDER BY id LIMIT 1')->fetchColumn();
$steps=$pdo->prepare('SELECT step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes FROM recipe_steps WHERE recipe_id=? ORDER BY sort_order,id LIMIT 2');
$steps->execute([$recipe]);$plan=$steps->fetchAll();
awareAssert(count($plan)>=1,'Seeded recipe steps missing');
$code='CIAWARE01';
$pdo->prepare("INSERT INTO dough_batches (batch_code,recipe_id,recorded_by_user_id,made_by_user_id,process_steps_snapshot) VALUES (?,?,?,?,?)")
    ->execute([$code,$recipe,$chef,$chef,json_encode($plan)]);
$batchId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO chat_batch_sessions (batch_id,recipe_id,user_id,client_request_id,recipe_fingerprint,status,next_step_index)
                 VALUES (?,?,?,'ci_aware_session_001',?,'active',0)")
    ->execute([$batchId,$recipe,$chef,str_repeat('a',64)]);
$pdo->prepare('INSERT INTO evidence (recipe_id,batch_id,label) VALUES (?,?,?)')->execute([$recipe,$batchId,'CI kitchen aware pizza A']);
$pizza=(int)$pdo->lastInsertId();
$send=static function(array $data)use($csrf):array{
 [$c,$j]=kitchenAwareHttp('api.php',$data,$csrf);return [$c,json_decode($j,true)?:[]];
};
[$code,$resp]=$send(['action'=>'cookActivity','event_type'=>'timer_started','source'=>'manual','evidence_id'=>$pizza,
                    'target_seconds'=>90,'client_event_id'=>'ci_aware_timer_000001']);
awareAssert($code===200,'Start time for context failed');
[$code,$r]=kitchenAwareHttp('api.php?action=chatKitchenAwareness');
$d=json_decode($r,true);$a=$d['awareness']??[];
awareAssert($code===200&&$d['ok']===true,'Kitchen context unavailable to authenticated chef');
$guides=array_values(array_filter($a['active_guides'],static fn($x)=>$x['batch_id']===$batchId));
awareAssert(count($guides)===1&&$guides[0]['next_step']['key']===$plan[0]['step_key'],'Saved dough stage missing or wrong');
$times=array_values(array_filter($a['active_pizza_timers'],static fn($x)=>$x['pizza_id']===$pizza));
awareAssert(count($times)===1&&$times[0]['status']==='running'&&$times[0]['planned_target_seconds']===90,'Running timer absent or misread');
awareAssert($times[0]['last_confirmed_physical_action_this_run']===null,'Timer start falsely treated as physical movement');
[$code,$resp]=$send(['action'=>'cookActivity','event_type'=>'rotation_confirmed','source'=>'voice_command',
    'evidence_id'=>$pizza,'client_event_id'=>'ci_aware_rotate_00001']);
awareAssert($code===200,'Rotation observation not saved');
[$code,$raw]=kitchenAwareHttp('api.php?action=chatKitchenAwareness');$a=json_decode($raw,true)['awareness'];
$t=array_values(array_filter($a['active_pizza_timers'],static fn($x)=>$x['pizza_id']===$pizza))[0];
awareAssert(($t['last_confirmed_physical_action_this_run']['event_type']??'')==='rotation_confirmed','Missing actual rotation event');
[$code,$resp]=$send(['action'=>'cookActivity','event_type'=>'timer_stopped','source'=>'manual',
    'evidence_id'=>$pizza,'client_event_id'=>'ci_aware_stop_000001']);
awareAssert($code===200,'Stop failed');
[$code,$resp]=$send(['action'=>'cookActivity','event_type'=>'timer_started','source'=>'manual',
    'evidence_id'=>$pizza,'target_seconds'=>100,'client_event_id'=>'ci_aware_timer_000002']);
awareAssert($code===200,'Restart failed');
[$code,$raw]=kitchenAwareHttp('api.php?action=chatKitchenAwareness');$a=json_decode($raw,true)['awareness'];
$t=array_values(array_filter($a['active_pizza_timers'],static fn($x)=>$x['pizza_id']===$pizza))[0];
awareAssert($t['last_confirmed_physical_action_this_run']===null,'Old rotation falsely assigned to new cook');
$firstCookie=$cookie;$cookie=tempnam(sys_get_temp_dir(),'kitchen-aware-admin-');
[$code,$html]=kitchenAwareHttp('auth.php');
awareAssert($code===200&&preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m),'Admin login CSRF missing');
[$code]=kitchenAwareHttp('auth.php',['email'=>'admin@example.test','password'=>'AdminPassword-2026!','csrf'=>$m[1]]);
awareAssert($code===302,'Admin login failed');
[$code,$raw]=kitchenAwareHttp('api.php?action=chatKitchenAwareness');$a=json_decode($raw,true)['awareness']??[];
awareAssert($code===200,'Shared kitchen status unavailable to admin');
awareAssert(count(array_filter($a['active_guides'],static fn($x)=>$x['batch_id']===$batchId))===0,'Personal dough guide leaked across accounts');
awareAssert(count(array_filter($a['active_pizza_timers'],static fn($x)=>$x['pizza_id']===$pizza))===1,'Shared pizza timer not available to team');
@unlink($cookie);$cookie=$firstCookie;
echo "PASS: authenticated current-kitchen snapshot; owner-private dough guide, shared timer, confirmed-action provenance and reset isolation\n";
