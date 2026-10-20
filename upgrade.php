<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$uid=auth();
$roleColumn=(bool)query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetchColumn();
if ($roleColumn) require_super_admin();
else {
    $oldest=(int)query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    if ($uid!==$oldest) fail('Only the original administrator can migrate account roles.',403);
}
$name=query('SELECT display_name FROM users WHERE id=?',[$uid])->fetchColumn();
if ($name===false) fail('User not found',401);
$results=[];
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    try {
        // Existing installations have a single original administrator.
        // Do not elevate arbitrary later users during upgrade.
        $rolePresent=(bool)query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetchColumn();
        if (!$rolePresent) {
            db()->exec("ALTER TABLE users ADD COLUMN role ENUM('super_admin','team_member') NOT NULL DEFAULT 'team_member' AFTER password_hash");
            query("UPDATE users SET role='super_admin' WHERE id=?",[$uid]);
            $results[]='Created Super Admin and Team Member roles';
        }
        $activePresent=(bool)query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_active'")->fetchColumn();
        if (!$activePresent) {
            db()->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role');
            $results[]='Added team account activation control';
        }
        $auditSchema=file_get_contents(__DIR__.'/schema.sql');
        if (!preg_match('/CREATE TABLE IF NOT EXISTS batch_ai_reviews \(.*?\) ENGINE=InnoDB;/s',$auditSchema,$coachMatch)) throw new RuntimeException('Missing batch AI review table definition.');
        db()->exec($coachMatch[0]);
        $results[]='Batch AI coach history ready';
        if (!preg_match('/CREATE TABLE IF NOT EXISTS team_audit_log \(.*?\) ENGINE=InnoDB;/s',$auditSchema,$auditMatch)) throw new RuntimeException('Missing team audit table schema');
        db()->exec($auditMatch[0]);
        $results[]='Team administration audit log ready';
        $changes=[
            'recipe_id'=>"ALTER TABLE evidence MODIFY recipe_id BIGINT UNSIGNED NULL DEFAULT NULL",
            'pizza_size_in'=>"ALTER TABLE evidence MODIFY pizza_size_in DECIMAL(5,2) NULL DEFAULT NULL",
            'dough_ball_g'=>"ALTER TABLE evidence MODIFY dough_ball_g DECIMAL(8,2) NULL DEFAULT NULL",
            'bake_seconds'=>"ALTER TABLE evidence MODIFY bake_seconds INT UNSIGNED NULL DEFAULT NULL",
            'oven_load'=>"ALTER TABLE evidence MODIFY oven_load TINYINT UNSIGNED NULL DEFAULT NULL",
        ];
        foreach ($changes as $col=>$sql) {
            $row=query('SELECT IS_NULLABLE,COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',['evidence',$col])->fetch();
            if (!$row) throw new RuntimeException('Missing evidence column: '.$col);
            if ($row['IS_NULLABLE']!=='YES' || $row['COLUMN_DEFAULT']!==null) {
                db()->exec($sql);
                $results[]='Updated evidence.'.$col;
            }
        }
        $columnExists=static function(string $table,string $col): bool {
            return (bool)query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$col])->fetchColumn();
        };
        if (!$columnExists('evidence','sauce_g')) {
            db()->exec("ALTER TABLE evidence ADD COLUMN sauce_g DECIMAL(8,2) NULL AFTER dough_ball_g");
            $results[]='Added sauce weight';
        }
        if (!$columnExists('evidence','cheese_g')) {
            db()->exec("ALTER TABLE evidence ADD COLUMN cheese_g DECIMAL(8,2) NULL AFTER sauce_g");
            $results[]='Added cheese weight';
        }
        if (!$columnExists('evidence','cheese_moisture')) {
            db()->exec("ALTER TABLE evidence ADD COLUMN cheese_moisture TINYINT UNSIGNED NULL AFTER cheese_g");
            $results[]='Added cheese moisture scale';
        }
        if (!$columnExists('events','actor_profile')) {
            db()->exec("ALTER TABLE events ADD COLUMN actor_profile VARCHAR(80) NULL AFTER message");
            $results[]='Added profile attribution';
        }
        if (!$columnExists('events','event_source')) {
            db()->exec("ALTER TABLE events ADD COLUMN event_source VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER actor_profile");
            $results[]='Added source attribution';
        }
        db()->exec("CREATE TABLE IF NOT EXISTS voice_profiles (
          user_id BIGINT UNSIGNED PRIMARY KEY,
          profile_name VARCHAR(80) NOT NULL DEFAULT 'Pizza chef',
          wake_phrase VARCHAR(40) NOT NULL DEFAULT 'pizza',
          language VARCHAR(15) NOT NULL DEFAULT 'en-US',
          voice_uri VARCHAR(200) NOT NULL DEFAULT '',
          voice_rate DECIMAL(3,2) NOT NULL DEFAULT 0.95,
          require_wake TINYINT(1) NOT NULL DEFAULT 1,
          start_alias VARCHAR(70) NOT NULL DEFAULT '',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          CONSTRAINT fk_voice_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        $results[]='Voice profile table verified';
        // v1.2.0: dough batches belong to recipe, with a frozen recipe snapshot.
        // Load table DDL from the fresh-install schema to avoid schema drift.
        $wholeSchema=file_get_contents(__DIR__.'/schema.sql');
        foreach (['dough_batches','dough_batch_events'] as $tableName) {
            $pattern='/CREATE TABLE IF NOT EXISTS '.preg_quote($tableName,'/').' \(.*?\) ENGINE=InnoDB;/s';
            if (!preg_match($pattern,$wholeSchema,$m)) throw new RuntimeException('Missing '.$tableName.' schema.');
            db()->exec($m[0]);
            $results[]=$tableName.' verified';
        }
        if (!$columnExists('dough_batches','recipe_snapshot')) {
            db()->exec('ALTER TABLE dough_batches ADD COLUMN recipe_snapshot LONGTEXT NULL AFTER recipe_id');
            $results[]='Added immutable recipe snapshots';
        }
        foreach (['rim_rise_score','crispness_score','bottom_color_score'] as $name) {
            if (!$columnExists('evidence',$name)) {
                db()->exec('ALTER TABLE evidence ADD COLUMN `'.$name.'` TINYINT UNSIGNED NULL');
                $results[]='Added '.$name.' evidence rating';
            }
        }
        if (!$columnExists('evidence','batch_id')) {
            db()->exec('ALTER TABLE evidence ADD COLUMN batch_id BIGINT UNSIGNED NULL AFTER recipe_id');
            $results[]='Added evidence batch reference';
        }
        $fkExists=(int)query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evidence' AND COLUMN_NAME='batch_id' AND REFERENCED_TABLE_NAME='dough_batches'")->fetchColumn();
        if (!$fkExists) {
            db()->exec('ALTER TABLE evidence ADD CONSTRAINT fk_evidence_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE SET NULL');
            $results[]='Linked evidence to dough batches';
        }
        $indexExists=(int)query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evidence' AND INDEX_NAME='idx_evidence_batch'")->fetchColumn();
        if (!$indexExists) {
            db()->exec('ALTER TABLE evidence ADD INDEX idx_evidence_batch (batch_id)');
            $results[]='Added batch analytics index';
        }
        // Future updates must not retroactively assume which formula older batches used.
        // An empty recipe_snapshot is displayed as unknown, not fabricated.
        // v1.3.0: immutable per-batch process plans, editable recipe steps,
        // container-specific proofing temperatures, dough usage and waste.
        $schema=file_get_contents(__DIR__.'/schema.sql');
        foreach (['recipe_steps','batch_step_logs','batch_containers','batch_temperature_readings','batch_usage','batch_waste'] as $tableName) {
            $pattern='/CREATE TABLE IF NOT EXISTS '.preg_quote($tableName,'/').' \(.*?\) ENGINE=InnoDB;/s';
            if(!preg_match($pattern,$schema,$m))throw new RuntimeException('Missing '.$tableName.' schema.');
            db()->exec($m[0]);
            $results[]=$tableName.' verified';
        }
        // v1.4.1: preserve historical ambiguity instead of assigning an invented chef.
        // Two identities: actual baker and the account that submitted the batch.
        if (!$columnExists('dough_batches','made_by_user_id')) {
            db()->exec('ALTER TABLE dough_batches ADD COLUMN made_by_user_id BIGINT UNSIGNED NULL AFTER recipe_id');
            $results[]='Added batch maker (existing entries remain not recorded)';
        }
        if (!$columnExists('dough_batches','recorded_by_user_id')) {
            db()->exec('ALTER TABLE dough_batches ADD COLUMN recorded_by_user_id BIGINT UNSIGNED NULL AFTER made_by_user_id');
            $results[]='Added batch recorder (existing entries remain not recorded)';
        }
        foreach (['made_by_user_id'=>'fk_batch_made_by','recorded_by_user_id'=>'fk_batch_recorded_by'] as $col=>$constraint) {
            $fk=(int)query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME=?',['dough_batches',$col,'users'])->fetchColumn();
            if(!$fk){db()->exec('ALTER TABLE dough_batches ADD CONSTRAINT '.$constraint.' FOREIGN KEY ('.$col.') REFERENCES users(id) ON DELETE RESTRICT');$results[]='Linked batch user field '.$col;}
        }
        if (!(int)query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',['dough_batches','idx_batch_maker'])->fetchColumn()) {
            db()->exec('ALTER TABLE dough_batches ADD INDEX idx_batch_maker (made_by_user_id,created_at)');
            $results[]='Indexed dough makers for future per-chef analytics';
        }
        if (!$columnExists('dough_batches','process_steps_snapshot')) {
            db()->exec('ALTER TABLE dough_batches ADD COLUMN process_steps_snapshot LONGTEXT NULL AFTER recipe_snapshot');
            $results[]='Added frozen process plans to batches';
        }
        // Create-only schema update preserves all existing recipes and batch history.
        $experimentSchema=file_get_contents(__DIR__.'/schema.sql');
        if (!preg_match('/CREATE TABLE IF NOT EXISTS pizza_experiments \(.*?\) ENGINE=InnoDB;/s',$experimentSchema,$experimentMatch)) throw new RuntimeException('Experiment schema missing');
        db()->exec($experimentMatch[0]);
        $results[]='Recipe-agnostic experiment registry ready';
        if (!preg_match('/CREATE TABLE IF NOT EXISTS experiment_ai_reviews \(.*?\) ENGINE=InnoDB;/s',$experimentSchema,$aiMatch)) throw new RuntimeException('AI review schema missing');
        db()->exec($aiMatch[0]);
        $results[]='AI experiment review history ready';
        require_once __DIR__.'/recipe_templates.php';
        // Seeder does not overwrite custom recipes, edited templates or batch snapshots.
        foreach(pizza_lab_seed_recipes(db()) as $message)$results[]=$message;
        foreach(pizza_lab_upgrade_default_stonefellows_process(db()) as $message)$results[]=$message;
        if (!preg_match('/CREATE TABLE IF NOT EXISTS ai_recipe_proposals \(.*?\) ENGINE=InnoDB;/s',$schema,$proposalTable)) throw new RuntimeException('AI proposal schema missing');
        db()->exec($proposalTable[0]);
        $results[]='v1.5.3 AI proposal approval registry ready';
        foreach (['workdays','workday_batch_events','workday_pizzas','visual_comparisons'] as $tableName) {
            $pattern='/CREATE TABLE IF NOT EXISTS '.preg_quote($tableName,'/').' \(.*?\) ENGINE=InnoDB;/s';
            if (!preg_match($pattern,$schema,$m)) throw new RuntimeException('Missing '.$tableName.' schema.');
            db()->exec($m[0]);
            $results[]='v1.5.4 '. $tableName .' ready';
        }
        // Create-only v1.5.5 upgrades: no edits to historical readings or recipes.
        foreach (['oven_layouts','oven_zone_readings','dough_readiness_checks','pizza_tracker_boxes'] as $tableName) {
            $pattern='/CREATE TABLE IF NOT EXISTS '.preg_quote($tableName,'/').' \(.*?\) ENGINE=InnoDB;/s';
            if (!preg_match($pattern,$schema,$m)) throw new RuntimeException('Missing '.$tableName.' schema.');
            db()->exec($m[0]);
            $results[]='v1.5.5 '.$tableName.' ready';
        }
        // v1.5.6: create-only cooking position history. Every temperature is measured, never inferred.
        foreach (['oven_cook_sessions','oven_cook_events'] as $tableName) {
            $pattern='/CREATE TABLE IF NOT EXISTS '.preg_quote($tableName,'/').' \(.*?\) ENGINE=InnoDB;/s';
            if (!preg_match($pattern,$schema,$m)) throw new RuntimeException('Missing '.$tableName.' schema.');
            db()->exec($m[0]);
            $results[]='v1.5.6 '.$tableName.' ready';
        }
        $results[]='v1.4.1 batch maker attribution and role-aware access verified';
    } catch (Throwable $e) {
        error_log('Pizza Lab upgrade failure: '.$e->getMessage());
        $error='Upgrade stopped; review database backup and server error log before retrying. It is safe to run again after resolving the problem.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pizza Lab Database Upgrade</title><link rel="stylesheet" href="assets/style.css"></head><body class="center-screen"><main class="auth-box"><div class="brand-mark">◉</div><h1>Pizza Lab database update</h1><p class="muted">For existing installations only. Make a full database backup before running. Fresh installations already contain these changes.</p><?php if($error): ?><div class="danger"><?=htmlspecialchars($error,ENT_QUOTES)?></div><?php endif; ?><?php if($results): ?><div class="notice-strip"><?php foreach($results as $r):?><p><?=htmlspecialchars($r,ENT_QUOTES)?></p><?php endforeach;?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf(),ENT_QUOTES)?>"><button class="primary">Apply safe schema update</button></form><p><a href="index.php">Return to Pizza Lab →</a></p></main></body></html>
