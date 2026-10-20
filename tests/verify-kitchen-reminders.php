<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/kitchen_reminder_lib.php';
function checkReminder(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
checkReminder(lab_reminder_milestone(0,90)===null,'Premature reminder');
checkReminder(lab_reminder_milestone(35999,90)===null,'Rotation too early');
checkReminder(lab_reminder_milestone(36000,90)==='rotation_reminder','Rotation threshold');
checkReminder(lab_reminder_milestone(67499,90)==='rotation_reminder','Pull-back too early');
checkReminder(lab_reminder_milestone(67500,90)==='pull_back_reminder','Pull-back threshold');
checkReminder(lab_reminder_milestone(90000,90)==='time_up_reminder','Time-up threshold');
checkReminder(lab_reminder_milestone(100000,90)==='time_up_reminder','Overdue reminder');
checkReminder(lab_reminder_milestone(50000,null)===null,'No implicit target');
checkReminder(lab_reminder_milestone(-1,90)===null,'Negative elapsed');
$lib=file_get_contents($root.'/kitchen_reminder_lib.php');
$schema=file_get_contents($root.'/schema.sql');
$api=file_get_contents($root.'/api.php');
$chat=file_get_contents($root.'/assets/agent_chat_timers.js');
foreach(['FOR UPDATE','beginTransaction','commit()','start_activity_id','agent_alert','reminder','INSERT IGNORE','kitchen_reminder_receipts'] as $s)checkReminder(str_contains($lib,$s),'Missing source guard '.$s);
checkReminder(str_contains($schema,'UNIQUE KEY uq_kitchen_reminder_run'),'Reminder dedupe constraint missing');
checkReminder(str_contains($schema,'PRIMARY KEY (reminder_id,user_id)'),'Per-user receipt dedupe missing');
checkReminder(str_contains($api,"action==='kitchenReminderPoll'"),'Missing authenticated POST handler');
checkReminder(str_contains($chat,'document.visibilityState'),'Foreground-only guard missing');
checkReminder(str_contains($chat,'Speak kitchen alerts:'),'Speech opt-in missing');
echo "PASS: v1.5.16B reminder milestone, privacy, dedupe and foreground source contracts\n";
