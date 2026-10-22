<?php
declare(strict_types=1);
/**
 * Pizza Lab v1.5.16A: server-owned kitchen timers.
 * Times are UTC MySQL DATETIME(6), NOT browser-local time.
 * All mutations occur while the parent pizza evidence row is locked.
 * A reminder or a photo is never proof that a chef moved a pizza.
 */
function lab_timer_event(string $event): bool {
    return in_array($event,['timer_started','timer_stopped','timer_paused','timer_resumed','timer_reset','pizza_finished'],true);
}
function lab_timer_snapshot(int $evidenceId): ?array {
    $row=query("SELECT t.evidence_id,t.status,t.accumulated_ms,t.target_seconds,t.revision,t.updated_by_user_id,t.updated_at,
      CASE WHEN t.status='running' THEN t.accumulated_ms + GREATEST(0,TIMESTAMPDIFF(MICROSECOND,t.started_at,UTC_TIMESTAMP(6)) DIV 1000)
      ELSE t.accumulated_ms END AS elapsed_ms
      FROM kitchen_activity_timers t WHERE t.evidence_id=?",[$evidenceId])->fetch(PDO::FETCH_ASSOC);
    if(!$row)return null;
    return [
      'evidence_id'=>(int)$row['evidence_id'],'status'=>$row['status'],
      'elapsed_ms'=>(int)$row['elapsed_ms'],
      'target_seconds'=>$row['target_seconds']===null?null:(int)$row['target_seconds'],
      'revision'=>(int)$row['revision'],'updated_by_user_id'=>(int)$row['updated_by_user_id'],
      'updated_at'=>$row['updated_at'].' UTC'
    ];
}
function lab_timer_list(?int $userId=null): array {
    // Legacy views remain shared. Chat requests only this signed-in chef's pizzas.
    $rows=query("SELECT e.id AS evidence_id,e.label,t.status,t.accumulated_ms,t.target_seconds,t.revision,t.updated_by_user_id,t.updated_at,
      CASE WHEN t.status='running' THEN t.accumulated_ms + GREATEST(0,TIMESTAMPDIFF(MICROSECOND,t.started_at,UTC_TIMESTAMP(6)) DIV 1000)
      ELSE t.accumulated_ms END AS elapsed_ms
      FROM kitchen_activity_timers t JOIN evidence e ON e.id=t.evidence_id
      WHERE t.status IN ('running','paused')".($userId===null?'':' AND e.created_by_user_id=?')."
      ORDER BY t.updated_at DESC,t.evidence_id DESC LIMIT 50",$userId===null?[]:[$userId])->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $r)=>[
      'evidence_id'=>(int)$r['evidence_id'],'label'=>$r['label'],'status'=>$r['status'],
      'elapsed_ms'=>(int)$r['elapsed_ms'],
      'target_seconds'=>$r['target_seconds']===null?null:(int)$r['target_seconds'],
      'revision'=>(int)$r['revision'],'updated_at'=>$r['updated_at'].' UTC',
      'updated_by_user_id'=>(int)$r['updated_by_user_id']
    ],$rows);
}
/**
 * Existing oven_cook_activity remains the audit source of truth.
 * Dedupe by actor/client ID is checked INSIDE the evidence row lock.
 * Invalid state changes insert no event and change no timer.
 */
function lab_timer_activity_save(int $evidenceId,array $event,string $clientId,int $userId,?int $sessionId,?int $targetSeconds): array {
    if(!lab_timer_event($event['event_type']))throw new InvalidArgumentException('Not a timer transition.');
    if($targetSeconds!==null&&($targetSeconds<10||$targetSeconds>7200))fail('Timer target must be between 10 and 7200 seconds.');
    if($targetSeconds!==null&&$event['event_type']!=='timer_started')fail('Timer target can only be set when starting.');
    $db=db();$db->beginTransaction();
    try{
        // Serializes two different devices acting on the same pizza.
        $lock=query('SELECT id FROM evidence WHERE id=? FOR UPDATE',[$evidenceId])->fetchColumn();
        if($lock===false)fail('Unknown pizza.',404);
        $prev=query('SELECT id,evidence_id,event_type,source,elapsed_seconds,media_id FROM oven_cook_activity WHERE created_by_user_id=? AND client_event_id=?',[$userId,$clientId])->fetch(PDO::FETCH_ASSOC);
        if($prev){
            if((int)$prev['evidence_id']!==$evidenceId||$prev['event_type']!==$event['event_type']||
               $prev['source']!==$event['source']||
               ($prev['elapsed_seconds']===null?null:(int)$prev['elapsed_seconds'])!==$event['elapsed_seconds']||
               ($prev['media_id']===null?null:(int)$prev['media_id'])!==$event['media_id'])fail('Event identifier already used for another event.',409);
            $state=lab_timer_snapshot($evidenceId);
            $db->commit();
            return ['ok'=>true,'id'=>(int)$prev['id'],'existing'=>true,'event_class'=>$event['event_class'],'timer'=>$state];
        }
        // The same actor/client identifier may already be used for another pizza.
        $current=query('SELECT status,accumulated_ms,started_at,target_seconds FROM kitchen_activity_timers WHERE evidence_id=? FOR UPDATE',[$evidenceId])->fetch(PDO::FETCH_ASSOC);
        $state=$current['status']??'stopped';
        $elapsed=$current ? (int)query("SELECT CASE WHEN status='running' THEN accumulated_ms + GREATEST(0,TIMESTAMPDIFF(MICROSECOND,started_at,UTC_TIMESTAMP(6)) DIV 1000) ELSE accumulated_ms END FROM kitchen_activity_timers WHERE evidence_id=?",[$evidenceId])->fetchColumn() : 0;
        $kind=$event['event_type'];
        if($kind==='timer_started' && $state!=='stopped')fail('This pizza already has an active timer. Stop it before starting another.',409);
        if($kind==='timer_paused' && $state!=='running')fail('Only a running timer can be paused.',409);
        if($kind==='timer_resumed' && $state!=='paused')fail('Only a paused timer can be resumed.',409);
        if($kind==='timer_stopped' && !in_array($state,['running','paused'],true))fail('There is no active timer to stop.',409);
        if($kind==='timer_reset' && $state==='running')fail('Pause or stop this timer before resetting it.',409);
        if($kind==='timer_reset' && !$current)fail('There is no timer to reset.',409);
        if($kind==='timer_started'){$state='running';$elapsed=0;$target=$targetSeconds;$running=true;}
        elseif($kind==='timer_resumed'){$state='running';$target=$current['target_seconds'];$running=true;}
        elseif($kind==='timer_paused'){$state='paused';$target=$current['target_seconds'];$running=false;}
        elseif($kind==='timer_stopped'||$kind==='pizza_finished'){$state='stopped';$target=$current['target_seconds']??null;$running=false;}
        else{$state='stopped';$target=null;$elapsed=0;$running=false;}
        // A finish event without a timer remains a valid physical log; it does not create a timer.
        query('INSERT INTO oven_cook_activity (evidence_id,session_id,event_type,source,event_class,elapsed_seconds,media_id,client_event_id,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?)',[
            $evidenceId,$sessionId,$event['event_type'],$event['source'],$event['event_class'],$event['elapsed_seconds'],$event['media_id'],$clientId,$userId
        ]);
        $activityId=(int)$db->lastInsertId();
        if($current || $kind!=='pizza_finished'){
            query("INSERT INTO kitchen_activity_timers
              (evidence_id,status,started_at,accumulated_ms,target_seconds,revision,updated_by_user_id,updated_at)
              VALUES (?,?,IF(?=1,UTC_TIMESTAMP(6),NULL),?,?,1,?,UTC_TIMESTAMP(6))
              ON DUPLICATE KEY UPDATE status=VALUES(status),started_at=VALUES(started_at),
              accumulated_ms=VALUES(accumulated_ms),target_seconds=VALUES(target_seconds),
              revision=revision+1,updated_by_user_id=VALUES(updated_by_user_id),updated_at=VALUES(updated_at)",[
              $evidenceId,$state,$running?1:0,$elapsed,$target,$userId
            ]);
        }
        $result=lab_timer_snapshot($evidenceId);
        $db->commit();
        return ['ok'=>true,'id'=>$activityId,'existing'=>false,'event_class'=>$event['event_class'],'timer'=>$result];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
