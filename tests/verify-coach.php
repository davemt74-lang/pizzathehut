<?php
declare(strict_types=1);
require __DIR__.'/../batch_insights_lib.php';
require __DIR__.'/../batch_ai.php';
$all=lab_insight_filters([]);
if($all['batch_id']!==null||$all['recipe_id']!==null)throw new RuntimeException('All-recipes scope failed');
$filter=lab_insight_filters(['batch_id'=>'7']);
[$where,$params]=lab_insight_conditions($filter);
if(!str_contains($where,'b.id=?')||$params!==[7])throw new RuntimeException('Batch scope is not parameterized');
try{lab_insight_filters(['batch_id'=>'7 OR 1=1']);throw new RuntimeException('Unsafe ID accepted');}catch(InvalidArgumentException $e){}
$a=lab_coach_normalize(['summary'=>'Observation','confidence'=>'high','measured_findings'=>['PL-A 7/10'],'evidence_references'=>['PL-A','#12']]);
if($a['summary']!=='Observation'||$a['evidence_references']!==['PL-A','#12'])throw new RuntimeException('Normalization failed');
if(lab_coach_normalize(['confidence'=>'unknown'])['confidence']!=='low')throw new RuntimeException('Confidence not normalized');
foreach(['api.php','schema.sql','upgrade.php','index.php','assets/app.js','agent_chat.php','batch_insights.php'] as $file)if(!is_file(__DIR__.'/../'.$file))throw new RuntimeException('Missing '.$file);
$api=file_get_contents(__DIR__.'/../api.php');
foreach(['batchCoachHistory','batchCoachAsk'] as $x)if(!str_contains($api,$x))throw new RuntimeException('Missing action '.$x);
$schema=file_get_contents(__DIR__.'/../schema.sql');
if(!str_contains($schema,'CREATE TABLE IF NOT EXISTS batch_ai_reviews')||!str_contains(file_get_contents(__DIR__.'/../upgrade.php'),'batch_ai_reviews'))throw new RuntimeException('Missing schema migration');
$ui=file_get_contents(__DIR__.'/../agent_chat.php');
foreach(['textContent','X-CSRF-Token','batchCoachAsk','batchCoachHistory'] as $x)if(!str_contains($ui,$x))throw new RuntimeException('Missing safe chat interface '.$x);
if(!str_contains(file_get_contents(__DIR__.'/../index.php'),'href="agent_chat.php"'))throw new RuntimeException('Agent Chat navigation missing');
if(!str_contains(file_get_contents(__DIR__.'/../assets/app.js'),'agent_chat.php?scope=batch'))throw new RuntimeException('Batch link missing');
echo "PASS: AI batch coach normalization, parameterized scope, source contracts and migration\n";
