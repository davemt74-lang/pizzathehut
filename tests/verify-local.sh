#!/bin/sh
# Source/UI/schema contract checks; not installed MySQL/iPhone/Claude certification.
set -eu
cd "$(dirname "$0")/.."
for f in ./*.php; do php -l "$f" >/dev/null; done
php tests/verify-schema.php
if command -v node >/dev/null 2>&1; then node --check assets/app.js; node tests/verify-batches.js; fi
printf '%s\n' 'PASS: PHP + JavaScript syntax and batch source contracts'
if command -v node >/dev/null 2>&1; then node tests/verify-process.js; fi
php -r 'require "recipe_templates.php"; $r=pizza_lab_recipe_templates(); if(abs($r["Gelato v1.0"]["water_g"]-5800)>0.001 || count($r["Stonefellows v1.0"]["steps"])!==8 || $r["Stonefellows v1.0"]["yeast_g"]!==85)throw new Exception("Recipe seed drift"); echo "PASS: recipe totals and process template data\n";'
if command -v node >/dev/null 2>&1; then node tests/verify-team.js; fi
if command -v node >/dev/null 2>&1; then node tests/verify-makers.js; fi
php tests/verify-experiments.php
php tests/verify-insights.php
php tests/verify-coach.php
php tests/verify-proposals.php
php tests/verify-workdays.php
php tests/verify-readiness-oven.php
node --check assets/oven_layout.js
node --check assets/pizza_tracker.js
php tests/verify-heatmap.php
node --check assets/heatmap.js
php tests/verify-cook-activity.php
php tests/verify-cooking-patterns.php
node --check assets/agent_chat_voice.js
php tests/verify-chat-first.php
# v1.5.9 in-chat pizza tracking, boundary checks and browser event test.
node --check assets/agent_chat_camera.js
php tests/verify-inline-camera.php
node tests/verify-inline-camera.js

# v1.5.10 deterministic commands and accessible hamburger navigation.
node --check assets/agent_chat_commands.js
node --check assets/agent_chat_nav.js
node tests/verify-chat-navigation.js
php tests/verify-chat-navigation.php
node --check assets/agent_chat_account.js
php tests/verify-account.php

php tests/verify-conversation.php
