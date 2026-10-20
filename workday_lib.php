<?php
declare(strict_types=1);
/** Explicit chronology; never infer a pizza's workday from its creation timestamp. */
function lab_valid_work_date(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) return false;
    return checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4));
}
function lab_workday_activity(string $activity): bool {
    return in_array($activity,['mixed','split','proofed','balled','baked','used','wasted','temperature','other'],true);
}
function lab_workday_summary(array $tests): array {
    $summary=['pizzas'=>count($tests),'rated_crispness'=>0,'average_crispness'=>null,'rated_rise'=>0,'average_rise'=>null,'rated_bottom'=>0,'average_bottom'=>null];
    foreach(['crispness'=>'crispness_score','rise'=>'rim_rise_score','bottom'=>'bottom_color_score'] as $name=>$key) {
        $values=array_values(array_filter(array_column($tests,$key),static fn($v)=>$v!==null&&$v!==''));
        $summary['rated_'.$name]=count($values);
        if ($values) $summary['average_'.$name]=round(array_sum(array_map('floatval',$values))/count($values),2);
    }
    return $summary;
}
