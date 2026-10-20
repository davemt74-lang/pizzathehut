#!/bin/sh
# Source/UI/schema contract tests, not an installed MySQL/iPhone/Claude certification.
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
