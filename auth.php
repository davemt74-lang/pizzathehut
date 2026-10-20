<?php
require __DIR__.'/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_GET['logout'])) {
    check_csrf(); $_SESSION=[]; session_regenerate_id(true); header('Location: auth.php'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    $email=strtolower(trim((string)($_POST['email']??'')));
    $row=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
    if ($row && (!isset($row['is_active']) || (int)$row['is_active']===1) && password_verify((string)($_POST['password']??''),$row['password_hash'])) {
        session_regenerate_id(true); $_SESSION['user_id']=(int)$row['id']; csrf(); header('Location: index.php'); exit;
    }
    $error='Invalid email or password.';
}

if (!empty($_SESSION['user_id'])) {
    $signed=query('SELECT * FROM users WHERE id=?',[(int)$_SESSION['user_id']])->fetch();
    if ($signed && (!isset($signed['is_active']) || (int)$signed['is_active']===1)) {header('Location: index.php');exit;}
    unset($_SESSION['user_id']);
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in — Pizza Lab</title><link rel="stylesheet" href="assets/style.css"></head><body class="center-screen"><main class="auth-box"><div class="brand-mark">◉</div><p class="overline">PIZZA LAB</p><h1>Welcome back.</h1><p class="muted">Sign in to your recipe and pizza evidence workspace.</p><?php if(!empty($error)):?><div class="danger"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf())?>"><label>Email<input type="email" name="email" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="primary">Sign in →</button></form></main></body></html>
