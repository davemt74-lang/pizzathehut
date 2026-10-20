<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/batch_insights_lib.php';
auth();
header('Cache-Control: no-store');
try {$filters=lab_insight_filters($_GET);} catch(InvalidArgumentException $e){http_response_code(400);exit('Invalid report filter.');}
$rows=lab_insight_query($filters);
$sum=lab_insight_summary($rows);
$observations=lab_insight_observations($rows);
$recipes=query('SELECT id,name FROM recipes ORDER BY name')->fetchAll();
$makers=query('SELECT id,display_name FROM users ORDER BY display_name')->fetchAll();
$h=static fn(mixed $v):string=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$val=static fn(mixed $v,string $suffix=''):string=>$v===null||$v===''?'Not recorded':(string)$v.$suffix;
$score=static fn(array $row,string $col):string=>(int)$row[$col.'_n']>0?$row[$col.'_avg'].' / 10 (n='.$row[$col.'_n'].')':'Not rated';
// Sanitize untrusted spreadsheet strings so opening exported CSV cannot execute formulas.
$csv=static function(mixed $v):string {
    $s=(string)($v??'');
    if(preg_match('/^[\s\x00-\x1f]*[=+@\-]/u',$s))return "'".$s;
    return $s;
};
if(($_GET['format']??'')==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pizza-lab-batches.csv"');
    $fp=fopen('php://output','w');
    $fields=['batch_code','recipe_name','maker_name','stage','mixed_at','balled_at','cold_bulk_hours','first_used','last_used','first_use_hours','pizzas','crisp_n','crisp_avg','rise_n','rise_avg','color_n','color_avg','bake_n','bake_avg','stone_n','stone_avg','sauce_n','sauce_avg','cheese_n','cheese_avg','temp_n','temp_min','temp_max','container_n','waste_n','waste_g','used_n','used_g','portions_used'];
    fputcsv($fp,$fields);
    foreach($rows as $r)fputcsv($fp,array_map(static fn($k)=>$csv($r[$k]??null),$fields));
    fclose($fp);exit;
}
$q=$_GET;unset($q['format']);$download='batch_insights.php?'.http_build_query([...$q,'format'=>'csv']);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#10151c"><title>Batch Intelligence · Pizza Lab</title><link rel="stylesheet" href="assets/style.css?v=1.5.1"><style>
.report{max-width:1240px;margin:0 auto;padding:24px 16px 70px}.report-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.report-kpi{border:1px solid rgba(142,170,191,.18);border-radius:12px;padding:15px;background:rgba(120,150,180,.06)}.report-kpi strong{display:block;font-size:25px;margin:5px 0}.report-kpi small{opacity:.75}.report-table-wrap{overflow:auto}.report-table{width:100%;min-width:1040px;border-collapse:collapse}.report-table th,.report-table td{text-align:left;border-bottom:1px solid rgba(142,170,191,.19);padding:11px 9px;vertical-align:top}.report-table th{white-space:nowrap;font-size:.82rem;opacity:.8}.report-table td{font-size:.9rem}.report-table small{display:block;opacity:.7}.report-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:12px}.report-filters label{display:block}.report-filters input,.report-filters select{width:100%;max-width:none}.report h2{margin-top:30px}.report a{color:inherit}.report-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}.report-note{border-left:3px solid #64b99f;padding:10px 14px;background:rgba(100,185,159,.08);margin:8px 0}.report-help{opacity:.8;max-width:75ch}.report .panel{margin-bottom:16px}@media(max-width:600px){.report{padding:14px 10px 50px}.report-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.report-kpi strong{font-size:20px}}
</style></head><body><main class="report">
<div class="report-actions"><a class="sm" href="index.php">← Pizza Lab</a><a href="index.php" class="sm">Recipes &amp; pizza evidence</a></div>
<h1>Batch performance intelligence</h1><p class="report-help">Evidence-based reports across every recipe, variation, dough maker, and fermentation schedule. Missing measurements are never guessed. No automated recipe changes.</p>
<section class="panel"><h2>Choose report scope</h2><form method="get" class="stack"><div class="report-filters"><label>Recipe<select name="recipe_id"><option value="">All recipes</option><?php foreach($recipes as $r):?><option value="<?=$h($r['id'])?>" <?=$filters['recipe_id']===(int)$r['id']?'selected':''?>><?=$h($r['name'])?></option><?php endforeach;?></select></label><label>Made by<select name="maker_id"><option value="">All chefs</option><?php foreach($makers as $u):?><option value="<?=$h($u['id'])?>" <?=$filters['maker_id']===(int)$u['id']?'selected':''?>><?=$h($u['display_name'])?></option><?php endforeach;?></select></label><label>Batch stage<select name="stage"><?php foreach([''=>'All','bulk'=>'Bulk','balled'=>'Balled','ready'=>'Ready','used'=>'Used','archived'=>'Archived'] as $key=>$label):?><option value="<?=$h($key)?>" <?=$filters['stage']===$key?'selected':''?>><?=$h($label)?></option><?php endforeach;?></select></label><label>Mixed from<input type="date" name="from" value="<?=$h($filters['from'])?>"></label><label>Mixed through<input type="date" name="to" value="<?=$h($filters['to'])?>"></label></div><div class="report-actions"><button type="submit" class="primary">Apply filters</button><a href="batch_insights.php">Reset</a><a href="<?=$h($download)?>" download>Export filtered CSV ↓</a></div></form></section>
<div class="report-grid" aria-label="Filtered report summary"><div class="report-kpi"><small>Batches</small><strong><?=$h($sum['batches'])?></strong></div><div class="report-kpi"><small>Pizza tests</small><strong><?=$h($sum['pizzas'])?></strong></div><div class="report-kpi"><small>Measured average crispness</small><strong><?=$h($val($sum['scores']['crisp']['avg'],' / 10'))?></strong><small><?=$h($sum['scores']['crisp']['count'])?> recorded ratings</small></div><div class="report-kpi"><small>Measured average rim rise</small><strong><?=$h($val($sum['scores']['rise']['avg'],' / 10'))?></strong><small><?=$h($sum['scores']['rise']['count'])?> recorded ratings</small></div><div class="report-kpi"><small>Reported waste</small><strong><?=number_format((float)$sum['waste_g_recorded'],0)?> g</strong><small>From <?=$h($sum['with_waste_logs'])?> batch(es); missing logs unknown</small></div><div class="report-kpi"><small>Dough temperatures</small><strong><?=$h($sum['temperature_readings'])?></strong><small>Individual readings</small></div></div>
<h2>Batch-by-batch outcomes</h2><div class="panel report-table-wrap"><table class="report-table"><thead><tr><th>Batch / recipe</th><th>Made by</th><th>Mix → ball</th><th>Pizza tests</th><th>Crispness</th><th>Rise</th><th>Bottom color</th><th>Average stone</th><th>Dough temperatures</th><th>Usage / waste</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><b><?=$h($r['batch_code'])?></b><small><?=$h($r['recipe_name'])?></small><small><?=$h($r['stage'])?></small><a href="index.php" title="Open the dough batch tracker">Open tracker ↗</a></td><td><?=$h($val($r['maker_name']))?></td><td><?=$h($val($r['cold_bulk_hours'],' h'))?><small>Mixed <?=$h($val($r['mixed_at']))?></small><small>Balled <?=$h($val($r['balled_at']))?></small></td><td><?=$h($r['pizzas'])?></td><td><?=$h($score($r,'crisp'))?></td><td><?=$h($score($r,'rise'))?></td><td><?=$h($score($r,'color'))?></td><td><?= (int)$r['stone_n']? $h($r['stone_avg'].' °F (n='.$r['stone_n'].')'):'Not recorded'?></td><td><?= (int)$r['temp_n']? $h($r['temp_min'].'–'.$r['temp_max'].' °F (n='.$r['temp_n'].')'):'Not recorded'?></td><td><?=$h($val($r['used_g'],' g used'))?><small><?=$h($val($r['waste_g'],' g discarded'))?></small><small>First used <?=$h($val($r['first_used']))?></small></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="10">No batches match these filters.</td></tr><?php endif;?></tbody></table><p class="report-help">Up to 500 most recent matching batches. Only scored pizzas contribute to scores; rows with zero readings say “Not recorded.” Current recipe edits do not alter batch snapshots.</p></div>
<h2>Evidence coverage &amp; next checks</h2><section class="panel"><?php foreach($observations as $observation):?><p class="report-note"><?=$h($observation)?></p><?php endforeach;?><p class="report-help">Recorded discard mass is not total dough waste unless all discarded dough was logged. Logged measurements alone cannot prove fermentation, oven heat, or sauce caused a crust change. Compare matched pizzas and confirm observations in repeated batches.</p></section>
<p class="report-help">This report displays manually recorded data. Claude experiment analysis remains available through the Experiment Lab; no Claude API call is made when opening or exporting this report.</p>
</main></body></html>
