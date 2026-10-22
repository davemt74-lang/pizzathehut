<?php
declare(strict_types=1);
/**
 * v1.5.16B foreground reminder polling.
 * Deterministic milestones use the chef-selected timer target; no made-up cook duration.
 * A reminder is advice, NEVER confirmation of any physical pizza action.
 */
function lab_reminder_milestone(int $elapsedMs, ?int $targetSeconds): ?string {
    if($targetSeconds===null || $targetSeconds<10 || $targetSeconds>7200 || $elapsedMs<0)return null;
    $targetMs=$targetSeconds*1000;
    if($elapsedMs >= $targetMs)return 'time_up_reminder';
    if($elapsedMs >= (int)ceil($targetMs*0.75))return 'pull_back_reminder';
    if($elapsedMs >= (int)ceil($targetMs*0.40))return 'rotation_reminder';
    return null;
}
function lab_reminder_text(string $kind,string $label): string {
    return match($kind){
        'rotation_reminder'=>'Pizza '.$label.': rotation check is due. Inspect the side facing the heat. Tell me after you actually rotate it.',
        'pull_back_reminder'=>'Pizza '.$label.': pull-back check is due. Check the rim and underside before deciding what to do.',
        'time_up_reminder'=>'Pizza '.$label.': planned cooking time has elapsed. Inspect the pizza and confirm when it is finished.',
        default=>'Kitchen reminder'
    };
}
/**
 * Called from an authenticated, CSRF-protected POST only while the browser is active.
 * The existing evidence-row lock serializes reminder generation against timer changes
 * and other clients' polls; unique(event generation, kind) prevents repeats.
 */
function lab_reminder_generate(int $evidenceId,int $userId): void {
    $db=db();$db->beginTransaction();
    try {
        if(query('SELECT id FROM evidence WHERE id=? FOR UPDATE',[$evidenceId])->fetchColumn()===false){
            $db->commit();return;
        }
        $timer=query("SELECT status,target_seconds,
            CASE WHEN status='running' THEN accumulated_ms+GREATEST(0,TIMESTAMPDIFF(MICROSECOND,started_at,UTC_TIMESTAMP(6)) DIV 1000)
                 ELSE accumulated_ms END AS elapsed_ms
            FROM kitchen_activity_timers WHERE evidence_id=? FOR UPDATE",[$evidenceId])->fetch(PDO::FETCH_ASSOC);
        if(!$timer || $timer['status']!=='running' || $timer['target_seconds']===null){$db->commit();return;}
        $kind=lab_reminder_milestone((int)$timer['elapsed_ms'],(int)$timer['target_seconds']);
        if($kind===null){$db->commit();return;}
        $started=query("SELECT id FROM oven_cook_activity WHERE evidence_id=? AND event_type='timer_started' ORDER BY id DESC LIMIT 1",[$evidenceId])->fetchColumn();
        if($started===false){$db->commit();return;}
        $exists=query('SELECT id FROM kitchen_timer_reminders WHERE evidence_id=? AND start_activity_id=? AND reminder_type=?',[$evidenceId,(int)$started,$kind])->fetchColumn();
        if($exists!==false){$db->commit();return;}
        $eventId='kitchen_reminder_'.(int)$started.'_'.str_replace('_reminder','',$kind);
        $elapsed=min(7200,(int)floor((int)$timer['elapsed_ms']/1000));
        query("INSERT INTO oven_cook_activity (evidence_id,session_id,event_type,source,event_class,elapsed_seconds,media_id,client_event_id,created_by_user_id)
            VALUES (?,NULL,?,'agent_alert','reminder',?,NULL,?,?)",[$evidenceId,$kind,$elapsed,$eventId,$userId]);
        $activityId=(int)$db->lastInsertId();
        query('INSERT INTO kitchen_timer_reminders (evidence_id,start_activity_id,activity_id,reminder_type,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP(6))',[
            $evidenceId,(int)$started,$activityId,$kind
        ]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/**
 * One delivery per signed-in user. Multiple devices on that account compete for
 * the receipt; duplicate polling does not produce repeated chat interruptions.
 * Claiming is best-effort (a lost HTTP response can lose a display), not push delivery.
 */
function lab_reminder_poll(int $userId,bool $mineOnly=false): array {
    $ids=query("SELECT t.evidence_id FROM kitchen_activity_timers t JOIN evidence e ON e.id=t.evidence_id
        WHERE t.status='running' AND t.target_seconds IS NOT NULL".($mineOnly?' AND e.created_by_user_id=?':'')."
        ORDER BY t.updated_at DESC,t.evidence_id DESC LIMIT 50",$mineOnly?[$userId]:[])->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id)lab_reminder_generate((int)$id,$userId);
    $db=db();$db->beginTransaction();
    try{
        $available=query("SELECT r.id,r.evidence_id,r.reminder_type,r.created_at,e.label
            FROM kitchen_timer_reminders r JOIN evidence e ON e.id=r.evidence_id
            JOIN kitchen_activity_timers t ON t.evidence_id=r.evidence_id AND t.status='running'
            WHERE r.created_at>=UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE
              AND r.start_activity_id=(SELECT MAX(a.id) FROM oven_cook_activity a
                  WHERE a.evidence_id=r.evidence_id AND a.event_type='timer_started')
              ".($mineOnly?'AND e.created_by_user_id=?':'')."
              AND NOT EXISTS (SELECT 1 FROM kitchen_reminder_receipts x
                  WHERE x.reminder_id=r.id AND x.user_id=?)
            ORDER BY r.id ASC LIMIT 20",$mineOnly?[$userId,$userId]:[$userId])->fetchAll(PDO::FETCH_ASSOC);
        $claimed=[];
        foreach($available as $row){
            query("INSERT IGNORE INTO kitchen_reminder_receipts (reminder_id,user_id,claimed_at)
                VALUES (?,?,UTC_TIMESTAMP(6))",[(int)$row['id'],$userId]);
            if(((int)db()->query('SELECT ROW_COUNT()')->fetchColumn())===1){
                $claimed[]=[
                    'id'=>(int)$row['id'],'evidence_id'=>(int)$row['evidence_id'],
                    'kind'=>$row['reminder_type'],'message'=>lab_reminder_text($row['reminder_type'],(string)$row['label']),
                    'created_at'=>$row['created_at'].' UTC'
                ];
            }
        }
        $db->commit();
        return $claimed;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
