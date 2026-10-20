<?php
declare(strict_types=1);
/**
 * v1.5.16C: deliberate, auditable conversational batch assistance.
 * Agent suggestions never create a batch or complete a physical step by themselves.
 */
function lab_chat_batch_token(mixed $token): string {
    if(!is_string($token)||!preg_match('/^[A-Za-z0-9_-]{12,64}$/D',$token))fail('Invalid request identifier.');
    return $token;
}
function lab_chat_batch_confirm(array $d): void {
    if(($d['confirmed']??null)!==true)fail('Confirm this action in Agent Chat first.');
}
function lab_chat_batch_plan(?int $recipeId=null,bool $lock=false): array {
    if($recipeId===null) {
        $available=query("SELECT id,name,status FROM recipes WHERE status='current' ORDER BY id")->fetchAll();
        if(count($available)!==1){
            return ['requires_selection'=>true,'reason'=>count($available)===0?'No recipe is marked current. Choose the recipe to use.':'Multiple recipes are marked current. Choose which one to use.',
                'recipes'=>query("SELECT id,name,status FROM recipes WHERE status<>'archived' ORDER BY name,id LIMIT 100")->fetchAll()];
        }
        $recipeId=(int)$available[0]['id'];
    }
    $recipe=query('SELECT * FROM recipes WHERE id=?'.($lock?' FOR UPDATE':''),[$recipeId])->fetch(PDO::FETCH_ASSOC);
    if(!$recipe || $recipe['status']==='archived')fail('Choose an available recipe.',404);
    $steps=query('SELECT step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes FROM recipe_steps WHERE recipe_id=? ORDER BY sort_order,id'.($lock?' FOR UPDATE':''),[$recipeId])->fetchAll(PDO::FETCH_ASSOC);
    if(!$steps)fail('This recipe has no production steps. Add steps before using the guided workflow.',409);
    if(count($steps)>100)fail('Recipe has too many production steps for the chat guide.',409);
    $recipeJSON=json_encode($recipe,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    $stepsJSON=json_encode($steps,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    $fingerprint=hash('sha256',$recipeJSON."\n".$stepsJSON);
    $weights=[];
    foreach(['flour_g','water_g','salt_g','oil_g','honey_g','yeast_g'] as $key){
        $weights[$key]=$recipe[$key]===null?null:(float)$recipe[$key];
    }
    return [
      'requires_selection'=>false,'recipe_id'=>$recipeId,'recipe_name'=>$recipe['name'],
      'status'=>$recipe['status'],'flour_name'=>$recipe['flour_name'],'yeast_type'=>$recipe['yeast_type'],
      'ingredients'=>$weights,'step_count'=>count($steps),'steps'=>$steps,
      'fingerprint'=>$fingerprint,'recipe_snapshot'=>$recipeJSON,'process_snapshot'=>$stepsJSON
    ];
}
function lab_chat_batch_public_plan(array $plan):array {
    unset($plan['recipe_snapshot'],$plan['process_snapshot']);
    return $plan;
}
function lab_chat_batch_record(int $sessionId,int $userId):array{
    $s=query("SELECT s.*,b.batch_code,b.recipe_snapshot,b.process_steps_snapshot,r.name AS recipe_name
             FROM chat_batch_sessions s JOIN dough_batches b ON b.id=s.batch_id
             JOIN recipes r ON r.id=b.recipe_id WHERE s.id=? AND s.user_id=?",[$sessionId,$userId])->fetch(PDO::FETCH_ASSOC);
    if(!$s)fail('Guided batch session not found for this account.',404);
    $steps=json_decode($s['process_steps_snapshot']??'null',true);
    if(!is_array($steps))$steps=[];
    $index=(int)$s['next_step_index'];
    $history=query('SELECT step_key,note,source,created_at FROM chat_batch_step_actions WHERE session_id=? ORDER BY id',[$sessionId])->fetchAll();
    $recipe=json_decode($s['recipe_snapshot']??'null',true);
    $weights=[];
    foreach(['flour_g','water_g','salt_g','oil_g','honey_g','yeast_g'] as $k)$weights[$k]=($recipe[$k]??null)===null?null:(float)$recipe[$k];
    return ['session_id'=>(int)$s['id'],'batch_id'=>(int)$s['batch_id'],'batch_code'=>$s['batch_code'],
        'recipe_name'=>$recipe['name']??$s['recipe_name'],'status'=>$s['status'],'next_step_index'=>$index,
        'step_count'=>count($steps),'current_step'=>$s['status']==='active'?($steps[$index]??null):null,
        'steps'=>$steps,'history'=>$history,'ingredients'=>$weights,
        'flour_name'=>$recipe['flour_name']??'','yeast_type'=>$recipe['yeast_type']??'',
        'created_at'=>$s['created_at']];
}
function lab_chat_batch_active(int $userId):array{
    $rows=query("SELECT s.id,s.batch_id,s.next_step_index,b.batch_code,r.name AS recipe_name
        FROM chat_batch_sessions s JOIN dough_batches b ON b.id=s.batch_id JOIN recipes r ON r.id=b.recipe_id
        WHERE s.user_id=? AND s.status='active' ORDER BY s.updated_at DESC,s.id DESC LIMIT 30",[$userId])->fetchAll();
    return array_map(static fn($x)=>['session_id'=>(int)$x['id'],'batch_id'=>(int)$x['batch_id'],
        'batch_code'=>$x['batch_code'],'recipe_name'=>$x['recipe_name'],'next_step_index'=>(int)$x['next_step_index']],$rows);
}
function lab_chat_batch_start(array $d,int $userId):array {
    lab_chat_batch_confirm($d);
    $token=lab_chat_batch_token($d['client_request_id']??null);
    $recipeId=filter_var($d['recipe_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($recipeId===false||$recipeId===null)fail('Choose the exact recipe to start.');
    $fingerprint=(string)($d['fingerprint']??'');
    if(!preg_match('/^[a-f0-9]{64}$/D',$fingerprint))fail('Invalid recipe preview. Reload the plan.');
    $existing=query('SELECT id,recipe_id,recipe_fingerprint FROM chat_batch_sessions WHERE user_id=? AND client_request_id=?',[$userId,$token])->fetch();
    if($existing){
        if((int)$existing['recipe_id']!==$recipeId||!hash_equals($existing['recipe_fingerprint'],$fingerprint))fail('Request identifier already used for a different batch.',409);
        return ['existing'=>true,'session'=>lab_chat_batch_record((int)$existing['id'],$userId)];
    }
    $db=db();$db->beginTransaction();
    try{
        $plan=lab_chat_batch_plan((int)$recipeId,true);
        if(!hash_equals($plan['fingerprint'],$fingerprint))fail('Recipe or steps changed since preview. Review the latest version before creating a batch.',409);
        $alphabet='23456789ABCDEFGHJKMNPQRSTUVWXYZ';$batchId=0;
        for($attempt=0;$attempt<8;$attempt++){
            $code='PL-';for($i=0;$i<6;$i++)$code.=$alphabet[random_int(0,strlen($alphabet)-1)];
            try{
                query("INSERT INTO dough_batches
                    (batch_code,recipe_id,made_by_user_id,recorded_by_user_id,recipe_snapshot,process_steps_snapshot,stage,label)
                    VALUES (?,?,?,?,?,?,'bulk','Agent Chat dough production')",[
                    $code,$recipeId,$userId,$userId,$plan['recipe_snapshot'],$plan['process_snapshot']
                ]);
                $batchId=(int)$db->lastInsertId();break;
            }catch(PDOException $e){if(($e->errorInfo[1]??null)!==1062)throw $e;}
        }
        if(!$batchId)fail('Unable to generate unique batch code.',503);
        query("INSERT INTO chat_batch_sessions
            (batch_id,recipe_id,user_id,client_request_id,recipe_fingerprint,next_step_index,status)
            VALUES (?,?,?,?,?,0,'active')",[$batchId,$recipeId,$userId,$token,$fingerprint]);
        $sessionId=(int)$db->lastInsertId();
        $db->commit();
        return ['existing'=>false,'session'=>lab_chat_batch_record($sessionId,$userId)];
    }catch(PDOException $e){
        if($db->inTransaction())$db->rollBack();
        if(($e->errorInfo[1]??null)===1062){
            $existing=query('SELECT id,recipe_id,recipe_fingerprint FROM chat_batch_sessions WHERE user_id=? AND client_request_id=?',[$userId,$token])->fetch();
            if($existing && (int)$existing['recipe_id']===$recipeId && hash_equals($existing['recipe_fingerprint'],$fingerprint))
                return ['existing'=>true,'session'=>lab_chat_batch_record((int)$existing['id'],$userId)];
            fail('Request conflicts with a different batch.',409);
        }
        throw $e;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function lab_chat_batch_step(array $d,int $userId):array {
    lab_chat_batch_confirm($d);
    $token=lab_chat_batch_token($d['client_event_id']??null);
    $sessionId=filter_var($d['session_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($sessionId===false||$sessionId===null)fail('Select a batch session.');
    $key=(string)($d['step_key']??'');
    if(!preg_match('/^[a-z0-9_]{3,50}$/D',$key))fail('Invalid step key.');
    $note=plain($d['note']??'',1000);
    $source=(string)($d['source']??'manual');
    if(!in_array($source,['manual','voice_command'],true))fail('Invalid action source.');
    $db=db();$db->beginTransaction();
    try{
        $session=query('SELECT * FROM chat_batch_sessions WHERE id=? AND user_id=? FOR UPDATE',[(int)$sessionId,$userId])->fetch(PDO::FETCH_ASSOC);
        if(!$session)fail('Session not found for this account.',404);
        $prior=query('SELECT id,session_id,step_key,note,source FROM chat_batch_step_actions WHERE user_id=? AND client_event_id=?',[$userId,$token])->fetch();
        if($prior){
            if((int)$prior['session_id']!==(int)$sessionId || $prior['step_key']!==$key ||
               $prior['source']!==$source || $prior['note']!==$note)fail('Action identifier used for a different step.',409);
            $db->commit();
            return ['existing'=>true,'session'=>lab_chat_batch_record((int)$sessionId,$userId)];
        }
        if($session['status']!=='active')fail('Session already completed.',409);
        $batch=query('SELECT process_steps_snapshot FROM dough_batches WHERE id=? FOR UPDATE',[(int)$session['batch_id']])->fetch();
        $steps=json_decode($batch['process_steps_snapshot']??'null',true);
        $index=(int)$session['next_step_index'];
        if(!is_array($steps)||!isset($steps[$index])||$steps[$index]['step_key']!==$key)
            fail('This is not the current step. Refresh the active batch.',409);
        // Confirmation proves only the chef's assertion, not photo/measurement verification.
        query('INSERT INTO chat_batch_step_actions (session_id,step_key,user_id,client_event_id,note,source) VALUES (?,?,?,?,?,?)',
            [$sessionId,$key,$userId,$token,$note,$source]);
        query("INSERT INTO batch_step_logs (batch_id,step_key,finished_at,notes) VALUES (?,?,UTC_TIMESTAMP(),?)
            ON DUPLICATE KEY UPDATE finished_at=COALESCE(finished_at,VALUES(finished_at))",
            [(int)$session['batch_id'],$key,$note]);
        $next=$index+1;
        $status=$next>=count($steps)?'completed':'active';
        query('UPDATE chat_batch_sessions SET next_step_index=?,status=?,updated_at=UTC_TIMESTAMP() WHERE id=?',[$next,$status,$sessionId]);
        $db->commit();
        return ['existing'=>false,'session'=>lab_chat_batch_record((int)$sessionId,$userId)];
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();throw $e;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
