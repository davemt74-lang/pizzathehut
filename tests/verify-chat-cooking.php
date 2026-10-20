<?php
declare(strict_types=1);
require __DIR__.'/../cook_activity_lib.php';
foreach(['rotation_confirmed','pull_back_confirmed','pizza_finished','timer_started','timer_stopped'] as $action){
 $x=lab_cook_activity($action,'voice_command',null,null);
 if($x['event_class']!=='observed'||$x['source']!=='voice_command')throw new RuntimeException('Confirmed voice act invalid');
}
foreach(['rotation_reminder','pull_back_reminder','visual_turn_suggestion'] as $act){
 try{lab_cook_activity($act,'voice_command',null,null);throw new RuntimeException('Voice reminder incorrectly credited as performed action');}
 catch(InvalidArgumentException $good){}
}
$code=file_get_contents(__DIR__.'/../assets/agent_chat_commands.js');
if(!str_contains($code,'PIZZA_CHAT_PIZZA_IDS')||!str_contains($code,"action:'cookActivity'"))throw new RuntimeException('Missing explicit pizza evidence guard');
echo "PASS: voice-confirmed physical acts, timer events and reminder isolation\n";
