<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
auth();
require_once __DIR__.'/batch_tracking.php';
require_once __DIR__.'/team.php';
require_once __DIR__.'/experiments.php';
require_once __DIR__.'/ai_experiments.php';
require_once __DIR__.'/batch_ai.php';
require_once __DIR__.'/ai_proposals.php';
header('Cache-Control: no-store');
try {
    $action=(string)($_GET['action'] ?? $_POST['action'] ?? '');
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        if ($action==='bootstrap') {
            $recipes=query('SELECT * FROM recipes ORDER BY updated_at DESC')->fetchAll();
            $recipeSteps=query('SELECT * FROM recipe_steps ORDER BY recipe_id,sort_order,id')->fetchAll();
            $batches=query("SELECT b.*,r.name AS recipe_name, maker.display_name AS made_by_name, recorder.display_name AS recorded_by_name,a.pizza_tests,a.avg_bake_s,a.avg_stone_f,a.avg_sauce_g,a.avg_cheese_g,a.avg_rise,a.avg_crisp,a.avg_color FROM dough_batches b JOIN recipes r ON r.id=b.recipe_id LEFT JOIN users maker ON maker.id=b.made_by_user_id LEFT JOIN users recorder ON recorder.id=b.recorded_by_user_id LEFT JOIN (SELECT batch_id,COUNT(*) AS pizza_tests,AVG(bake_seconds) AS avg_bake_s,AVG(stone_start_f) AS avg_stone_f,AVG(sauce_g) AS avg_sauce_g,AVG(cheese_g) AS avg_cheese_g,AVG(rim_rise_score) AS avg_rise,AVG(crispness_score) AS avg_crisp,AVG(bottom_color_score) AS avg_color FROM evidence WHERE batch_id IS NOT NULL GROUP BY batch_id) a ON a.batch_id=b.id ORDER BY (b.stage='archived'),b.created_at DESC,b.id DESC LIMIT 500")->fetchAll();
            $evidence=query('SELECT e.*,r.name AS recipe_name,b.batch_code AS batch_code FROM evidence e LEFT JOIN recipes r ON r.id=e.recipe_id LEFT JOIN dough_batches b ON b.id=e.batch_id ORDER BY e.created_at DESC,e.id DESC LIMIT 100')->fetchAll();
            $counts=['recipes'=>count($recipes),'tests'=>(int)db()->query('SELECT COUNT(*) FROM evidence')->fetchColumn(),'photos'=>(int)db()->query("SELECT COUNT(*) FROM media WHERE kind IN ('top','bottom','side','crumb')")->fetchColumn(),'batches'=>(int)db()->query('SELECT COUNT(*) FROM dough_batches')->fetchColumn()];
            json_out(['ok'=>true,'recipes'=>$recipes,'recipe_steps'=>$recipeSteps,'batches'=>$batches,'batch_makers'=>query('SELECT id,display_name,is_active FROM users ORDER BY display_name,id')->fetchAll(),'evidence'=>$evidence,'counts'=>$counts,'voice_profile'=>query('SELECT profile_name,wake_phrase,language,voice_uri,voice_rate,require_wake,start_alias FROM voice_profiles WHERE user_id=?',[auth()])->fetch() ?: null,'experiments'=>lab_experiments(),'settings'=>['model'=>setting('model','claude-haiku-5-5'),'key_configured'=>setting('claude_key')!=='']]);
        }
        if ($action==='teamUsers') {
            require_super_admin();
            $users=query('SELECT id,display_name,email,role,is_active,created_at FROM users ORDER BY FIELD(role,"super_admin","team_member"),display_name,id')->fetchAll();
            json_out(['ok'=>true,'users'=>$users]);
        }
        if ($action==='batchCoachHistory') {
            [$scope,$target]=lab_coach_scope((string)($_GET['scope']??'all'),$_GET['target_id']??null);
            json_out(['ok'=>true,'reviews'=>lab_coach_history($scope,$target,auth())]);
        }
        if ($action==='experimentDetail') { json_out(['ok'=>true,'report'=>lab_experiment_detail((int)($_GET['id']??0))]); }
        if ($action==='labAnalytics') { json_out(['ok'=>true,'analytics'=>lab_recipe_analytics()]); }
        if ($action==='experimentReviews') { json_out(['ok'=>true,'reviews'=>lab_ai_prior_reviews((int)($_GET['id']??0))]); }
        if ($action==='batchDetail') {
            $id=(int)($_GET['id']??0);$batch=row_owned('dough_batches',$id);
            $batch['recipe_name']=query('SELECT name FROM recipes WHERE id=?',[$batch['recipe_id']])->fetchColumn();
            $batch['made_by_name']=$batch['made_by_user_id']?query('SELECT display_name FROM users WHERE id=?',[$batch['made_by_user_id']])->fetchColumn():null;
            $batch['recorded_by_name']=$batch['recorded_by_user_id']?query('SELECT display_name FROM users WHERE id=?',[$batch['recorded_by_user_id']])->fetchColumn():null;
            $batch['recipe_at_mixing']=$batch['recipe_snapshot']?json_decode($batch['recipe_snapshot'],true):null;
            $batch['events']=query('SELECT * FROM dough_batch_events WHERE batch_id=? ORDER BY occurred_at DESC,id DESC LIMIT 250',[$id])->fetchAll();
            $batch['planned_steps']=$batch['process_steps_snapshot'] ? json_decode($batch['process_steps_snapshot'],true) : [];
            $batch['step_logs']=query('SELECT * FROM batch_step_logs WHERE batch_id=? ORDER BY id',[$id])->fetchAll();
            $batch['containers']=query('SELECT * FROM batch_containers WHERE batch_id=? ORDER BY id',[$id])->fetchAll();
            $batch['temperatures']=query('SELECT * FROM batch_temperature_readings WHERE batch_id=? ORDER BY measured_at DESC,id DESC LIMIT 500',[$id])->fetchAll();
            $batch['usage']=query('SELECT * FROM batch_usage WHERE batch_id=? ORDER BY occurred_at DESC,id DESC LIMIT 250',[$id])->fetchAll();
            $batch['waste']=query('SELECT * FROM batch_waste WHERE batch_id=? ORDER BY occurred_at DESC,id DESC LIMIT 250',[$id])->fetchAll();
            $batch['process_analytics']=lab_batch_analytics($id);
            $batch['pizzas']=query('SELECT e.id,e.label,e.created_at,e.pizza_size_in,e.dough_ball_g,e.dough_age_h,e.bake_seconds,e.stone_start_f,e.stone_finish_f,e.sauce_g,e.cheese_g,e.cheese_moisture,e.rim_rise_score,e.crispness_score,e.bottom_color_score,e.notes, (SELECT COUNT(*) FROM observations o WHERE o.evidence_id=e.id) AS ai_reports FROM evidence e WHERE e.batch_id=? ORDER BY e.created_at DESC,e.id DESC LIMIT 1000',[$id])->fetchAll();
            $batch['analytics']=query('SELECT COUNT(*) AS pizzas,COUNT(bake_seconds) AS time_count,AVG(bake_seconds) AS avg_bake_s,MIN(bake_seconds) AS min_bake_s,MAX(bake_seconds) AS max_bake_s,COUNT(stone_start_f) AS stone_count,AVG(stone_start_f) AS avg_stone_f,COUNT(sauce_g) AS sauce_count,AVG(sauce_g) AS avg_sauce_g,COUNT(cheese_g) AS cheese_count,AVG(cheese_g) AS avg_cheese_g,COUNT(dough_age_h) AS dough_age_count,AVG(dough_age_h) AS avg_dough_age_h,COUNT(rim_rise_score) AS rise_count,AVG(rim_rise_score) AS avg_rise,COUNT(crispness_score) AS crisp_count,AVG(crispness_score) AS avg_crisp,COUNT(bottom_color_score) AS color_count,AVG(bottom_color_score) AS avg_color FROM evidence WHERE batch_id=?',[$id])->fetch();
            json_out(['ok'=>true,'batch'=>$batch]);
        }
        if ($action==='detail') {
            $id=(int)($_GET['id']??0);$e=row_owned('evidence',$id);
            $e['media']=query('SELECT id,kind,original_name,created_at FROM media WHERE evidence_id=? ORDER BY id DESC',[$id])->fetchAll();
            $e['readings']=query('SELECT * FROM oven_readings WHERE evidence_id=? ORDER BY elapsed_seconds,id',[$id])->fetchAll();
            $e['observations']=query('SELECT * FROM observations WHERE evidence_id=? ORDER BY id DESC LIMIT 40',[$id])->fetchAll();
            $e['events']=query('SELECT * FROM events WHERE evidence_id=? ORDER BY id DESC LIMIT 80',[$id])->fetchAll();
            json_out(['ok'=>true,'detail'=>$e]);
        }
        fail('Unknown action',404);
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') fail('Method not allowed',405);
    check_csrf();
    $d=posted(); $action=(string)($d['action']??$action);
    if (in_array($action,['createTeamMember','updateTeamMember','resetTeamPassword'],true)) lab_team_action($action,$d);
    if ($action==='changeMyPassword') {
        $u=auth_user();
        if(!password_verify((string)($d['current_password']??''),$u['password_hash']))fail('Current password is incorrect.',403);
        $pwd=(string)($d['new_password']??'');
        if(strlen($pwd)<12||strlen($pwd)>1024)fail('New password must contain 12–1024 characters.');
        query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($pwd,PASSWORD_DEFAULT),(int)$u['id']]);
        json_out(['ok'=>true]);
    }
    if ($action==='generateProposal') { json_out(['ok'=>true,...lab_generate_proposal($d)]); }
    if ($action==='decideProposal') { json_out(['ok'=>true,...lab_decide_proposal($d)]); }
    if ($action==='batchCoachAsk') {json_out(['ok'=>true,'review'=>lab_coach_ask($d)]);}
    if ($action==='createExperiment') { json_out(['ok'=>true,'id'=>lab_create_experiment($d)]); }
    if ($action==='reviewExperiment') { json_out(['ok'=>true,'review'=>lab_ai_review_experiment((int)($d['id']??0))]); }
    if ($action==='updateExperiment') { lab_update_experiment($d); json_out(['ok'=>true]); }
    if ($action==='saveRecipe') {
        $id=(int)($d['id']??0);
        if ($id) row_owned('recipes',$id);
        $name=plain($d['name']??'',180); if ($name==='') fail('Recipe name required');
        $status=plain($d['status']??'experimental',30);if (!in_array($status,['current','experimental','archived'],true)) fail('Invalid recipe status');
        $vals=[];foreach (['flour_g','water_g','salt_g','oil_g','honey_g','yeast_g'] as $key) {
            $v=$d[$key]??null;$vals[]=$v===''||$v===null?null:number($v,0,200000,$key);
        }
        $p=[$name,$status,plain($d['flour_name']??'',180),...$vals,plain($d['yeast_type']??'fresh compressed',40),plain($d['process_notes']??'',10000),plain($d['notes']??'',10000)];
        if ($id) { query('UPDATE recipes SET name=?,status=?,flour_name=?,flour_g=?,water_g=?,salt_g=?,oil_g=?,honey_g=?,yeast_g=?,yeast_type=?,process_notes=?,notes=? WHERE id=?',[...$p,$id]); }
        else {query('INSERT INTO recipes (name,status,flour_name,flour_g,water_g,salt_g,oil_g,honey_g,yeast_g,yeast_type,process_notes,notes,parent_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$p,($d['parent_id']??null)?:null]);$id=(int)db()->lastInsertId();}
        json_out(['ok'=>true,'id'=>$id]);
    }
    if ($action==='cloneRecipe') {
        $id=(int)($d['id']??0);$r=row_owned('recipes',$id);
        query('INSERT INTO recipes (parent_id,name,status,flour_name,flour_g,water_g,salt_g,oil_g,honey_g,yeast_g,yeast_type,process_notes,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[
            $id,plain($r['name'].' — Variation',180),'experimental',$r['flour_name'],$r['flour_g'],$r['water_g'],$r['salt_g'],$r['oil_g'],$r['honey_g'],$r['yeast_g'],$r['yeast_type'],$r['process_notes'],$r['notes']
        ]);
        $newId=(int)db()->lastInsertId();
        query('INSERT INTO recipe_steps (recipe_id,step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes) SELECT ?,step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes FROM recipe_steps WHERE recipe_id=?',[$newId,$id]);
        json_out(['ok'=>true,'id'=>$newId]);
    }
    if ($action==='saveRecipeStep') { lab_save_recipe_step($d); json_out(['ok'=>true]); }
    if ($action==='deleteRecipeStep') { lab_delete_recipe_step($d); json_out(['ok'=>true]); }
    if ($action==='moveRecipeStep') { lab_move_recipe_step($d); json_out(['ok'=>true]); }
    if ($action==='saveBatchStep') { lab_save_batch_step($d); json_out(['ok'=>true]); }
    if ($action==='addContainer') { lab_add_container($d); json_out(['ok'=>true]); }
    if ($action==='batchTemperature') { lab_batch_temperature($d); json_out(['ok'=>true]); }
    if ($action==='batchUsage') { lab_batch_usage($d); json_out(['ok'=>true]); }
    if ($action==='batchWaste') { lab_batch_waste($d); json_out(['ok'=>true]); }
    if ($action==='saveBatch') {
        $id=(int)($d['id']??0);
        $old=$id?row_owned('dough_batches',$id):null;
        $recipeValue=$d['recipe_id']??null;
        if ($recipeValue===null||$recipeValue==='') fail('Choose a recipe for this batch. Create a recipe first if needed.');
        $recipeId=(int)number($recipeValue,1,2147483647,'recipe');
        $recipeRow=row_owned('recipes',$recipeId);
        // The person who made the dough can differ from the account recording the batch.
        // A newly created batch always records an attributable maker (defaults to the signed-in chef).
        // Historical batches with no maker stay unknown until one is deliberately selected.
        $makerRaw=$d['made_by_user_id']??null;
        if($makerRaw===null||$makerRaw==='') {
            $makerId=$old?($old['made_by_user_id']===null?null:(int)$old['made_by_user_id']):auth();
        } else {
            $makerId=(int)number($makerRaw,1,2147483647,'made by user');
            $maker=query('SELECT id,is_active FROM users WHERE id=?',[$makerId])->fetch();
            if(!$maker)fail('Selected dough maker was not found.');
            if((int)$maker['is_active']!==1 && (!$old||(int)$old['made_by_user_id']!==$makerId))fail('Choose an active team member as the dough maker.');
        }

        $recipeSnapshot=json_encode($recipeRow,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        $stepsSnapshot=json_encode(query('SELECT step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes FROM recipe_steps WHERE recipe_id=? ORDER BY sort_order,id',[$recipeId])->fetchAll(),JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        if ($id && (string)($old['recipe_id']??'')!==(string)($recipeId??'')) {
            $linked=(int)query('SELECT COUNT(*) FROM evidence WHERE batch_id=?',[$id])->fetchColumn();
            $history=0;
            foreach(['batch_step_logs','batch_containers','dough_batch_events','batch_temperature_readings','batch_usage','batch_waste'] as $table) {
                $history+=(int)query('SELECT COUNT(*) FROM '.$table.' WHERE batch_id=?',[$id])->fetchColumn();
            }
            if ($linked||$history) fail('Cannot change a batch recipe after pizza evidence or production events exist. Create a new batch.');
        }
        $label=plain($d['label']??'',180);
        $stage=plain($d['stage']??'bulk',20);
        if(!in_array($stage,['bulk','balled','ready','used','archived'],true)) fail('Invalid batch stage');
        $date=function(string $name)use($d):?string {
            $v=trim((string)($d[$name]??''));
            if ($v==='') return null;
            $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$v);
            if(!$dt||$dt->format('Y-m-d\TH:i')!==$v) fail('Invalid '.$name.' date/time');
            return $dt->format('Y-m-d H:i:s');
        };
        $mixed=$date('mixed_at');$balled=$date('balled_at');
        if($mixed!==null&&$balled!==null&&$balled<$mixed) fail('Balling time cannot precede mixing time');
        $optional=function(string $key,float $min,float $max)use($d):?float {
            return !isset($d[$key])||$d[$key]===''?null:number($d[$key],$min,$max,$key);
        };
        $doughTemp=$optional('dough_temp_f',-20,150);
        $fridgeTemp=$optional('fridge_temp_f',-20,100);
        $containers=$optional('container_count',1,100);
        if($containers!==null && floor($containers)!==$containers) fail('Container count must be a whole number');
        $vals=[$recipeId,$label,$stage,$mixed,$balled,$doughTemp,$fridgeTemp,$containers===null?null:(int)$containers,plain($d['notes']??'',10000)];
        if($id) {
            // Never silently rewrite recipe history for batches that have baked pizzas.
            $hasLinked=(int)query('SELECT COUNT(*) FROM evidence WHERE batch_id=?',[$id])->fetchColumn()>0;
            if ($hasLinked && (int)$old['recipe_id']!==$recipeId) fail('This batch has pizza tests. Its recipe cannot be changed; create a new batch instead.');
            if($hasLinked) query('UPDATE dough_batches SET made_by_user_id=?,label=?,stage=?,mixed_at=?,balled_at=?,dough_temp_f=?,fridge_temp_f=?,container_count=?,notes=? WHERE id=?',[$makerId,...array_slice([...$vals,$id],1)]);
            else query('UPDATE dough_batches SET recipe_id=?,recipe_snapshot=?,process_steps_snapshot=?,made_by_user_id=?,label=?,stage=?,mixed_at=?,balled_at=?,dough_temp_f=?,fridge_temp_f=?,container_count=?,notes=? WHERE id=?',[$recipeId,$recipeId===(int)$old['recipe_id']&&$old['recipe_snapshot']? $old['recipe_snapshot'] : $recipeSnapshot,$recipeId===(int)$old['recipe_id'] ? $old['process_steps_snapshot'] : $stepsSnapshot,$makerId,...array_slice($vals,1),$id]);
        }
        else {
            $alphabet='23456789ABCDEFGHJKMNPQRSTUVWXYZ';
            $made=false;
            for($attempt=0;$attempt<6;$attempt++) {
                $code='PL-';for($i=0;$i<6;$i++) $code.=$alphabet[random_int(0,strlen($alphabet)-1)];
                try {query('INSERT INTO dough_batches (batch_code,recipe_id,recipe_snapshot,process_steps_snapshot,made_by_user_id,recorded_by_user_id,label,stage,mixed_at,balled_at,dough_temp_f,fridge_temp_f,container_count,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$code,$recipeId,$recipeSnapshot,$stepsSnapshot,$makerId,auth(),...array_slice($vals,1)]);$made=true;break;}
                catch(PDOException $ex){ if(($ex->errorInfo[1]??0)!=1062) throw $ex; }
            }
            if(!$made) fail('Could not generate a unique batch code. Try again.',503);
            $id=(int)db()->lastInsertId();
        }
        $b=row_owned('dough_batches',$id);
        json_out(['ok'=>true,'id'=>$id,'code'=>$b['batch_code']]);
    }
    if ($action==='batchEvent') {
        $id=(int)($d['batch_id']??0);row_owned('dough_batches',$id);
        $phase=plain($d['phase']??'',25);
        if(!in_array($phase,['mixed','refrigerated','balled','ready','used','temperature','note'],true)) fail('Invalid batch event');
        $input=trim((string)($d['occurred_at']??''));
        if($input==='') $occurred=date('Y-m-d H:i:s');
        else {
            $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$input);
            if(!$dt||$dt->format('Y-m-d\TH:i')!==$input) fail('Invalid event time');
            $occurred=$dt->format('Y-m-d H:i:s');
        }
        $value=$d['temperature_f']??null;
        $temp=$value===null||$value===''?null:number($value,-20,150,'dough/fridge temperature');
        $note=plain($d['note']??'',1000);
        query('INSERT INTO dough_batch_events (batch_id,phase,occurred_at,temperature_f,note) VALUES (?,?,?,?,?)',[$id,$phase,$occurred,$temp,$note]);
        if($phase==='mixed') query('UPDATE dough_batches SET mixed_at=COALESCE(mixed_at,?),stage=IF(stage="archived",stage,"bulk") WHERE id=?',[$occurred,$id]);
        if($phase==='balled') query('UPDATE dough_batches SET balled_at=COALESCE(balled_at,?),stage=IF(stage="archived",stage,"balled") WHERE id=?',[$occurred,$id]);
        if($phase==='ready'||$phase==='used') query('UPDATE dough_batches SET stage=IF(stage="archived",stage,?) WHERE id=?',[$phase,$id]);
        json_out(['ok'=>true]);
    }
    if ($action==='saveEvidence') {
        $id=(int)($d['id']??0);if ($id) row_owned('evidence',$id);
        $recipeValue=$d['recipe_id']??null;
        $recipe=$recipeValue===null || $recipeValue==='' ? null : (int)number($recipeValue,1,2147483647,'recipe');
        if ($recipe!==null) row_owned('recipes',$recipe);
        $batchValue=$d['batch_id']??null;
        $batchId=$batchValue===null||$batchValue===''?null:(int)number($batchValue,1,2147483647,'batch');
        if($batchId!==null){
            $batch=row_owned('dough_batches',$batchId);
            if($batch['recipe_id']!==null){
                if($recipe!==null && $recipe!==(int)$batch['recipe_id']) fail('Batch and pizza recipe do not match. Select the batch recipe or a different batch.');
                $recipe=(int)$batch['recipe_id'];
            }
        }
        $label=plain($d['label']??'',180);
        if ($label==='') $label='Pizza test · '.date('Y-m-d H:i:s');
        $optionalNumber=function(string $field,float $min,float $max)use($d): ?float {
            $value=$d[$field]??null;
            return $value===null || $value==='' ? null : number($value,$min,$max,$field);
        };
        $moisture=$optionalNumber('cheese_moisture',1,10);
        if ($moisture!==null && floor($moisture)!==$moisture) fail('Cheese moisture must be a whole number from 1 to 10');
        $load=$optionalNumber('oven_load',0,100);
        if ($load!==null && floor($load)!==$load) fail('Oven load must be a whole number');
        $bake=$optionalNumber('bake_seconds',10,3600);
        if ($bake!==null && floor($bake)!==$bake) fail('Bake seconds must be a whole number');
        $rating=function(string $key)use($optionalNumber):?int {
            $v=$optionalNumber($key,1,10);
            if($v!==null && floor($v)!==$v) fail('Rating must be a whole number from 1 to 10');
            return $v===null?null:(int)$v;
        };
        $v=[$recipe,$batchId,$label,$optionalNumber('pizza_size_in',3,30),$optionalNumber('dough_ball_g',10,5000),$optionalNumber('sauce_g',0,2000),$optionalNumber('cheese_g',0,2000),$optionalNumber('dough_age_h',0,2000),$optionalNumber('stone_start_f',0,1500),$optionalNumber('stone_finish_f',0,1500),$moisture===null?null:(int)$moisture,$rating('rim_rise_score'),$rating('crispness_score'),$rating('bottom_color_score'),$bake===null?null:(int)$bake,$load===null?null:(int)$load,plain($d['notes']??'',10000)];
        if ($id) query('UPDATE evidence SET recipe_id=?,batch_id=?,label=?,pizza_size_in=?,dough_ball_g=?,sauce_g=?,cheese_g=?,dough_age_h=?,stone_start_f=?,stone_finish_f=?,cheese_moisture=?,rim_rise_score=?,crispness_score=?,bottom_color_score=?,bake_seconds=?,oven_load=?,notes=? WHERE id=?',[...$v,$id]);
        else {query('INSERT INTO evidence (recipe_id,batch_id,label,pizza_size_in,dough_ball_g,sauce_g,cheese_g,dough_age_h,stone_start_f,stone_finish_f,cheese_moisture,rim_rise_score,crispness_score,bottom_color_score,bake_seconds,oven_load,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$v);$id=(int)db()->lastInsertId();}
        json_out(['ok'=>true,'id'=>$id]);
    }
    if ($action==='event') {
        $id=(int)($d['evidence_id']??0);row_owned('evidence',$id);
        $message=plain($d['message']??'',500);if ($message==='') fail('Missing event message');
        $source=plain($d['event_source']??'manual',20);
        if (!in_array($source,['manual','voice_command','agent_alert','system'],true)) fail('Invalid event source');
        $profile=query('SELECT profile_name FROM voice_profiles WHERE user_id=?',[auth()])->fetchColumn() ?: null;
        query('INSERT INTO events (evidence_id,elapsed_seconds,message,actor_profile,event_source) VALUES (?,?,?,?,?)',[$id,(int)number($d['elapsed_seconds']??0,0,7200,'elapsed time'),$message,$profile,$source]);
        json_out(['ok'=>true]);
    }
    if ($action==='finishEvidence') {
        $id=(int)($d['evidence_id']??0);row_owned('evidence',$id);
        $seconds=(int)number($d['bake_seconds']??0,10,3600,'bake seconds');
        query('UPDATE evidence SET bake_seconds=? WHERE id=?',[$seconds,$id]);
        json_out(['ok'=>true]);
    }
    if ($action==='reading') {
        $id=(int)($d['evidence_id']??0); row_owned('evidence',$id);
        $phase=plain($d['phase']??'check',30); if (!in_array($phase,['launch','finish','recovery','check'],true)) fail('Invalid reading type');
        query('INSERT INTO oven_readings (evidence_id,phase,stone_f,elapsed_seconds) VALUES (?,?,?,?)',[$id,$phase,number($d['stone_f']??null,0,1500,'stone temperature'),(int)number($d['elapsed_seconds']??0,0,7200,'elapsed seconds')]);
        json_out(['ok'=>true]);
    }
    if ($action==='upload') {
        $id=(int)($d['evidence_id']??0);row_owned('evidence',$id);
        $kind=(string)($d['kind']??'');if (!in_array($kind,['top','bottom','side','crumb','scan','audio'],true)) fail('Invalid media type');
        if (empty($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK) fail('Upload failed. Check PHP upload_max_filesize and post_max_size.');
        $file=$_FILES['file']; $max=$kind==='audio'?15000000:6500000;
        if ($file['size']<=0 || $file['size']>$max) fail('File too large. Photos up to 6.5 MB, audio up to 15 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allow=$kind==='audio'?['audio/mp4'=>'m4a','video/mp4'=>'mp4','audio/x-m4a'=>'m4a','audio/webm'=>'webm','video/webm'=>'webm','audio/ogg'=>'ogg','application/ogg'=>'ogg','audio/wav'=>'wav','audio/x-wav'=>'wav']:['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allow[$mime])) fail('Unsupported upload type: '.$mime);
        if ($kind!=='audio' && !@getimagesize($file['tmp_name'])) fail('Invalid image file');
        if ($kind==='scan') {
            $count=(int)query("SELECT COUNT(*) FROM media WHERE evidence_id=? AND kind='scan'",[$id])->fetchColumn();
            if ($count>=12) fail('This test has reached its 12 saved scanner images.');
        }
        $stored=bin2hex(random_bytes(18)).'.'.$allow[$mime];
        if (!is_dir(DATA_ROOT.'/uploads')) mkdir(DATA_ROOT.'/uploads',0700,true);
        if (!move_uploaded_file($file['tmp_name'],DATA_ROOT.'/uploads/'.$stored)) fail('Could not store media',500);
        @chmod(DATA_ROOT.'/uploads/'.$stored,0600);
        query('INSERT INTO media (evidence_id,kind,stored_name,mime,original_name) VALUES (?,?,?,?,?)',[$id,$kind,$stored,$mime,plain($file['name'],180)]);
        json_out(['ok'=>true,'media_id'=>(int)db()->lastInsertId()]);
    }
    if ($action==='saveTrackerBox') {
        $mediaId=(int)($d['media_id']??0);$evidenceId=(int)($d['evidence_id']??0);
        $row=query('SELECT evidence_id,kind FROM media WHERE id=?',[$mediaId])->fetch();
        if(!$row || $row['kind']!=='scan' || (int)$row['evidence_id']!==$evidenceId)fail('Image is not a scan belonging to this pizza.',403);
        row_owned('evidence',$evidenceId);
        $x=number($d['x']??null,0,1,'box X');$y=number($d['y']??null,0,1,'box Y');
        $w=number($d['width']??null,.01,1,'box width');$h=number($d['height']??null,.01,1,'box height');
        if($x+$w>1.00001||$y+$h>1.00001)fail('Bounding box exceeds image.');
        $source=(string)($d['source']??'manual');$confidence=(string)($d['confidence']??'low');
        if(!in_array($source,['ai','manual','local'],true)||!in_array($confidence,['low','medium','high'],true))fail('Invalid detection provenance.');
        // One immutable box per captured photo. Avoid silent edits to evidence.
        try{query('INSERT INTO pizza_tracker_boxes (media_id,evidence_id,x_norm,y_norm,width_norm,height_norm,source,confidence,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?)',[$mediaId,$evidenceId,$x,$y,$w,$h,$source,$confidence,auth()]);}
        catch(PDOException $ex){if($ex->getCode()==='23000')fail('Bounding box already saved for this photo.',409);throw $ex;}
        json_out(['ok'=>true]);
    }
    if ($action==='saveVoiceProfile') {
        $name=plain($d['profile_name']??'Pizza chef',80);
        if ($name==='') fail('Profile name required');
        $wake=mb_cut(strtolower(trim((string)($d['wake_phrase']??'pizza'))),40);
        if (!preg_match('/^[a-z][a-z ]{1,39}$/',$wake)) fail('Wake phrase must be 2–40 letters/spaces');
        $language=plain($d['language']??'en-US',15);
        if (!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/',$language)) fail('Invalid language');
        $voice=plain($d['voice_uri']??'',200);
        $rate=number($d['voice_rate']??0.95,0.6,1.5,'voice speed');
        $required=!empty($d['require_wake']) ? 1 : 0;
        $alias=mb_cut(strtolower(trim((string)($d['start_alias']??''))),70);
        if ($alias!=='' && !preg_match('/^[a-z][a-z ]{2,69}$/',$alias)) fail('Start alias must use simple letters/spaces');
        query('INSERT INTO voice_profiles (user_id,profile_name,wake_phrase,language,voice_uri,voice_rate,require_wake,start_alias) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE profile_name=VALUES(profile_name),wake_phrase=VALUES(wake_phrase),language=VALUES(language),voice_uri=VALUES(voice_uri),voice_rate=VALUES(voice_rate),require_wake=VALUES(require_wake),start_alias=VALUES(start_alias)',[auth(),$name,$wake,$language,$voice,$rate,$required,$alias]);
        json_out(['ok'=>true]);
    }
    if ($action==='saveSettings') {
        require_super_admin();
        $model=plain($d['model']??'claude-haiku-5-5',100);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,99}$/',$model)) fail('Invalid model ID');
        change_setting('model',$model);
        $key=trim((string)($d['api_key']??''));if ($key!=='') change_setting('claude_key',encrypted_key($key));
        if (!empty($d['remove_key'])) change_setting('claude_key','');
        json_out(['ok'=>true]);
    }
    if ($action==='analyze') {
        $id=(int)($d['evidence_id']??0);$e=row_owned('evidence',$id);
        $kind=(string)($d['kind']??'evidence');if (!in_array($kind,['live','evidence'],true)) fail('Invalid analysis mode');
        $enc=setting('claude_key');if ($enc==='') fail('Add your Claude API key in Settings first.');
        if ($kind==='live') {
            $n=(int)query("SELECT COUNT(*) FROM observations WHERE evidence_id=? AND kind='live' AND created_at >= NOW()-INTERVAL 1 HOUR",[$id])->fetchColumn();
            if ($n>=12) fail('Live scan limit reached (12 analyses per hour for this test).');
            $last=query("SELECT created_at FROM observations WHERE evidence_id=? AND kind='live' ORDER BY id DESC LIMIT 1",[$id])->fetchColumn();
            if ($last && time()-strtotime($last)<20) fail('Live scan cooldown: allow 20 seconds between scans.',429);
        }
        $r=$e['recipe_id']===null ? null : row_owned('recipes',(int)$e['recipe_id']);
        $batch=$e['batch_id']===null?null:row_owned('dough_batches',(int)$e['batch_id']);
        $batchEvents=$batch===null?[]:query('SELECT phase,occurred_at,temperature_f,note FROM dough_batch_events WHERE batch_id=? ORDER BY occurred_at DESC LIMIT 25',[$batch['id']])->fetchAll();
        $batchStats=$batch===null?null:query('SELECT COUNT(*) AS pizza_count, AVG(bake_seconds) AS avg_bake_s,AVG(stone_start_f) AS avg_stone_f FROM evidence WHERE batch_id=?',[$batch['id']])->fetch();
        $processInfo=$batch===null?null:['planned_steps'=>$batch['process_steps_snapshot']?json_decode($batch['process_steps_snapshot'],true):null,'step_actuals'=>query('SELECT step_key,started_at,finished_at,actual_amount_g,actual_temp_f,notes FROM batch_step_logs WHERE batch_id=?',[$batch['id']])->fetchAll(),'temperature_history'=>query('SELECT measured_at,container_id,temperature_f FROM batch_temperature_readings WHERE batch_id=? ORDER BY measured_at DESC LIMIT 30',[$batch['id']])->fetchAll(),'usage_waste'=>lab_batch_analytics((int)$batch['id'])];
        $media=$kind==='live' ? query("SELECT * FROM media WHERE evidence_id=? AND kind='scan' ORDER BY id DESC LIMIT 1",[$id])->fetchAll() : query("SELECT * FROM media WHERE evidence_id=? AND kind IN ('top','bottom','side','crumb') ORDER BY id DESC LIMIT 30",[$id])->fetchAll();
        if ($kind==='evidence') { $seen=[]; $media=array_values(array_filter($media,function($m)use(&$seen){if(isset($seen[$m['kind']]))return false;$seen[$m['kind']]=true;return true;})); }
        if (!$media) fail('Add a scan or photo first.');
        $prompt='You are an expert pizza dough and wood-fired oven coach. Analyze visible evidence conservatively. NO temperature measurement from photos. NO definitive internal doneness or food safety claims. Clearly distinguish observed pixels versus hypotheses. Be alert to glare, steam, fire reflections and under/overexposure. Use short actionable suggestions for oven spring, crispness, char, bottom color and sauce moisture. Treat every null value as NOT MEASURED and do not invent a recipe or bake duration. Cheese moisture is a subjective 1=driest to 10=wettest score. More complete evidence can justify more specific advice. A human makes all oven decisions. Reply only as JSON with keys: summary (string), confidence (low/medium/high), visual_findings (array of up to 4 strings), suggestions (array of up to 4 strings), warning (string or empty), turn_now (boolean; only true if clearly urgent uneven browning is visible).';
        $prompt.="\nEvidence: ".json_encode(['label'=>$e['label'],'size_in'=>$e['pizza_size_in'],'dough_g'=>$e['dough_ball_g'],'sauce_g'=>$e['sauce_g'],'cheese_g'=>$e['cheese_g'],'cheese_moisture_1_to_10'=>$e['cheese_moisture'],'rim_rise_score_1_to_10'=>$e['rim_rise_score'],'crispness_score_1_to_10'=>$e['crispness_score'],'bottom_color_score_1_to_10'=>$e['bottom_color_score'],'dough_age_h'=>$e['dough_age_h'],'stone_start_f'=>$e['stone_start_f'],'stone_finish_f'=>$e['stone_finish_f'],'bake_s'=>$e['bake_seconds'],'oven_load_0_to_100'=>$e['oven_load'],'notes'=>mb_cut((string)$e['notes'],1500)],JSON_INVALID_UTF8_SUBSTITUTE);
        $prompt.="\nCurrent recipe record: ".json_encode($r,JSON_INVALID_UTF8_SUBSTITUTE).($r===null?" (No recipe supplied; do not infer ingredients.)":"");
        $prompt.="\nDough batch tracking (if known; do not infer any missing process timestamps): ".json_encode(['batch'=>$batch,'recipe_at_batch_creation'=>$batch===null?null:json_decode($batch['recipe_snapshot']??'null',true),'timeline'=>$batchEvents,'linked_test_aggregates'=>$batchStats,'production_process'=>$processInfo],JSON_INVALID_UTF8_SUBSTITUTE);
        $prompt.="\nIf a historical batch recipe snapshot and its current recipe record differ, use the snapshot as the batch formula. Batch aggregation is correlation and is not a controlled causal experiment. Do not infer precise pizza cook age from evidence creation date.";
        if($kind==='live') {
            $latestBox=query('SELECT x_norm,y_norm,width_norm,height_norm,source,confidence FROM pizza_tracker_boxes WHERE media_id=?',[(int)$media[0]['id']])->fetch();
            if($latestBox)$prompt.="\nCamera tracker bounding-box guide (normalized coordinates, not verified object recognition): ".json_encode($latestBox).". Inspect the entire image; do not assume the highlighted area is actually a pizza.";
        }
        $contents=[['type'=>'text','text'=>$prompt]];
        foreach ($media as $m) {
            $f=DATA_ROOT.'/uploads/'.basename($m['stored_name']);if (!is_file($f)) continue;
            $contents[]=['type'=>'text','text'=>'Image: '.$m['kind']];
            $contents[]=['type'=>'image','source'=>['type'=>'base64','media_type'=>$m['mime'],'data'=>base64_encode(file_get_contents($f))]];
        }
        $payload=['model'=>setting('model','claude-haiku-5-5'),'max_tokens'=>700,'messages'=>[['role'=>'user','content'=>$contents]]];
        $ch=curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>40,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>['x-api-key: '.decrypted_key($enc),'anthropic-version: 2023-06-01','content-type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);
        $resp=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
        if (!$resp || $http<200 || $http>=300) { $api=json_decode((string)$resp,true); fail('Claude request failed: '.($api['error']['message']??($err?:'HTTP '.$http)),502); }
        $out=json_decode($resp,true);$text=implode("\n",array_map(fn($a)=>$a['text']??'',array_filter($out['content']??[],fn($a)=>($a['type']??'')==='text')));
        if ($text==='') fail('No analysis returned.',502);
        $parsed=json_decode(trim(preg_replace('/^```(?:json)?\s*|\s*```$/m','',$text)),true);
        if (!is_array($parsed)) $parsed=['summary'=>mb_cut($text,2000),'confidence'=>'low','visual_findings'=>[],'suggestions'=>[],'warning'=>'','turn_now'=>false];
        $parsed=['summary'=>plain($parsed['summary']??'',2000),'confidence'=>in_array($parsed['confidence']??'', ['low','medium','high'],true)?$parsed['confidence']:'low','visual_findings'=>array_slice(array_values(array_filter((array)($parsed['visual_findings']??[]),'is_string')),0,4),'suggestions'=>array_slice(array_values(array_filter((array)($parsed['suggestions']??[]),'is_string')),0,4),'warning'=>plain($parsed['warning']??'',500),'turn_now'=>($parsed['turn_now']??false)===true];
        query('INSERT INTO observations (evidence_id,kind,text) VALUES (?,?,?)',[$id,$kind,json_encode($parsed,JSON_INVALID_UTF8_SUBSTITUTE)]);
        json_out(['ok'=>true,'analysis'=>$parsed]);
    }
    fail('Unknown action',404);
} catch (Throwable $e) { error_log('Pizza Lab API error: '.$e->getMessage()); fail('Server error. Check PHP error log.',500); }
