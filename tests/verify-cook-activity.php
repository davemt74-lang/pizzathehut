<?php
declare(strict_types=1);
require __DIR__.'/../cook_activity_lib.php';
function ensure(bool $x,string $msg): void {if(!$x)throw new RuntimeException('COOK ACTIVITY: '.$msg);}
$start=lab_cook_activity('timer_started','voice_command',0,null);
ensure($start['event_class']==='observed'&&$start['elapsed_seconds']===0,'voice start saved as actual command');
$cue=lab_cook_activity('rotation_reminder','agent_alert',120,null);
ensure($cue['event_class']==='reminder'&&$cue['media_id']===null,'rotation reminder not confirmed rotation');
$alert=lab_cook_activity('visual_turn_suggestion','ai_suggestion',null,null);
ensure($alert['event_class']==='suggestion'&&$alert['elapsed_seconds']===null,'visual suggestion has unknown elapsed');
$photo=lab_cook_activity('photo_captured','camera','',12);
ensure($photo['media_id']===12&&$photo['elapsed_seconds']===null,'photo saves media, unknown elapsed');
ensure(lab_cook_activity('photo_captured','voice_command',null,12)['source']==='voice_command','spoken photo capture is attributable to voice command');
foreach ([['rotate','agent_alert',60,null],['rotation_confirmed','agent_alert',60,null],['photo_captured','camera',60,null],['timer_started','voice_command',-1,null],['timer_stopped','manual',7201,null],['time_left_reminder','manual',60,null],['timer_stopped','camera',30,12]] as [$e,$s,$t,$m]) {
    try{lab_cook_activity($e,$s,$t,$m);throw new RuntimeException('Illegal event accepted: '.$e);}
    catch(InvalidArgumentException $expected){}
}
$schema=file_get_contents(__DIR__.'/../schema.sql');$upgrade=file_get_contents(__DIR__.'/../upgrade.php');$api=file_get_contents(__DIR__.'/../api.php');$js=file_get_contents(__DIR__.'/../assets/app.js');$tracker=file_get_contents(__DIR__.'/../assets/pizza_tracker.js');$heat=file_get_contents(__DIR__.'/../heatmap.php');
ensure(str_contains($schema,'UNIQUE KEY uniq_activity_client')&&str_contains($schema,'CREATE TABLE IF NOT EXISTS oven_cook_activity'),'idempotent schema');
ensure(str_contains($upgrade,'oven_cook_activity')&&str_contains($api,"$"."action==='cookActivity'"),'upgrader and API');
ensure(str_contains($api,"'kind']!=='scan'")&&str_contains($api,'client_event_id'),'image belongs to pizza and idempotence');
ensure(str_contains($js,"'rotation_reminder'")&&str_contains($js,"'rotation_confirmed'")&&str_contains($js,"'timer_started'")&&str_contains($js,"'timer_stopped'"),'typed timer events and actual action');
ensure(str_contains($tracker,"'voice_command'?'voice_command':'camera'"),'voice capture attribution');
ensure(str_contains($tracker,"'photo_captured'")&&str_contains($tracker,"'analysis_complete'")&&str_contains($tracker,"'visual_turn_suggestion'"),'camera and AI activity');
ensure(str_contains($heat,'lab_cook_label')&&str_contains($heat,'oven_cook_activity'),'heatmap activity history');
echo "PASS: cooking event classes, media linkage, idempotency, audit timeline and voice separation\n";
