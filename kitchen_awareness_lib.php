<?php
declare(strict_types=1);
/**
 * v1.5.16E — bounded, read-only active kitchen snapshot.
 * Conversational guide progress belongs to its signed-in user; pizza timers
 * reflect shared kitchen evidence. Data is not a command to the model.
 */
function lab_kitchen_awareness(int $userId): array {
    $sessions=query("SELECT s.id AS session_id,s.batch_id,s.next_step_index,s.status,
              b.batch_code,b.process_steps_snapshot,r.name AS recipe_name
          FROM chat_batch_sessions s
          JOIN dough_batches b ON b.id=s.batch_id
          JOIN recipes r ON r.id=s.recipe_id
          WHERE s.user_id=? AND s.status='active'
          ORDER BY s.updated_at DESC,s.id DESC LIMIT 8",[$userId])->fetchAll(PDO::FETCH_ASSOC);
    $guides=[];
    foreach($sessions as $s){
        $steps=json_decode((string)($s['process_steps_snapshot']??''),true);
        $idx=(int)$s['next_step_index'];
        $step=is_array($steps)&&isset($steps[$idx])&&is_array($steps[$idx])?$steps[$idx]:null;
        $guides[]=[
            'session_id'=>(int)$s['session_id'],
            'batch_id'=>(int)$s['batch_id'],
            'batch_code'=>$s['batch_code'],
            'recipe_name'=>$s['recipe_name'],
            'confirmed_step_count'=>$idx,
            'total_steps'=>is_array($steps)?count($steps):null,
            'next_step'=>!$step?null:[
                'key'=>(string)($step['step_key']??''),
                'title'=>mb_cut((string)($step['title']??''),160),
                'instructions'=>mb_cut((string)($step['instructions']??''),400),
                'duration_min_minutes'=>$step['target_min_minutes']===null?null:(int)$step['target_min_minutes'],
                'duration_max_minutes'=>$step['target_max_minutes']===null?null:(int)$step['target_max_minutes']
            ]
        ];
    }
    $timers=query("SELECT t.evidence_id,e.label,t.status,t.target_seconds,t.updated_at,
          CASE WHEN t.status='running' THEN t.accumulated_ms+GREATEST(0,TIMESTAMPDIFF(MICROSECOND,t.started_at,UTC_TIMESTAMP(6)) DIV 1000)
          ELSE t.accumulated_ms END AS elapsed_ms
          FROM kitchen_activity_timers t
          JOIN evidence e ON e.id=t.evidence_id
          WHERE t.status IN ('running','paused')
          ORDER BY t.updated_at DESC,t.evidence_id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
    $active=[];
    foreach($timers as $t){
        $id=(int)$t['evidence_id'];
        $last=query("SELECT a.event_type,a.source,a.event_class,a.occurred_at
          FROM oven_cook_activity a
          WHERE a.evidence_id=? AND a.event_class='observed'
          ORDER BY a.id DESC LIMIT 1",[$id])->fetch(PDO::FETCH_ASSOC)?:null;
        $alerts=query("SELECT r.reminder_type,r.created_at
          FROM kitchen_timer_reminders r
          WHERE r.evidence_id=?
          AND r.start_activity_id=(SELECT MAX(a.id) FROM oven_cook_activity a
                      WHERE a.evidence_id=? AND a.event_type='timer_started')
          ORDER BY r.id DESC LIMIT 3",[$id,$id])->fetchAll(PDO::FETCH_ASSOC);
        $elapsed=(int)$t['elapsed_ms'];
        $target=$t['target_seconds']===null?null:(int)$t['target_seconds'];
        $active[]=[
            'pizza_id'=>$id,
            'pizza_label'=>mb_cut((string)$t['label'],120),
            'status'=>$t['status'],
            'elapsed_seconds'=>(int)floor($elapsed/1000),
            'planned_target_seconds'=>$target,
            'planned_time_reached'=>$target===null?null:$elapsed >= $target*1000,
            'last_recorded_physical_event'=>$last,
            'advisory_reminders_emitted'=>$alerts
        ];
    }
    return [
        'snapshot_utc'=>(string)query('SELECT UTC_TIMESTAMP(6)')->fetchColumn().' UTC',
        'guide_count_included'=>count($guides),
        'active_guides'=>$guides,
        'timer_count_included'=>count($active),
        'active_pizza_timers'=>$active,
        'scope_and_caveats'=>[
            'Only the signed-in account’s active batch guides are listed; pizza timers are shared kitchen records.',
            'This is a read-only snapshot, not a live camera view, ambient microphone feed, or scheduled background job.',
            'Elapsed seconds are a server-time snapshot and may have advanced since the request.',
            'A reminder is an advisory to check; it is NOT evidence that a pizza was physically rotated, pulled back, or finished.',
            'Missing logs are unknown, never proof an action did not occur.',
            'No recipe step, timer, measurement or physical action may be changed by a Claude response.'
        ]
    ];
}
