<?php
declare(strict_types=1);
/** Read-only analytics. Missing values are unknown; aggregates never double count joins. */
function lab_insight_filters(array $get): array {
    $filters=[];
    foreach (['recipe_id','maker_id'] as $key) {
        $value=trim((string)($get[$key]??''));
        if($value==='') {$filters[$key]=null;continue;}
        if(!ctype_digit($value) || (int)$value<1 || (int)$value>2147483647) throw new InvalidArgumentException('Invalid '.$key);
        $filters[$key]=(int)$value;
    }
    $stage=trim((string)($get['stage']??''));
    if(!in_array($stage,['','bulk','balled','ready','used','archived'],true))throw new InvalidArgumentException('Invalid batch stage');
    $filters['stage']=$stage;
    foreach(['from','to'] as $key) {
        $value=trim((string)($get[$key]??''));
        if($value!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)||!checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4))))throw new InvalidArgumentException('Invalid date');
        $filters[$key]=$value;
    }
    if($filters['from'] && $filters['to'] && $filters['from']>$filters['to'])throw new InvalidArgumentException('From date must precede to date');
    return $filters;
}
function lab_insight_conditions(array $filters): array {
    $where=[];$params=[];
    if($filters['recipe_id']!==null){$where[]='b.recipe_id=?';$params[]=$filters['recipe_id'];}
    if($filters['maker_id']!==null){$where[]='b.made_by_user_id=?';$params[]=$filters['maker_id'];}
    if($filters['stage']!==''){$where[]='b.stage=?';$params[]=$filters['stage'];}
    if($filters['from']!==''){$where[]='b.mixed_at>=?';$params[]=$filters['from'].' 00:00:00';}
    if($filters['to']!==''){$where[]='b.mixed_at<?';$params[]=(new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00';}
    return [$where?'WHERE '.implode(' AND ',$where):'',$params];
}
function lab_insight_query(array $filters): array {
    [$where,$params]=lab_insight_conditions($filters);
    $sql="SELECT b.id,b.batch_code,b.label,b.recipe_id,b.made_by_user_id,b.stage,b.mixed_at,b.balled_at,b.created_at,
       r.name AS recipe_name,u.display_name AS maker_name,
       COALESCE(e.pizzas,0) AS pizzas,e.crisp_n,e.crisp_avg,e.rise_n,e.rise_avg,e.color_n,e.color_avg,
       e.bake_n,e.bake_avg,e.stone_n,e.stone_avg,e.sauce_n,e.sauce_avg,e.cheese_n,e.cheese_avg,
       e.age_n,e.age_avg,e.mature_earliest,e.mature_latest,
       COALESCE(t.temp_n,0) AS temp_n,t.temp_min,t.temp_max,t.temp_avg,
       COALESCE(c.container_n,0) AS container_n,
       COALESCE(w.waste_n,0) AS waste_n,w.waste_g,
       COALESCE(us.used_n,0) AS used_n,us.used_g,us.portions_used,us.first_used,us.last_used,
       TIMESTAMPDIFF(HOUR,b.mixed_at,b.balled_at) AS cold_bulk_hours,
       TIMESTAMPDIFF(HOUR,b.mixed_at,us.first_used) AS first_use_hours
     FROM dough_batches b JOIN recipes r ON r.id=b.recipe_id LEFT JOIN users u ON u.id=b.made_by_user_id
     LEFT JOIN (SELECT batch_id,COUNT(*) pizzas,COUNT(crispness_score) crisp_n,ROUND(AVG(crispness_score),2) crisp_avg,
         COUNT(rim_rise_score) rise_n,ROUND(AVG(rim_rise_score),2) rise_avg,COUNT(bottom_color_score) color_n,ROUND(AVG(bottom_color_score),2) color_avg,
         COUNT(bake_seconds) bake_n,ROUND(AVG(bake_seconds),1) bake_avg,COUNT(stone_start_f) stone_n,ROUND(AVG(stone_start_f),1) stone_avg,
         COUNT(sauce_g) sauce_n,ROUND(AVG(sauce_g),1) sauce_avg,COUNT(cheese_g) cheese_n,ROUND(AVG(cheese_g),1) cheese_avg,
         COUNT(dough_age_h) age_n,ROUND(AVG(dough_age_h),1) age_avg,MIN(dough_age_h) mature_earliest,MAX(dough_age_h) mature_latest
         FROM evidence WHERE batch_id IS NOT NULL GROUP BY batch_id) e ON e.batch_id=b.id
     LEFT JOIN (SELECT batch_id,COUNT(*) temp_n,MIN(temperature_f) temp_min,MAX(temperature_f) temp_max,ROUND(AVG(temperature_f),1) temp_avg
         FROM batch_temperature_readings GROUP BY batch_id) t ON t.batch_id=b.id
     LEFT JOIN (SELECT batch_id,COUNT(*) container_n FROM batch_containers GROUP BY batch_id) c ON c.batch_id=b.id
     LEFT JOIN (SELECT batch_id,COUNT(*) waste_n,SUM(weight_g) waste_g FROM batch_waste GROUP BY batch_id) w ON w.batch_id=b.id
     LEFT JOIN (SELECT batch_id,COUNT(*) used_n,SUM(weight_g) used_g,SUM(portions_count) portions_used,MIN(occurred_at) first_used,MAX(occurred_at) last_used FROM batch_usage WHERE event_type='used' GROUP BY batch_id) us ON us.batch_id=b.id
     $where ORDER BY COALESCE(b.mixed_at,b.created_at) DESC,b.id DESC LIMIT 500";
    return query($sql,$params)->fetchAll();
}
/** Report coverage is not an estimate of a missing measurement. */
function lab_insight_summary(array $rows): array {
    $metrics=['crisp','rise','color','bake','stone','sauce','cheese','age'];
    $out=['batches'=>count($rows),'pizzas'=>0,'waste_g_recorded'=>0.,'used_g_recorded'=>0.,'containers'=>0,'temperature_readings'=>0,'with_waste_logs'=>0,'scores'=>[]];
    $weighted=[];$count=[];foreach($metrics as $m){$weighted[$m]=0.;$count[$m]=0;}
    foreach($rows as $b){
        $out['pizzas']+=(int)$b['pizzas'];$out['containers']+=(int)$b['container_n'];$out['temperature_readings']+=(int)$b['temp_n'];
        $out['with_waste_logs']+=(int)$b['waste_n']>0?1:0;
        if($b['waste_g']!==null)$out['waste_g_recorded']+=(float)$b['waste_g'];
        if($b['used_g']!==null)$out['used_g_recorded']+=(float)$b['used_g'];
        foreach($metrics as $m){$n=(int)($b[$m.'_n']??0);$avg=$b[$m.'_avg']??null;if($n && $avg!==null){$count[$m]+=$n;$weighted[$m]+=$n*(float)$avg;}}
    }
    foreach($metrics as $m){$out['scores'][$m]=['count'=>$count[$m],'avg'=>$count[$m]?round($weighted[$m]/$count[$m],2):null];}
    $tracked=$out['used_g_recorded']+$out['waste_g_recorded'];
    $out['measured_waste_pct']=$tracked>0 && $out['with_waste_logs']===$out['batches'] ? round(100*$out['waste_g_recorded']/$tracked,1):null;
    return $out;
}
function lab_insight_observations(array $rows):array {
    $notes=[];$count=count($rows);$withPizza=count(array_filter($rows,static fn($x)=>(int)$x['pizzas']>0));
    if($count===0)return ['No matching batches. Adjust filters or record your first batch.'];
    if($withPizza<2)$notes[]='Only '. $withPizza .' batch(es) have pizza tests: comparisons are preliminary.';
    if(array_sum(array_map(static fn($x)=>(int)$x['temp_n'],$rows))===0)$notes[]='No per-container dough temperatures were recorded: cooling/fermentation comparisons are not possible.';
    if(array_sum(array_map(static fn($x)=>(int)$x['crisp_n'],$rows))<3)$notes[]='Fewer than three crispness scores recorded: collect blind 1–10 ratings before drawing conclusions.';
    if(array_sum(array_map(static fn($x)=>(int)$x['waste_n'],$rows))===0)$notes[]='No waste entries recorded: waste totals are unknown, not necessarily zero.';
    $notes[]='Correlations are not causes. Compare similarly aged pizzas, the same pizza size, consistent sauce/cheese weights, and matching stone temperatures.';
    return $notes;
}
