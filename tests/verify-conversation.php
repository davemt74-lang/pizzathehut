<?php
$src=file_get_contents(__DIR__.'/../batch_ai.php');$ui=file_get_contents(__DIR__.'/../agent_chat.php');
$test=static function(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);};
$test(str_contains($src,'ORDER BY id DESC LIMIT 24'),'bounded history');
$test(str_contains($src,'array_slice($history,-6)'),'six prior turns');
$test(str_contains($src,'INTERVAL 5 SECOND')&&str_contains($src,'INTERVAL 1 HOUR'),'rate and cost budget');
$test(str_contains($src,'created_by_user_id=? AND scope=? AND target_id <=> ?'),'user-scoped history');
$test(str_contains($ui,'coach-user')&&str_contains($ui,'Evidence and reasoning'),'minimal bubbles');
echo "PASS: persisted context and simple bubbles\n";
