<?php
declare(strict_types=1);
require __DIR__.'/../batch_insights_lib.php';
$valid=lab_insight_filters(['recipe_id'=>'8','maker_id'=>'2','stage'=>'balled','from'=>'2026-01-31','to'=>'2026-02-01']);
if($valid['recipe_id']!==8 || $valid['maker_id']!==2 || $valid['to']!=='2026-02-01')throw new RuntimeException('Filter parsing failed');
foreach([['recipe_id'=>'1 OR 1=1'],['stage'=>'deleted'],['from'=>'2026-02-30'],['from'=>'2026-05-01','to'=>'2026-04-01'],['maker_id'=>'-3']] as $bad){
    try{lab_insight_filters($bad);throw new RuntimeException('Unsafe filter accepted: '.json_encode($bad));}catch(InvalidArgumentException $e){}
}
[$where,$params]=lab_insight_conditions($valid);
if(!str_contains($where,'b.recipe_id=?')||count($params)!==5)throw new RuntimeException('Parameterization failed');
$rows=[['pizzas'=>2,'temp_n'=>2,'container_n'=>1,'waste_n'=>1,'waste_g'=>100,'used_g'=>900,'crisp_n'=>2,'crisp_avg'=>6,'rise_n'=>1,'rise_avg'=>8,'color_n'=>0,'color_avg'=>null,'bake_n'=>0,'bake_avg'=>null,'stone_n'=>0,'stone_avg'=>null,'sauce_n'=>0,'sauce_avg'=>null,'cheese_n'=>0,'cheese_avg'=>null,'age_n'=>0,'age_avg'=>null],['pizzas'=>6,'temp_n'=>3,'container_n'=>2,'waste_n'=>0,'waste_g'=>null,'used_g'=>null,'crisp_n'=>1,'crisp_avg'=>9,'rise_n'=>0,'rise_avg'=>null,'color_n'=>0,'color_avg'=>null,'bake_n'=>0,'bake_avg'=>null,'stone_n'=>0,'stone_avg'=>null,'sauce_n'=>0,'sauce_avg'=>null,'cheese_n'=>0,'cheese_avg'=>null,'age_n'=>0,'age_avg'=>null]];
$s=lab_insight_summary($rows);
if($s['batches']!==2||$s['pizzas']!==8||$s['scores']['crisp']['count']!==3||abs($s['scores']['crisp']['avg']-7)>0.0001||$s['scores']['color']['avg']!==null||$s['measured_waste_pct']!==null)throw new RuntimeException('Weighted/null-safe aggregation failed');
$complete=lab_insight_summary([$rows[0]]);
if($complete['measured_waste_pct']!==10.) throw new RuntimeException('Measured waste ratio failed');
$blank=lab_insight_summary([]);
if($blank['scores']['crisp']['avg']!==null || $blank['measured_waste_pct']!==null)throw new RuntimeException('Missing evidence must remain unknown');
$ui=file_get_contents(__DIR__.'/../batch_insights.php');$idx=file_get_contents(__DIR__.'/../index.php');
foreach(['fputcsv','Content-Disposition','lab_insight_query','htmlspecialchars','waste_g','temp_n'] as $term)if(!str_contains($ui,$term))throw new RuntimeException('Missing report control: '.$term);
if(!str_contains($idx,'href="batch_insights.php"'))throw new RuntimeException('Batch report not linked from navigation');
echo "PASS: parameterized batch filters, weighted ratings, unknown evidence, report navigation and CSV export\n";
