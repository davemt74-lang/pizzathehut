<?php
declare(strict_types=1);
// Execute against disposable CI MySQL only, never customer records.
$host=getenv('PIZZA_TEST_DB_HOST')?:'127.0.0.1';
$name=getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci';
if($name!=='pizzalab_ci')throw new RuntimeException('CI database required');
$pdo=new PDO("mysql:host=$host;port=3306;dbname=$name;charset=utf8mb4",getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cookie=tempnam(sys_get_temp_dir(),'pizza-day-ci');
register_shutdown_function(static fn()=>@unlink($cookie));
function hitDay(string $path,?array $post=null):array{
 global $cookie;
 $ch=curl_init('http://127.0.0.1:8081/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie]);
 if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
 $raw=curl_exec($ch);if($raw===false)throw new RuntimeException(curl_error($ch));
 $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$n=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
 return [$code,substr($raw,0,$n),substr($raw,$n)];
}
function getContext(string $date,?int $id=null):array{
 $url='api.php?action=chatWorkdayContext&date='.rawurlencode($date);
 if($id!==null)$url.='&workday_id='.$id;
 [$status,, $body]=hitDay($url);
 $decoded=json_decode($body,true);
 if($status!==200||!is_array($decoded)||empty($decoded['ok']))throw new RuntimeException('Workday context GET failed: '.$status.' '.substr($body,0,200));
 return $decoded['context'];
}
[$status,,$body]=hitDay('auth.php');
if($status!==200||!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))throw new RuntimeException('Login CSRF missing');
[$status]=hitDay('auth.php',['csrf'=>$m[1],'email'=>'chef@example.test','password'=>'TestPassword-2026!']);
if($status!==302)throw new RuntimeException('Chef login failed');
$date=date('Y-m-d');
$none=getContext($date);
if($none['state']!=='none'||$none['batches']!==[])throw new RuntimeException('Absent workday incorrectly inferred');
$chefId=(int)$pdo->query("SELECT id FROM users WHERE email='chef@example.test'")->fetchColumn();
$recipeId=(int)$pdo->query('SELECT id FROM recipes ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO workdays (work_date,title,created_by_user_id) VALUES (?,?,?)')->execute([$date,'First CI Shift',$chefId]);
$day1=(int)$pdo->lastInsertId();
$one=getContext($date);
if($one['state']!=='single'||$one['day']['id']!==$day1||$one['batches']!==[])throw new RuntimeException('Single workday context incorrect');
$pdo->prepare('INSERT INTO dough_batches (batch_code,recipe_id,recorded_by_user_id) VALUES (?,?,?)')->execute(['CIWD14A',$recipeId,$chefId]);
$batch=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO workday_batch_events (workday_id,batch_id,activity,occurred_at,recorded_by_user_id) VALUES (?,?,?,?,?)')->execute([$day1,$batch,'balled',$date.' 10:00:00',$chefId]);
$linked=getContext($date);
if(count($linked['batches'])!==1||(int)$linked['batches'][0]['id']!==$batch||$linked['coaching']['batch_id']!==$batch)throw new RuntimeException('Only explicitly linked batches should appear');
$pdo->prepare('INSERT INTO evidence (recipe_id,batch_id,label) VALUES (?,?,?)')->execute([$recipeId,$batch,'CI unrated pizza']);
$pizza=(int)$pdo->lastInsertId();
$unlinked=getContext($date);
if($unlinked['pizzas']!==[])throw new RuntimeException('Unlinked pizza was inferred from batch');
$pdo->prepare('INSERT INTO workday_pizzas (evidence_id,workday_id,recorded_by_user_id) VALUES (?,?,?)')->execute([$pizza,$day1,$chefId]);
$scored=getContext($date);
if(count($scored['pizzas'])!==1||$scored['avg_crispness']!==null||$scored['rated_crispness']!==0)throw new RuntimeException('Missing pizza rating was treated as zero');
$pdo->prepare('INSERT INTO workdays (work_date,title,created_by_user_id) VALUES (?,?,?)')->execute([$date,'Second CI Shift',$chefId]);
$ambiguous=getContext($date);
if($ambiguous['state']!=='multiple'||$ambiguous['batches']!==[]||$ambiguous['coaching']['question']!==null)throw new RuntimeException('Ambiguous workdays were silently attributed');
$selected=getContext($date,$day1);
if($selected['state']!=='single'||$selected['day']['id']!==$day1)throw new RuntimeException('Explicit workday selector failed');
[$bad]=hitDay('api.php?action=chatWorkdayContext&date=not-a-day');
if($bad<400)throw new RuntimeException('Invalid date accepted');
echo "PASS: live HTTP MySQL none, single and ambiguous workdays; explicit links and unrated pizza\n";
