'use strict';
const assert=require('node:assert/strict');const {parseCommand}=require('../assets/agent_chat_commands.js');
const cases=[['batch PL-123 at 68 degrees',{type:'kitchen',action_type:'dough_temp',term:'PL-123',temperature_f:68}],['we just balled batch PL-123',{type:'kitchen',action_type:'batch_balled',term:'PL-123'}],['refrigerator at 38 degrees',{type:'kitchen',action_type:'fridge_temp',location_label:'Refrigerator',temperature_f:38}]];
for(const [s,expect] of cases)assert.deepEqual(parseCommand(s),expect,s);
for(const s of ['Batch was warm','Balled some dough','Delete batch PL-123','The fridge might be too hot'])assert.equal(parseCommand(s),null);
console.log('PASS: safe kitchen commands with exact batch codes and temperature input');
