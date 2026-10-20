<?php
/** Dough readiness is a logged-observation aid, not an automatic proofing or safety verdict. */
declare(strict_types=1);
function lab_readiness_target_hours(?string $stepsJson): ?float {
    if(!$stepsJson)return null;
    $steps=json_decode($stepsJson,true);if(!is_array($steps))return null;
    foreach($steps as $s){
        if(!is_array($s))continue;
        if(($s['stage']??'')==='cold_ball' && isset($s['target_min_minutes']) && is_numeric($s['target_min_minutes']) && (float)$s['target_min_minutes']>0)
            return round((float)$s['target_min_minutes']/60,2);
    }
    return null;
}
function lab_readiness_assess(array $batch,array $checks,?int $now=null): array {
    $now??=time();$ballTs=!empty($batch['balled_at'])?strtotime((string)$batch['balled_at']):false;
    $age=$ballTs!==false&&$now>=$ballTs?round(($now-$ballTs)/3600,1):null;
    $target=lab_readiness_target_hours($batch['process_steps_snapshot']??null);
    $latest=$checks[0]??null;
    $problems=[];
    if($age===null)$problems[]='Balling date not recorded';
    if($target===null)$problems[]='No planned cold-ball fermentation target in the frozen batch timeline';
    if(!$latest)$problems[]='No actual dough-condition check recorded';
    if($latest&&!isset($latest['dough_temp_f']))$problems[]='Most recent dough temperature not recorded';
    $status='Insufficient observations';
    if($target!==null && $age!==null){
        $status=$age<$target*.85?'Before scheduled check window':($age>$target*1.15?'After scheduled target':'Near scheduled target');
    }
    if($latest&&in_array($latest['handling']??'',['slack','sticky','tearing'],true))$problems[]='Recorded handling may indicate a weak or difficult dough; inspect before baking';
    $certainty=$age!==null && $target!==null && $latest && $latest['dough_temp_f']!==null?'limited':'very limited';
    return ['status'=>$status,'hours_since_balling'=>$age,'target_ball_hours'=>$target,'latest_check'=>$latest,'evidence_confidence'=>$certainty,'cautions'=>$problems,'recommendation'=>'Compare handling, gas retention and actual pizza results. Time on target alone does not establish readiness.'];
}
