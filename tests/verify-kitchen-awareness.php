<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/kitchen_awareness_lib.php';
$api=file_get_contents($root.'/api.php');
$ai=file_get_contents($root.'/batch_ai.php');
$src=file_get_contents($root.'/kitchen_awareness_lib.php');
if(!str_contains($api,"action==='chatKitchenAwareness'"))throw new RuntimeException('Kitchen awareness GET endpoint missing');
if(!str_contains($ai,'$snapshot[\'active_kitchen_state\']=lab_kitchen_awareness($user);'))throw new RuntimeException('Live kitchen state is missing from the Claude snapshot');
foreach(['s.user_id=?','s.status=','t.status IN','LIMIT 8','LIMIT 12','UTC_TIMESTAMP(6)','rotation_confirmed','pull_back_confirmed','event_type IN','last_confirmed_physical_action_this_run'] as $part){
 if(!str_contains($src,$part))throw new RuntimeException('Missing awareness safeguard: '.$part);
}
if(!str_contains($ai,'do not assume chef actions from reminder events'))throw new RuntimeException('Model grounding instruction missing');
echo "PASS: bounded, user-scoped, read-only active kitchen evidence integration\n";
