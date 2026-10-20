<?php
declare(strict_types=1);
function lab_visual_angle(string $angle):bool{return in_array($angle,['top','bottom','side','crumb'],true);}
function lab_visual_snapshot(array $a,array $b,string $angle):array {
    $fields=['id','label','recipe_id','batch_id','pizza_size_in','dough_ball_g','dough_age_h','stone_start_f','stone_finish_f','bake_seconds','sauce_g','cheese_g','cheese_moisture','rim_rise_score','crispness_score','bottom_color_score','notes'];
    $reduce=static function(array $e) use($fields): array {return array_intersect_key($e,array_flip($fields));};
    return ['angle'=>$angle,'pizza_a'=>$reduce($a),'pizza_b'=>$reduce($b),'limitations'=>['Ratings are subjective and optional','Images cannot determine actual crunch, internal moisture or temperatures','Two different recipes or cooking conditions are not a controlled experiment','Correlation alone does not establish the cause of any visual difference']];
}
function lab_visual_normalize(array $answer):array {
    $summary=trim((string)($answer['summary']??''));
    if($summary==='')throw new RuntimeException('Claude did not provide a structured summary.');
    $clip=static fn($v,$n)=>substr(trim((string)$v),0,$n);
    $lines=static function($v)use($clip){return array_slice(array_values(array_map(static fn($s)=>$clip($s,350),array_filter(is_array($v)?$v:[], 'is_string'))),0,5);};
    $confidence=in_array(($answer['confidence']??''),['low','medium','high'],true)?$answer['confidence']:'low';
    return ['summary'=>$clip($summary,1200),'confidence'=>$confidence,'visible_differences'=>$lines($answer['visible_differences']??[]),'measured_context'=>$lines($answer['measured_context']??[]),'hypotheses'=>$lines($answer['hypotheses']??[]),'next_controlled_test'=>$clip($answer['next_controlled_test']??'',600),'limitations'=>$lines($answer['limitations']??[])];
}
