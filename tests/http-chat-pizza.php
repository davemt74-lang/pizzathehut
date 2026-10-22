<?php
declare(strict_types=1);
// Disposable MySQL + real authenticated HTTP acceptance of hands-free pizza setup.
if((getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci')!=='pizzalab_ci')throw new RuntimeException('Refusing non-CI database.');
$db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=pizzalab_ci;charset=utf8mb4',getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cookies=[];
register_shutdown_function(static function()use(&$cookies){foreach($cookies as $f)@unlink($f);});
function pizzaAssert(bool $yes,string $what):void{if(!$yes)throw new RuntimeException($what);}
function pizzaHttp(string $cookie,string $path,?array $post=null,?string $csrf=null):array{
 $h=curl_init('http://127.0.0.1:8081/'.$path);
 $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie];
 if($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$csrf===null?http_build_query($post):json_encode($post);if($csrf!==null)$opts[CURLOPT_HTTPHEADER]=['X-CSRF-Token: '.$csrf,'Content-Type: application/json'];}
 curl_setopt_array($h,$opts);$raw=curl_exec($h);if($raw===false)throw new RuntimeException(curl_error($h));
 $status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);$n=(int)curl_getinfo($h,CURLINFO_HEADER_SIZE);curl_close($h);
 return [$status,substr($raw,$n)];
}
function pizzaLogin(string $cookie,string $email):string{
 [$s,$html]=pizzaHttp($cookie,'auth.php');pizzaAssert($s===200&&preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$matches)===1,'Login form missing');
 [$s]=pizzaHttp($cookie,'auth.php',['csrf'=>$matches[1],'email'=>$email,'password'=>'TestPassword-2026!']);pizzaAssert($s===302,'Chef login failed');
 [$s,$html]=pizzaHttp($cookie,'agent_chat.php');pizzaAssert($s===200&&preg_match('/const CSRF=("[a-f0-9]+");/',$html,$matches)===1,'Chat CSRF missing');
 return (string)json_decode($matches[1],true);
}
$a=tempnam(sys_get_temp_dir(),'chef-a-');$cookies[]=$a;
[$status]=pizzaHttp($a,'api.php?action=chatPizzaContext');pizzaAssert($status===302||$status===401||$status===403,'Unauthenticated pizza context exposed');
$csrf=pizzaLogin($a,'chef@example.test');
$chef=(int)$db->query("SELECT id FROM users WHERE email='chef@example.test'")->fetchColumn();
$call=static function(string $cookie,string $csrf,array $data):array{
 [$status,$raw]=pizzaHttp($cookie,'api.php',$data,$csrf);return [$status,json_decode($raw,true)?:[]];
};
[$status,$bad]=$call($a,$csrf,['action'=>'chatNewPizza','work_date'=>date('Y-m-d'),'client_event_id'=>'bad']);pizzaAssert($status>=400,'Malformed new-pizza request accepted');
$idempotency='ci_new_pizza_chef_a_001';$date=date('Y-m-d');
// Earlier acceptance tests create two legacy shifts today. Chat should reuse
// the oldest as the one shared active day, preserving the second as history.
$dayCount=$db->prepare('SELECT COUNT(*) FROM workdays WHERE work_date=?');$dayCount->execute([$date]);
$beforeDays=(int)$dayCount->fetchColumn();
[$status,$ensured]=$call($a,$csrf,['action'=>'chatEnsureWorkday','work_date'=>$date]);
pizzaAssert($status===200&&($ensured['day']['work_date']??null)===$date,'Chat did not automatically activate today’s shared workday');
$activeDay=(int)$ensured['day']['id'];
[$status,$again]=$call($a,$csrf,['action'=>'chatEnsureWorkday','work_date'=>$date]);
pizzaAssert($status===200&&(int)$again['day']['id']===$activeDay,'Repeated daily activation created another workday');
$dayCount->execute([$date]);$afterDays=(int)$dayCount->fetchColumn();
pizzaAssert($beforeDays===$afterDays,'Shared workday activation ignored an existing dated workday');
[$status,$first]=$call($a,$csrf,['action'=>'chatNewPizza','work_date'=>$date,'client_event_id'=>$idempotency]);
pizzaAssert($status===200&&!empty($first['ok'])&&$first['existing']===false,'New pizza creation failed');
$firstId=(int)$first['id'];
$owner=$db->prepare('SELECT created_by_user_id,chat_create_request_id,pizza_size_in,stone_start_f FROM evidence WHERE id=?');$owner->execute([$firstId]);$actual=$owner->fetch(PDO::FETCH_ASSOC);
pizzaAssert((int)$actual['created_by_user_id']===$chef&&$actual['chat_create_request_id']===$idempotency,'Pizza ownership or idempotency not saved');
pizzaAssert($actual['pizza_size_in']===null&&$actual['stone_start_f']===null,'Unknown physical measurements fabricated');
$linked=$db->prepare('SELECT w.workday_id,w.recorded_by_user_id,d.work_date FROM workday_pizzas w JOIN workdays d ON d.id=w.workday_id WHERE w.evidence_id=?');$linked->execute([$firstId]);$day=$linked->fetch(PDO::FETCH_ASSOC);
pizzaAssert($day&&(int)$day['recorded_by_user_id']===$chef&&(int)$day['workday_id']===$activeDay&&$day['work_date']===$date,'Pizza not linked to the shared workday with correct chef');
[$status,$replay]=$call($a,$csrf,['action'=>'chatNewPizza','work_date'=>$date,'client_event_id'=>$idempotency]);
pizzaAssert($status===200&&$replay['existing']===true&&(int)$replay['id']===$firstId,'Retry created a duplicate pizza');
[$status,$second]=$call($a,$csrf,['action'=>'chatNewPizza','work_date'=>$date,'client_event_id'=>'ci_new_pizza_chef_a_002']);
pizzaAssert($status===200&&(int)$second['id']!==$firstId,'Distinct new pizza should create its own record');
[$status,$raw]=pizzaHttp($a,'api.php?action=chatPizzaContext');$context=json_decode($raw,true)['context']??[];
pizzaAssert($status===200&&$context['current_id']===(int)$second['id'],'Most recently created chef pizza not selected');
[$status,$picked]=$call($a,$csrf,['action'=>'chatSelectPizza','evidence_id'=>$firstId]);
pizzaAssert($status===200&&$picked['pizza']['id']===$firstId,'Explicit chef pizza selection failed');
[$status,$raw]=pizzaHttp($a,'api.php?action=chatPizzaContext');
pizzaAssert(($status===200)&&((json_decode($raw,true)['context']['current_id']??null)===$firstId),'Chef pizza selection not persisted across HTTP requests');
[$status,$started]=$call($a,$csrf,['action'=>'cookActivity','evidence_id'=>$firstId,'event_type'=>'timer_started','source'=>'voice_command','client_event_id'=>'ci_chat_pizza_timer_001','target_seconds'=>480]);
pizzaAssert($status===200&&($started['timer']['status']??null)==='running','Voice timer did not start from one explicit request');
[$status,$raw]=pizzaHttp($a,'api.php?action=kitchenTimers&mine=1');$ownTimers=json_decode($raw,true)['timers']??[];
pizzaAssert($status===200&&count(array_filter($ownTimers,static fn($t)=>$t['evidence_id']===$firstId))===1,'Chef-owned active timer missing');

$db->prepare('INSERT INTO users (display_name,email,password_hash,role) VALUES (?,?,?,?)')->execute(['Second Chef','chef-two@example.test',password_hash('TestPassword-2026!',PASSWORD_DEFAULT),'team_member']);
$chefB=(int)$db->lastInsertId();$b=tempnam(sys_get_temp_dir(),'chef-b-');$cookies[]=$b;
$csrfB=pizzaLogin($b,'chef-two@example.test');
[$status,$shared]=$call($b,$csrfB,['action'=>'chatEnsureWorkday','work_date'=>$date]);
pizzaAssert($status===200&&(int)$shared['day']['id']===$activeDay,'Second chef was assigned a different workday');
[$status,$raw]=pizzaHttp($b,'api.php?action=chatPizzaContext');$other=json_decode($raw,true)['context']??[];
pizzaAssert($status===200&&$other['current_id']===null&&$other['pizzas']===[],'Another chef inherited the first chef’s current pizza');
[$status,$raw]=pizzaHttp($b,'api.php?action=kitchenTimers&mine=1');
pizzaAssert($status===200&&(json_decode($raw,true)['timers']??[])===[],'Another chef saw someone else’s proactive timers');
[$status]=$call($b,$csrfB,['action'=>'chatSelectPizza','evidence_id'=>$firstId]);
pizzaAssert($status===403,'Second chef was allowed to hijack first chef’s current pizza');
[$status,$otherPizza]=$call($b,$csrfB,['action'=>'chatNewPizza','work_date'=>$date,'client_event_id'=>'ci_new_pizza_chef_b_001']);
pizzaAssert($status===200&&(int)$otherPizza['id']!==$firstId&&(int)$otherPizza['workday_id']===$activeDay,'Second chef pizza did not join shared workday');
$whose=$db->prepare('SELECT created_by_user_id FROM evidence WHERE id=?');$whose->execute([$otherPizza['id']]);
pizzaAssert((int)$whose->fetchColumn()===$chefB,'Second chef ownership not attached');
[$status,$raw]=pizzaHttp($b,'api.php?action=chatWorkdayContext&date='.$date);$bDay=json_decode($raw,true)['context']??[];
pizzaAssert($status===200&&($bDay['state']??null)==='single'&&(int)$bDay['day']['id']===$activeDay,'Second chef’s Chat did not see canonical kitchen workday');
pizzaAssert(in_array((int)$otherPizza['id'],array_column($bDay['pizzas'],'id'),true)&&!in_array($firstId,array_column($bDay['pizzas'],'id'),true),'Second chef’s workday view leaked the other chef’s pizza');
[$status,$raw]=pizzaHttp($a,'api.php?action=chatPizzaContext');$mine=json_decode($raw,true)['context']??[];
pizzaAssert($status===200&&$mine['current_id']===$firstId&&!in_array((int)$otherPizza['id'],array_column($mine['pizzas'],'id'),true),'Second chef changed first chef’s focus or list');
// Simulate the next local date without waiting for midnight. Two chefs and
// repeat requests must still produce exactly one new daily record.
$tomorrow=date('Y-m-d',strtotime('+1 day'));
[$status,$newDay]=$call($a,$csrf,['action'=>'chatEnsureWorkday','work_date'=>$tomorrow]);
pizzaAssert($status===200&&(int)$newDay['day']['id']!==$activeDay,'Automatic next-day workday did not roll over');
[$status,$sameDay]=$call($b,$csrfB,['action'=>'chatEnsureWorkday','work_date'=>$tomorrow]);
pizzaAssert($status===200&&(int)$sameDay['day']['id']===(int)$newDay['day']['id'],'Second chef created a duplicate next-day workday');
$count=$db->prepare('SELECT COUNT(*) FROM workdays WHERE work_date=?');$count->execute([$tomorrow]);
pizzaAssert((int)$count->fetchColumn()===1,'More than one new next-day workday was created');
$mapped=$db->prepare('SELECT workday_id FROM workday_daily_registry WHERE work_date=?');$mapped->execute([$tomorrow]);
pizzaAssert((int)$mapped->fetchColumn()===(int)$newDay['day']['id'],'Canonical daily registry did not persist day selection');
echo "PASS: automatic shared daily workday/rollover, chef-owned pizzas, durable focus, timer start, replay and two-chef isolation\n";
