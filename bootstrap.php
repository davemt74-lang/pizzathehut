<?php
declare(strict_types=1);
const APP_ROOT = __DIR__;
const DATA_ROOT = __DIR__ . '/private';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
if (!is_file(APP_ROOT . '/config.php')) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || basename($_SERVER['SCRIPT_NAME'] ?? '') === 'api.php') {
            http_response_code(503); header('Content-Type: application/json'); echo json_encode(['error'=>'Run install.php first']); exit;
        }
        header('Location: install.php'); exit;
    }
} else {
    $cfg = require APP_ROOT . '/config.php';
    if (!is_array($cfg)) { http_response_code(500); exit('Invalid configuration'); }
}
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('pizzalab_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function db(): PDO {
    static $db = null;
    if ($db) return $db;
    global $cfg;
    try {
        $db = new PDO('mysql:host='.$cfg['db_host'].';port='.$cfg['db_port'].';dbname='.$cfg['db_name'].';charset=utf8mb4', $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    } catch (Throwable $e) { throw new RuntimeException('Database connection unavailable.'); }
    return $db;
}
function query(string $sql, array $args=[]): PDOStatement { $q=db()->prepare($sql); $q->execute($args); return $q; }
function auth_user(): array {
    $id=(int)($_SESSION['user_id']??0);
    if ($id<1) fail('Sign in required',401);
    $u=query('SELECT * FROM users WHERE id=?',[$id])->fetch();
    if (!$u || (array_key_exists('is_active',$u) && (int)$u['is_active']!==1)) {
        unset($_SESSION['user_id']);fail('Account inactive or session expired. Sign in again.',401);
    }
    return $u;
}
function auth(): int {return (int)auth_user()['id'];}
function is_super_admin(): bool {return (auth_user()['role']??'')==='super_admin';}
function require_super_admin(): void {if(!is_super_admin())fail('Super Admin permissions required.',403);}

function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function check_csrf(): void { if (!hash_equals(csrf(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '')))) fail('Security token expired. Refresh the page.',419); }
function json_out(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function fail(string $message, int $status=400): never { json_out(['ok'=>false,'error'=>$message], $status); }
function posted(): array { $s=file_get_contents('php://input'); if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) { $v=json_decode($s, true); if (!is_array($v)) fail('Invalid JSON'); return $v; } return $_POST; }
function number(mixed $x, float $min, float $max, string $label): float { if (!is_numeric($x) || !is_finite((float)$x) || $x<$min || $x>$max) fail("Invalid $label"); return (float)$x; }
function plain(mixed $x, int $max=1000): string { return mb_cut(trim((string)($x ?? '')), $max); }
function mb_cut(string $value, int $limit): string { return function_exists('mb_substr') ? mb_substr($value,0,$limit) : substr($value,0,$limit); }
function encrypted_key(string $value): string { global $cfg; $key=hex2bin($cfg['secret']); $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); return base64_encode($nonce.sodium_crypto_secretbox($value,$nonce,$key)); }
function decrypted_key(string $value): string { global $cfg; if ($value==='') return ''; $all=base64_decode($value,true); if ($all===false) return ''; $nonce=substr($all,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); $cipher=substr($all,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); return sodium_crypto_secretbox_open($cipher,$nonce,hex2bin($cfg['secret'])) ?: ''; }
function setting(string $key, string $default=''): string { $v=query('SELECT value FROM settings WHERE name=?',[$key])->fetchColumn(); return $v===false ? $default : (string)$v; }
function change_setting(string $key,string $value): void { query('INSERT INTO settings (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); }
function row_owned(string $table,int $id): array { if (!in_array($table,['recipes','evidence','dough_batches'],true)) fail('Invalid entity'); $row=query("SELECT * FROM $table WHERE id=?",[$id])->fetch(); if (!$row) fail('Not found',404); return $row; }
