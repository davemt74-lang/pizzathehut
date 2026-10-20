'use strict';
const assert=require('node:assert/strict');
const {parseBatchCommand}=require('../assets/agent_chat_batch.js');
const positive=[
 ["let's make a batch of dough, main recipe",{type:'start',recipe:'main recipe'}],
 ["Pizza, let's make a batch of dough using the main recipe",{type:'start',recipe:'main recipe'}],
 ["make a batch with Stonefellows v1.0",{type:'start',recipe:'stonefellows v1.0'}],
 ["start dough batch",{type:'start',recipe:'main recipe'}],
 ["continue batch PL-A2K7P9",{type:'resume',code:'pl-a2k7p9'}],
 ["resume batch",{type:'resume',code:null}],
 ["next step",{type:'current'}],
 ["where did we leave off",{type:'current'}],
 ["step done",{type:'complete'}],
 ["i finished that step",{type:'complete'}],
 ["list active dough sessions",{type:'list'}]
];
for(const [input,expect] of positive)assert.deepEqual(parseBatchCommand(input),expect,input);
for(const input of ["I think we should use less water","Should I make a batch?","delete the main recipe","rotate pizza #22","I made a batch yesterday","mix flour and water","I am done","show batches","help","make a batch and delete recipe","batch completed","create 10 batches"])assert.equal(parseBatchCommand(input),null,'Unsafe or off-topic command: '+input);
assert.equal(parseBatchCommand('x'.repeat(601)),null);
console.log('PASS: optional batch intents recognized without unsafe automatic writes or step completions');
