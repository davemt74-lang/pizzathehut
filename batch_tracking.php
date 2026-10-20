<?php
/** Authenticated helpers for recipe process templates and evidence-backed batch tracking. */
declare(strict_types=1);
function lab_datetime(mixed $value, string $label, bool $useNow=false):?string {
    $v=trim((string)($value??''));if($v==='')return $useNow?date('Y-m-d H:i:s'):null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$v);
    if(!$date||$date->format('Y-m-d\TH:i')!==$v) fail('Invalid '.$label.' date/time');
    return $date->format('Y-m-d H:i:s');
}
function lab_optional(array $d,string $key,float $min,float $max):?float {
    return !isset($d[$key])||$d[$key]===''?null:number($d[$key],$min,$max,$key);
}
function lab_owned_container(int $batchId, int $containerId): void {
    if(!$containerId)return;
    if(!query('SELECT id FROM batch_containers WHERE batch_id=? AND id=?',[$batchId,$containerId])->fetch()) fail('Container does not belong to this batch');
}
function lab_save_recipe_step(array $d): void {
    $recipeId=(int)number($d['recipe_id']??null,1,2147483647,'recipe');row_owned('recipes',$recipeId);
    $key=plain($d['step_key']??'',50);
    if($key==='')$key='step_'.strtolower(bin2hex(random_bytes(4)));
    if(!preg_match('/^[a-z0-9_]{3,50}$/',$key)) fail('Invalid step code');
    $stage=plain($d['stage']??'',24);
    if(!in_array($stage,['mix','rest','cold_bulk','ball','cold_ball','bake','other'],true)) fail('Invalid step stage');
    $title=plain($d['title']??'',160);if($title==='')fail('Step title required');
    $instruction=plain($d['instructions']??'',5000);
    $order=lab_optional($d,'sort_order',0,10000);
    if($order===null||floor($order)!==$order) fail('Sort order must be a whole number');
    $start=lab_optional($d,'target_start_min',0,525600);$min=lab_optional($d,'target_min_minutes',0,525600);$max=lab_optional($d,'target_max_minutes',0,525600);
    if($max!==null&&$min!==null&&$max<$min)fail('Maximum duration cannot be less than minimum');
    query('INSERT INTO recipe_steps (recipe_id,step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order),stage=VALUES(stage),title=VALUES(title),instructions=VALUES(instructions),target_start_min=VALUES(target_start_min),target_min_minutes=VALUES(target_min_minutes),target_max_minutes=VALUES(target_max_minutes)',[$recipeId,$key,(int)$order,$stage,$title,$instruction,$start,$min,$max]);
}
function lab_move_recipe_step(array $d): void {
    $recipeId=(int)number($d['recipe_id']??null,1,2147483647,'recipe');row_owned('recipes',$recipeId);
    $key=plain($d['step_key']??'',50);
    $direction=plain($d['direction']??'',8);
    if(!in_array($direction,['up','down'],true))fail('Invalid move direction');
    $db=db();$db->beginTransaction();
    try {
        $get=$db->prepare('SELECT step_key FROM recipe_steps WHERE recipe_id=? ORDER BY sort_order,id FOR UPDATE');
        $get->execute([$recipeId]);$keys=$get->fetchAll(PDO::FETCH_COLUMN);
        $pos=array_search($key,$keys,true);
        if($pos===false)fail('Recipe step does not exist');
        $other=$pos+($direction==='up'?-1:1);
        if($other<0||$other>=count($keys))fail('Cannot move past first or last step');
        [$keys[$pos],$keys[$other]]=[$keys[$other],$keys[$pos]];
        $put=$db->prepare('UPDATE recipe_steps SET sort_order=? WHERE recipe_id=? AND step_key=?');
        foreach($keys as $i=>$stepKey)$put->execute([($i+1)*10,$recipeId,$stepKey]);
        $db->commit();
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function lab_delete_recipe_step(array $d):void {
    $recipeId=(int)number($d['recipe_id']??null,1,2147483647,'recipe');row_owned('recipes',$recipeId);
    $key=plain($d['step_key']??'',50);if($key==='')fail('Step code required');
    query('DELETE FROM recipe_steps WHERE recipe_id=? AND step_key=?',[$recipeId,$key]);
}
function lab_save_batch_step(array $d):void {
    $id=(int)number($d['batch_id']??null,1,2147483647,'batch');$batch=row_owned('dough_batches',$id);
    $key=plain($d['step_key']??'',50);
    $steps=json_decode($batch['process_steps_snapshot']??'null',true);
    if(!is_array($steps)||!array_filter($steps,fn($x)=>($x['step_key']??'')===$key))fail('Step not in the batch’s frozen recipe plan');
    $start=lab_datetime($d['started_at']??null,'start');$end=lab_datetime($d['finished_at']??null,'finish');
    if($start!==null&&$end!==null&&$end<$start)fail('Step finish cannot precede its start');
    $amount=lab_optional($d,'actual_amount_g',0,100000);$temp=lab_optional($d,'actual_temp_f',-30,150);$notes=plain($d['notes']??'',5000);
    if(!$start&&!$end&&$amount===null&&$temp===null&&$notes==='')fail('Record at least one actual value for this step');
    query('INSERT INTO batch_step_logs (batch_id,step_key,started_at,finished_at,actual_amount_g,actual_temp_f,notes) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE started_at=VALUES(started_at),finished_at=VALUES(finished_at),actual_amount_g=VALUES(actual_amount_g),actual_temp_f=VALUES(actual_temp_f),notes=VALUES(notes)',[$id,$key,$start,$end,$amount,$temp,$notes]);
}
function lab_add_container(array $d):void {
    $id=(int)number($d['batch_id']??null,1,2147483647,'batch');$batch=row_owned('dough_batches',$id);
    $count=(int)query('SELECT COUNT(*) FROM batch_containers WHERE batch_id=?',[$id])->fetchColumn();if($count>=100)fail('Container limit reached');
    $code=$batch['batch_code'].'-'.($count+1);
    $label=plain($d['label']??'',120);$mass=lab_optional($d,'mass_g',0,100000);$temp=lab_optional($d,'temperature_f',-30,150);
    $split=lab_datetime($d['split_at']??null,'split time',true);
    query('INSERT INTO batch_containers (batch_id,container_code,label,split_at,mass_g,temperature_f,notes) VALUES (?,?,?,?,?,?,?)',[$id,$code,$label,$split,$mass,$temp,plain($d['notes']??'',5000)]);
    if($temp!==null)query('INSERT INTO batch_temperature_readings (batch_id,container_id,measured_at,temperature_f,notes) VALUES (?,?,?,?,?)',[$id,(int)db()->lastInsertId(),$split,$temp,'Temperature at splitting']);
    query('UPDATE dough_batches SET container_count=? WHERE id=?',[$count+1,$id]);
}
function lab_batch_temperature(array $d):void {
    $id=(int)number($d['batch_id']??null,1,2147483647,'batch');row_owned('dough_batches',$id);
    $cid=(int)($d['container_id']??0);lab_owned_container($id,$cid);
    $temp=number($d['temperature_f']??null,-30,150,'temperature');$at=lab_datetime($d['measured_at']??null,'measurement time',true);
    query('INSERT INTO batch_temperature_readings (batch_id,container_id,measured_at,temperature_f,notes) VALUES (?,?,?,?,?)',[$id,$cid?:null,$at,$temp,plain($d['notes']??'',1000)]);
}
function lab_batch_usage(array $d):void {
    $id=(int)number($d['batch_id']??null,1,2147483647,'batch');row_owned('dough_batches',$id);
    $type=plain($d['event_type']??'',12);if(!in_array($type,['balled','used'],true))fail('Invalid usage event');
    $at=lab_datetime($d['occurred_at']??null,'usage time',true);
    $count=lab_optional($d,'portions_count',1,100000);if($count!==null&&floor($count)!==$count)fail('Portion count must be whole');
    $weight=lab_optional($d,'weight_g',0,200000);
    if($count===null&&$weight===null)fail('Record portion count or weight');
    query('INSERT INTO batch_usage (batch_id,event_type,occurred_at,portions_count,weight_g,notes) VALUES (?,?,?,?,?,?)',[$id,$type,$at,$count===null?null:(int)$count,$weight,plain($d['notes']??'',1000)]);
    if($type==='balled')query('UPDATE dough_batches SET balled_at=COALESCE(balled_at,?),stage=IF(stage="archived",stage,"balled") WHERE id=?',[$at,$id]);
}
function lab_batch_waste(array $d):void {
    $id=(int)number($d['batch_id']??null,1,2147483647,'batch');row_owned('dough_batches',$id);
    $cid=(int)($d['container_id']??0);lab_owned_container($id,$cid);
    $weight=number($d['weight_g']??null,0.01,200000,'waste weight');$reason=plain($d['reason']??'',120);if($reason==='')fail('Waste reason is required');
    $at=lab_datetime($d['occurred_at']??null,'waste time',true);
    query('INSERT INTO batch_waste (batch_id,container_id,occurred_at,weight_g,reason,notes) VALUES (?,?,?,?,?,?)',[$id,$cid?:null,$at,$weight,$reason,plain($d['notes']??'',1000)]);
}
function lab_batch_analytics(int $id):array {
    $t=query('SELECT COUNT(*) AS readings,MIN(temperature_f) AS min_temp_f,MAX(temperature_f) AS max_temp_f,AVG(temperature_f) AS avg_temp_f FROM batch_temperature_readings WHERE batch_id=?',[$id])->fetch();
    $w=query('SELECT COUNT(*) AS records,SUM(weight_g) AS total_g FROM batch_waste WHERE batch_id=?',[$id])->fetch();
    $u=query('SELECT event_type,SUM(weight_g) AS weight_g,SUM(portions_count) AS portions FROM batch_usage WHERE batch_id=? GROUP BY event_type',[$id])->fetchAll();
    $used=null;$balled=null;foreach($u as $row){if($row['event_type']==='used')$used=$row;else $balled=$row;}
    return ['temperature'=>$t,'waste'=>$w,'used'=>$used,'balled'=>$balled,'logged_waste_share_percent'=>($w['total_g']??null)!==null &&($used['weight_g']??null)!==null && ($w['total_g']+$used['weight_g'])>0 ? round(100*$w['total_g']/($w['total_g']+$used['weight_g']),1):null];
}
