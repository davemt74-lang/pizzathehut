CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 display_name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('super_admin','team_member') NOT NULL DEFAULT 'team_member',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS team_audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NOT NULL,
 target_user_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(40) NOT NULL,
 occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_team_audit (target_user_id,occurred_at),
 CONSTRAINT fk_team_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_team_audit_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS recipes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 parent_id BIGINT UNSIGNED NULL,
 name VARCHAR(180) NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'experimental',
 flour_name VARCHAR(180) NOT NULL DEFAULT '',
 flour_g DECIMAL(12,2) NULL,
 water_g DECIMAL(12,2) NULL,
 salt_g DECIMAL(12,2) NULL,
 oil_g DECIMAL(12,2) NULL,
 honey_g DECIMAL(12,2) NULL,
 yeast_g DECIMAL(12,2) NULL,
 yeast_type VARCHAR(40) NOT NULL DEFAULT 'fresh compressed',
 process_notes TEXT NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX (parent_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS dough_batches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_code VARCHAR(12) NOT NULL UNIQUE,
 recipe_id BIGINT UNSIGNED NOT NULL,
 made_by_user_id BIGINT UNSIGNED NULL COMMENT 'Team member who physically made the dough; unknown for legacy batches',
 recorded_by_user_id BIGINT UNSIGNED NULL COMMENT 'Account that created the batch record; unknown for legacy batches',
 recipe_snapshot LONGTEXT NULL,
 process_steps_snapshot LONGTEXT NULL,
 label VARCHAR(180) NOT NULL DEFAULT '',
 stage VARCHAR(20) NOT NULL DEFAULT 'bulk',
 mixed_at DATETIME NULL,
 balled_at DATETIME NULL,
 dough_temp_f DECIMAL(6,2) NULL,
 fridge_temp_f DECIMAL(6,2) NULL,
 container_count SMALLINT UNSIGNED NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_batch_recipe (recipe_id),
 INDEX idx_batch_maker (made_by_user_id,created_at),
 INDEX idx_batch_stage (stage,created_at),
 CONSTRAINT fk_batch_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_batch_made_by FOREIGN KEY (made_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_batch_recorded_by FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS dough_batch_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 phase VARCHAR(25) NOT NULL,
 occurred_at DATETIME NOT NULL,
 temperature_f DECIMAL(6,2) NULL,
 note VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_batch_events (batch_id,occurred_at),
 CONSTRAINT fk_dough_event_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS evidence (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 recipe_id BIGINT UNSIGNED NULL,
 batch_id BIGINT UNSIGNED NULL,
 label VARCHAR(180) NOT NULL,
 pizza_size_in DECIMAL(5,2) NULL DEFAULT NULL,
 dough_ball_g DECIMAL(8,2) NULL DEFAULT NULL,
 sauce_g DECIMAL(8,2) NULL,
 cheese_g DECIMAL(8,2) NULL,
 cheese_moisture TINYINT UNSIGNED NULL COMMENT '1=driest, 10=wettest perceived moisture',
 rim_rise_score TINYINT UNSIGNED NULL,
 crispness_score TINYINT UNSIGNED NULL,
 bottom_color_score TINYINT UNSIGNED NULL,
 dough_age_h DECIMAL(8,2) NULL,
 stone_start_f DECIMAL(7,2) NULL,
 stone_finish_f DECIMAL(7,2) NULL,
 bake_seconds INT UNSIGNED NULL DEFAULT NULL,
 oven_load TINYINT UNSIGNED NULL DEFAULT NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX (recipe_id),
 INDEX idx_evidence_batch (batch_id),
 CONSTRAINT fk_evidence_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE SET NULL,
 CONSTRAINT fk_evidence_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS media (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evidence_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(25) NOT NULL,
 stored_name VARCHAR(100) NOT NULL,
 mime VARCHAR(64) NOT NULL,
 original_name VARCHAR(180) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(evidence_id),
 CONSTRAINT fk_media_evidence FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS oven_readings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evidence_id BIGINT UNSIGNED NOT NULL,
 phase VARCHAR(30) NOT NULL DEFAULT 'check',
 stone_f DECIMAL(7,2) NOT NULL,
 elapsed_seconds INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(evidence_id),
 CONSTRAINT fk_reading_evidence FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS observations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evidence_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(20) NOT NULL,
 text LONGTEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(evidence_id,kind,created_at),
 CONSTRAINT fk_observation_evidence FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evidence_id BIGINT UNSIGNED NOT NULL,
 elapsed_seconds INT UNSIGNED NOT NULL DEFAULT 0,
 message VARCHAR(500) NOT NULL,
 actor_profile VARCHAR(80) NULL,
 event_source VARCHAR(20) NOT NULL DEFAULT 'manual',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX(evidence_id,created_at),
 CONSTRAINT fk_event_evidence FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS settings (
 name VARCHAR(100) NOT NULL PRIMARY KEY,
 value LONGTEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS voice_profiles (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recipe_steps (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 recipe_id BIGINT UNSIGNED NOT NULL,
 step_key VARCHAR(50) NOT NULL,
 sort_order INT UNSIGNED NOT NULL,
 stage VARCHAR(24) NOT NULL,
 title VARCHAR(160) NOT NULL,
 instructions TEXT NULL,
 target_start_min DECIMAL(10,1) NULL,
 target_min_minutes DECIMAL(10,1) NULL,
 target_max_minutes DECIMAL(10,1) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_recipe_step (recipe_id,step_key),
 INDEX idx_recipe_step_order (recipe_id,sort_order),
 CONSTRAINT fk_step_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS batch_step_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 step_key VARCHAR(50) NOT NULL,
 started_at DATETIME NULL,
 finished_at DATETIME NULL,
 actual_amount_g DECIMAL(12,2) NULL,
 actual_temp_f DECIMAL(6,2) NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_batch_step (batch_id,step_key),
 CONSTRAINT fk_log_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS batch_containers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 container_code VARCHAR(24) NOT NULL,
 label VARCHAR(120) NOT NULL DEFAULT '',
 split_at DATETIME NULL,
 mass_g DECIMAL(12,2) NULL,
 temperature_f DECIMAL(6,2) NULL,
 notes TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_batch_container (batch_id,container_code),
 INDEX idx_container_batch (batch_id),
 CONSTRAINT fk_container_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS batch_temperature_readings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 container_id BIGINT UNSIGNED NULL,
 measured_at DATETIME NOT NULL,
 temperature_f DECIMAL(6,2) NOT NULL,
 notes VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_batch_temp (batch_id,measured_at),
 CONSTRAINT fk_temp_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE CASCADE,
 CONSTRAINT fk_temp_container FOREIGN KEY (container_id) REFERENCES batch_containers(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS batch_usage (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(12) NOT NULL,
 occurred_at DATETIME NOT NULL,
 portions_count INT UNSIGNED NULL,
 weight_g DECIMAL(12,2) NULL,
 notes VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_batch_usage (batch_id,occurred_at),
 CONSTRAINT fk_usage_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS batch_waste (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 container_id BIGINT UNSIGNED NULL,
 occurred_at DATETIME NOT NULL,
 weight_g DECIMAL(12,2) NOT NULL,
 reason VARCHAR(120) NOT NULL,
 notes VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_batch_waste (batch_id,occurred_at),
 CONSTRAINT fk_waste_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE CASCADE,
 CONSTRAINT fk_waste_container FOREIGN KEY (container_id) REFERENCES batch_containers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Recipe-agnostic experiment registry: every variation is a real recipe.
CREATE TABLE IF NOT EXISTS pizza_experiments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(180) NOT NULL,
 hypothesis TEXT NULL,
 source_recipe_id BIGINT UNSIGNED NOT NULL,
 variation_recipe_id BIGINT UNSIGNED NOT NULL,
 target_metric VARCHAR(35) NOT NULL DEFAULT 'crispness',
 status VARCHAR(20) NOT NULL DEFAULT 'planned',
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_experiment_source (source_recipe_id),
 INDEX idx_experiment_variation (variation_recipe_id),
 CONSTRAINT fk_experiment_source FOREIGN KEY (source_recipe_id) REFERENCES recipes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_experiment_variation FOREIGN KEY (variation_recipe_id) REFERENCES recipes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_experiment_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS experiment_ai_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 experiment_id BIGINT UNSIGNED NOT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 model VARCHAR(100) NOT NULL,
 evidence_snapshot_json LONGTEXT NOT NULL,
 analysis_json LONGTEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_experiment_review (experiment_id,created_at),
 INDEX idx_experiment_review_author (created_by_user_id,created_at),
 CONSTRAINT fk_ai_exp FOREIGN KEY (experiment_id) REFERENCES pizza_experiments(id) ON DELETE CASCADE,
 CONSTRAINT fk_ai_author FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;


-- AI dough coach reports remain owned by the requesting user; snapshots are immutable.
CREATE TABLE IF NOT EXISTS batch_ai_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 scope ENUM('all','recipe','batch') NOT NULL,
 target_id BIGINT UNSIGNED NULL,
 question VARCHAR(600) NOT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 model VARCHAR(100) NOT NULL,
 evidence_snapshot_json LONGTEXT NOT NULL,
 analysis_json LONGTEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_batch_ai_user_scope (created_by_user_id,scope,target_id,id),
 INDEX idx_batch_ai_rate (created_by_user_id,created_at),
 CONSTRAINT fk_batch_ai_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Pending Claude suggestions are not executable recipes until a signed-in member approves.
CREATE TABLE IF NOT EXISTS ai_recipe_proposals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source_recipe_id BIGINT UNSIGNED NOT NULL,
 source_recipe_hash CHAR(64) NOT NULL,
 title VARCHAR(180) NOT NULL,
 hypothesis TEXT NULL,
 target_metric VARCHAR(35) NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 proposed_changes_json LONGTEXT NOT NULL,
 step_guidance TEXT NULL,
 evidence_basis TEXT NULL,
 model VARCHAR(100) NOT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 decided_by_user_id BIGINT UNSIGNED NULL,
 approved_experiment_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 decided_at DATETIME NULL,
 INDEX idx_proposal_pending (status,id),
 INDEX idx_proposal_creator (created_by_user_id,created_at),
 CONSTRAINT fk_proposal_recipe FOREIGN KEY (source_recipe_id) REFERENCES recipes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_proposal_author FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_proposal_decider FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_proposal_experiment FOREIGN KEY (approved_experiment_id) REFERENCES pizza_experiments(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- v1.5.4 workday records: a batch may span several days; pizza tests belong to one recorded day.
CREATE TABLE IF NOT EXISTS workdays (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 work_date DATE NOT NULL,
 title VARCHAR(160) NOT NULL DEFAULT 'Pizza production',
 notes TEXT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_workday_date (work_date,id),
 CONSTRAINT fk_workday_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS workday_batch_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 workday_id BIGINT UNSIGNED NOT NULL,
 batch_id BIGINT UNSIGNED NOT NULL,
 activity ENUM('mixed','split','proofed','balled','baked','used','wasted','temperature','other') NOT NULL,
 occurred_at DATETIME NOT NULL,
 recorded_by_user_id BIGINT UNSIGNED NOT NULL,
 notes VARCHAR(1000) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_wbe_workday (workday_id,occurred_at),
 INDEX idx_wbe_batch (batch_id,occurred_at),
 CONSTRAINT fk_wbe_day FOREIGN KEY (workday_id) REFERENCES workdays(id) ON DELETE CASCADE,
 CONSTRAINT fk_wbe_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE RESTRICT,
 CONSTRAINT fk_wbe_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS workday_pizzas (
 evidence_id BIGINT UNSIGNED PRIMARY KEY,
 workday_id BIGINT UNSIGNED NOT NULL,
 recorded_by_user_id BIGINT UNSIGNED NOT NULL,
 linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_workday_pizzas_day (workday_id,evidence_id),
 CONSTRAINT fk_wp_evidence FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE,
 CONSTRAINT fk_wp_workday FOREIGN KEY (workday_id) REFERENCES workdays(id) ON DELETE CASCADE,
 CONSTRAINT fk_wp_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
-- v1.5.4 comparison reviews are evidence snapshots, not authoritative measurements.
CREATE TABLE IF NOT EXISTS visual_comparisons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evidence_a_id BIGINT UNSIGNED NOT NULL,
 evidence_b_id BIGINT UNSIGNED NOT NULL,
 view_angle ENUM('top','bottom','side','crumb') NOT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 model VARCHAR(100) NOT NULL,
 snapshot_json LONGTEXT NOT NULL,
 analysis_json LONGTEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_visual_pair (evidence_a_id,evidence_b_id,id),
 CONSTRAINT fk_vc_evidence_a FOREIGN KEY (evidence_a_id) REFERENCES evidence(id) ON DELETE RESTRICT,
 CONSTRAINT fk_vc_evidence_b FOREIGN KEY (evidence_b_id) REFERENCES evidence(id) ON DELETE RESTRICT,
 CONSTRAINT fk_vc_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- v1.5.5: physical oven dimensions and manually observed heat zones.
-- x/y are normalized to the ellipse bounding rectangle (0=west/north, 1=east/south).
CREATE TABLE IF NOT EXISTS oven_layouts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(140) NOT NULL,
 north_south_in DECIMAL(8,2) NOT NULL,
 east_west_in DECIMAL(8,2) NOT NULL,
 internal_height_in DECIMAL(8,2) NOT NULL,
 fire_x DECIMAL(6,4) NOT NULL,
 fire_y DECIMAL(6,4) NOT NULL,
 zones_json LONGTEXT NOT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 updated_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_oven_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_oven_editor FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS oven_zone_readings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 oven_layout_id BIGINT UNSIGNED NOT NULL,
 zone_key VARCHAR(28) NOT NULL,
 stone_f DECIMAL(7,2) NOT NULL,
 observed_at DATETIME NOT NULL,
 recorded_by_user_id BIGINT UNSIGNED NOT NULL,
 notes VARCHAR(600) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_oven_zone_temp (oven_layout_id,zone_key,observed_at),
 CONSTRAINT fk_oven_zone_layout FOREIGN KEY (oven_layout_id) REFERENCES oven_layouts(id) ON DELETE CASCADE,
 CONSTRAINT fk_oven_zone_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS dough_readiness_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 container_id BIGINT UNSIGNED NULL,
 observed_at DATETIME NOT NULL,
 dough_temp_f DECIMAL(6,2) NULL,
 rise_score TINYINT UNSIGNED NULL COMMENT '1-5 visual expansion rating, subjective',
 handling ENUM('unknown','tight','balanced','slack','sticky','tearing') NOT NULL DEFAULT 'unknown',
 notes VARCHAR(1500) NOT NULL DEFAULT '',
 recorded_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_readiness_batch (batch_id,observed_at),
 CONSTRAINT fk_readiness_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE CASCADE,
 CONSTRAINT fk_readiness_container FOREIGN KEY (container_id) REFERENCES batch_containers(id) ON DELETE SET NULL,
 CONSTRAINT fk_readiness_author FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS pizza_tracker_boxes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 media_id BIGINT UNSIGNED NOT NULL UNIQUE,
 evidence_id BIGINT UNSIGNED NOT NULL,
 x_norm DECIMAL(6,4) NOT NULL,
 y_norm DECIMAL(6,4) NOT NULL,
 width_norm DECIMAL(6,4) NOT NULL,
 height_norm DECIMAL(6,4) NOT NULL,
 source ENUM('ai','manual','local') NOT NULL DEFAULT 'manual',
 confidence ENUM('low','medium','high') NOT NULL DEFAULT 'low',
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_tracker_evidence (evidence_id,created_at),
 CONSTRAINT fk_tracker_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
 CONSTRAINT fk_tracker_evidence FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE,
 CONSTRAINT fk_tracker_author FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- v1.5.6: a pizza is explicitly assigned to one oven; position history is optional.
CREATE TABLE IF NOT EXISTS oven_cook_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 oven_layout_id BIGINT UNSIGNED NOT NULL,
 evidence_id BIGINT UNSIGNED NOT NULL UNIQUE,
 notes VARCHAR(1000) NOT NULL DEFAULT '',
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_oven_session (oven_layout_id,created_at),
 CONSTRAINT fk_cook_oven FOREIGN KEY (oven_layout_id) REFERENCES oven_layouts(id) ON DELETE RESTRICT,
 CONSTRAINT fk_cook_pizza FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE,
 CONSTRAINT fk_cook_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS oven_cook_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 phase ENUM('launch','initial_puff','pull_back','rotate','finish','check') NOT NULL,
 zone_key VARCHAR(28) NOT NULL,
 zone_name VARCHAR(80) NOT NULL,
 x_norm DECIMAL(6,4) NOT NULL,
 y_norm DECIMAL(6,4) NOT NULL,
 elapsed_seconds INT UNSIGNED NULL,
 stone_f DECIMAL(7,2) NULL COMMENT 'Actual user-entered IR stone reading, never inferred',
 notes VARCHAR(1000) NOT NULL DEFAULT '',
 observed_at DATETIME NOT NULL,
 recorded_by_user_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_cook_event (session_id,observed_at,id),
 CONSTRAINT fk_cook_event_session FOREIGN KEY (session_id) REFERENCES oven_cook_sessions(id) ON DELETE CASCADE,
 CONSTRAINT fk_cook_event_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- v1.5.7: structured, idempotent cooking activity. Reminder vs confirmed action always explicit.
CREATE TABLE IF NOT EXISTS oven_cook_activity (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evidence_id BIGINT UNSIGNED NOT NULL,
 session_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(40) NOT NULL,
 source VARCHAR(25) NOT NULL,
 event_class ENUM('observed','reminder','suggestion') NOT NULL,
 elapsed_seconds INT UNSIGNED NULL,
 media_id BIGINT UNSIGNED NULL,
 client_event_id VARCHAR(64) NOT NULL,
 created_by_user_id BIGINT UNSIGNED NOT NULL,
 occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_activity_client (created_by_user_id,client_event_id),
 INDEX idx_cook_activity_timeline (evidence_id,occurred_at,id),
 CONSTRAINT fk_activity_evidence FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE,
 CONSTRAINT fk_activity_session FOREIGN KEY (session_id) REFERENCES oven_cook_sessions(id) ON DELETE SET NULL,
 CONSTRAINT fk_activity_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE SET NULL,
 CONSTRAINT fk_activity_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- v1.5.15: confirmed and idempotent conversational kitchen observations
CREATE TABLE IF NOT EXISTS chat_kitchen_actions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 client_event_id VARCHAR(64) NOT NULL,
 action_type ENUM('dough_temp','fridge_temp','batch_balled') NOT NULL,
 source ENUM('manual','voice_command') NOT NULL,
 batch_id BIGINT UNSIGNED NULL,
 measured_f DECIMAL(6,2) NULL,
 environment_label VARCHAR(120) NULL,
 observed_at DATETIME NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_chat_action (user_id,client_event_id),
 INDEX idx_chat_action_batch (batch_id,observed_at),
 CONSTRAINT fk_chat_action_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_chat_action_batch FOREIGN KEY (batch_id) REFERENCES dough_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS kitchen_environment_readings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 action_id BIGINT UNSIGNED NOT NULL UNIQUE,
 location_label VARCHAR(120) NOT NULL,
 temperature_f DECIMAL(6,2) NOT NULL,
 observed_at DATETIME NOT NULL,
 CONSTRAINT fk_kitchen_env_action FOREIGN KEY (action_id) REFERENCES chat_kitchen_actions(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- v1.5.16A: restart-safe shared cooking timers; evidence-row locking serializes actions.
CREATE TABLE IF NOT EXISTS kitchen_activity_timers (
 evidence_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 status ENUM('running','paused','stopped') NOT NULL DEFAULT 'stopped',
 started_at DATETIME(6) NULL,
 accumulated_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
 target_seconds INT UNSIGNED NULL,
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
 updated_by_user_id BIGINT UNSIGNED NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 INDEX idx_kitchen_timer_status (status,updated_at),
 CONSTRAINT fk_kitchen_timer_pizza FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE,
 CONSTRAINT fk_kitchen_timer_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- v1.5.16B: one advisory alert per timer run/milestone (not a chef action)
CREATE TABLE IF NOT EXISTS kitchen_timer_reminders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 evidence_id BIGINT UNSIGNED NOT NULL,
 start_activity_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 reminder_type ENUM('rotation_reminder','pull_back_reminder','time_up_reminder') NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_kitchen_reminder_run (evidence_id,start_activity_id,reminder_type),
 UNIQUE KEY uq_kitchen_reminder_event (activity_id),
 INDEX idx_kitchen_reminder_recent (created_at),
 CONSTRAINT fk_kitchen_reminder_pizza FOREIGN KEY (evidence_id) REFERENCES evidence(id) ON DELETE CASCADE,
 CONSTRAINT fk_kitchen_reminder_start FOREIGN KEY (start_activity_id) REFERENCES oven_cook_activity(id) ON DELETE CASCADE,
 CONSTRAINT fk_kitchen_reminder_event FOREIGN KEY (activity_id) REFERENCES oven_cook_activity(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS kitchen_reminder_receipts (
 reminder_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 claimed_at DATETIME(6) NOT NULL,
 PRIMARY KEY (reminder_id,user_id),
 INDEX idx_kitchen_reminder_receipts_user (user_id,claimed_at),
 CONSTRAINT fk_kitchen_receipt_reminder FOREIGN KEY (reminder_id) REFERENCES kitchen_timer_reminders(id) ON DELETE CASCADE,
 CONSTRAINT fk_kitchen_receipt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
