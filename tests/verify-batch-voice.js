'use strict';
const assert=require('node:assert/strict');
const fs=require('fs'),vm=require('vm');
const {parseBatchCommand}=require('../assets/agent_chat_batch.js');
for(const [input,expected] of [
 ['pizza, read the current step',{type:'read_step'}],
 ['repeat instructions',{type:'read_step'}],
 ['read ingredients',{type:'read_ingredients'}],
 ['confirm step',{type:'confirm_step'}],
 ['approve completed step',{type:'confirm_step'}],
 ['cancel step confirmation',{type:'cancel_step'}],
 ['confirm batch creation',{type:'confirm_batch'}],
 ['cancel batch creation',{type:'cancel_batch'}],
])assert.deepEqual(parseBatchCommand(input),expected,input);
for(const s of ['yes','okay','done','confirm','record it','confirm every step','confirm batch','step was done','confirm step and change recipe'])assert.equal(parseBatchCommand(s),null,s);
const chat=fs.readFileSync('agent_chat.php','utf8');
const bs=chat.indexOf('<script defer src="assets/agent_chat_batch.js?');
const cs=chat.indexOf('<script defer src="assets/agent_chat_commands.js?');
const vs=chat.indexOf('<script defer src="assets/agent_chat_voice.js?');
assert(bs>=0&&cs>bs&&vs>cs,'Batch module must load ahead of command routing and voice handlers');
assert.match(chat,/window\.PIZZA_CHAT_RECIPE_LIST=RECIPES;/);
assert.match(chat,/window\.PIZZA_CHAT_CSRF=CSRF;/);
function fakeDOM(){
 let nodes=[];
 function el(tag){
  const item={tagName:tag.toUpperCase(),children:[],listeners:{},hidden:false,value:'',textContent:'',dataset:{},classList:{toggle(){}},
    append(...children){this.children.push(...children);},replaceChildren(...children){this.children=[...children];},
    remove(){this.removed=true;},scrollIntoView(){},
    addEventListener(event,fn){this.listeners[event]=fn;},
    querySelectorAll(tag){return this.children.filter(x=>x.tagName===tag.toUpperCase()&&!x.removed);},
    setAttribute(){},parentNode:null};
  nodes.push(item);return item;
 }
 const history=el('section'),status=el('span'),parent=el('main');
 history.parentNode=parent;parent.insertBefore=(n)=>parent.children.push(n);
 const listeners={};const doc={visibilityState:'visible',createElement:el,
   getElementById(id){return id==='coachHistory'?history:id==='coachStatus'?status:null;},
   addEventListener(type,fn){listeners[type]=fn}};
 return {history,status,doc,listeners,nodes};
}
async function flush(){await new Promise(resolve=>setImmediate(resolve));}
async function scenario(){
 const {history,doc,listeners,nodes}=fakeDOM();
 const server={sessions:[],current:null,events:[],requestCount:0};
 const plan={recipe_id:3,recipe_name:'Main recipe',requires_selection:false,step_count:2,fingerprint:'a'.repeat(64),
   ingredients:{flour_g:1000,water_g:580,salt_g:22,oil_g:null,honey_g:0,yeast_g:3},
   steps:[{step_key:'mix',title:'Mix flour',instructions:'Mix for three minutes.',target_min_minutes:3,target_max_minutes:3},
          {step_key:'ball',title:'Ball dough',instructions:'Shape dough balls.',target_min_minutes:null,target_max_minutes:null}]};
 const voice=[],sent=[],winListeners={};
 async function fetch(url,options={}){
  const u=String(url),post=options.method==='POST';
  const d=post?JSON.parse(options.body||'{}'):{};
  const action=post?d.action:new URL(u,'http://localhost/').searchParams.get('action');
  server.requestCount++;
  let response={ok:true};
  if(action==='chatBatchSessions')response.sessions=server.sessions;
  else if(action==='chatBatchPlan')response.plan=plan;
  else if(action==='chatBatchSession')response.session=server.current;
  else if(action==='chatBatchStart'){
   assert.equal(d.confirmed,true);assert.equal(d.client_request_id.length>=12,true);
   server.current={session_id:9,batch_id:43,batch_code:'PL-A2K7P9',recipe_name:'Main recipe',next_step_index:0,step_count:2,
     ingredients:plan.ingredients,status:'active',current_step:plan.steps[0]};
   server.sessions=[{session_id:9,batch_code:'PL-A2K7P9',recipe_name:'Main recipe'}];
   response.session=server.current;response.existing=false;server.events.push('batch-created');
  }else if(action==='chatBatchStep'){
   assert.equal(d.confirmed,true);assert.equal(d.step_key,server.current.current_step?.step_key);
   server.current={...server.current,next_step_index:1,current_step:plan.steps[1]};
   response.session=server.current;response.existing=false;server.events.push('step-saved');
  }else throw Error('Unexpected API call '+action);
  return {ok:true,json:async()=>response};
 }
 const win={PIZZA_CHAT_CSRF:'csrf',PIZZA_CHAT_RECIPE_LIST:[{id:3,name:'Main recipe'}],
   crypto:{randomUUID(){return 'unique_request_123456789';}},
   dispatchEvent(e){sent.push(e.type);if(e.type==='pizza-chat-speak-request')voice.push(e.detail.text);},
   addEventListener(k,fn){winListeners[k]=fn}};
 const env={window:win,document:doc,fetch,URL,URLSearchParams,console,Date,CustomEvent:class {constructor(type,init){this.type=type;this.detail=init?.detail}},
   setTimeout,clearTimeout};
 vm.runInNewContext(fs.readFileSync('assets/agent_chat_batch.js','utf8'),env);await flush();
 assert.equal(server.events.length,0,'No default background writes');
 const invoke=(v,source='voice_command')=>win.pizzaChatBatchCommand(v,source);
 await invoke("Pizza, let's make a batch of dough, main recipe");
 assert.equal(server.events.length,0,'Voice batch phrase must only preview');
 assert(voice.some(x=>x.includes('confirm batch creation')),'Expected spoken confirmation guidance');
 await invoke('confirm batch creation');
 assert.deepEqual(server.events,['batch-created'],'Voice batch confirmed exactly once');
 assert(voice.some(x=>x.includes('First step')),'Expected spoken first step');
 await invoke('step done');
 assert.deepEqual(server.events,['batch-created'],'First step done is not a write');
 await invoke('yes');
 assert.deepEqual(server.events,['batch-created'],'Bare yes cannot commit');
 await invoke('confirm step');
 assert.deepEqual(server.events,['batch-created','step-saved'],'Second explicit confirmation writes once');
 assert(voice.some(x=>x.includes('Next step')),'Expected spoken next step');
 await invoke('step done');
 doc.visibilityState='hidden';listeners.visibilitychange();
 await invoke('confirm step');
 assert.deepEqual(server.events,['batch-created','step-saved'],'Hidden page must revoke pending confirmation');
 doc.visibilityState='visible';listeners.visibilitychange();await flush();
 await invoke('read the current step');
 assert(voice.some(x=>x.includes('Shape dough balls')),'Explicit read must use current saved step');
 assert(sent.every(x=>x==='pizza-chat-speak-request'),'Unexpected side effects');
 assert.equal(server.sessions.length,1);
 // Construct a fresh script environment to model reload: a new controller must rehydrate read-only.
 const newer=fakeDOM(), reload={...win,dispatchEvent(){},addEventListener(){}};
 vm.runInNewContext(fs.readFileSync('assets/agent_chat_batch.js','utf8'),{...env,window:reload,document:newer.doc});
 await flush();assert.equal(server.events.length,2,'Reload must not create or complete records');
 assert(newer.nodes.some(x=>String(x.textContent).includes('PL-A2K7P9')),'Reload must restore existing session');
}
scenario().then(()=>console.log('PASS: voice intents, explicit confirmations, hidden-page revocation, spoken instruction and reload recovery')).catch(e=>{console.error(e);process.exit(1);});
