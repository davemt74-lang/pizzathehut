/* Conversation-first actions: deterministic, never ask an LLM to mutate data. */
(()=>{'use strict';
function parseCommand(raw){
 const input=String(raw||'').trim().replace(/\s+/g,' ');
 const lower=input.toLowerCase().replace(/^[, ]+|[, .!?]+$/g,'');
 if(!input||input.length>600)return null;
 if(/^(?:new|create|add|next) (?:a )?(?:new )?pizza(?: test)?$/.test(lower))return{type:'new_pizza'};
 if(/^(?:current pizza|pizza status|status of (?:my |the )?pizza|what(?:'s| is) cooking)$/.test(lower))return{type:'pizza_status'};
 if(/^(?:time left|how much time(?: is)? left|how long(?: has| is) (?:my |the )?pizza(?: been cooking)?|pizza time)$/.test(lower))return{type:'pizza_time'};
 if(/^(?:i|we) (?:just )?rotated (?:it|the pizza)$/.test(lower))return{type:'cook_observed',event_type:'rotation_confirmed'};
 if(/^(?:i|we) (?:just )?pulled (?:it|the pizza) back$|^(?:i|we) (?:just )?moved (?:it|the pizza) away from (?:the )?fire$/.test(lower))return{type:'cook_observed',event_type:'pull_back_confirmed'};
 if(/^(?:i|we) (?:just )?(?:(?:finished|removed) (?:it|the pizza)|took (?:it|the pizza) out)(?: from (?:the )?oven)?$|^(?:the |my )?pizza (?:is )?finished$/.test(lower))return{type:'cook_observed',event_type:'pizza_finished'};
 if(/^(open|show) (the )?(menu|navigation|sidebar)$/.test(lower))return{type:'menu'};
 if(/^(open|start|show) (the )?(pizza )?(camera|tracker|camera tracker)$/.test(lower))return{type:'camera_open'};
 if(/^(close|stop|hide) (the )?(pizza )?(camera|tracker|camera tracker)$/.test(lower))return{type:'camera_close'};
 const toolPages={
  'live oven':['index.php?workspace=1#live','Live oven & timers'],
  'live scanner':['index.php?workspace=1#live','Live oven & timers'],
  'oven layout':['oven_layout.php','Oven builder'],
  'oven builder':['oven_layout.php','Oven builder'],
  'heat map':['heatmap.php','Cooking heat map'],
  'settings':['index.php?workspace=1#settings','Workspace settings'],
  'recipes':['index.php?workspace=1#recipes','Recipes & variations'],
  'recipe editor':['index.php?workspace=1#recipes','Recipes & variations'],
  'pizza evidence':['index.php?workspace=1#evidence','Pizza evidence'],
  'dough batches':['index.php?workspace=1#batches','Dough batches']
 };
 let tool=lower.match(/^(?:open|show|go to) (?:the )?(.+)$/);
 if(tool&&toolPages[tool[1]])return{type:'tool',href:toolPages[tool[1]][0],title:toolPages[tool[1]][1]};
 if(/^(?:open|show|configure) (?:the |my )?(?:claude|anthropic)(?: api)?(?: key| settings| model)?$/.test(lower))return{type:'ai_settings'};
 let m=input.match(/^(?:pizza[, ]+)?(?:the )?(?:dough |batch )?(?:temperature|temp)(?: for)? (?:batch )?([A-Za-z0-9-]{3,18}) (?:is |at )?(-?\d+(?:\.\d{1,2})?) (?:degrees?|°?f)?$/i);
 if(m)return{type:'kitchen',action_type:'dough_temp',term:m[1],temperature_f:Number(m[2])};
 m=input.match(/^(?:pizza[, ]+)?(?:batch )([A-Za-z0-9-]{3,18}) (?:is |at )(-?\d+(?:\.\d{1,2})?) (?:degrees?|°?f)?$/i);
 if(m)return{type:'kitchen',action_type:'dough_temp',term:m[1],temperature_f:Number(m[2])};
 m=input.match(/^(?:pizza[, ]+)?(?:the )?(?:fridge|refrigerator)(?: (.+?))? (?:is |at )(-?\d+(?:\.\d{1,2})?) (?:degrees?|°?f)?$/i);
 if(m)return{type:'kitchen',action_type:'fridge_temp',location_label:(m[1]||'Refrigerator').trim(),temperature_f:Number(m[2])};
 m=input.match(/^(?:pizza[, ]+)?(?:we (?:just )?|i (?:just )?)?(?:balled|ball) (?:the )?batch ([A-Za-z0-9-]{3,18})$/i);
 if(m)return{type:'kitchen',action_type:'batch_balled',term:m[1]};
 m=input.match(/^(?:pizza[, ]+)?(pause|resume|reset) (?:the )?(?:pizza )?timer (?:for )?pizza #?(\\d{1,9})$/i);
 if(m)return{type:'cook',event_type:'timer_'+(m[1].toLowerCase()==='pause'?'paused':m[1].toLowerCase()==='resume'?'resumed':'reset'),evidence_id:Number(m[2])};
 m=input.match(/^(?:pizza[, ]+)?start (?:the )?(?:pizza )?timer (?:for )?pizza #?(\d{1,9}) (?:for |set for |at )?(\d{1,4}) (seconds?|secs?|minutes?|mins?)$/i);
 if(m){const seconds=Number(m[2])*(/^min/i.test(m[3])?60:1);return{type:'cook',event_type:'timer_started',evidence_id:Number(m[1]),target_seconds:seconds};}
 m=input.match(/^(?:pizza[, ]+)?(start|stop) (?:the )?(?:pizza )?timer (?:for )?pizza #?(\d{1,9})$/i);
 if(m)return{type:'cook',event_type:m[1].toLowerCase()==='start'?'timer_started':'timer_stopped',evidence_id:Number(m[2])};
 m=lower.match(/^(?:pizza[, ]+)?(?:start|begin) (?:the )?(?:pizza )?timer?(?: (?:for|at) (\d{1,4}) (seconds?|secs?|minutes?|mins?))?$/);
 if(m)return{type:'cook_pick',event_type:'timer_started',target_seconds:m[1]?Number(m[1])*(/^min/.test(m[2])?60:1):480};
 if(/^(?:confirm|start) (?:the )?timer now$|^confirm timer$|^confirm cooking action$/.test(lower))return{type:'cook_confirm'};
 if(/^(?:cancel|discard) (?:the )?timer(?: start| confirmation)?$/.test(lower))return{type:'cook_cancel'};
 m=lower.match(/^pizza #?(\d{1,9})$/);
 if(m)return{type:'cook_choose',evidence_id:Number(m[1])};
 m=lower.match(/^(?:select|use|switch to) (?:my )?pizza #?(\d{1,9})$/);
 if(m)return{type:'pizza_select',evidence_id:Number(m[1])};
 m=input.match(/^(?:pizza[, ]+)?(?:i |we )?(?:just )?(rotated|rotate|pulled back|pull back|finished|finish) pizza #?(\d{1,9})$/i);
 if(m)return{type:'cook',event_type:/rotat/i.test(m[1])?'rotation_confirmed':/pull/i.test(m[1])?'pull_back_confirmed':'pizza_finished',evidence_id:Number(m[2])};
 if(/^(help|show commands|what can you do)$/.test(lower))return{type:'help'};
 if(/^(show|list|find|open) (recent |my |the )?workdays?$/.test(lower))return{type:'workdays'};
 if(/^(show|list|find) (all |my |the )?recipes$/.test(lower))return{type:'recipes'};
 if(/^(show|list|find) (recent |all |my |the )?batches$/.test(lower))return{type:'batches'};
 m=input.match(/^(?:find|show|look up|select|choose|use) (?:the )?recipe (.+)$/i);
 if(m)return{type:'recipe',term:m[1].trim()};
 m=input.match(/^(?:find|show|look up|select|choose|use) (?:the )?(?:dough )?batch (.+)$/i);
 if(m)return{type:'batch',term:m[1].trim()};
 m=input.match(/^(?:create|start|new) (?:a )?workday(?:\s+(today|tomorrow|\d{4}-\d{2}-\d{2}))?(?:\s+(?:called|named|for)\s+(.+))?$/i);
 if(m)return{type:'create_workday',when:(m[1]||'today').toLowerCase(),title:(m[2]||'Pizza production').trim()};
 return null;
}
if(typeof module!=='undefined'&&module.exports)module.exports={parseCommand};
if(typeof document==='undefined')return;
const q=id=>document.getElementById(id),history=q('coachHistory'),status=q('coachStatus');
if(!history||!status)return;
let pending=null,pendingCook=null,pendingPicker=null;
function element(tag,text){const node=document.createElement(tag);if(text!==undefined)node.textContent=String(text);return node;}
function card(title,description){const c=element('article');c.className='coach-command-card';c.append(element('strong',title));if(description)c.append(element('p',description));history.append(c);c.scrollIntoView({block:'nearest',behavior:'smooth'});return c;}
function buttonRow(c,items){const bar=element('div');bar.className='coach-command-list';for(const item of items){const b=element('button',item.label);b.type='button';b.addEventListener('click',item.run);bar.append(b);}c.append(bar);}
function voicePrompt(message){if(window.pizzaChatCommandSource==='voice_command')window.dispatchEvent(new CustomEvent('pizza-chat-speak-request',{detail:{text:message}}));}
function localDay(offset=0){const d=new Date();d.setDate(d.getDate()+offset);const yy=d.getFullYear(),mm=String(d.getMonth()+1).padStart(2,'0'),dd=String(d.getDate()).padStart(2,'0');return `${yy}-${mm}-${dd}`;}
function actualDate(value){const v=value==='today'?localDay():value==='tomorrow'?localDay(1):value; if(!/^\d{4}-\d{2}-\d{2}$/.test(v))return null;const d=new Date(`${v}T12:00:00`);if(Number.isNaN(d.getTime())||`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`!==v)return null;return v;}
function linkTo(label,url){const a=element('a',label);a.href=url;return a;}
function match(items,term,render){const word=term.toLocaleLowerCase();return items.filter(v=>render(v).toLocaleLowerCase().includes(word)).slice(0,12);}
function chooseScope(scope,id){q('scope').value=scope;if(typeof choose==='function')choose(String(id));
 if(typeof loadHistory==='function')loadHistory().catch(e=>{status.textContent=e.message});}
async function getWorkdays(){const res=await fetch('api.php?action=chatWorkdays',{credentials:'same-origin'});const json=await res.json();if(!res.ok||!json.ok)throw Error(json.error||'Could not load workdays');return json.workdays||[];}
async function createWorkday(date,title){const res=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({action:'chatCreateWorkday',work_date:date,title,confirmed:true})});const json=await res.json();if(!res.ok||!json.ok)throw Error(json.error||'Workday was not created');return json;}
async function pizzaApi(action,data={}){const res=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({action,...data})});const json=await res.json();if(!res.ok||!json.ok)throw Error(json.error||'Pizza action unavailable');return json;}
async function refreshPizzaContext(){const r=await fetch('api.php?action=chatPizzaContext',{credentials:'same-origin',cache:'no-store'}),data=await r.json();if(!r.ok||!data.ok)throw Error(data.error||'Pizza context unavailable');const ctx=data.context||{};window.PIZZA_CHAT_ACTIVE_PIZZA_ID=ctx.current_id||null;window.PIZZA_CHAT_PIZZA_IDS=(ctx.pizzas||[]).map(p=>p.id);window.PIZZA_CHAT_PIZZAS=ctx.pizzas||[];return ctx;}
function makeClientId(){return window.crypto?.randomUUID?.()||('pizza_'+Date.now()+'_'+Math.random().toString(36).slice(2));}
async function run(cmd){
 if(cmd.type==='new_pizza'){
  const result=await pizzaApi('chatNewPizza',{client_event_id:makeClientId(),work_date:localDay()});
  window.PIZZA_CHAT_ACTIVE_PIZZA_ID=Number(result.id);
  window.PIZZA_CHAT_PIZZA_IDS=[Number(result.id),...(window.PIZZA_CHAT_PIZZA_IDS||[]).filter(id=>id!==Number(result.id))].slice(0,1000);
  const c=card(result.existing?'Pizza already created':'New pizza ready',result.label+' · Workday #'+result.workday_id+'. Your pizza is linked to your account and today’s workday. Measurements remain unrecorded until you enter them.');
  buttonRow(c,[{label:'Start 8-minute timer',run:()=>run({type:'cook_pick',event_type:'timer_started',target_seconds:480})},{label:'Open pizza camera',run:()=>run({type:'camera_open'})}]);
  window.pizzaChatRefreshWorkday?.(Number(result.workday_id));window.dispatchEvent(new Event('pizza-timers-updated'));
  voicePrompt('New pizza '+result.id+' is ready. Say start timer when you put it in the oven. I will remind you to check rotation, pull-back and finish.');return true;
 }
 if(cmd.type==='pizza_status'){
  const ctx=await refreshPizzaContext(),current=ctx.pizzas?.find(p=>p.id===ctx.current_id);
  if(!current){card('No pizza selected','Say “new pizza” and I will set up the current workday and pizza record.');voicePrompt('No pizza yet. Say new pizza to begin.');return true;}
  card('Current pizza',current.label+' · '+(current.timer_status||'Timer not started')+(current.workday_id?' · Workday #'+current.workday_id:''));
  voicePrompt('Your current pizza is '+current.id+'. '+(current.timer_status==='running'?'Its timer is running.':'Say start timer when it enters the oven.'));return true;
 }
 if(cmd.type==='pizza_time'){
  if(!window.PIZZA_CHAT_ACTIVE_PIZZA_ID)await refreshPizzaContext();
  const id=window.PIZZA_CHAT_ACTIVE_PIZZA_ID;
  const res=await fetch('api.php?action=kitchenTimers&mine=1',{credentials:'same-origin',cache:'no-store'}),data=await res.json();
  if(!res.ok||!data.ok)throw Error(data.error||'Timer status unavailable');
  const timer=(data.timers||[]).find(t=>t.evidence_id===id);
  if(!timer){card('No running timer','Say “start timer” for your current pizza.');voicePrompt('No timer is running for your current pizza.');return true;}
  const elapsed=Math.floor(timer.elapsed_ms/1000),left=timer.target_seconds===null?null:Math.max(0,timer.target_seconds-elapsed);
  const detail=timer.status==='paused'?'paused · ':'';
  const message=left===null?'Elapsed '+elapsed+' seconds. No target set.':detail+(left===0?'Planned time reached; inspect the pizza.':Math.floor(left/60)+' minutes '+left%60+' seconds until the planned time.');
  card('Pizza #'+id+' time',message);voicePrompt(message);return true;
 }
 if(cmd.type==='cook_observed'){
  if(!window.PIZZA_CHAT_ACTIVE_PIZZA_ID)await refreshPizzaContext();
  const id=window.PIZZA_CHAT_ACTIVE_PIZZA_ID;
  if(!id){card('No current pizza','Say “new pizza” first.');return true;}
  const data=await pizzaApi('cookActivity',{evidence_id:id,event_type:cmd.event_type,source:window.pizzaChatCommandSource||'manual',client_event_id:makeClientId()});
  const name={rotation_confirmed:'Rotation',pull_back_confirmed:'Pull-back',pizza_finished:'Pizza finished'}[cmd.event_type];
  card(name+' recorded','Pizza #'+id+' · Logged as your explicit physical-action report.');window.dispatchEvent(new Event('pizza-timers-updated'));
  voicePrompt(name+' recorded for pizza '+id+'.'+(cmd.event_type==='pizza_finished'?' Timer stopped.':''));return true;
 }
 if(cmd.type==='pizza_select'){
  const selected=await pizzaApi('chatSelectPizza',{evidence_id:cmd.evidence_id});window.PIZZA_CHAT_ACTIVE_PIZZA_ID=selected.pizza.id;
  card('Current pizza changed',selected.pizza.label+' is now your current pizza.');window.pizzaChatRefreshWorkday?.();voicePrompt('Pizza '+selected.pizza.id+' selected.');return true;
 }
 if(cmd.type==='cook_choose'){
  if(!pendingPicker)return run({type:'pizza_select',evidence_id:cmd.evidence_id});
  if(!pendingPicker||Date.now()-pendingPicker.createdAt>120000||!pendingPicker.ids.includes(cmd.evidence_id)){
   card('Pizza selection unavailable','Say “start timer for pizza” followed by its saved number.');pendingPicker=null;return true;
  }
  const target=pendingPicker.command;pendingPicker=null;return run({...target,type:'cook',evidence_id:cmd.evidence_id});
 }
 if(cmd.type==='cook_confirm'){
  if(!pendingCook||Date.now()-pendingCook.createdAt>120000){card('No timer awaiting confirmation','Say “start timer” for a selected pizza first.');pendingCook=null;return true;}
  await pendingCook.approve();return true;
 }
 if(cmd.type==='cook_cancel'){
  if(pendingCook){pendingCook.cancel();pendingCook=null;}else card('No pending timer','No timer was awaiting confirmation.');return true;
 }
 if(pending){pending.remove();pending=null;}
 if(cmd.type==='kitchen'){
   const matches=cmd.action_type==='fridge_temp'?[]:BATCHES.filter(b=>b.batch_code.toLowerCase()===cmd.term.toLowerCase());
   if(cmd.action_type!=='fridge_temp'&&matches.length!==1){card('Select batch','Use the exact batch code or choose a batch from the batch menu. Nothing saved.');return true;}
   if(cmd.temperature_f!==undefined&&(!Number.isFinite(cmd.temperature_f)||cmd.temperature_f < -30||cmd.temperature_f>150)){card('Measurement not saved','Enter an actual temperature between -30 and 150°F.');return true;}
   const b=matches[0],data={action:'chatKitchenAction',action_type:cmd.action_type,
     source:window.pizzaChatCommandSource||'manual',batch_id:b?.id??null,temperature_f:cmd.temperature_f??null,
     location_label:cmd.location_label??null,client_event_id:(window.crypto?.randomUUID?.()||('pizza_'+Date.now()+'_'+Math.random().toString(36).slice(2)))};
   const info=cmd.action_type==='batch_balled'?'Balled batch '+b.batch_code:cmd.action_type==='fridge_temp'?'Refrigerator '+data.location_label+' at '+data.temperature_f+'°F':'Dough batch '+b.batch_code+' at '+data.temperature_f+'°F';
   const c=card('Confirm kitchen observation',info+' · Time recorded on confirmation. Nothing saved yet.');
   const row=element('div');row.className='coach-confirm';
   const approve=element('button','Confirm and save'),cancel=element('button','Cancel');approve.type=cancel.type='button';approve.className='primary';
   approve.addEventListener('click',async()=>{if(approve.disabled)return;approve.disabled=cancel.disabled=true;try{
     const response=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({...data,confirmed:true})});
     const value=await response.json();if(!response.ok||!value.ok)throw Error(value.error||'Save failed');
     c.append(element('p',value.existing?'Already saved, no duplicate created.':'Saved · record #'+value.id));row.remove();pending=null;
   }catch(e){c.append(element('p',e.message));approve.disabled=cancel.disabled=false;}});
   cancel.addEventListener('click',()=>{row.remove();c.append(element('p','Cancelled, nothing saved.'));pending=null;});
   row.append(approve,cancel);c.append(row);pending=row;return true;
 }
 if(cmd.type==='cook_pick'){
  if(!window.PIZZA_CHAT_ACTIVE_PIZZA_ID)await refreshPizzaContext();
  const ids=window.PIZZA_CHAT_PIZZA_IDS||[];
  if(!ids.length){card('Create your pizza','Say “new pizza” first. I will create your pizza and workday automatically.');voicePrompt('Say new pizza first and I will set everything up.');return true;}
  if(window.PIZZA_CHAT_ACTIVE_PIZZA_ID&&ids.includes(window.PIZZA_CHAT_ACTIVE_PIZZA_ID))return run({...cmd,type:'cook',evidence_id:window.PIZZA_CHAT_ACTIVE_PIZZA_ID});
  if(ids.length===1)return run({...cmd,type:'cook',evidence_id:ids[0]});
  pendingPicker={command:cmd,ids:ids.slice(0,12),createdAt:Date.now()};
  const c=card('Which pizza timer?','Choose the current pizza record. You can also say “start timer for pizza” followed by its number.');
  buttonRow(c,ids.slice(0,12).map(id=>({label:'Pizza #'+id,run:()=>{pendingPicker=null;return run({...cmd,type:'cook',evidence_id:id});}})));
  voicePrompt('Which pizza? Say pizza followed by its saved number, such as pizza '+ids[0]+'.');return true;
 }
 if(cmd.type==='cook'){
   if(pendingCook){pendingCook.cancel();pendingCook=null;}
   const id=Number(cmd.evidence_id);
   if(!Number.isSafeInteger(id)||id<1||!(window.PIZZA_CHAT_PIZZA_IDS||[]).includes(id)){
     card('Pizza not selected','Use a pizza test number from your recorded evidence. Nothing was logged.');return true;
   }
   const labels={timer_started:'Start pizza timer',timer_stopped:'Stop pizza timer',timer_paused:'Pause pizza timer',timer_resumed:'Resume pizza timer',timer_reset:'Reset pizza timer',rotation_confirmed:'Confirm actual rotation',pull_back_confirmed:'Confirm actual pull-back',pizza_finished:'Finish pizza'};
   const source=/^timer_(paused|resumed|reset)$/.test(cmd.event_type)?'manual':(window.pizzaChatCommandSource||'manual');const action={action:'cookActivity',evidence_id:id,event_type:cmd.event_type,source,target_seconds:cmd.target_seconds??null,client_event_id:makeClientId()};
   if(cmd.event_type==='timer_started'){
    // The explicit "start timer" command is consent to start a digital timer.
    // Physical movements still require the chef's own confirmation.
    const data=await pizzaApi('cookActivity',action);
    card(data.existing?'Timer already recorded':'Pizza timer started','Pizza #'+id+' · '+(cmd.target_seconds??480)+' seconds planned. I will prompt rotation, pull-back and finish checks while Chat stays open.');
    window.dispatchEvent(new Event('pizza-timers-updated'));
    voicePrompt('Timer started for pizza '+id+'. I will remind you to check rotation, pull-back and when the planned bake time is reached.');return true;
   }
   const c=card('Confirm cooking action',labels[cmd.event_type]+' · Pizza #'+id+(cmd.target_seconds?' · '+cmd.target_seconds+' seconds':'')+'. An agent reminder is not a completed movement. Nothing saved yet.');
   const row=element('div');row.className='coach-confirm',approve=element('button','Confirm'),cancel=element('button','Cancel');approve.type=cancel.type='button';approve.className='primary';
   const intent={createdAt:Date.now(),approve:async()=>{if(approve.disabled)return;approve.disabled=cancel.disabled=true;try{
     const response=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(action)});
     const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Cooking event unavailable');
     c.append(element('p','Saved cooking action #'+(data.id??data.activity_id??'—')+'.'));window.dispatchEvent(new Event('pizza-timers-updated'));row.remove();pending=null;if(pendingCook===intent)pendingCook=null;
     if(cmd.event_type==='timer_started')voicePrompt('Timer started for pizza '+id+'.');
   }catch(e){c.append(element('p',e.message));approve.disabled=cancel.disabled=false;}},cancel:()=>{row.remove();c.append(element('p','Cancelled, no cooking event saved.'));pending=null;if(pendingCook===intent)pendingCook=null;}};
   approve.addEventListener('click',intent.approve);
   cancel.addEventListener('click',intent.cancel);
   if(cmd.event_type.startsWith('timer_')){pendingCook=intent;c.append(element('p','To confirm by voice, say “confirm timer” within two minutes.'));}
   row.append(approve,cancel);c.append(row);pending=row;
   if(cmd.event_type==='timer_started')voicePrompt('Ready to start the '+(cmd.target_seconds?Math.round(cmd.target_seconds/60*10)/10+' minute':'')+' timer for pizza '+id+'. Say confirm timer to start.');
   return true;
 }
 if(cmd.type==='menu'){window.dispatchEvent(new Event('pizza-chat-nav'));return true;}
 if(cmd.type==='tool'){window.dispatchEvent(new CustomEvent('pizza-chat-tool-open',{detail:{href:cmd.href,title:cmd.title}}));return true;}
 if(cmd.type==='ai_settings'){window.dispatchEvent(new Event('pizza-chat-ai-settings'));return true;}
 if(cmd.type==='camera_open'){window.dispatchEvent(new Event('pizza-chat-camera-open'));card('Pizza camera','The camera opens in chat. Choose a pizza record and start the tracker before capturing.');return true;}
 if(cmd.type==='camera_close'){window.dispatchEvent(new Event('pizza-chat-camera-close'));card('Pizza camera','Camera panel closed.');return true;}
 if(cmd.type==='help'){const c=card('Quick commands','Say “new pizza,” then “start timer.” The agent sets up your workday and reminds you of cooking checkpoints.');buttonRow(c,[{label:'New pizza',run:()=>run({type:'new_pizza'})},{label:'Start timer',run:()=>run({type:'cook_pick',event_type:'timer_started',target_seconds:480})},{label:'Current pizza',run:()=>run({type:'pizza_status'})},{label:'Show recipes',run:()=>run({type:'recipes'})},{label:'Open oven layout',run:()=>run({type:'tool',href:'oven_layout.php',title:'Oven builder'})},{label:'Open camera',run:()=>run({type:'camera_open'})}]);return true;}
 if(cmd.type==='recipes'||cmd.type==='batches'||cmd.type==='recipe'||cmd.type==='batch'){
   const isBatch=cmd.type.includes('batch'),items=isBatch?BATCHES:RECIPES;
   const label=v=>isBatch?`${v.batch_code} · ${v.recipe_name}`:v.name;
   const selected=cmd.term?match(items,cmd.term,label):items.slice(0,12);
   const c=card(isBatch?'Dough batches':'Recipes',selected.length?'Choose a record to analyze in this conversation.':'No matching records found.');
   buttonRow(c,selected.map(v=>({label:label(v),run:()=>{chooseScope(isBatch?'batch':'recipe',v.id);card(isBatch?'Batch selected':'Recipe selected',label(v));}})));return true;
 }
 if(cmd.type==='workdays'){
   const days=await getWorkdays(),c=card('Recent workdays',days.length?'Open an existing workday.':'No workdays have been recorded yet.');
   for(const day of days.slice(0,12))c.append(linkTo(`${day.work_date} · ${day.title}`,`workdays.php?id=${encodeURIComponent(day.id)}`),element('div'));
   return true;
 }
 if(cmd.type==='create_workday'){
   const date=actualDate(cmd.when);if(!date){card('Workday not created','Enter a valid calendar date in YYYY-MM-DD format.');return true;}
   const title=cmd.title.slice(0,160);if(!title){card('Workday not created','Give the shift a title.');return true;}
   const c=card('Confirm new workday',`${date} · ${title}. Nothing has been saved yet.`),actions=element('div');actions.className='coach-confirm';
   const approve=element('button','Create workday'),cancel=element('button','Cancel');approve.type=cancel.type='button';approve.className='primary';
   approve.addEventListener('click',async()=>{if(approve.disabled)return;approve.disabled=true;cancel.disabled=true;try{const result=await createWorkday(date,title);c.append(element('p',result.existing?'This workday already exists.':'Workday created.'));c.append(linkTo('Open workday →',`workdays.php?id=${encodeURIComponent(result.id)}`));actions.remove();pending=null;}catch(e){c.append(element('p',e.message));approve.disabled=false;cancel.disabled=false;}});
   cancel.addEventListener('click',()=>{actions.remove();c.append(element('p','Cancelled; no record created.'));pending=null;});actions.append(approve,cancel);c.append(actions);pending=actions;return true;
 }
 return false;
}
window.pizzaChatCommands=async value=>{const source=window.pizzaChatNextInputSource||'manual';window.pizzaChatNextInputSource=null;window.pizzaChatCommandSource=source;if(await window.pizzaChatBatchCommand?.(value,source)){window.dispatchEvent(new Event('pizza-chat-local-command'));return true;}const cmd=parseCommand(value);if(!cmd)return false;window.dispatchEvent(new Event('pizza-chat-local-command'));try{await run(cmd);}catch(e){card('Command unavailable',e.message||'Try again.');voicePrompt('That action was not completed. '+(e.message||'Please try again.'));}return true;};
document.addEventListener('click',event=>{const button=event.target.closest?.('[data-chat-command]');if(!button)return;const input=q('question'),form=q('coachForm');if(!input||!form)return;input.value=button.dataset.chatCommand;form.requestSubmit();});
refreshPizzaContext().catch(error=>{status.textContent='Pizza tracking unavailable: '+error.message+' · Run upgrade.php if you have just updated.';});
function discardTimerConfirmation(){if(pendingCook)pendingCook.cancel();pendingCook=null;pendingPicker=null;}
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')discardTimerConfirmation();});
window.addEventListener('pagehide',discardTimerConfirmation);
})();
