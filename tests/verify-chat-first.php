<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$chat=file_get_contents($root.'/agent_chat.php');
$home=file_get_contents($root.'/index.php');
$voice=file_get_contents($root.'/assets/agent_chat_voice.js');
$report=file_get_contents($root.'/cooking_patterns.php');
$tests=[
 'chat-first root'=>str_contains($home,"header('Location: agent_chat.php')")&&str_contains($home,"workspace"),
 'accessible workspace'=>str_contains($chat,'index.php?workspace=1'),
 'sticky chat composer'=>str_contains($chat,'class="coach-inputbar"')&&str_contains($chat,'id="question"'),
 'footer microphone and camera'=>str_contains($chat,'id="chatMic"')&&str_contains($chat,'id="chatCamera"'),
 'camera opens protected tracker'=>str_contains($voice,"pizza_tracker.php"),
 'speech fallback'=>str_contains($voice,'SpeechRecognition')&&str_contains($voice,'recognition is unavailable'),
 'reply speech opt in'=>str_contains($voice,'voiceQuestion')&&str_contains($voice,'speechSynthesis'),
 'no automatic photo'=>!str_contains($voice,'getUserMedia'),
 'pattern report sample count'=>str_contains($report,"crispness_n")&&str_contains($report,"Limited comparable evidence"),
 'no reminder as action'=>str_contains(file_get_contents($root.'/cooking_patterns_lib.php'),"event_class")&&str_contains(file_get_contents($root.'/cooking_patterns_lib.php'),"observed")
];
foreach($tests as $name=>$ok){if(!$ok)throw new RuntimeException('FAIL: '.$name);}
echo "PASS: chat-first UX, safe voice and camera footer, observational reporting\n";
