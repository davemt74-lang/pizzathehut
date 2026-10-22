'use strict';
const {parseCommand}=require('../assets/agent_chat_commands.js');
const assert=require('node:assert/strict');
const tests=[
 ['open menu',{type:'menu'}],['show navigation',{type:'menu'}],
 ['show all recipes',{type:'recipes'}],['find recipe Stonefellows v1.0',{type:'recipe',term:'Stonefellows v1.0'}],
 ['select batch PL-K7M4X2',{type:'batch',term:'PL-K7M4X2'}],
 ['show batches',{type:'batches'}],['list recent workdays',{type:'workdays'}],
 ['open pizza camera',{type:'camera_open'}],['close camera',{type:'camera_close'}],
 ['open live oven',{type:'tool',href:'index.php?workspace=1#live',title:'Live oven & timers'}],
 ['open oven layout',{type:'tool',href:'oven_layout.php',title:'Oven builder'}],
 ['open settings',{type:'tool',href:'index.php?workspace=1#settings',title:'Workspace settings'}],
 ['open claude api key',{type:'ai_settings'}],
 ['start timer',{type:'cook_pick',event_type:'timer_started',target_seconds:480}],
 ['start timer for 6 minutes',{type:'cook_pick',event_type:'timer_started',target_seconds:360}],
 ['start timer for pizza 42',{type:'cook',event_type:'timer_started',evidence_id:42}],
 ['pizza 42',{type:'cook_choose',evidence_id:42}],
 ['confirm timer',{type:'cook_confirm'}],['cancel timer',{type:'cook_cancel'}],
 ['create workday today called Night dough prep',{type:'create_workday',when:'today',title:'Night dough prep'}],
 ['new workday tomorrow',{type:'create_workday',when:'tomorrow',title:'Pizza production'}],
 ['create a workday 2026-10-23 named Lunch shift',{type:'create_workday',when:'2026-10-23',title:'Lunch shift'}],
 ['help',{type:'help'}],['what can you do?',{type:'help'}]
];
for(const [prompt,expected] of tests)assert.deepEqual(parseCommand(prompt),expected,prompt);
for(const prompt of ['I need tips for pizza dough hydration','Should I change the Stonefellows recipe?','Please change the recipe','Delete batch 5','Create all workdays','create workday today and delete records','take photo right now','rotate my pizza','analyze batch 17'])assert.equal(parseCommand(prompt),null,`Must not treat ${prompt} as a local command`);
assert.equal(parseCommand('x'.repeat(601)),null,'limit input size');
const fs=require('fs');const nav=fs.readFileSync(require('path').resolve(__dirname,'../assets/agent_chat_nav.js'),'utf8');
assert.match(nav,/aria-expanded/);assert.match(nav,/\.inert=true/);assert.match(nav,/e\.key==='Escape'/);assert.match(nav,/lastFocus/);
console.log(`PASS: ${tests.length} recognized commands, unsupported mutation fallbacks, navigation keyboard and focus rules`);
