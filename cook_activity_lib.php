<?php
declare(strict_types=1);
/** Typed, auditable cooking events. Timed reminders are NEVER evidence of physical movement. */
function lab_cook_activity(string $event, string $source, mixed $seconds, mixed $mediaId): array {
    $types=[
      'timer_started'=>['manual','voice_command'], 'timer_stopped'=>['manual','voice_command'],
      'timer_paused'=>['manual'], 'timer_resumed'=>['manual'], 'timer_reset'=>['manual'],
      'pull_back_reminder'=>['agent_alert'], 'rotation_reminder'=>['agent_alert'],
      'time_left_reminder'=>['agent_alert'], 'time_up_reminder'=>['agent_alert'],
      'photo_captured'=>['camera','voice_command'], 'analysis_complete'=>['system'],
      'visual_turn_suggestion'=>['ai_suggestion'],
      'pull_back_confirmed'=>['manual'], 'rotation_confirmed'=>['manual'],
      'pizza_finished'=>['manual']
    ];
    if(!array_key_exists($event,$types)||!in_array($source,$types[$event],true)) throw new InvalidArgumentException('Unrecognized cooking activity or source.');
    $class = in_array($event,['pull_back_reminder','rotation_reminder','time_left_reminder','time_up_reminder'],true) ? 'reminder' : ($event==='visual_turn_suggestion'?'suggestion':'observed');
    $time=null;
    if($seconds!==null&&$seconds!==''){
        if(!is_numeric($seconds)||!preg_match('/^\d+$/',(string)$seconds)||(int)$seconds>7200)throw new InvalidArgumentException('Invalid elapsed time.');
        $time=(int)$seconds;
    }
    $media=null;
    if($mediaId!==null&&$mediaId!==''){
        if(!is_numeric($mediaId)||!preg_match('/^[1-9]\d*$/',(string)$mediaId))throw new InvalidArgumentException('Invalid media reference.');
        $media=(int)$mediaId;
    }
    if($event==='photo_captured'&&$media===null) throw new InvalidArgumentException('A captured photo must reference saved media.');
    if($event!=='photo_captured'&&$media!==null)throw new InvalidArgumentException('Only photo captures can reference media.');
    return ['event_type'=>$event,'source'=>$source,'event_class'=>$class,'elapsed_seconds'=>$time,'media_id'=>$media];
}
function lab_cook_label(string $type):string {
    return [
      'timer_started'=>'Timer started','timer_stopped'=>'Timer stopped',
      'timer_paused'=>'Timer paused','timer_resumed'=>'Timer resumed','timer_reset'=>'Timer reset',
      'pull_back_reminder'=>'Pull-back reminder sent (not confirmed)',
      'rotation_reminder'=>'Rotation reminder sent (not confirmed)',
      'time_left_reminder'=>'Time-left announcement','time_up_reminder'=>'Timer ended; check pizza',
      'photo_captured'=>'Pizza photo captured','analysis_complete'=>'Photo analysis finished',
      'visual_turn_suggestion'=>'AI suggests checking rotation (not confirmed)',
      'pull_back_confirmed'=>'Chef confirmed pull-back','rotation_confirmed'=>'Chef confirmed rotation',
      'pizza_finished'=>'Chef marked pizza finished'
    ][$type]??$type;
}
