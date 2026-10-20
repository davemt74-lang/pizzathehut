<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if (is_file(__DIR__.'/config.php')) { header('Location: index.php'); exit; }
$error='';
$required=['pdo_mysql','sodium','fileinfo','curl','json'];
$missing=array_values(array_filter($required,fn($e)=>!extension_loaded($e)));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if ($missing) throw new RuntimeException('Missing extensions: '.implode(', ',$missing));
        if (!is_writable(__DIR__)) throw new RuntimeException('Application directory is not writable: installer cannot safely create config.php.');
        if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Form expired. Refresh.');
        $host=trim($_POST['host']??'localhost'); $port=(int)($_POST['port']??3306);
        $name=trim($_POST['database']??'pizzalab'); $user=trim($_POST['dbuser']??''); $pass=(string)($_POST['dbpass']??'');
        $email=strtolower(trim($_POST['email']??'')); $admin=trim($_POST['admin']??''); $pwd=(string)($_POST['password']??'');
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/',$name) || $port<1 || $port>65535 || $host==='' || $user==='') throw new RuntimeException('Check the database fields.');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $admin==='' || strlen($pwd)<12) throw new RuntimeException('Enter a valid email, admin name, and a password of at least 12 characters.');
        $db=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        try { $db->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`','',$name).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); }
        catch (PDOException $e) { /* existing database with restricted CREATE privilege is fine */ }
        $db->exec('USE `'.$name.'`');
        $schema=file_get_contents(__DIR__.'/schema.sql');
        foreach (preg_split('/;\s*(?:\r?\n|$)/',$schema) as $stmt) { if (trim($stmt)!=='') $db->exec($stmt); }
        if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()>0) throw new RuntimeException('This database already has an admin. Use an empty database.');
        $stmt=$db->prepare("INSERT INTO users (display_name,email,password_hash,role,is_active) VALUES (?,?,?,'super_admin',1)");
        $stmt->execute([$admin,$email,password_hash($pwd,PASSWORD_DEFAULT)]);
        $adminId=(int)$db->lastInsertId();
        // Seed the two explicitly named formulas and their editable workflow templates.
        require_once __DIR__.'/recipe_templates.php';
        pizza_lab_seed_recipes($db);
        $config="<?php\nreturn ".var_export(['db_host'=>$host,'db_port'=>$port,'db_name'=>$name,'db_user'=>$user,'db_pass'=>$pass,'secret'=>bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))],true).";\n";
        if (file_put_contents(__DIR__.'/config.php',$config,LOCK_EX)===false) throw new RuntimeException('Could not write config.php. Check folder permissions.');
        @chmod(__DIR__.'/config.php',0600);
        $_SESSION['user_id']=$adminId; session_regenerate_id(true); csrf();
        header('Location: index.php'); exit;
    } catch (Throwable $e) { $error=$e->getMessage(); }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install Pizza Lab</title><link rel="stylesheet" href="assets/style.css"></head><body class="center-screen"><main class="auth-box"><div class="brand-mark">◉</div><p class="overline">PIZZA LAB · INITIAL SETUP</p><h1>Build your pizza evidence lab.</h1><p class="muted">Connect MySQL/MariaDB and create your first administrator. Run this installer once.</p><?php if($missing): ?><div class="danger">Server missing PHP extensions: <?=htmlspecialchars(implode(', ',$missing))?></div><?php endif; ?><?php if($error): ?><div class="danger"><?=htmlspecialchars($error)?></div><?php endif; ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf())?>"><h3>Database</h3><div class="two"><label>Host<input name="host" value="localhost" required></label><label>Port<input name="port" type="number" value="3306" required></label></div><label>Database<input name="database" value="pizzalab" required></label><div class="two"><label>Database user<input name="dbuser" required></label><label>Database password<input name="dbpass" type="password"></label></div><h3>First administrator</h3><label>Name<input name="admin" required></label><label>Email<input name="email" type="email" required></label><label>Password (12+ characters)<input name="password" type="password" minlength="12" required></label><button class="primary" type="submit" <?= $missing?'disabled':'' ?>>Install Pizza Lab →</button></form><small>Use HTTPS for iPhone camera and microphone permissions. Requires PHP 8.2+, PDO MySQL, cURL, Sodium, and fileinfo.</small></main></body></html>
