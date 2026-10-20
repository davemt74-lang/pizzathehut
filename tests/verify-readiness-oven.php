<?php
declare(strict_types=1);
require __DIR__.'/../oven_lib.php';require __DIR__.'/../readiness_lib.php';
function ok(bool $condition,string $why):void {if(!$condition)throw new RuntimeException($why);}
$a=lab_oven_diameter(72,84);ok($a['largest_centered_circle_in']===72.0,'ellipse centered inscribed circle');
ok(lab_ellipse_inside(.5,.5),'ellipse center');ok(!lab_ellipse_inside(.98,.98),'ellipse edge exclusion');
$zones=lab_oven_zones([['key'=>'launch','name'=>'Launch','x'=>.4,'y'=>.5,'radius_in'=>8]]);ok(count($zones)===1,'heat zone');
try{lab_oven_zones([['key'=>'oops','name'=>'Outside','x'=>.96,'y'=>.99,'radius_in'=>8]]);throw new RuntimeException('Invalid zone accepted');}catch(InvalidArgumentException $e){}
$steps=json_encode([['stage'=>'cold_bulk','target_min_minutes'=>1440],['stage'=>'cold_ball','target_min_minutes'=>2880]]);
$batch=['balled_at'=>'2026-10-20 10:00:00','process_steps_snapshot'=>$steps];
$r=lab_readiness_assess($batch,[['dough_temp_f'=>38,'handling'=>'balanced']],strtotime('2026-10-22 10:00:00'));
ok($r['status']==='Near scheduled target'&&$r['evidence_confidence']==='limited','planned window not physical proofing verdict');
$unknown=lab_readiness_assess(['balled_at'=>null,'process_steps_snapshot'=>null],[]);ok($unknown['status']==='Insufficient observations'&&$unknown['evidence_confidence']==='very limited','missing evidence never assumed');
$schema=file_get_contents(__DIR__.'/../schema.sql');foreach(['oven_layouts','oven_zone_readings','dough_readiness_checks','pizza_tracker_boxes'] as $table)ok(str_contains($schema,'CREATE TABLE IF NOT EXISTS '.$table.' ('),'migration '.$table);
$api=file_get_contents(__DIR__.'/../api.php');ok(str_contains($api,"if (\$action==='saveTrackerBox')"),'bounded photo metadata endpoint');
$js=file_get_contents(__DIR__.'/../assets/pizza_tracker.js');ok(str_contains($js,"takePhoto()")&&str_contains($js,"voiceEnabled")&&str_contains($js,"startsWith(wake+' ')")&&!str_contains($js,"'pizza rotate'"),'voice photo command belongs to camera tracker');
$page=file_get_contents(__DIR__.'/../pizza_tracker.php');ok(str_contains($page,'Take photo + analyze')&&str_contains($page,'boxWidth'),'tracker photo UI and box correction');
echo "PASS: dough readiness, oven geometry, photo attribution and voice-command source contracts\n";
