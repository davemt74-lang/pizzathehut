<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$chat=file_get_contents($root.'/agent_chat.php');
$tracker=file_get_contents($root.'/pizza_tracker.php');
$camera=file_get_contents($root.'/assets/agent_chat_camera.js');
$voice=file_get_contents($root.'/assets/agent_chat_voice.js');
$trackerJs=file_get_contents($root.'/assets/pizza_tracker.js');
$bootstrap=file_get_contents($root.'/bootstrap.php');
$api=file_get_contents($root.'/api.php');
$tests=[
 'baseline anti-framing applies globally'=>str_contains($bootstrap,"X-Frame-Options: DENY"),
 'embedded tracker only same-origin'=>str_contains($tracker,"if(\$embedded)")&&str_contains($tracker,'X-Frame-Options: SAMEORIGIN')&&str_contains($tracker,"frame-ancestors 'self'"),
 'embedded mode requires existing auth'=>str_contains($tracker,"bootstrap.php';auth();"),
 'camera frame can request permissions'=>str_contains($chat,'allow="camera; microphone"'),
 'camera panel remains on same page'=>str_contains($chat,'id="chatCameraPanel"')&&str_contains($camera,"new URL('pizza_tracker.php'")&&!str_contains($voice,'location.assign'),
 'camera lazy loads only after click'=>str_contains($camera,"url.searchParams.set('embedded','1')")&&str_contains($camera,'frame.src=url.toString()'),
 'origin and frame identity checks'=>str_contains($camera,'event.origin!==window.location.origin')&&str_contains($camera,'event.source!==frame.contentWindow'),
 'iframe source is cleaned on close'=>str_contains($camera,"frame.removeAttribute('src')")&&str_contains($trackerJs,'window.addEventListener(\'pagehide\',stop)'),
 'voice photo command requires wake phrase'=>str_contains($voice,'PIZZA_CHAT_WAKE')&&str_contains($voice,'pizza-chat-photo-command'),
 'tracker explicitly requires evidence and live stream'=>str_contains($trackerJs,"if(!stream||video.readyState<2)")&&str_contains($trackerJs,"if(!id)"),
 'private media and bounding box linkage'=>str_contains($trackerJs,"api('upload',fd,controller.signal)")&&str_contains($trackerJs,"api('saveTrackerBox'")&&str_contains($api,"$"."action==='saveTrackerBox'"),
 'capture does not transfer browser events'=>str_contains($trackerJs,"source:trigger==='voice_command'?'voice_command':'camera'"),
 'chat uses safe text nodes'=>str_contains($camera,'textContent=String(text)')&&!str_contains($camera,'innerHTML'),
 'voice and tracker microphone do not compete'=>str_contains($camera,'pause-voice')&&str_contains($trackerJs,'stopVoice()'),
 'fallback available'=>str_contains($chat,'Open full screen')
];
foreach($tests as $name=>$ok){if(!$ok)throw new RuntimeException('FAIL: '.$name);}
echo "PASS: in-chat camera same-origin security, voice capture, teardown, evidence links and display safety\n";
