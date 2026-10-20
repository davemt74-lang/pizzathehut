<?php
declare(strict_types=1);
require_once __DIR__.'/workday_lib.php';

/** Only declared, explicitly linked workday evidence. Never infer a batch from record age. */
function lab_chat_workday_view(array $days, array $events, array $pizzas): array {
    if (count($days)===0) return ['state'=>'none','days'=>[],'day'=>null,'batches'=>[],'pizzas'=>[],'rated_crispness'=>0,'avg_crispness'=>null];
    $options=array_map(static fn(array $d):array=>['id'=>(int)$d['id'],'work_date'=>(string)$d['work_date'],'title'=>(string)$d['title']],array_slice($days,0,20));
    if (count($options)!==1) return ['state'=>'multiple','days'=>$options,'day'=>null,'batches'=>[],'pizzas'=>[],'rated_crispness'=>0,'avg_crispness'=>null];
    $batches=[];
    foreach($events as $event){
        $id=(int)$event['batch_id'];
        if($id<1)continue;
        if(!isset($batches[$id]))$batches[$id]=['id'=>$id,'batch_code'=>(string)$event['batch_code'],'recipe_name'=>(string)$event['recipe_name'],'activities'=>[]];
        if(!in_array((string)$event['activity'],$batches[$id]['activities'],true))$batches[$id]['activities'][]=(string)$event['activity'];
    }
    $samples=array_values(array_filter(array_column($pizzas,'crispness_score'),static fn($v)=>$v!==null&&$v!==''));
    return ['state'=>'single','days'=>$options,'day'=>$options[0],
      'batches'=>array_values($batches),'pizzas'=>array_map(static fn(array $p)=>['id'=>(int)$p['id'],'label'=>(string)$p['label']],$pizzas),
      'rated_crispness'=>count($samples),'avg_crispness'=>$samples?round(array_sum(array_map('floatval',$samples))/count($samples),2):null];
}
function lab_chat_workday_context(string $date, ?int $selectedId=null): array {
    if(!lab_valid_work_date($date))fail('Choose a valid workday date.');
    $days=query('SELECT id,work_date,title FROM workdays WHERE work_date=? ORDER BY id DESC LIMIT 20',[$date])->fetchAll();
    if($selectedId!==null){
        $days=array_values(array_filter($days,static fn(array $d)=>(int)$d['id']===$selectedId));
        if(!$days)fail('Workday not found on selected date.',404);
    }
    if(count($days)!==1)return lab_chat_workday_view($days,[],[]);
    $id=(int)$days[0]['id'];
    $events=query('SELECT e.batch_id,b.batch_code,r.name AS recipe_name,e.activity FROM workday_batch_events e JOIN dough_batches b ON b.id=e.batch_id JOIN recipes r ON r.id=b.recipe_id WHERE e.workday_id=? ORDER BY e.id DESC LIMIT 120',[$id])->fetchAll();
    $pizzas=query('SELECT p.id,p.label,p.crispness_score FROM workday_pizzas w JOIN evidence p ON p.id=w.evidence_id WHERE w.workday_id=? ORDER BY p.id DESC LIMIT 100',[$id])->fetchAll();
    return lab_chat_workday_view($days,$events,$pizzas);
}
