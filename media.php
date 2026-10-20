<?php
require __DIR__.'/bootstrap.php'; auth();
$id=(int)($_GET['id']??0);
$row=query('SELECT stored_name,mime FROM media WHERE id=?',[$id])->fetch();
if (!$row) {http_response_code(404);exit;}
$file=DATA_ROOT.'/uploads/'.basename($row['stored_name']);
if (!is_file($file)) {http_response_code(404);exit;}
header('Content-Type: '.$row['mime']); header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=300');header('Content-Security-Policy: default-src \'none\'; sandbox');
readfile($file);
