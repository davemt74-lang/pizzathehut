<?php
declare(strict_types=1);
require_once __DIR__.'/oven_lib.php';
/** Pure normalization. No missing temperature, oven position, or rating is ever inferred. */
function lab_heatmap_event(string $phase, mixed $elapsed, mixed $stone, float $x, float $y): array {
    $allowed=['launch','initial_puff','pull_back','rotate','finish','check'];
    if(!in_array($phase,$allowed,true)) throw new InvalidArgumentException('Choose a known cooking step.');
    if(!lab_ellipse_inside($x,$y)) throw new InvalidArgumentException('The cooking position must lie inside the oven floor.');
    $duration=null;
    if($elapsed!==null && $elapsed!=='') {
        if(!is_numeric($elapsed)||(string)(int)$elapsed!==(string)$elapsed || (int)$elapsed<0 || (int)$elapsed>7200)throw new InvalidArgumentException('Elapsed seconds must be 0–7200 or blank.');
        $duration=(int)$elapsed;
    }
    $temp=null;
    if($stone!==null && $stone!==''){
        if(!is_numeric($stone)||!is_finite((float)$stone)||(float)$stone<0||(float)$stone>1500)throw new InvalidArgumentException('Stone temperature must be 0–1500°F or blank.');
        $temp=round((float)$stone,1);
    }
    return ['phase'=>$phase,'elapsed_seconds'=>$duration,'stone_f'=>$temp,'x'=>round($x,4),'y'=>round($y,4)];
}
/** Per zone, one pizza counts once even when turned multiple times within the zone. */
function lab_heatmap_summary(array $zones, array $readings, array $cooks): array {
    $out=[];
    foreach($zones as $z){$out[$z['key']]=['key'=>$z['key'],'name'=>$z['name'],'x'=>$z['x'],'y'=>$z['y'],'radius_in'=>$z['radius_in'], 'reading_count'=>0,'last_stone_f'=>null,'last_observed_at'=>null,'pizzas'=>0,'crisp_n'=>0,'crisp_avg'=>null,'color_n'=>0,'color_avg'=>null,'rise_n'=>0,'rise_avg'=>null,'cook_event_count'=>0, '_scores'=>['crisp'=>[],'color'=>[],'rise'=>[]],'_seen'=>[]];}
    foreach($readings as $r){$key=(string)($r['zone_key']??'');if(!isset($out[$key]))continue;$s=&$out[$key];$s['reading_count']++;$stamp=(string)($r['observed_at']??'');if($s['last_observed_at']===null || $stamp>$s['last_observed_at']){$s['last_observed_at']=$stamp;$s['last_stone_f']=isset($r['stone_f'])?(float)$r['stone_f']:null;}unset($s);}
    foreach($cooks as $c){$key=(string)($c['zone_key']??'');if(!isset($out[$key]))continue;$s=&$out[$key];$s['cook_event_count']++;$id=(int)($c['session_id']??0);if($id<1 || isset($s['_seen'][$id])){unset($s);continue;}$s['_seen'][$id]=true;$s['pizzas']++;
        foreach(['crisp'=>'crispness_score','color'=>'bottom_color_score','rise'=>'rim_rise_score'] as $short=>$field){$v=$c[$field]??null;if($v!==null && is_numeric($v) && (float)$v>=1 && (float)$v<=10)$s['_scores'][$short][]=(float)$v;}
        unset($s);
    }
    foreach($out as &$s){foreach(['crisp','color','rise'] as $short){$values=$s['_scores'][$short];$s[$short.'_n']=count($values);$s[$short.'_avg']=$values?round(array_sum($values)/count($values),2):null;}unset($s['_scores'],$s['_seen']);}unset($s);
    return array_values($out);
}
