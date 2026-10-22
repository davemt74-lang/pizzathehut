<?php
declare(strict_types=1);
require_once __DIR__.'/workday_lib.php';

/** Requires an open transaction. Serializes daily creation across every chef. */
function lab_chat_daily_workday_locked(string $date,int $userId,string $title='Pizza production'): array {
    query('INSERT IGNORE INTO workday_daily_registry (work_date) VALUES (?)',[$date]);
    $registry=query('SELECT workday_id FROM workday_daily_registry WHERE work_date=? FOR UPDATE',[$date])->fetch(PDO::FETCH_ASSOC);
    if(!$registry)throw new RuntimeException('Unable to reserve today’s workday.');
    if($registry['workday_id']!==null){
        $day=query('SELECT id,title,work_date FROM workdays WHERE id=?',[(int)$registry['workday_id']])->fetch(PDO::FETCH_ASSOC);
        if(!$day)throw new RuntimeException('Daily workday reference is missing.');
        return $day;
    }
    // Reuse an existing day if one was recorded before the shared-day registry.
    // Do not delete or merge any historical workday records.
    $day=query('SELECT id,title,work_date FROM workdays WHERE work_date=? ORDER BY id ASC LIMIT 1',[$date])->fetch(PDO::FETCH_ASSOC);
    if(!$day){
        query('INSERT INTO workdays (work_date,title,notes,created_by_user_id) VALUES (?,?,?,?)',[$date,$title,'',$userId]);
        $day=['id'=>(int)db()->lastInsertId(),'title'=>$title,'work_date'=>$date];
    }
    query('UPDATE workday_daily_registry SET workday_id=? WHERE work_date=?',[(int)$day['id'],$date]);
    return $day;
}

function lab_chat_daily_workday_ensure(string $date,int $userId,string $title='Pizza production'): array {
    if(!lab_valid_work_date($date))fail('Invalid local workday date.');
    $db=db();$db->beginTransaction();
    try{
        $day=lab_chat_daily_workday_locked($date,$userId,$title);
        $db->commit();
        return ['id'=>(int)$day['id'],'title'=>(string)$day['title'],'work_date'=>(string)$day['work_date']];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

/** Each chef has a durable current pizza. Other chefs' pizzas never become the default. */
function lab_chat_pizza_context(int $userId): array {
    $records=query('SELECT e.id,e.label,e.created_at,w.workday_id,t.status AS timer_status FROM evidence e
        LEFT JOIN workday_pizzas w ON w.evidence_id=e.id
        LEFT JOIN kitchen_activity_timers t ON t.evidence_id=e.id
        WHERE e.created_by_user_id=? ORDER BY e.id DESC LIMIT 100',[$userId])->fetchAll(PDO::FETCH_ASSOC);
    $selected=query('SELECT f.evidence_id FROM chat_pizza_focus f JOIN evidence e ON e.id=f.evidence_id
        WHERE f.user_id=? AND e.created_by_user_id=?',[$userId,$userId])->fetchColumn();
    $current=$selected===false?($records[0]['id']??null):$selected;
    return ['current_id'=>$current===null?null:(int)$current,
        'pizzas'=>array_map(static fn(array $r):array=>[
            'id'=>(int)$r['id'],'label'=>(string)$r['label'],'created_at'=>(string)$r['created_at'],
            'workday_id'=>$r['workday_id']===null?null:(int)$r['workday_id'],
            'timer_status'=>$r['timer_status']??null
        ],$records)];
}

function lab_chat_pizza_focus(int $userId,int $evidenceId): array {
    $pizza=query('SELECT id,label FROM evidence WHERE id=? AND created_by_user_id=?',[$evidenceId,$userId])->fetch(PDO::FETCH_ASSOC);
    if(!$pizza)fail('Choose a pizza recorded by your account.',403);
    query('INSERT INTO chat_pizza_focus (user_id,evidence_id) VALUES (?,?)
        ON DUPLICATE KEY UPDATE evidence_id=VALUES(evidence_id),updated_at=CURRENT_TIMESTAMP',[$userId,$evidenceId]);
    return ['id'=>(int)$pizza['id'],'label'=>(string)$pizza['label']];
}

/** One transaction: idempotency, owned workday, pizza evidence, link and focus. */
function lab_chat_pizza_new(array $input,int $userId): array {
    $clientId=(string)($input['client_event_id']??'');
    if(!preg_match('/^[A-Za-z0-9_-]{8,90}$/D',$clientId))fail('Invalid pizza creation request identifier.');
    $date=(string)($input['work_date']??'');
    if(!lab_valid_work_date($date))fail('Invalid local workday date.');
    $db=db();$db->beginTransaction();
    try {
        // A per-account lock prevents racing chef focus changes.
        $owner=query('SELECT id FROM users WHERE id=? AND is_active=1 FOR UPDATE',[$userId])->fetchColumn();
        if($owner===false)throw new RuntimeException('Signed-in chef not available.');
        $prior=query('SELECT e.id,e.label,w.workday_id FROM evidence e LEFT JOIN workday_pizzas w ON w.evidence_id=e.id
            WHERE e.created_by_user_id=? AND e.chat_create_request_id=?',[$userId,$clientId])->fetch(PDO::FETCH_ASSOC);
        if($prior){
            $db->commit();
            return ['id'=>(int)$prior['id'],'label'=>$prior['label'],'workday_id'=>$prior['workday_id']===null?null:(int)$prior['workday_id'],'existing'=>true];
        }
        // Every user joins the same canonical daily kitchen workday.
        $day=lab_chat_daily_workday_locked($date,$userId);
        // A single current recipe is safe to link. Unknown/ambiguous batch and
        // physical measurements remain NULL until the chef supplies evidence.
        $recipes=query("SELECT id FROM recipes WHERE status='current' ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
        $recipe=count($recipes)===1?(int)$recipes[0]:null;
        query('INSERT INTO evidence (created_by_user_id,chat_create_request_id,recipe_id,label)
            VALUES (?,?,?,?)',[$userId,$clientId,$recipe,'Pizza in progress']);
        $id=(int)$db->lastInsertId();$label='Pizza #'.$id;
        query('UPDATE evidence SET label=? WHERE id=?',[$label,$id]);
        query('INSERT INTO workday_pizzas (evidence_id,workday_id,recorded_by_user_id) VALUES (?,?,?)',[$id,(int)$day['id'],$userId]);
        query('INSERT INTO chat_pizza_focus (user_id,evidence_id) VALUES (?,?)
            ON DUPLICATE KEY UPDATE evidence_id=VALUES(evidence_id),updated_at=CURRENT_TIMESTAMP',[$userId,$id]);
        $db->commit();
        return ['id'=>$id,'label'=>$label,'workday_id'=>(int)$day['id'],'workday_title'=>(string)$day['title'],
            'recipe_id'=>$recipe,'existing'=>false];
    } catch (Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
