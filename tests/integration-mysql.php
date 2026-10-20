<?php
declare(strict_types=1);
// Fresh-install smoke test. Must NEVER run against a production database.
$host=getenv('PIZZA_TEST_DB_HOST') ?: '127.0.0.1';
$name=getenv('PIZZA_TEST_DB_NAME') ?: 'pizzalab_ci';
$user=getenv('PIZZA_TEST_DB_USER') ?: 'pizza';
$pass=getenv('PIZZA_TEST_DB_PASS') ?: 'pizza_ci';
if($name!=='pizzalab_ci')throw new RuntimeException('Refusing to test a non-CI database');
$pdo=null;$dsn="mysql:host=$host;port=3306;dbname=$name;charset=utf8mb4";
for($i=0;$i<25;$i++){
 try{$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);break;}
 catch(PDOException $e){if($i===24)throw $e;sleep(1);}
}
$schema=file_get_contents(__DIR__.'/../schema.sql');
foreach([1,2] as $passNo){
 foreach(preg_split('/;\s*(?:\r?\n|$)/',$schema) as $sql){if(trim($sql)!=='')$pdo->exec($sql);}
}
$hash=password_hash('TestPassword-2026!',PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (display_name,email,password_hash,role,is_active) VALUES (?,?,?,'team_member',1)")->execute(['Kitchen Chef','chef@example.test',$hash]);
$pdo->prepare("INSERT INTO users (display_name,email,password_hash,role,is_active) VALUES (?,?,?,'super_admin',1)")->execute(['Lab Admin','admin@example.test',password_hash('AdminPassword-2026!',PASSWORD_DEFAULT)]);
require_once __DIR__.'/../recipe_templates.php';
pizza_lab_seed_recipes($pdo);
$checks=[
 'team login'=>(bool)$pdo->query("SELECT password_hash FROM users WHERE email='chef@example.test'")->fetchColumn(),
 'recipes'=>(int)$pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn()>=2,
 'workdays'=>(bool)$pdo->query('SHOW TABLES LIKE "workdays"')->fetchColumn(),
 'AI reviews'=>(bool)$pdo->query('SHOW TABLES LIKE "batch_ai_reviews"')->fetchColumn(),
 'pizza tracker'=>(bool)$pdo->query('SHOW TABLES LIKE "pizza_tracker_boxes"')->fetchColumn(),
 'cooking activity'=>(bool)$pdo->query('SHOW TABLES LIKE "oven_cook_activity"')->fetchColumn()
];
foreach($checks as $label=>$ok)if(!$ok)throw new RuntimeException('Database check failed: '.$label);
$config=__DIR__.'/../config.php';
if(file_exists($config))throw new RuntimeException('config.php already exists');
$conf=['db_host'=>$host,'db_port'=>3306,'db_name'=>$name,'db_user'=>$user,'db_pass'=>$pass,'secret'=>bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))];
file_put_contents($config,"<?php\nreturn ".var_export($conf,true).";\n",LOCK_EX);
chmod($config,0600);
echo "PASS: MySQL DDL replay, seeded recipes, team login roles and evidence tables\n";
