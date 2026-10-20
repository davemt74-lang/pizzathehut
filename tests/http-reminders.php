<?php
declare(strict_types=1);
// Disposable MySQL / real authenticated HTTP only.
if((getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci')!=='pizzalab_ci')throw new RuntimeException('Refusing non-CI database');
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=pizzalab_ci;charset=utf8mb4',getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cookie=tempnam(sys_get_temp_dir(),'reminder-http-');register_shutdown_function(static fn()=>@unlink($cookie));
function reminderRequest(string $url,?array $post=null,?string $csrf=null):array {
 global $cookie;
 $c=curl_init('http://127.0.0.1:8081/'.$url);
 $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie];
 if($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$csrf===null?http_build_query($post):json_encode($post,JSON_THROW_ON_ERROR);
 if($csrf!==null)$opts[CURLOPT_HTTPHEADER]=['X-CSRF-Token: '.$csrf,'Content-Type: application/json'];}
 curl_setopt_array($c,$opts);$raw=curl_exec($c);if($raw===false)throw new RuntimeException(curl_error($c));
 $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);$header=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
 return [$status,substr($raw,$header)];
}
function assertReminder(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
[$status]=reminderRequest('api.php',['action'=>'kitchenReminderPoll'],'bogus');assertReminder($status===302||$status===401||$status===403,'Unauthenticated reminders exposed');
[$status,$html]=reminderRequest('auth.php');
assertReminder($status===200&&preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m),'Login csrf missing');
[$status]=reminderRequest('auth.php',['csrf'=>$m[1],'email'=>'chef@example.test','password'=>'TestPassword-2026!']);
assertReminder($status===302,'Reminder login failed');
[$status,$chat]=reminderRequest('agent_chat.php');
assertReminder($status===200&&preg_match('/const CSRF=("[a-f0-9]+");/',$chat,$m),'Chat token missing');
$csrf=json_decode($m[1],true);
$send=static function(array $payload)use($csrf):array {
 [$code,$body]=reminderRequest('api.php',$payload,$csrf);
 return [$code,json_decode($body,true)?:[]];
};
$recipe=(int)$pdo->query('SELECT id FROM recipes LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO evidence (recipe_id,label) VALUES (?,?)')->execute([$recipe,'CI rotation reminder pizza']);
$id=(int)$pdo->lastInsertId();
$base=['action'=>'cookActivity','evidence_id'=>$id,'event_type'=>'timer_started','source'=>'manual','target_seconds'=>30,'client_event_id'=>'ci_reminder_start_00001'];
[$status,$started]=$send($base);assertReminder($status===200&&($started['timer']['target_seconds']??null)===30,'Configured timer start failed');
$poll=['action'=>'kitchenReminderPoll'];
[$status,$response]=$send($poll);assertReminder($status===200&&$response['reminders']===[],'Premature reminder sent');
$rewind=$pdo->prepare('UPDATE kitchen_activity_timers SET started_at=UTC_TIMESTAMP(6)-INTERVAL ? SECOND WHERE evidence_id=?');
$rewind->execute([14,$id]);
[$status,$response]=$send($poll);
assertReminder($status===200&&count($response['reminders'])===1&&$response['reminders'][0]['kind']==='rotation_reminder','First rotation reminder missing');
$activity=(int)$pdo->query("SELECT COUNT(*) FROM oven_cook_activity WHERE evidence_id=$id AND source='agent_alert' AND event_class='reminder'")->fetchColumn();
assertReminder($activity===1,'Reminder activity provenance incorrect');
[$status,$response]=$send($poll);
assertReminder($status===200&&$response['reminders']===[],'Duplicate reminder delivered');
$rewind->execute([25,$id]);
[$status,$response]=$send($poll);
assertReminder($status===200&&count($response['reminders'])===1&&$response['reminders'][0]['kind']==='pull_back_reminder','Pull-back reminder missing');
$rewind->execute([32,$id]);
[$status,$response]=$send($poll);
assertReminder($status===200&&count($response['reminders'])===1&&$response['reminders'][0]['kind']==='time_up_reminder','Time-up reminder missing');
[$status,$response]=$send($poll);assertReminder($status===200&&$response['reminders']===[],'Time-up delivered twice');
$count=(int)$pdo->query("SELECT COUNT(*) FROM kitchen_timer_reminders WHERE evidence_id=$id")->fetchColumn();
assertReminder($count===3,'Unexpected milestone count');
[$status,$stopped]=$send(['action'=>'cookActivity','evidence_id'=>$id,'event_type'=>'timer_stopped','source'=>'manual','client_event_id'=>'ci_reminder_stop_00001']);
assertReminder($status===200,'Stop action failed');
[$status,$response]=$send($poll);assertReminder($status===200&&$response['reminders']===[],'Stopped timer still alerts');
$pdo->prepare('INSERT INTO evidence (recipe_id,label) VALUES (?,?)')->execute([$recipe,'CI untargeted timer pizza']);
$noTarget=(int)$pdo->lastInsertId();
[$status,$untargeted]=$send(['action'=>'cookActivity','evidence_id'=>$noTarget,'event_type'=>'timer_started','source'=>'manual','client_event_id'=>'ci_reminder_noduration_1']);
assertReminder($status===200&&$untargeted['timer']['target_seconds']===null,'Untargeted start failed');
$rewind->execute([360,$noTarget]);
[$status,$response]=$send($poll);assertReminder($status===200&&$response['reminders']===[],'Invented reminder without target');
echo "PASS: authenticated foreground reminders, timing, distinct milestones, source provenance, stop suppression, no implicit duration, single delivery\n";
