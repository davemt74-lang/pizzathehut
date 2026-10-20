'use strict';
const {parseCommand}=require('../assets/agent_chat_commands.js');const assert=require('node:assert/strict');
const cases=[['start timer for pizza 12',{type:'cook',event_type:'timer_started',evidence_id:12}],['stop pizza timer for pizza #12',{type:'cook',event_type:'timer_stopped',evidence_id:12}],['rotate pizza 12',{type:'cook',event_type:'rotation_confirmed',evidence_id:12}],['we just pulled back pizza 17',{type:'cook',event_type:'pull_back_confirmed',evidence_id:17}],['finish pizza 12',{type:'cook',event_type:'pizza_finished',evidence_id:12}]];
for(const [s,v] of cases)assert.deepEqual(parseCommand(s),v,s);
for(const s of ['rotate the pizza','start pizza timer','timer is on','pizza timer start','rotate the dough'])assert.equal(parseCommand(s),null,s);
console.log('PASS: exact pizza cooking commands and ambiguous action fallbacks');
