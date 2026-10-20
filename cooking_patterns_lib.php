<?php
declare(strict_types=1);
/**
 * Pizza Lab v1.5.8: observational cooking-pattern metrics.
 * Never infer actual movement from reminders or estimate absent temperatures.
 */
function lab_pattern_mean(array $values): ?float {
    $values=array_values(array_filter($values,static fn($v)=>$v!==null && $v!=='' && is_numeric($v)));
    return $values ? round(array_sum(array_map('floatval',$values))/count($values),2) : null;
}
function lab_pattern_first_elapsed(array $positions,array $activities,string $phase,string $confirmed): ?int {
    $times=[];
    foreach($positions as $x) if(($x['phase']??null)===$phase && isset($x['elapsed_seconds']))$times[]=(int)$x['elapsed_seconds'];
    foreach($activities as $x) if(($x['event_type']??null)===$confirmed && ($x['event_class']??null)==='observed' && isset($x['elapsed_seconds']))$times[]=(int)$x['elapsed_seconds'];
    return $times ? min($times) : null;
}
function lab_pattern_summarize(array $pizzas): array {
    $grouped=[];
    foreach($pizzas as $p){
        $name=(string)($p['recipe_name']??'Unknown recipe');
        $zone=(string)($p['launch_zone']??'');
        $zone=$zone!==''?$zone:'Not recorded';
        $key=$name.' · '.$zone;
        if(!isset($grouped[$key]))$grouped[$key]=['label'=>$key,'recipe'=>$name,'launch_zone'=>$zone,
            'pizza_count'=>0,'crispness'=>[],'bottom_color'=>[],'rim_rise'=>[],'bake_seconds'=>[],
            'pull_back_seconds'=>[],'rotation_seconds'=>[],'stone_f'=>[]];
        $g=&$grouped[$key];$g['pizza_count']++;
        foreach(['crispness','bottom_color','rim_rise','bake_seconds','pull_back_seconds','rotation_seconds','stone_f'] as $metric) {
            if(isset($p[$metric])&&$p[$metric]!==''&&is_numeric($p[$metric]))$g[$metric][]=(float)$p[$metric];
        }
        unset($g);
    }
    $rows=[];
    foreach($grouped as $g){
        $row=['label'=>$g['label'],'recipe'=>$g['recipe'],'launch_zone'=>$g['launch_zone'],'pizza_count'=>$g['pizza_count']];
        foreach(['crispness','bottom_color','rim_rise','bake_seconds','pull_back_seconds','rotation_seconds','stone_f'] as $metric){
            $row[$metric.'_avg']=lab_pattern_mean($g[$metric]);
            $row[$metric.'_n']=count($g[$metric]);
        }
        $row['comparison_eligible']=$g['pizza_count']>=3&&$row['crispness_n']>=3;
        $rows[]=$row;
    }
    usort($rows,static fn($a,$b)=>$b['pizza_count']<=>$a['pizza_count']);
    return $rows;
}
function lab_pattern_pizzas(?int $ovenId=null,?int $recipeId=null): array {
    $pizzas=query('SELECT e.id,e.recipe_id,r.name AS recipe_name,e.batch_id,e.rim_rise_score,e.crispness_score,
      e.bottom_color_score,e.bake_seconds,e.stone_start_f,cs.id AS session_id,o.name AS oven_name
      FROM oven_cook_sessions cs
      JOIN evidence e ON e.id=cs.evidence_id
      LEFT JOIN recipes r ON r.id=e.recipe_id
      JOIN oven_layouts o ON o.id=cs.oven_layout_id
      WHERE (? IS NULL OR cs.oven_layout_id=?) AND (? IS NULL OR e.recipe_id=?)
      ORDER BY e.id DESC LIMIT 150',[$ovenId,$ovenId,$recipeId,$recipeId])->fetchAll();
    $out=[];
    foreach($pizzas as $p){
        $positions=query('SELECT phase,zone_key,zone_name,elapsed_seconds,stone_f FROM oven_cook_events WHERE session_id=? ORDER BY id ASC LIMIT 100',[(int)$p['session_id']])->fetchAll();
        $activity=query("SELECT event_type,event_class,elapsed_seconds FROM oven_cook_activity WHERE evidence_id=? AND event_class='observed' ORDER BY id ASC LIMIT 120",[(int)$p['id']])->fetchAll();
        $launch=null;$temp=null;
        foreach($positions as $x){
            if(($x['phase']??'')==='launch'){
                $launch=(string)($x['zone_name']?:$x['zone_key']);
                $temp=$x['stone_f']===null?null:(float)$x['stone_f'];
                break;
            }
        }
        $out[]=['id'=>(int)$p['id'],'recipe_name'=>$p['recipe_name']??'Unassigned','batch_id'=>$p['batch_id'],
          'oven_name'=>$p['oven_name'],'launch_zone'=>$launch,'stone_f'=>$temp??($p['stone_start_f']===null?null:(float)$p['stone_start_f']),
          'crispness'=>$p['crispness_score'],'bottom_color'=>$p['bottom_color_score'],
          'rim_rise'=>$p['rim_rise_score'],'bake_seconds'=>$p['bake_seconds'],
          'pull_back_seconds'=>lab_pattern_first_elapsed($positions,$activity,'pull_back','pull_back_confirmed'),
          'rotation_seconds'=>lab_pattern_first_elapsed($positions,$activity,'rotate','rotation_confirmed')];
    }
    return $out;
}
