<?php
declare(strict_types=1);
require __DIR__.'/../chat_kitchen_lib.php';
$x=lab_kitchen_action_input(['confirmed'=>true,'client_event_id'=>'unique_action_1234567','source'=>'voice_command','batch_id'=>'9','action_type'=>'dough_temp','temperature_f'=>'68.5']);
if($x['batch_id']!==9||$x['temperature_f']!==68.5)throw new RuntimeException('Dough parsing');
$base=['confirmed'=>true,'client_event_id'=>'unique_action_1234567','source'=>'manual','batch_id'=>'9','action_type'=>'dough_temp','temperature_f'=>'68'];
foreach([['confirmed'=>false],['client_event_id'=>'bad'],['temperature_f'=>'not measured'],['temperature_f'=>'900'],['batch_id'=>null],['source'=>'agent_alert']] as $patch){
 try{lab_kitchen_action_input(array_replace($base,$patch));throw new RuntimeException('Accepted invalid input: '.json_encode($patch));}catch(InvalidArgumentException $expected){}
}
$f=lab_kitchen_action_input(['confirmed'=>true,'client_event_id'=>'unique_action_1234567','source'=>'manual','action_type'=>'fridge_temp','temperature_f'=>'38','location_label'=>'Main refrigerator']);
if($f['batch_id']!==null||$f['location_label']!=='Main refrigerator')throw new RuntimeException('Fridge mislabeled as dough');
$s=file_get_contents(__DIR__.'/../api.php');
if(!str_contains($s,"\$action==='chatKitchenAction'"))throw new RuntimeException('Missing action route');
echo "PASS: confirmed, typed, attributable kitchen action contracts\n";
