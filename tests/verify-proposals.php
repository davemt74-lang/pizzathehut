<?php
declare(strict_types=1);
require __DIR__.'/../ai_proposals.php';
function check(bool $ok,string $label): void {if(!$ok)throw new Exception('FAIL '.$label);}
$r=['id'=>11,'name'=>'Test v1','flour_name'=>'Blue','yeast_type'=>'fresh compressed','flour_g'=>'10000','water_g'=>'5800','salt_g'=>'220','oil_g'=>'150','honey_g'=>'0','yeast_g'=>'30','process_notes'=>''];
$a=lab_proposal_changes(['honey_g'=>90,'salt_g'=>230,'evil_sql'=>'DELETE FROM recipes','water_g'=>100000,'yeast_g'=>600,'oil_g'=>null],$r);
check(count($a)===2 && $a['honey_g']===90.0 && $a['salt_g']===230.0,'allowlist bounds + known fields');
check(!isset($a['evil_sql'])&&!isset($a['water_g'])&&!isset($a['yeast_g']),'untrusted/model additions rejected');
$r['oil_g']=null;check(!lab_proposal_changes(['oil_g'=>100],$r),'unknown source must stay unknown');
$fp=lab_proposal_snapshot($r);$r['salt_g']='224';check(!hash_equals($fp,lab_proposal_snapshot($r)),'stale recipe snapshot invalidation');
$schema=file_get_contents(__DIR__.'/../schema.sql');$upgrade=file_get_contents(__DIR__.'/../upgrade.php');$api=file_get_contents(__DIR__.'/../api.php');$ui=file_get_contents(__DIR__.'/../proposals.php');$gallery=file_get_contents(__DIR__.'/../gallery.php');
check(str_contains($schema,'CREATE TABLE IF NOT EXISTS ai_recipe_proposals')&&str_contains($upgrade,'ai_recipe_proposals'),'fresh and upgrade schema');
foreach(['generateProposal','decideProposal'] as $key)check(str_contains($api,$key),'API '.$key);
check(str_contains($api,"'crumb'")&&str_contains($gallery,"'crumb'")&&str_contains($ui,'data-decision'),'crumb upload/gallery and review actions');
check(str_contains(file_get_contents(__DIR__.'/../index.php'),'href="gallery.php"'),'gallery linked navigation');
check(str_contains(file_get_contents(__DIR__.'/../ai_proposals.php'),'FOR UPDATE')&&str_contains(file_get_contents(__DIR__.'/../ai_proposals.php'),'beginTransaction()'),'transactionally protected approval');
echo "PASS: proposal validation, review gate, source fingerprint, private gallery contracts\n";
