<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';auth();
require_once __DIR__.'/cooking_patterns_lib.php';
$ovens=query('SELECT id,name FROM oven_layouts ORDER BY name')->fetchAll();
$recipes=query('SELECT id,name FROM recipes ORDER BY name')->fetchAll();
$pick=static function(string $key):?int {
  $value=$_GET[$key]??'';
  return is_string($value)&&preg_match('/^[1-9][0-9]{0,9}$/',$value)?(int)$value:null;
};
$oven=$pick('oven');$recipe=$pick('recipe');
if($oven!==null && !in_array($oven,array_map(static fn($x)=>(int)$x['id'],$ovens),true))$oven=null;
if($recipe!==null && !in_array($recipe,array_map(static fn($x)=>(int)$x['id'],$recipes),true))$recipe=null;
$photos=lab_pattern_pizzas($oven,$recipe);
$groups=lab_pattern_summarize($photos);
$h=static fn($v)=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$score=static fn($avg,$count)=>$count>0?number_format((float)$avg,1).' /10 (n='.$count.')':'Not recorded';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cooking Patterns · Pizza Lab</title><link rel="stylesheet" href="assets/style.css?v=1.5.8"><style>
.patterns{max-width:1180px;margin:0 auto;padding:24px 16px 70px}.patterns header{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}.patterns form{display:flex;gap:12px;align-items:end;flex-wrap:wrap}.patterns form label{flex:1;min-width:190px}.patterns select{width:100%}.pattern-table{width:100%;border-collapse:collapse;min-width:650px}.pattern-table th,.pattern-table td{text-align:left;padding:12px;border-bottom:1px solid #344650}.scroll-table{overflow-x:auto}.hint{color:#b4c6cf;font-size:.85rem}
</style></head><body><main class="patterns">
<header><a href="agent_chat.php">← Agent Chat</a><h1>Cooking Patterns</h1><a href="heatmap.php">Heat map ↗</a></header>
<p class="hint">Observed results only. Logged rotation reminders are not actual rotations. Averages use only pizzas with recorded scores, and differences do not establish causation.</p>
<section class="panel"><form method="get">
<label>Oven<select name="oven"><option value="">All ovens</option><?php foreach($ovens as $o): ?><option value="<?=$h($o['id'])?>" <?=$oven===(int)$o['id']?'selected':''?>><?=$h($o['name'])?></option><?php endforeach; ?></select></label>
<label>Recipe<select name="recipe"><option value="">All recipes</option><?php foreach($recipes as $r): ?><option value="<?=$h($r['id'])?>" <?=$recipe===(int)$r['id']?'selected':''?>><?=$h($r['name'])?></option><?php endforeach; ?></select></label><button class="primary">Compare</button></form></section>
<section class="panel"><h2>Cooking performance by recipe and launch zone</h2><p class="hint"><?=count($photos)?> recorded pizza sessions (latest 150 maximum). Small samples are exploratory only; at least three rated pizzas are required for a cautious comparison.</p>
<div class="scroll-table"><table class="pattern-table"><thead><tr><th>Recipe · launch zone</th><th>Pizzas</th><th>Crispness</th><th>Bottom color</th><th>Oven spring</th><th>First pull-back</th><th>First rotation</th></tr></thead><tbody>
<?php foreach($groups as $g): ?><tr><td><?=$h($g['label'])?><?php if(!$g['comparison_eligible']): ?><div class="hint">Limited comparable evidence</div><?php endif; ?></td><td><?=$g['pizza_count']?></td><td><?=$score($g['crispness_avg'],$g['crispness_n'])?></td><td><?=$score($g['bottom_color_avg'],$g['bottom_color_n'])?></td><td><?=$score($g['rim_rise_avg'],$g['rim_rise_n'])?></td><td><?=$g['pull_back_seconds_n']?number_format((float)$g['pull_back_seconds_avg']).'s (n='.$g['pull_back_seconds_n'].')':'Not recorded'?></td><td><?=$g['rotation_seconds_n']?number_format((float)$g['rotation_seconds_avg']).'s (n='.$g['rotation_seconds_n'].')':'Not recorded'?></td></tr><?php endforeach; ?>
<?php if(!$groups): ?><tr><td colspan="7">No linked oven cooking sessions yet. Assign an oven and record positions to begin comparing.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel"><h2>Ask the agent</h2><p>Claude can examine measured cooking events alongside recipe, fermentation, and pizza-quality records.</p><a class="primary" href="agent_chat.php">Open voice chat →</a><p class="hint">Suggested question: “Compare actual pull-back and rotation timing with crispness and bottom color. Separate measured findings from hypotheses.”</p></section>
</main></body></html>
