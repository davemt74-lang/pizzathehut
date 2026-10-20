<?php
declare(strict_types=1);
$s=file_get_contents(__DIR__.'/../upgrade.php');
foreach (["require_super_admin()","check_csrf()","backup_confirmed","hash_equals('yes'","type=\"checkbox\"","config.php and private uploads"] as $token)
    if (!str_contains($s,$token)) throw new RuntimeException('Missing upgrade protection: '.$token);
$check=strpos($s,"hash_equals('yes'");
$mutation=strpos($s,'db()->exec("ALTER TABLE users');
if($check===false||$mutation===false||$check>$mutation)throw new RuntimeException('Backup acknowledgement occurs after migration');
echo "PASS: admin-authenticated CSRF-protected opt-in upgrade preflight\n";
