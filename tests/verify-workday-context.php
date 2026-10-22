<?php
declare(strict_types=1);
require_once __DIR__.'/../workday_context_lib.php';
function check(bool $x,string $what):void{if(!$x)throw new RuntimeException($what);}
check(lab_chat_workday_view([],[],[])['state']==='none','empty day');
$days=[['id'=>3,'work_date'=>'2026-10-20','title'=>'Kitchen']];
$event=[['batch_id'=>7,'batch_code'=>'B-7','recipe_name'=>'House','activity'=>'balled'],['batch_id'=>7,'batch_code'=>'B-7','recipe_name'=>'House','activity'=>'baked']];
$a=lab_chat_workday_view($days,$event,[['id'=>2,'label'=>'Pizza','crispness_score'=>null]]);
check($a['state']==='single'&&count($a['batches'])===1&&$a['rated_crispness']===0&&$a['avg_crispness']===null,'only linked batches, missing ratings remain null');
$b=lab_chat_workday_view([...$days,['id'=>4,'work_date'=>'2026-10-20','title'=>'Other']],$event,[]);
check($b['state']==='multiple'&&$b['batches']===[],'ambiguous day cannot silently assign a batch');
$api=file_get_contents(__DIR__.'/../api.php');$ui=file_get_contents(__DIR__.'/../agent_chat.php');$script=file_get_contents(__DIR__.'/../assets/agent_chat_workday.js');
check(str_contains($api,"$"."action==='chatWorkdayContext'"),'GET action');
check(str_contains($ui,'id="chatWorkdayContext"')&&str_contains($script,'chatEnsureWorkday'),'automatic shared daily activation from Chat');
check(!str_contains($script,"action:'chatCreateWorkday'"),'no duplicate legacy workday submission');
echo "PASS: automatically activated shared current workday, historical ambiguity and missing evidence\n";
