<?php
declare(strict_types=1);
$js=file_get_contents(__DIR__.'/../assets/pizza_tracker.js');
foreach(['cameraEpoch','epoch!==cameraEpoch','getVideoTracks()','track.onended','captureController?.abort()','new AbortController()',"api('upload',fd,controller.signal)","api('analyze',{evidence_id:id,kind:'live',media_id:upload.media_id},controller.signal)",'visibilitychange','Capture cancelled. If upload already completed','Microphone stopped. Tap Enable voice commands'] as $token)
 if(!str_contains($js,$token))throw new RuntimeException('Camera recovery regression: '.$token);
if(str_contains($js,"voice.onend=()=>{if(voiceEnabled&&stream"))throw new RuntimeException('Unbounded voice restart still enabled');
$parent=file_get_contents(__DIR__.'/../assets/agent_chat_camera.js');
if(!str_contains($parent,"type:'stop'")||!str_contains($parent,"frame.removeAttribute('src')"))throw new RuntimeException('Closing chat must stop and unload camera');
echo "PASS: camera lifecycle epoch, cancellation, mic repermission and evidence recovery warning\n";
