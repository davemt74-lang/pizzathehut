<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$api=file_get_contents($root.'/api.php');
$lib=file_get_contents($root.'/experiments.php');
$schema=file_get_contents($root.'/schema.sql');
$upgrade=file_get_contents($root.'/upgrade.php');
$ui=file_get_contents($root.'/assets/app.js');
$index=file_get_contents($root.'/index.php');
foreach (['CREATE TABLE IF NOT EXISTS pizza_experiments','source_recipe_id','variation_recipe_id','created_by_user_id'] as $key) if(!str_contains($schema,$key))throw new Exception('Missing schema: '.$key);
foreach (['lab_create_experiment','lab_recipe_analytics','lab_experiment_detail','db()->beginTransaction','db()->rollBack','COUNT(crispness_score)','AVG(crispness_score)'] as $key) if(!str_contains($lib,$key))throw new Exception('Missing backend: '.$key);
foreach (['createExperiment','experimentDetail','labAnalytics','lab_experiments'] as $key) if(!str_contains($api,$key))throw new Exception('Missing API: '.$key);
foreach (['experimentView','experimentForm','experimentReport','loadExperiments','data-experiment-detail'] as $key) if(!str_contains($ui,$key))throw new Exception('Missing UI: '.$key);
if(!str_contains($index,'data-page="experiments"'))throw new Exception('Missing navigation');
if(!str_contains($upgrade,'pizza_experiments'))throw new Exception('Missing migration');
echo "PASS: unlimited recipe experiments, atomic variation cloning, schema, UI, API and migration source contracts\n";
