<?php
declare(strict_types=1);
// Disposable MySQL 8.0 and PHP HTTP server only; never run on production.
$name=getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci';
if($name!=='pizzalab_ci')throw new RuntimeException('Refusing non-CI database');
$pdo=new PDO('mysql:host='.(getenv('PIZZA_TEST_DB_HOST')?:'127.0.0.1').';port=3306;dbname=pizzalab_ci;charset=utf8mb4',getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cookie=tempnam(sys_get_temp_dir(),'pizza-kitchen-ci');
register_shutdown_function(static fn()=>@unlink($cookie));
function kitchenHttp(string $uri,?array $post=null,?string $csrf=null):array{
 global $cookie;
 $curl=curl_init('http://127.0.0.1:8081/'.$uri);
 $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie];
 if($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$csrf===null?http_build_query($post):json_encode($post,JSON_THROW_ON_ERROR);if($csrf!==null)$opts[CURLOPT_HTTPHEADER]=['X-CSRF-Token: '.$csrf,'Content-Type: application/json'];}
 curl_setopt_array($curl,$opts);
 $raw=curl_exec($curl);if($raw===false)throw new RuntimeException(curl_error($curl));
 $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$n=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);curl_close($curl);
 return [$status,substr($raw,0,$n),substr($raw,$n)];
}
function kitchenPost(array $data,string $csrf):array{
 [$status,$h,$body]=kitchenHttp('api.php',$data,$csrf);return [$status,json_decode($body,true)?:[]];
}
[$status,$h,$html]=kitchenHttp('auth.php');
if($status!==200||!preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m))throw new RuntimeException('Login CSRF missing');
[$status]=kitchenHttp('auth.php',['csrf'=>$m[1],'email'=>'chef@example.test','password'=>'TestPassword-2026!']);
if($status!==302)throw new RuntimeException('Chef login failed');
[$status,$h,$html]=kitchenHttp('agent_chat.php');
if($status!==200||!preg_match('/const CSRF=("[a-f0-9]+");/',$html,$m))throw new RuntimeException('Chat token missing');
$csrf=json_decode($m[1],true);
$chef=(int)$pdo->query("SELECT id FROM users WHERE email='chef@example.test'")->fetchColumn();
$recipe=(int)$pdo->query('SELECT id FROM recipes ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO dough_batches (batch_code,recipe_id,recorded_by_user_id) VALUES (?,?,?)')->execute(['CI1515A',$recipe,$chef]);
$batch=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO evidence (recipe_id,batch_id,label) VALUES (?,?,?)')->execute([$recipe,$batch,'CI kitchen cooking pizza']);
$pizza=(int)$pdo->lastInsertId();
$base=['action'=>'chatKitchenAction','action_type'=>'dough_temp','source'=>'voice_command','batch_id'=>$batch,'temperature_f'=>68,'client_event_id'=>'ci_kitchen_temp_12345'];
[$status,$result]=kitchenPost($base,$csrf);
if($status<400)throw new RuntimeException('Unconfirmed kitchen write was allowed');
$base['confirmed']=true;
[$status,$result]=kitchenPost($base,$csrf);
if($status!==200||empty($result['ok'])||!empty($result['existing']))throw new RuntimeException('Temperature confirmation failed: '.$status.' '.json_encode($result));
[$status,$duplicate]=kitchenPost($base,$csrf);
if($status!==200||empty($duplicate['existing'])||$duplicate['id']!==$result['id'])throw new RuntimeException('Duplicate action was inserted twice');
$temps=(int)$pdo->query('SELECT COUNT(*) FROM batch_temperature_readings WHERE batch_id='.$batch)->fetchColumn();
if($temps!==1)throw new RuntimeException('Duplicate temperature record created');
$conflict=$base;$conflict['temperature_f']=69;
[$status]=kitchenPost($conflict,$csrf);
if($status!==409)throw new RuntimeException('Changed duplicate identifier was accepted');
$fridge=['action'=>'chatKitchenAction','action_type'=>'fridge_temp','source'=>'manual','temperature_f'=>38,'location_label'=>'Main refrigerator','client_event_id'=>'ci_kitchen_fridge_1234','confirmed'=>true];
[$status,$fr]=kitchenPost($fridge,$csrf);
if($status!==200||empty($fr['id']))throw new RuntimeException('Refrigerator reading failed');
$env=$pdo->query('SELECT location_label,temperature_f FROM kitchen_environment_readings WHERE action_id='.(int)$fr['id'])->fetch(PDO::FETCH_ASSOC);
if(!$env||$env['location_label']!=='Main refrigerator'||(float)$env['temperature_f']!==38.0)throw new RuntimeException('Fridge ambient reading not stored correctly');
$ball=['action'=>'chatKitchenAction','action_type'=>'batch_balled','source'=>'voice_command','batch_id'=>$batch,'client_event_id'=>'ci_kitchen_balled_123','confirmed'=>true];
[$status,$b]=kitchenPost($ball,$csrf);
if($status!==200||empty($b['id']))throw new RuntimeException('Balled command failed');
[$status,$b2]=kitchenPost($ball,$csrf);
if($status!==200||empty($b2['existing']))throw new RuntimeException('Balled command was not idempotent');
$stage=$pdo->query('SELECT stage,balled_at FROM dough_batches WHERE id='.$batch)->fetch(PDO::FETCH_ASSOC);
if($stage['stage']!=='balled'||$stage['balled_at']===null)throw new RuntimeException('Balled batch stage not updated');
$eventCount=(int)$pdo->query("SELECT COUNT(*) FROM dough_batch_events WHERE batch_id=$batch AND phase='balled'")->fetchColumn();
if($eventCount!==1)throw new RuntimeException('Balled event duplicated');
$rotation=['action'=>'cookActivity','evidence_id'=>$pizza,'event_type'=>'rotation_confirmed','source'=>'voice_command','client_event_id'=>'ci_kitchen_rotate_12345'];
[$status,$c]=kitchenPost($rotation,$csrf);
if($status!==200||empty($c['ok']))throw new RuntimeException('Confirmed voice rotation failed');
$row=$pdo->query('SELECT event_class,source FROM oven_cook_activity WHERE evidence_id='.$pizza)->fetch(PDO::FETCH_ASSOC);
if(!$row||$row['event_class']!=='observed'||$row['source']!=='voice_command')throw new RuntimeException('Voice rotation provenance incorrect');
$rotation['event_type']='rotation_reminder';$rotation['client_event_id']='ci_kitchen_reminder_123';
[$status]=kitchenPost($rotation,$csrf);
if($status<400)throw new RuntimeException('Reminder was accepted as chef-confirmed activity');
echo "PASS: authenticated kitchen confirmation, same-ID dedupe, typed fridge/dough readings, balling and voice cooking attribution\n";
