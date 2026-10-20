<?php
declare(strict_types=1);
require __DIR__.'/../cooking_patterns_lib.php';
function check(bool $x,string $msg):void{if(!$x)throw new RuntimeException($msg);}
$rows=lab_pattern_summarize([
 ['recipe_name'=>'A','launch_zone'=>'Right','crispness'=>4,'bottom_color'=>null,'rim_rise'=>8,'pull_back_seconds'=>40],
 ['recipe_name'=>'A','launch_zone'=>'Right','crispness'=>8,'bottom_color'=>7,'rim_rise'=>null,'pull_back_seconds'=>null],
 ['recipe_name'=>'A','launch_zone'=>'Right','crispness'=>6,'bottom_color'=>9,'rim_rise'=>9,'pull_back_seconds'=>60],
 ['recipe_name'=>'B','launch_zone'=>null,'crispness'=>null,'bottom_color'=>null]
]);
check(count($rows)===2,'groups');
check($rows[0]['pizza_count']===3,'dedup');
check($rows[0]['crispness_avg']===6.0&&$rows[0]['crispness_n']===3,'crispness');
check($rows[0]['bottom_color_avg']===8.0&&$rows[0]['bottom_color_n']===2,'missing rating excluded');
check($rows[0]['pull_back_seconds_avg']===50.0&&$rows[0]['pull_back_seconds_n']===2,'actual pull-back');
check(!$rows[1]['comparison_eligible']&&$rows[1]['crispness_avg']===null,'unmeasured is not zero');
check(lab_pattern_first_elapsed([], [['event_type'=>'rotation_reminder','event_class'=>'reminder','elapsed_seconds'=>40]],'rotate','rotation_confirmed')===null,'reminder is not movement');
check(lab_pattern_first_elapsed([['phase'=>'rotate','elapsed_seconds'=>65]], [['event_type'=>'rotation_confirmed','event_class'=>'observed','elapsed_seconds'=>72]],'rotate','rotation_confirmed')===65,'earliest observed rotation');
echo "PASS: cooking patterns, observed-only times, explicit missing data\n";
