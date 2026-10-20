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

// Add representative existing pizza and private media metadata before migrating.
// Only the disposable CI database can be used here.
if((getenv('PIZZA_TEST_DB_NAME')?:'pizzalab_ci')!=='pizzalab_ci')throw new RuntimeException('No existing media or pizza records may be rewritten outside the CI database');
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;dbname=pizzalab_ci;charset=utf8mb4',getenv('PIZZA_TEST_DB_USER')?:'pizza',getenv('PIZZA_TEST_DB_PASS')?:'pizza_ci',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$rid=(int)$pdo->query('SELECT id FROM recipes ORDER BY id LIMIT 1')->fetchColumn();
if($rid<1)throw new RuntimeException('Missing initial recipe');
$pdo->prepare('INSERT INTO evidence (recipe_id,label,notes,crispness_score) VALUES (?,?,?,?)')->execute([$rid,'pizza upgrade sentinel','keep this exact pizza evidence',7]);
$eid=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO media (evidence_id,kind,stored_name,mime,original_name) VALUES (?,?,?,?,?)')->execute([$eid,'scan','ci-sentinel.jpg','image/jpeg','old-kitchen-photo.jpg']);
$mid=(int)$pdo->lastInsertId();

[$status,$h,$body]=hit('upgrade.php');
if($status!==200||!str_contains($body,'name="backup_confirmed"')||!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$token))throw new RuntimeException('Upgrade preflight unavailable');
[$status,$h,$body]=hit('upgrade.php',['csrf'=>$token[1]]);
if(!str_contains($body,'Confirm a completed database'))throw new RuntimeException('Unchecked upgrade POST was not rejected');
[$status,$h,$body]=hit('upgrade.php',['csrf'=>$token[1],'backup_confirmed'=>'yes']);
if($status!==200||str_contains($body,'Upgrade stopped;'))throw new RuntimeException('Idempotent confirmed upgrade failed');
$post=$pdo->prepare('SELECT label,notes,crispness_score FROM evidence WHERE id=?');$post->execute([$eid]);$saved=$post->fetch(PDO::FETCH_ASSOC);
if(!$saved||$saved['label']!=='pizza upgrade sentinel'||$saved['notes']!=='keep this exact pizza evidence'||(int)$saved['crispness_score']!==7)throw new RuntimeException('Upgrade altered existing pizza evidence');
$photo=$pdo->prepare('SELECT original_name FROM media WHERE id=? AND evidence_id=?');$photo->execute([$mid,$eid]);
if($photo->fetchColumn()!=='old-kitchen-photo.jpg')throw new RuntimeException('Upgrade altered media ownership');
echo "PASS: admin login, explicit backup acknowledgement, repeatable upgrade, preserved pizza scores and media metadata\n";
