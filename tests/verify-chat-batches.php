<?php
declare(strict_types=1);
require __DIR__.'/../chat_batch_guide_lib.php';
function checkBatch(bool $condition,string $why):void{if(!$condition)throw new RuntimeException($why);}
$tokens=['chat_batch_sessions','chat_batch_step_actions','uq_chat_batch_request','uq_chat_step_once'];
$schema=file_get_contents(__DIR__.'/../schema.sql');
foreach($tokens as $token)checkBatch(str_contains($schema,$token),'Missing database contract '.$token);
$api=file_get_contents(__DIR__.'/../api.php');
foreach(["action==='chatBatchPlan'","action==='chatBatchStart'","action==='chatBatchStep'","action==='chatBatchSessions'","action==='chatBatchSession'"] as $line)checkBatch(str_contains($api,$line),'Missing API '.$line);
$lib=file_get_contents(__DIR__.'/../chat_batch_guide_lib.php');
foreach(['FOR UPDATE','beginTransaction()','rollBack()','hash_equals','recipe_snapshot','process_steps_snapshot','confirmed','client_request_id','client_event_id'] as $key)checkBatch(str_contains($lib,$key),'Missing safeguard '.$key);
$ui=file_get_contents(__DIR__.'/../assets/agent_chat_batch.js');
checkBatch(str_contains($ui,'No batch created.')&&str_contains($ui,'Confirm physical step')&&str_contains($ui,'window.pizzaChatBatchCommand'),'Unsafe UX flow');
echo "PASS: batch conversation source, schema, owner and explicit-confirmation contracts\n";
