<?php
declare(strict_types=1);
/** Experiments are arbitrary recipe-to-variation relationships, never hard-coded pairs. */
function lab_experiments(): array {
    return query('SELECT x.*,a.name AS source_name,b.name AS variation_name FROM pizza_experiments x JOIN recipes a ON a.id=x.source_recipe_id JOIN recipes b ON b.id=x.variation_recipe_id ORDER BY x.created_at DESC,x.id DESC LIMIT 500')->fetchAll();
}
function lab_metric(string $value): string {
    if(!in_array($value,['crispness','oven_spring','bottom_color','bake_time'],true)) fail('Unsupported experiment metric');
    return $value;
}
function lab_create_experiment(array $d): int {
    $source=(int)number($d['source_recipe_id']??null,1,2147483647,'source recipe');
    $r=row_owned('recipes',$source);
    $metric=lab_metric((string)($d['target_metric']??'crispness'));
    $title=plain($d['title']??'',180);
    if($title==='')fail('Experiment title required');
    $hypothesis=plain($d['hypothesis']??'',4000);
    // Atomic clone of formula and timeline. Parent remains source even if this recipe is itself a variation.
    db()->beginTransaction();
    try {
        query('INSERT INTO recipes (parent_id,name,status,flour_name,flour_g,water_g,salt_g,oil_g,honey_g,yeast_g,yeast_type,process_notes,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[
        $source,plain($r['name'].' — '.$title,180),'experimental',$r['flour_name'],$r['flour_g'],$r['water_g'],$r['salt_g'],$r['oil_g'],$r['honey_g'],$r['yeast_g'],$r['yeast_type'],$r['process_notes'],$r['notes']]);
        $variation=(int)db()->lastInsertId();
        query('INSERT INTO recipe_steps (recipe_id,step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes) SELECT ?,step_key,sort_order,stage,title,instructions,target_start_min,target_min_minutes,target_max_minutes FROM recipe_steps WHERE recipe_id=?',[$variation,$source]);
        query('INSERT INTO pizza_experiments (title,hypothesis,source_recipe_id,variation_recipe_id,target_metric,created_by_user_id) VALUES (?,?,?,?,?,?)',[$title,$hypothesis,$source,$variation,$metric,auth()]);
        $id=(int)db()->lastInsertId();
        db()->commit();return $id;
    } catch(Throwable $e) {db()->rollBack();throw $e;}
}
function lab_update_experiment(array $d): void {
    $id=(int)number($d['id']??null,1,2147483647,'experiment');
    $status=(string)($d['status']??'planned');
    if(!in_array($status,['planned','running','completed','cancelled'],true))fail('Invalid experiment status');
    $exists=query('SELECT id FROM pizza_experiments WHERE id=?',[$id])->fetchColumn();
    if(!$exists)fail('Experiment not found',404);
    query('UPDATE pizza_experiments SET status=? WHERE id=?',[$status,$id]);
}
function lab_recipe_analytics(): array {
    return query('SELECT r.id,r.name,r.status,(SELECT COUNT(*) FROM dough_batches b WHERE b.recipe_id=r.id) AS batch_count,(SELECT COUNT(*) FROM evidence e WHERE e.recipe_id=r.id) AS pizza_count,(SELECT COUNT(*) FROM evidence e WHERE e.recipe_id=r.id AND e.crispness_score IS NOT NULL) AS crisp_count,(SELECT ROUND(AVG(e.crispness_score),2) FROM evidence e WHERE e.recipe_id=r.id) AS avg_crisp,(SELECT COUNT(*) FROM evidence e WHERE e.recipe_id=r.id AND e.rim_rise_score IS NOT NULL) AS rise_count,(SELECT ROUND(AVG(e.rim_rise_score),2) FROM evidence e WHERE e.recipe_id=r.id) AS avg_rise,(SELECT ROUND(AVG(e.bottom_color_score),2) FROM evidence e WHERE e.recipe_id=r.id) AS avg_color FROM recipes r ORDER BY r.name')->fetchAll();
}
function lab_experiment_detail(int $id): array {
    $x=query('SELECT * FROM pizza_experiments WHERE id=?',[$id])->fetch();if(!$x)fail('Experiment not found',404);
    $summary=[];
    foreach (['source_recipe_id','variation_recipe_id'] as $side) {
        $rid=(int)$x[$side];
        $r=row_owned('recipes',$rid);
        $m=query('SELECT COUNT(*) AS pizza_count,COUNT(crispness_score) AS crisp_count,ROUND(AVG(crispness_score),2) AS avg_crisp,COUNT(rim_rise_score) AS rise_count,ROUND(AVG(rim_rise_score),2) AS avg_rise,COUNT(bottom_color_score) AS color_count,ROUND(AVG(bottom_color_score),2) AS avg_color,COUNT(bake_seconds) AS bake_count,ROUND(AVG(bake_seconds),1) AS avg_bake_s FROM evidence WHERE recipe_id=?',[$rid])->fetch();
        $summary[$side]=['recipe'=>$r,'measurements'=>$m,'batches'=>query('SELECT batch_code,stage,mixed_at,balled_at FROM dough_batches WHERE recipe_id=? ORDER BY id DESC LIMIT 30',[$rid])->fetchAll()];
    }
    return ['experiment'=>$x,'comparison'=>$summary,'guidance'=>'These are descriptive measurements, not proof of causation. Compare like-for-like dough ages, toppings, stone temperatures, oven usage and sample sizes. Missing scores remain missing.'];
}
