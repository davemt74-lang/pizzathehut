<?php
declare(strict_types=1);
require __DIR__.'/../workday_lib.php';
require __DIR__.'/../visual_compare_lib.php';
function verify(bool $ok,string $message):void {if(!$ok)throw new RuntimeException('FAIL: '.$message);}
verify(lab_valid_work_date('2026-10-20') && !lab_valid_work_date('2026-02-30') && !lab_valid_work_date('2026-1-01'),'validated dates');
verify(lab_workday_activity('balled')&&!lab_workday_activity('DELETE FROM workdays'),'activity whitelist');
$tests=[['crispness_score'=>null,'rim_rise_score'=>9,'bottom_color_score'=>5],['crispness_score'=>7,'rim_rise_score'=>null,'bottom_color_score'=>null],['crispness_score'=>9,'rim_rise_score'=>7,'bottom_color_score'=>null]];
$sum=lab_workday_summary($tests);
verify($sum['pizzas']===3&&$sum['rated_crispness']===2&&$sum['average_crispness']===8.0&&$sum['rated_bottom']===1&&$sum['average_bottom']===5.0,'optional evidence produces rated-only averages');
$snap=lab_visual_snapshot(['id'=>10,'label'=>'A','crispness_score'=>null,'notes'=>'none','secret_key'=>'redacted'],['id'=>11,'label'=>'B','stone_start_f'=>650],'bottom');
verify($snap['pizza_a']['crispness_score']===null&&!isset($snap['pizza_a']['secret_key'])&&$snap['pizza_b']['stone_start_f']===650,'preserve unknowns and whitelist snapshot');
verify(!lab_visual_angle('audio')&&lab_visual_angle('crumb'),'image angles');
$normalized=lab_visual_normalize(['summary'=>'A differs from B','confidence'=>'invented','visible_differences'=>['A has darker rim'],'hypotheses'=>['Maybe heat'],'next_controlled_test'=>'Try same oven position']);
verify($normalized['confidence']==='low'&&count($normalized['visible_differences'])===1,'normalized results');
$schema=file_get_contents(__DIR__.'/../schema.sql');$upgrade=file_get_contents(__DIR__.'/../upgrade.php');
foreach(['workdays','workday_batch_events','workday_pizzas','visual_comparisons'] as $name)verify(str_contains($schema,'CREATE TABLE IF NOT EXISTS '.$name)&&str_contains($upgrade,"'".$name."'"),'migration and install '.$name);
$ui=file_get_contents(__DIR__.'/../workdays.php');
verify(str_contains($ui,'workday_batch_events')&&str_contains($ui,'workday_pizzas')&&str_contains($ui,'check_csrf()'),'workday forms and auth');
verify(str_contains($ui,"$".'prior!==false')&&str_contains($ui,"$".'linked'),'avoid assigning to different workdays; require batch presence');
$compare=file_get_contents(__DIR__.'/../visual_compare.php');
verify(str_contains($compare,'media.php?id=')&&str_contains($compare,'batch_temperature_readings')&&str_contains($compare,'CURLOPT_TIMEOUT')&&str_contains($compare,'visual_comparisons'),'comparison with temperature context and authorized media');
verify(str_contains(file_get_contents(__DIR__.'/../batch_ai.php'),'explicit_workday_activity'),'AI includes workday evidence');
verify(str_contains(file_get_contents(__DIR__.'/../index.php'),'href="workdays.php"'),'navigation');
echo "PASS: workday chronology, rated-only metrics, visual context, migration and auth contracts\n";
