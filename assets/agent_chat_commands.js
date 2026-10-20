/* Conversation-first actions: deterministic, never ask an LLM to mutate data. */
(()=>{'use strict';
function parseCommand(raw){
 const input=String(raw||'').trim().replace(/\s+/g,' ');
 const lower=input.toLowerCase().replace(/^[, ]+|[, .!?]+$/g,'');
 if(!input||input.length>600)return null;
 if(/^(open|show) (the )?(menu|navigation|sidebar)$/.test(lower))return{type:'menu'};
 if(/^(open|start|show) (the )?(pizza )?(camera|tracker|camera tracker)$/.test(lower))return{type:'camera_open'};
 if(/^(close|stop|hide) (the )?(pizza )?(camera|tracker|camera tracker)$/.test(lower))return{type:'camera_close'};
 let m=input.match(/^(?:pizza[, ]+)?(?:the )?(?:dough |batch )?(?:temperature|temp)(?: for)? (?:batch )?([A-Za-z0-9-]{3,18}) (?:is |at )?(-?\d+(?:\.\d{1,2})?) (?:degrees?|°?f)?$/i);
 if(m)return{type:'kitchen',action_type:'dough_temp',term:m[1],temperature_f:Number(m[2])};
 m=input.match(/^(?:pizza[, ]+)?(?:batch )([A-Za-z0-9-]{3,18}) (?:is |at )(-?\d+(?:\.\d{1,2})?) (?:degrees?|°?f)?$/i);
 if(m)return{type:'kitchen',action_type:'dough_temp',term:m[1],temperature_f:Number(m[2])};
 m=input.match(/^(?:pizza[, ]+)?(?:the )?(?:fridge|refrigerator)(?: (.+?))? (?:is |at )(-?\d+(?:\.\d{1,2})?) (?:degrees?|°?f)?$/i);
 if(m)return{type:'kitchen',action_type:'fridge_temp',location_label:(m[1]||'Refrigerator').trim(),temperature_f:Number(m[2])};
 m=input.match(/^(?:pizza[, ]+)?(?:we (?:just )?|i (?:just )?)?(?:balled|ball) (?:the )?batch ([A-Za-z0-9-]{3,18})$/i);
 if(m)return{type:'kitchen',action_type:'batch_balled',term:m[1]};
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
let pending=null;
function element(tag,text){const node=document.createElement(tag);if(text!==undefined)node.textContent=String(text);return node;}
function card(title,description){const c=element('article');c.className='coach-command-card';c.append(element('strong',title));if(description)c.append(element('p',description));history.append(c);c.scrollIntoView({block:'nearest',behavior:'smooth'});return c;}
function buttonRow(c,items){const bar=element('div');bar.className='coach-command-list';for(const item of items){const b=element('button',item.label);b.type='button';b.addEventListener('click',item.run);bar.append(b);}c.append(bar);}
function localDay(offset=0){const d=new Date();d.setDate(d.getDate()+offset);const yy=d.getFullYear(),mm=String(d.getMonth()+1).padStart(2,'0'),dd=String(d.getDate()).padStart(2,'0');return `${yy}-${mm}-${dd}`;}
function actualDate(value){const v=value==='today'?localDay():value==='tomorrow'?localDay(1):value; if(!/^\d{4}-\d{2}-\d{2}$/.test(v))return null;const d=new Date(`${v}T12:00:00`);if(Number.isNaN(d.getTime())||`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`!==v)return null;return v;}
function linkTo(label,url){const a=element('a',label);a.href=url;return a;}
function match(items,term,render){const word=term.toLocaleLowerCase();return items.filter(v=>render(v).toLocaleLowerCase().includes(word)).slice(0,12);}
function chooseScope(scope,id){q('scope').value=scope;if(typeof choose==='function')choose(String(id));
 if(typeof loadHistory==='function')loadHistory().catch(e=>{status.textContent=e.message});}
async function getWorkdays(){const res=await fetch('api.php?action=chatWorkdays',{credentials:'same-origin'});const json=await res.json();if(!res.ok||!json.ok)throw Error(json.error||'Could not load workdays');return json.workdays||[];}
async function createWorkday(date,title){const res=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({action:'chatCreateWorkday',work_date:date,title,confirmed:true})});const json=await res.json();if(!res.ok||!json.ok)throw Error(json.error||'Workday was not created');return json;}
async function run(cmd){
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
 if(cmd.type==='menu'){window.dispatchEvent(new Event('pizza-chat-nav'));return true;}
 if(cmd.type==='camera_open'){window.dispatchEvent(new Event('pizza-chat-camera-open'));card('Pizza camera','The camera opens in chat. Choose a pizza record and start the tracker before capturing.');return true;}
 if(cmd.type==='camera_close'){window.dispatchEvent(new Event('pizza-chat-camera-close'));card('Pizza camera','Camera panel closed.');return true;}
 if(cmd.type==='help'){const c=card('Quick commands','You can also use the hamburger navigation at any time.');buttonRow(c,[{label:'Show recipes',run:()=>run({type:'recipes'})},{label:'Show batches',run:()=>run({type:'batches'})},{label:'Recent workdays',run:()=>run({type:'workdays'})},{label:'Open camera',run:()=>run({type:'camera_open'})}]);return true;}
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
window.pizzaChatCommands=async value=>{const source=window.pizzaChatNextInputSource||'manual';window.pizzaChatNextInputSource=null;window.pizzaChatCommandSource=source;const cmd=parseCommand(value);if(!cmd)return false;window.dispatchEvent(new Event('pizza-chat-local-command'));try{await run(cmd);}catch(e){card('Command unavailable',e.message||'Try again.');}return true;};
})();
