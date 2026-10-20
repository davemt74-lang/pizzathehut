<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$lib=file_get_contents($root.'/kitchen_timer_lib.php');
$api=file_get_contents($root.'/api.php');
$schema=file_get_contents($root.'/schema.sql');
$upgrade=file_get_contents($root.'/upgrade.php');
$ui=file_get_contents($root.'/assets/agent_chat_timers.js');
function ensure(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
require $root.'/kitchen_timer_lib.php';
ensure(lab_timer_event('timer_started')&&lab_timer_event('timer_reset')&&!lab_timer_event('rotation_reminder'),'Timer event allowlist');
foreach(['FOR UPDATE','beginTransaction','rollBack','client_event_id','revision=revision+1','UTC_TIMESTAMP(6)','TIMESTAMPDIFF(MICROSECOND'] as $token)ensure(str_contains($lib,$token),'Missing timer guard '.$token);
ensure(str_contains($api,"action==='kitchenTimers'")&&str_contains($api,'lab_timer_activity_save'),'Timer API integration missing');
ensure(str_contains($schema,'CREATE TABLE IF NOT EXISTS kitchen_activity_timers'),'Fresh-install timer schema missing');
ensure(str_contains($upgrade,'kitchen_activity_timers ready'),'Timer upgrade missing');
ensure(str_contains($ui,'pizza-timers-updated')&&str_contains($ui,'visibilitychange'),'Chat timer recovery missing');
echo "PASS: v1.5.16A kitchen timer source contracts\n";
