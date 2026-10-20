<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/workday_lib.php';
$user=auth();
header('Cache-Control: private, no-store');
$h=static fn($s): string =>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$validId=static function(mixed $raw):int {
    if (!is_scalar($raw)||!ctype_digit((string)$raw)||(int)$raw<1)fail('Choose a valid record.');
    return (int)$raw;
};
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    $action=(string)($_POST['action']??'');
    if ($action==='create') {
        $date=(string)($_POST['work_date']??'');
        if(!lab_valid_work_date($date))fail('Invalid work date.');
        $title=plain($_POST['title']??'',160);if($title==='')$title='Pizza production';
        query('INSERT INTO workdays (work_date,title,notes,created_by_user_id) VALUES (?,?,?,?)',[$date,$title,plain($_POST['notes']??'',4000),$user]);
        $day=(int)db()->lastInsertId();
    } else {
        $day=$validId($_POST['workday_id']??'');
        $workday=query('SELECT * FROM workdays WHERE id=?',[$day])->fetch();if(!$workday)fail('Workday not found.',404);
        if($action==='logBatch') {
            $batch=$validId($_POST['batch_id']??'');row_owned('dough_batches',$batch);
            $activity=(string)($_POST['activity']??'');if(!lab_workday_activity($activity))fail('Invalid workday activity.');
            $when=(string)($_POST['occurred_at']??'');
            if($when==='') { $when=date('Y-m-d H:i:s'); if(substr($when,0,10)!==$workday['work_date'])fail('Enter an actual event date/time for a historical workday.'); }
            else {
                $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$when);
                if(!$date||$date->format('Y-m-d\TH:i')!==$when)fail('Invalid event time.');
                $when=$date->format('Y-m-d H:i:s');
            }
            if(substr($when,0,10)!==$workday['work_date'])fail('Event time must match selected workday.');
            query('INSERT INTO workday_batch_events (workday_id,batch_id,activity,occurred_at,recorded_by_user_id,notes) VALUES (?,?,?,?,?,?)',[$day,$batch,$activity,$when,$user,plain($_POST['notes']??'',1000)]);
        } elseif($action==='linkPizza') {
            $pizza=$validId($_POST['evidence_id']??'');$ev=row_owned('evidence',$pizza);
            if($ev['batch_id']!==null) {
                $linked=(bool)query('SELECT 1 FROM workday_batch_events WHERE workday_id=? AND batch_id=? LIMIT 1',[$day,(int)$ev['batch_id']])->fetchColumn();
                if(!$linked)fail('First log this dough batch in the workday before linking its pizza.');
            }
            $prior=query('SELECT workday_id FROM workday_pizzas WHERE evidence_id=?',[$pizza])->fetchColumn();
            if($prior!==false && (int)$prior!==$day)fail('Pizza already belongs to another workday. Unlink it there first.');
            query('INSERT INTO workday_pizzas (evidence_id,workday_id,recorded_by_user_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE workday_id=VALUES(workday_id)',[$pizza,$day,$user]);
        } elseif($action==='unlinkPizza') {
            $pizza=$validId($_POST['evidence_id']??'');query('DELETE FROM workday_pizzas WHERE evidence_id=? AND workday_id=?',[$pizza,$day]);
        } else fail('Unknown workday action.',400);
    }
    header('Location: workdays.php?id='.$day, true, 303);exit;
}
$day=(int)($_GET['id']??0);
$selected=$day?query('SELECT w.*,u.display_name AS creator FROM workdays w JOIN users u ON u.id=w.created_by_user_id WHERE w.id=?',[$day])->fetch():null;
if($day&&!$selected){http_response_code(404);exit('Workday not found');}
$filterBatch=filter_var($_GET['batch_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
$filterSql=$filterBatch?' WHERE EXISTS (SELECT 1 FROM workday_batch_events wbe WHERE wbe.workday_id=w.id AND wbe.batch_id=?)':'';
$days=query('SELECT w.*,u.display_name AS creator,(SELECT COUNT(DISTINCT e.batch_id) FROM workday_batch_events e WHERE e.workday_id=w.id) AS batch_count,(SELECT COUNT(*) FROM workday_pizzas p WHERE p.workday_id=w.id) AS pizza_count FROM workdays w JOIN users u ON u.id=w.created_by_user_id'.$filterSql.' ORDER BY w.work_date DESC,w.id DESC LIMIT 100',$filterBatch?[$filterBatch]:[])->fetchAll();
$batches=query('SELECT b.id,b.batch_code,r.name AS recipe_name,u.display_name AS maker FROM dough_batches b JOIN recipes r ON r.id=b.recipe_id LEFT JOIN users u ON u.id=b.made_by_user_id ORDER BY b.id DESC LIMIT 500')->fetchAll();
$events=$selected?query('SELECT wbe.*,b.batch_code,r.name AS recipe_name,u.display_name AS actor FROM workday_batch_events wbe JOIN dough_batches b ON b.id=wbe.batch_id JOIN recipes r ON r.id=b.recipe_id JOIN users u ON u.id=wbe.recorded_by_user_id WHERE wbe.workday_id=? ORDER BY wbe.occurred_at,wbe.id',[$day])->fetchAll():[];
$evidence=$selected?query('SELECT e.id,e.label,e.batch_id,e.rim_rise_score,e.crispness_score,e.bottom_color_score,e.stone_start_f,e.bake_seconds,b.batch_code FROM workday_pizzas wp JOIN evidence e ON e.id=wp.evidence_id LEFT JOIN dough_batches b ON b.id=e.batch_id WHERE wp.workday_id=? ORDER BY e.id',[$day])->fetchAll():[];
$stats=lab_workday_summary($evidence);
$unusedPizzas=$selected?query('SELECT e.id,e.label,b.batch_code FROM evidence e LEFT JOIN dough_batches b ON b.id=e.batch_id LEFT JOIN workday_pizzas wp ON wp.evidence_id=e.id WHERE wp.evidence_id IS NULL ORDER BY e.id DESC LIMIT 300')->fetchAll():[];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pizza Lab · Workdays</title><link rel="stylesheet" href="assets/style.css?v=1.5.4"><style>.work-shell{max-width:1350px;margin:auto;padding:clamp(16px,4vw,42px)}.work-grid{display:grid;grid-template-columns:minmax(240px,1fr) minmax(0,2fr);gap:20px}.work-grid form{display:grid;gap:12px}.work-grid .panel{margin-bottom:18px}.day-list{display:grid;gap:9px}.day-list a{display:block;padding:12px;border:1px solid #33454d;border-radius:9px}.day-list a[aria-current="page"]{border-color:#f2aa5f}.work-table{width:100%;border-collapse:collapse}.work-table th,.work-table td{padding:10px;border-bottom:1px solid #31414d;text-align:left}.work-table{display:block;overflow-x:auto;max-width:100%}@media(max-width:750px){.work-grid{grid-template-columns:1fr}}</style></head><body><main class="work-shell"><p class="overline">DOUGH PRODUCTION</p><h1>Workday tracker</h1><p>Link one dough batch to multiple workdays. Record when a batch was handled, and link the actual pizzas baked on each day. Missing records remain unknown.</p><p><a href="index.php">← Workspace</a> · <a href="visual_compare.php">Visual crust comparison</a> · <a href="batch_insights.php">Batch reports</a></p><div class="work-grid"><aside><section class="panel"><h2>New workday</h2><form method="post"><input type="hidden" name="csrf" value="<?=$h(csrf())?>"><input type="hidden" name="action" value="create"><label>Work date<input name="work_date" type="date" required value="<?=$h(date('Y-m-d'))?>"></label><label>Workday label<input name="title" maxlength="160" placeholder="Lunch shift / dough prep"></label><label>Notes (optional)<textarea name="notes" rows="2"></textarea></label><button class="primary">Create workday</button></form></section><section class="panel"><h2>Recent workdays</h2><?php if($filterBatch):?><p><a href="workdays.php">Show all workdays</a></p><?php endif;?><div class="day-list"><?php foreach($days as $entry):?><a href="?id=<?=$h($entry['id'])?><?= $filterBatch?'&amp;batch_id='.$h($filterBatch):'' ?>" <?=$day===(int)$entry['id']?'aria-current="page"':''?>><b><?=$h($entry['work_date'])?> · <?=$h($entry['title'])?></b><br><small><?=$h($entry['batch_count'])?> batches · <?=$h($entry['pizza_count'])?> pizzas · By <?=$h($entry['creator'])?></small></a><?php endforeach;?></div></section></aside><article><?php if(!$selected):?><section class="panel"><h2>Select a workday</h2><p class="muted">Create a workday or select a previous day to record batches and link pizza tests.</p></section><?php else:?><h2><?=$h($selected['work_date'].' · '.$selected['title'])?></h2><p class="muted">Created by <?=$h($selected['creator'])?> · <?=$h($selected['notes']??'')?></p><section class="grid-3 mb"><div class="metric"><small>PIZZAS LOGGED</small><strong><?=$h($stats['pizzas'])?></strong></div><div class="metric"><small>CRISPNESS RATING</small><strong><?=$h($stats['average_crispness']??'—')?></strong><small><?=$h($stats['rated_crispness'])?> rated</small></div><div class="metric"><small>RIM RISE RATING</small><strong><?=$h($stats['average_rise']??'—')?></strong><small><?=$h($stats['rated_rise'])?> rated</small></div></section><div class="grid-2"><section class="panel"><h2>Log batch activity</h2><form method="post"><input type="hidden" name="csrf" value="<?=$h(csrf())?>"><input type="hidden" name="action" value="logBatch"><input type="hidden" name="workday_id" value="<?=$h($day)?>"><label>Dough batch<select name="batch_id" required><option value="">Select batch</option><?php foreach($batches as $b):?><option value="<?=$h($b['id'])?>"><?=$h($b['batch_code'].' · '.$b['recipe_name'].' · '.($b['maker']??'Maker unknown'))?></option><?php endforeach;?></select></label><label>Activity<select name="activity"><?php foreach(['mixed','split','proofed','balled','baked','used','wasted','temperature','other'] as $activity):?><option value="<?=$h($activity)?>"><?=$h(ucfirst($activity))?></option><?php endforeach;?></select></label><label>Actual time (optional; must match workday)<input type="datetime-local" name="occurred_at"></label><label>Notes<textarea name="notes" rows="2" placeholder="Container temperatures, balls made, handling notes"></textarea></label><button class="primary">Log batch activity</button></form></section><section class="panel"><h2>Link baked pizza</h2><form method="post"><input type="hidden" name="csrf" value="<?=$h(csrf())?>"><input type="hidden" name="action" value="linkPizza"><input type="hidden" name="workday_id" value="<?=$h($day)?>"><label>Pizza evidence<select name="evidence_id" required><option value="">Select previously recorded pizza</option><?php foreach($unusedPizzas as $p):?><option value="<?=$h($p['id'])?>"><?=$h('#'.$p['id'].' · '.$p['label'].' · '.($p['batch_code']??'No batch'))?></option><?php endforeach;?></select></label><p class="small muted">If this pizza belongs to a dough batch, first log that batch's activity on this workday. One pizza can belong to only one workday.</p><button class="primary">Link pizza evidence</button></form></section></div><section class="panel"><h2>Batch timeline</h2><?php if(!$events):?><p class="muted">No batch activity recorded yet.</p><?php else:?><table class="work-table"><tr><th>Time</th><th>Batch / recipe</th><th>Activity</th><th>Recorded by</th><th>Notes</th></tr><?php foreach($events as $ev):?><tr><td><?=$h($ev['occurred_at'])?></td><td><?=$h($ev['batch_code'].' · '.$ev['recipe_name'])?></td><td><?=$h($ev['activity'])?></td><td><?=$h($ev['actor'])?></td><td><?=$h($ev['notes'])?></td></tr><?php endforeach;?></table><?php endif;?></section><section class="panel"><h2>Pizzas baked this workday</h2><?php if(!$evidence):?><p class="muted">No pizza tests linked. Blank does not mean no pizza was made.</p><?php else:?><div class="stack"><?php foreach($evidence as $ev):?><div class="between"><div><a href="index.php?evidence_id=<?=$h($ev['id'])?>"><strong><?=$h($ev['label'])?></strong></a><p class="small muted">Batch <?=$h($ev['batch_code']??'Unrecorded')?> · Crispness <?=$h($ev['crispness_score']??'—')?>/10 · Rise <?=$h($ev['rim_rise_score']??'—')?>/10 · Bottom <?=$h($ev['bottom_color_score']??'—')?>/10</p></div><form method="post"><input type="hidden" name="csrf" value="<?=$h(csrf())?>"><input type="hidden" name="action" value="unlinkPizza"><input type="hidden" name="workday_id" value="<?=$h($day)?>"><input type="hidden" name="evidence_id" value="<?=$h($ev['id'])?>"><button class="sm" title="Unlink from this workday, preserve evidence">Unlink</button></form></div><?php endforeach;?></div><?php endif;?></section><?php endif;?></article></div></main></body></html>
