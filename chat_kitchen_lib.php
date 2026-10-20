<?php
declare(strict_types=1);
/** Confirmed observations, never inferred measurements or an unassigned batch. */
function lab_kitchen_action_input(array $d):array {
    $type=(string)($d['action_type']??'');
    if(!in_array($type,['dough_temp','fridge_temp','batch_balled'],true))throw new InvalidArgumentException('Unsupported kitchen action.');
    $source=(string)($d['source']??'manual');
    if(!in_array($source,['manual','voice_command'],true))throw new InvalidArgumentException('Invalid source.');
    $clientId=(string)($d['client_event_id']??'');
    if(!preg_match('/^[A-Za-z0-9_-]{12,64}$/D',$clientId))throw new InvalidArgumentException('Invalid action identifier.');
    if(($d['confirmed']??null)!==true)throw new InvalidArgumentException('Confirm this action before recording it.');
    $raw=$d['batch_id']??null;
    $batch=($raw!==null&&$raw!=='')?filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]):null;
    if($batch===false||($type!=='fridge_temp'&&$batch===null))throw new InvalidArgumentException('Choose one dough batch.');
    if($type==='fridge_temp'&&$batch!==null)throw new InvalidArgumentException('Fridge ambient temperature is not dough temperature.');
    $temp=null;
    if($type!=='batch_balled'){
        $raw=$d['temperature_f']??null;
        if(!is_scalar($raw)||!preg_match('/^-?\d{1,3}(?:\.\d{1,2})?$/D',(string)$raw))throw new InvalidArgumentException('Enter an actual Fahrenheit reading.');
        $temp=(float)$raw;
        if($temp < -30||$temp>150)throw new InvalidArgumentException('Temperature outside supported range.');
    }
    $label=null;
    if($type==='fridge_temp'){
        $label=trim((string)($d['location_label']??''));
        if($label===''||strlen($label)>120||preg_match('/[<>\r\n]/',$label))throw new InvalidArgumentException('Enter a valid refrigerator name.');
    }
    return ['action_type'=>$type,'source'=>$source,'client_event_id'=>$clientId,'batch_id'=>$batch,'temperature_f'=>$temp,'location_label'=>$label];
}
function lab_kitchen_action_save(array $d):array {
    try{$v=lab_kitchen_action_input($d);}catch(InvalidArgumentException $e){fail($e->getMessage());}
    $uid=auth();$id=$v['client_event_id'];
    if($v['batch_id']!==null)row_owned('dough_batches',$v['batch_id']);
    $get=static fn()=>query('SELECT id,action_type,source,batch_id,measured_f,environment_label FROM chat_kitchen_actions WHERE user_id=? AND client_event_id=?',[$uid,$id])->fetch();
    $same=static fn(array $e):bool=>$e['action_type']===$v['action_type']&&$e['source']===$v['source']&&($e['batch_id']===null?null:(int)$e['batch_id'])===$v['batch_id']&&($e['measured_f']===null?null:(float)$e['measured_f'])===$v['temperature_f']&&($e['environment_label']??null)===$v['location_label'];
    if($previous=$get()){if(!$same($previous))fail('Action identifier conflicts with a different record.',409);return ['id'=>(int)$previous['id'],'existing'=>true];}
    $db=db();
    try{
        $db->beginTransaction();$now=date('Y-m-d H:i:s');
        query('INSERT INTO chat_kitchen_actions (user_id,client_event_id,action_type,source,batch_id,measured_f,environment_label,observed_at) VALUES (?,?,?,?,?,?,?,?)',[$uid,$id,$v['action_type'],$v['source'],$v['batch_id'],$v['temperature_f'],$v['location_label'],$now]);
        $actionId=(int)$db->lastInsertId();
        if($v['action_type']==='dough_temp'){
            query('INSERT INTO batch_temperature_readings (batch_id,container_id,measured_at,temperature_f,notes) VALUES (?,NULL,?,?,?)',[$v['batch_id'],$now,$v['temperature_f'],'Confirmed '.$v['source'].' action #'.$actionId]);
        }elseif($v['action_type']==='fridge_temp'){
            query('INSERT INTO kitchen_environment_readings (action_id,location_label,temperature_f,observed_at) VALUES (?,?,?,?)',[$actionId,$v['location_label'],$v['temperature_f'],$now]);
        }else{
            query('INSERT INTO dough_batch_events (batch_id,phase,occurred_at,note) VALUES (?,?,?,?)',[$v['batch_id'],'balled',$now,'Confirmed '.$v['source'].' action #'.$actionId]);
            query("UPDATE dough_batches SET balled_at=COALESCE(balled_at,?),stage=IF(stage='archived',stage,'balled') WHERE id=?",[$now,$v['batch_id']]);
        }
        $db->commit();return ['id'=>$actionId,'existing'=>false,'observed_at'=>$now];
    }catch(PDOException $e){
        if($db->inTransaction())$db->rollBack();
        if($e->getCode()==='23000'&&$previous=$get()){if(!$same($previous))fail('Action identifier conflict.',409);return ['id'=>(int)$previous['id'],'existing'=>true];}
        throw $e;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
