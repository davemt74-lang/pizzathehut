<?php
declare(strict_types=1);
require __DIR__.'/../heatmap_lib.php';
function check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException('HEATMAP FAIL: '.$msg);}
$e=lab_heatmap_event('rotate','75','642.5',.4,.5);
check($e['elapsed_seconds']===75 && $e['stone_f']===642.5,'valid measured event');
$unknown=lab_heatmap_event('pull_back','',null,.3,.4);
check($unknown['stone_f']===null&&$unknown['elapsed_seconds']===null,'unknown stays null');
foreach([['burn','',null,.4,.5],['launch',0,null,.98,.98],['launch',-1,645,.4,.5],['launch',12,1800,.4,.5]] as [$phase,$time,$temp,$x,$y]){try{lab_heatmap_event($phase,$time,$temp,$x,$y);throw new RuntimeException('Illegal event accepted.');}catch(InvalidArgumentException $okay){}}
$zones=[['key'=>'launch','name'=>'Launch','x'=>.3,'y'=>.4,'radius_in'=>11],['key'=>'finish','name'=>'Finish','x'=>.7,'y'=>.5,'radius_in'=>11]];
$readings=[['zone_key'=>'launch','stone_f'=>645,'observed_at'=>'2026-10-20 11:00:00'],['zone_key'=>'launch','stone_f'=>632,'observed_at'=>'2026-10-20 10:00:00']];
$cooks=[['session_id'=>1,'zone_key'=>'launch','crispness_score'=>8,'bottom_color_score'=>null,'rim_rise_score'=>7],['session_id'=>1,'zone_key'=>'launch','crispness_score'=>8,'bottom_color_score'=>null,'rim_rise_score'=>7],['session_id'=>1,'zone_key'=>'finish','crispness_score'=>8,'bottom_color_score'=>null,'rim_rise_score'=>7],['session_id'=>2,'zone_key'=>'finish','crispness_score'=>null,'bottom_color_score'=>6,'rim_rise_score'=>null]];
$out=lab_heatmap_summary($zones,$readings,$cooks);
check(count($out)===2,'zone count');
check($out[0]['pizzas']===1&&$out[0]['cook_event_count']===2,'deduplicate zones by pizza');
check($out[0]['reading_count']===2&&$out[0]['last_stone_f']===645.0,'measured latest temp only');
check($out[0]['color_avg']===null&&$out[0]['color_n']===0,'missing scores remain unknown');
check($out[1]['pizzas']===2&&$out[1]['crisp_n']===1&&$out[1]['color_n']===1,'score-specific sample counts');
check($out[1]['last_stone_f']===null,'unmeasured zone must not invent temp');
$schema=file_get_contents(__DIR__.'/../schema.sql');$upgrade=file_get_contents(__DIR__.'/../upgrade.php');
foreach(['oven_cook_sessions','oven_cook_events'] as $table){check(str_contains($schema,'CREATE TABLE IF NOT EXISTS '.$table)&&str_contains($upgrade,"'".$table."'"),'create-only schema upgrade '.$table);}
$page=file_get_contents(__DIR__.'/../heatmap.php');$coach=file_get_contents(__DIR__.'/../batch_ai.php');$js=file_get_contents(__DIR__.'/../assets/heatmap.js');
check(str_contains($page,'check_csrf()')&&str_contains($page,'auth()')&&str_contains($page,'lab_ellipse_inside')===false,'CSRF and authenticated safe action');
check(str_contains($page,'lab_heatmap_event')&&str_contains($page,'zone_name')&&str_contains($page,'recorded_by_user_id'),'server-side validated event history');
check(str_contains($coach,'actual_oven_cooking_positions')&&str_contains($coach,'Correlation is not causation'),'read-only agent context');
check(str_contains($js,'No interpolation')&&str_contains($js,'Not recorded'),'measured-only visual label');
check(str_contains(file_get_contents(__DIR__.'/../index.php'),'href="heatmap.php"'),'navigation entry');
echo "PASS: oven cooking paths, per-zone unique pizzas, unmeasured temperatures, AI context and schema\n";
