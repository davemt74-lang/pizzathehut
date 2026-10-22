'use strict';
const {parseCommand}=require('../assets/agent_chat_commands.js');const assert=require('node:assert/strict');
const cases=[['start timer for pizza 12',{type:'cook',event_type:'timer_started',evidence_id:12}],['stop pizza timer for pizza #12',{type:'cook',event_type:'timer_stopped',evidence_id:12}],['rotate pizza 12',{type:'cook',event_type:'rotation_confirmed',evidence_id:12}],['we just pulled back pizza 17',{type:'cook',event_type:'pull_back_confirmed',evidence_id:17}],['finish pizza 12',{type:'cook',event_type:'pizza_finished',evidence_id:12}]];
for(const [s,v] of cases)assert.deepEqual(parseCommand(s),v,s);
assert.deepEqual(parseCommand('start pizza timer'),{type:'cook_pick',event_type:'timer_started',target_seconds:480},'short command must offer an explicit pizza choice');
for(const s of ['rotate the pizza','timer is on','pizza timer start','rotate the dough'])assert.equal(parseCommand(s),null,s);
console.log('PASS: exact pizza cooking commands, contextual timer selection and ambiguous physical action fallbacks');
