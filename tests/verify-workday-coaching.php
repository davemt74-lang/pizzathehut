<?php
declare(strict_types=1);
require_once __DIR__.'/../workday_context_lib.php';
function verify(bool $cond,string $reason):void{if(!$cond)throw new RuntimeException($reason);}
$d=['state'=>'single','batches'=>[['id'=>2,'batch_code'=>'PL-X2']],'pizzas'=>[],'rated_crispness'=>0];
$t=lab_chat_workday_tip($d);
verify($t['batch_id']===2&&str_contains($t['note'],'No pizza tests'),'no photos or samples inferred');
$d['pizzas']=[['id'=>10,'label'=>'Pizza']];$t=lab_chat_workday_tip($d);
verify(str_contains($t['note'],'small sample')&&$t['batch_id']===2,'small sample caveat');
$d['rated_crispness']=3;$t=lab_chat_workday_tip($d);
verify(str_contains($t['note'],'Compare outcomes'),'rated samples threshold');
$d['batches'][]=['id'=>3,'batch_code'=>'PL-X3'];$t=lab_chat_workday_tip($d);
verify($t['question']===null,'do not assign multiple batches');
$d['state']='multiple';verify(lab_chat_workday_tip($d)['batch_id']===null,'workday ambiguity remains');
$ai=file_get_contents(__DIR__.'/../batch_ai.php');$chat=file_get_contents(__DIR__.'/../agent_chat.php');
$js=file_get_contents(__DIR__.'/../assets/agent_chat_workday.js');
verify(str_contains($ai,"recorded_workday_context")&&str_contains($ai,"unset($"."workday['coaching'])"),'LLM context includes only limited evidence');
verify(str_contains($chat,'window.pizzaChatLocalWorkdayDate?.()')&&str_contains($js,'Ask agent'),'user initiated coaching');
verify(!str_contains($js,'batchCoachAsk'),'no automatic Claude request');
echo "PASS: opt-in workday coaching, missing samples, contextual prompt and ambiguity\n";
