<?php
declare(strict_types=1);
// Real authenticated PHP HTTP/MySQL smoke, disposable CI schema only.
if((getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci')!=='pizzalab_ci')throw new RuntimeException('Refusing non-CI database');
$db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=pizzalab_ci;charset=utf8mb4',getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cookie=tempnam(sys_get_temp_dir(),'batchchat-');register_shutdown_function(static fn()=>@unlink($cookie));
function chatHttp(string $uri,?array $post=null,?string $csrf=null):array {
 global $cookie;$c=curl_init('http://127.0.0.1:8081/'.$uri);
 $opt=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie];
 if($post!==null){$opt[CURLOPT_POST]=true;$opt[CURLOPT_POSTFIELDS]=$csrf===null?http_build_query($post):json_encode($post,JSON_THROW_ON_ERROR);
 if($csrf!==null)$opt[CURLOPT_HTTPHEADER]=['X-CSRF-Token: '.$csrf,'Content-Type: application/json'];}
 curl_setopt_array($c,$opt);$raw=curl_exec($c);if($raw===false)throw new RuntimeException(curl_error($c));
 $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);$offset=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
 return [$status,substr($raw,$offset)];
}
function checkChat(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function getChat(string $query):array {[$status,$body]=chatHttp('api.php?'.$query);return [$status,json_decode($body,true)?:[]];}
function postChat(array $payload,string $csrf):array{[$status,$body]=chatHttp('api.php',$payload,$csrf);return [$status,json_decode($body,true)?:[]];}
[$status]=getChat('action=chatBatchSessions');checkChat($status===302||$status===401||$status===403,'Unauthenticated guide read');
[$status,$login]=chatHttp('auth.php');checkChat($status===200&&preg_match('/name="csrf" value="([a-f0-9]+)"/',$login,$m),'Login csrf');
[$status]=chatHttp('auth.php',['email'=>'chef@example.test','password'=>'TestPassword-2026!','csrf'=>$m[1]]);checkChat($status===302,'Team member login');
[$status,$html]=chatHttp('agent_chat.php');checkChat($status===200&&preg_match('/const CSRF=("[a-f0-9]+");/',$html,$m),'Chat csrf');
$csrf=json_decode($m[1],true);
[$status,$data]=getChat('action=chatBatchPlan');
checkChat($status===200&&$data['plan']['requires_selection']===false&&count($data['plan']['steps'])>0,'Main recipe plan');
$plan=$data['plan'];$recipe=(int)$plan['recipe_id'];$step=(string)$plan['steps'][0]['step_key'];
$base=['action'=>'chatBatchStart','recipe_id'=>$recipe,'fingerprint'=>$plan['fingerprint'],'client_request_id'=>'ci_chat_batch_request_00001'];
[$status]=postChat($base,$csrf);checkChat($status>=400,'Unconfirmed batch creation accepted');
$countBefore=(int)$db->query('SELECT COUNT(*) FROM chat_batch_sessions')->fetchColumn();
$base['confirmed']=true;
[$status,$new]=postChat($base,$csrf);checkChat($status===200&&$new['existing']===false,'Confirmed batch create failed');
$sess=$new['session'];$sid=(int)$sess['session_id'];$bid=(int)$sess['batch_id'];
checkChat($sess['current_step']['step_key']===$step,'First step mismatched recipe');
checkChat((int)$db->query('SELECT COUNT(*) FROM chat_batch_sessions')->fetchColumn()===$countBefore+1,'One batch session expected');
[$status,$repeat]=postChat($base,$csrf);checkChat($status===200&&$repeat['existing']===true&&$repeat['session']['batch_id']===$bid,'Batch replay created duplicate');
[$status]=postChat([...$base,'recipe_id'=>$recipe+999],$csrf);checkChat($status===409,'Conflicting request ID accepted');
[$status,$listed]=getChat('action=chatBatchSessions');
checkChat($status===200&&count(array_filter($listed['sessions'],fn($s)=>$s['session_id']===$sid))===1,'Resume listing did not include active session');
[$status,$full]=getChat('action=chatBatchSession&session_id='.$sid);
checkChat($status===200&&$full['session']['batch_code']===$sess['batch_code'],'Saved session recovery');
$event=['action'=>'chatBatchStep','session_id'=>$sid,'step_key'=>$step,'client_event_id'=>'ci_chat_step_request_00001','source'=>'voice_command','note'=>'Chef confirmed the first step'];
[$status]=postChat($event,$csrf);checkChat($status>=400,'Unconfirmed physical step accepted');
[$status]=postChat([...$event,'confirmed'=>true,'step_key'=>'not_current'],$csrf);checkChat($status===409,'Wrong step advanced session');
[$status,$next]=postChat([...$event,'confirmed'=>true],$csrf);
checkChat($status===200&&$next['existing']===false&&$next['session']['next_step_index']===1,'Step not advanced');
[$status,$same]=postChat([...$event,'confirmed'=>true],$csrf);
checkChat($status===200&&$same['existing']===true&&$same['session']['next_step_index']===1,'Duplicate advanced again');
[$status]=postChat([...$event,'confirmed'=>true,'note'=>'Changed note'],$csrf);
checkChat($status===409,'Different payload same step ID was accepted');
$actions=(int)$db->query("SELECT COUNT(*) FROM chat_batch_step_actions WHERE session_id=$sid")->fetchColumn();
checkChat($actions===1,'One step action expected');
$logs=(int)$db->query("SELECT COUNT(*) FROM batch_step_logs WHERE batch_id=$bid")->fetchColumn();
checkChat($logs===1,'Batch step history not integrated');
$before=$db->query("SELECT recipe_snapshot,process_steps_snapshot FROM dough_batches WHERE id=$bid")->fetch(PDO::FETCH_ASSOC);
$db->prepare('UPDATE recipes SET notes=? WHERE id=?')->execute(['CI temporary formula change',$recipe]);
[$status,$stale]=postChat(['action'=>'chatBatchStart','recipe_id'=>$recipe,'fingerprint'=>$plan['fingerprint'],'client_request_id'=>'ci_chat_batch_request_00002','confirmed'=>true],$csrf);
checkChat($status===409,'Stale preview allowed batch creation');
$after=$db->query("SELECT recipe_snapshot,process_steps_snapshot FROM dough_batches WHERE id=$bid")->fetch(PDO::FETCH_ASSOC);
checkChat($before===$after,'Historical batch recipe snapshot was overwritten');
$otherCookie=tempnam(sys_get_temp_dir(),'batch-other-');$chefCookie=$cookie;$cookie=$otherCookie;
[$status,$login]=chatHttp('auth.php');checkChat($status===200&&preg_match('/name="csrf" value="([a-f0-9]+)"/',$login,$m),'Admin login csrf');
[$status]=chatHttp('auth.php',['email'=>'admin@example.test','password'=>'AdminPassword-2026!','csrf'=>$m[1]]);checkChat($status===302,'Admin login');
[$status]=getChat('action=chatBatchSession&session_id='.$sid);checkChat($status===404,'Other user read private guided session');
[$status,$page]=chatHttp('agent_chat.php');preg_match('/const CSRF=("[a-f0-9]+");/',$page,$m);$adminCsrf=json_decode($m[1]??'""',true);
[$status]=postChat([...$event,'client_event_id'=>'ci_admin_illegal_request','confirmed'=>true],$adminCsrf);
checkChat($status===404,'Other user modified guided session');
$cookie=$chefCookie;@unlink($otherCookie);
echo "PASS: chat-created batch, exact recipe, snapshots, stale-plan protection, duplicate prevention, step confirmation, recovery, user isolation\n";
