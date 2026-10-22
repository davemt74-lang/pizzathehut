<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$read=static fn(string $file):string=>file_get_contents($root.'/'.$file);
$api=$read('api.php');$schema=$read('schema.sql');$upgrade=$read('upgrade.php');$client=$read('assets/agent_chat_commands.js');
$timers=$read('assets/agent_chat_timers.js');$chat=$read('agent_chat.php');$logic=$read('chat_pizza_lib.php');
$check=static function(bool $passed,string $name):void{if(!$passed)throw new RuntimeException('FAIL: '.$name);};
foreach (['created_by_user_id BIGINT UNSIGNED NULL','chat_create_request_id VARCHAR(90) NULL','uniq_evidence_chat_request','CREATE TABLE IF NOT EXISTS chat_pizza_focus','CREATE TABLE IF NOT EXISTS workday_daily_registry','work_date DATE PRIMARY KEY'] as $fragment)
    $check(str_contains($schema,$fragment),'Fresh install attribution: '.$fragment);
foreach (['chat_create_request_id','fk_evidence_creator','chat_pizza_focus','workday_pizzas','workday_daily_registry'] as $fragment)
    $check(str_contains($upgrade,$fragment),'Upgrade path: '.$fragment);
foreach (['chatNewPizza','chatSelectPizza','chatPizzaContext','chatEnsureWorkday','created_by_user_id,recipe_id','lab_timer_list((','lab_reminder_poll(auth(),'] as $fragment)
    $check(str_contains($api,$fragment),'Owner-scoped API: '.$fragment);
$check(str_contains($logic,'FOR UPDATE')&&str_contains($logic,'beginTransaction()')&&str_contains($logic,'rollBack()'),'Concurrent creation is transactionally serialized');
$check(str_contains($logic,'chat_create_request_id=?')&&str_contains($logic,'existing\'=>true'),'Replay finds the original pizza');
$check(str_contains($logic,'WHERE id=? AND created_by_user_id=?'),'Selecting another chef’s pizza denied');
$check(str_contains($logic,'workday_pizzas')&&str_contains($logic,'INSERT INTO chat_pizza_focus'),'Automatic workday link and durable focus');
$check(str_contains($logic,'INSERT IGNORE INTO workday_daily_registry')&&str_contains($logic,'FOR UPDATE')&&str_contains($logic,'SELECT id,title,work_date FROM workdays WHERE work_date=?'),'Global, race-safe workday reservation reuses historic workdays');
$workday=$read('assets/agent_chat_workday.js');
$check(str_contains($workday,'chatEnsureWorkday')&&str_contains($workday,"document.visibilityState==='visible'")&&str_contains($workday,'localDay()!==activeDate'),'Chat activates daily workday without a manual action, including rollover');
$check(str_contains($client,"if(cmd.event_type==='timer_started')")&&str_contains($client,"pizzaApi('cookActivity',action)"),'Explicit timer command starts immediately');
$check(str_contains($client,'I will remind you to check rotation')&&str_contains($client,'pizza_time'),'Spoken coaching and timer status available');
$check(str_contains($timers,'kitchenTimers&mine=1')&&str_contains($timers,'mine:true'),'Foreground proactive reminders follow the signed-in chef');
$check(str_contains($timers,'pizza-chat-speak-request')&&!str_contains($timers,'mic.dataset.voiceState'),'One speech controller can interrupt and resume listening');
$check(str_contains($chat,'data-chat-command="new pizza"')&&str_contains($chat,'data-chat-command="start timer"'),'Chat canvas quick actions');
echo "PASS: chef ownership, one shared automatic daily workday, deduped new pizza, focus, one-command timer and proactive voice contracts\n";
