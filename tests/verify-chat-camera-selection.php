<?php
$chat=file_get_contents(__DIR__.'/../agent_chat.php');$camera=file_get_contents(__DIR__.'/../assets/agent_chat_camera.js');$tracker=file_get_contents(__DIR__.'/../pizza_tracker.php');
foreach(['$batchEvidence=query(',"FROM evidence WHERE batch_id IS NOT NULL",'PIZZA_CHAT_BATCH_EVIDENCE'] as $x)if(!str_contains($chat,$x))throw new RuntimeException('Missing scoped batch lookup: '.$x);
foreach(['matches.length===1',"url.searchParams.set('evidence_id'",'String(e.batch_id)===String(target.value)'] as $x)if(!str_contains($camera,$x))throw new RuntimeException('Missing unique selection: '.$x);
foreach(['ctype_digit($requested)','in_array((int)$requested','count($records)===1'] as $x)if(!str_contains($tracker,$x))throw new RuntimeException('Ambiguous photo evidence: '.$x);
echo "PASS: explicit or unambiguous photo record selection\n";
