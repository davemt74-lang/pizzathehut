<?php
declare(strict_types=1);
$a=file_get_contents(__DIR__.'/../tests/http-upgrade.php');
foreach (['pizza upgrade sentinel','SELECT label,notes,crispness_score FROM evidence','SELECT original_name FROM media','No existing media or pizza records may be rewritten'] as $needle)
 if(!str_contains($a,$needle))throw new RuntimeException('Missing upgrade preservation proof: '.$needle);
echo "PASS: upgrade preservation check covers existing pizza scores and media linkage\n";
