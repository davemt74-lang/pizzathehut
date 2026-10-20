<?php
declare(strict_types=1);
$a=file_get_contents(__DIR__.'/../api.php');$js=file_get_contents(__DIR__.'/../assets/pizza_tracker.js');$parent=file_get_contents(__DIR__.'/../assets/agent_chat_camera.js');
foreach(["$"."requestedMedia=$"."d['media_id']??null", "AND kind='scan' AND id=?", 'Selected scan does not belong to this pizza.'] as $part)
 if(!str_contains($a,$part))throw new RuntimeException('Missing exact scan ownership check: '.$part);
if(!str_contains($js,'media_id:upload.media_id')||!str_contains($js,"type:'capture-error'"))throw new RuntimeException('Missing fixed media binding or error reporting');
if(!str_contains($parent,"data.type==='capture-error'"))throw new RuntimeException('Chat is missing failed capture acknowledgement');
echo "PASS: Claude targets exact authorized scan, chat reports failed capture\n";
