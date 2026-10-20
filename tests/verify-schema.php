<?php
$schema=file_get_contents(__DIR__.'/../schema.sql');
foreach (['dough_batches','dough_batch_events','recipe_steps','batch_step_logs','batch_containers','batch_temperature_readings','batch_usage','batch_waste'] as $table) {
    $regex='/CREATE TABLE IF NOT EXISTS '.preg_quote($table,'/').' \(.*?\) ENGINE=InnoDB;/s';
    if (!preg_match($regex,$schema,$m)) throw new RuntimeException('Missing batch table '.$table);
}
foreach (['recipe_id BIGINT UNSIGNED NOT NULL','recipe_snapshot LONGTEXT','process_steps_snapshot LONGTEXT','batch_code VARCHAR(12) NOT NULL UNIQUE','FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE RESTRICT','batch_id BIGINT UNSIGNED NULL','FOREIGN KEY (batch_id) REFERENCES dough_batches(id)','rim_rise_score','crispness_score','bottom_color_score'] as $term) {
    if (!str_contains($schema,$term)) throw new RuntimeException('Missing contract: '.$term);
}
$api=file_get_contents(__DIR__.'/../api.php');
foreach (['Choose a recipe for this batch','Batch and pizza recipe do not match','recipe_at_batch_creation','random_int(','Cannot change a batch recipe after pizza evidence or production events exist'] as $term) {
    if (!str_contains($api,$term)) throw new RuntimeException('Missing API guard: '.$term);
}
$templates=file_get_contents(__DIR__.'/../recipe_templates.php');
foreach (['Gelato v1.0','Stonefellows v1.0','initial_mix','yeast_add','salt_add','bulk_24h','ball_48h'] as $s) {
    if(!str_contains($templates,$s))throw new RuntimeException('Missing process seed: '.$s);
}
foreach (['saveRecipeStep','saveBatchStep','addContainer','batchTemperature','batchUsage','batchWaste'] as $s) {
    if(!str_contains($api,$s))throw new RuntimeException('Missing API mutation: '.$s);
}
echo "PASS: process schema, recipe seeds, recipe-locking and evidence contracts\n";
