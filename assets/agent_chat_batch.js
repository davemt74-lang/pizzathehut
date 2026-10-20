/* Pizza Lab v1.5.16D: opt-in spoken directions, confirmed voice actions and reload-safe kitchen state. */
(()=>{'use strict';
 function parseBatchCommand(raw){
   const input=String(raw||'').trim().replace(/\s+/g,' ');
   if(!input||input.length>600)return null;
   const s=input.toLowerCase().replace(/^[, ]+|[, .!?]+$/g,'').replace(/^(pizza[, ]+)?/,'');
   const pattern=/^(?:let'?s |we (?:should |need to )?|i (?:want to |need to )?)?(?:make|mix|prepare|start|create) (?:a |one |the )?(?:new )?(?:batch(?: of dough)?|dough batch)(?: (?:using|with|from|of) (?:the )?(.+)|, (main(?: recipe)?|current(?: recipe)?|default(?: recipe)?))?$/;
   const batch=s.match(pattern);
   if(batch)return {type:'start',recipe:batch[1]||batch[2]||'main recipe'};
   if(/^(?:show|list) (?:my |the )?(?:active |ongoing )?(?:dough )?(?:batch sessions|batch guides|dough sessions)$/.test(s))return{type:'list'};
   const m=s.match(/^(?:resume|continue|show status of|where are we with) (?:the )?(?:dough )?batch(?: (pl-[a-z0-9]{6}))?$/);
   if(m)return{type:'resume',code:m[1]||null};
   if(/^(?:what'?s (?:the )?next step|next step|where did we leave off|what should i do next)$/.test(s))return{type:'current'};
   if(/^(?:read|speak|say|repeat) (?:the |my )?(?:current |next )?(?:step|instructions)(?: aloud)?$/.test(s))return{type:'read_step'};
   if(/^(?:read|speak|say|repeat) (?:the |my )?(?:ingredients|ingredient list|recipe amounts)(?: aloud)?$/.test(s))return{type:'read_ingredients'};
   if(/^(?:confirm|approve) (?:the |this )?(?:completed )?step$/.test(s))return{type:'confirm_step'};
   if(/^(?:cancel|discard) (?:the |this )?step confirmation$/.test(s))return{type:'cancel_step'};
   if(/^(?:confirm|approve) (?:the |this )?(?:batch creation|new batch)$/.test(s))return{type:'confirm_batch'};
   if(/^(?:cancel|discard) (?:the |this )?batch creation$/.test(s))return{type:'cancel_batch'};
   if(/^(?:step (?:is )?(?:done|complete|completed)|i (?:have )?(?:finished|completed) (?:that |the |this )?step|we (?:have )?(?:finished|completed) (?:that |the |this )?step)$/.test(s))return{type:'complete'};
   return null;
 }
 if(typeof module!=='undefined'&&module.exports)module.exports={parseBatchCommand};
 if(typeof document==='undefined')return;
 const history=document.getElementById('coachHistory'),status=document.getElementById('coachStatus');
 if(!history||!status)return;
 let session=null,proposal=null,source='manual',pendingStep=null,pendingBatch=null;
 const CONFIRM_WINDOW_MS=120000;
 const speak=text=>{if(document.visibilityState!=='hidden')window.dispatchEvent(new CustomEvent('pizza-chat-speak-request',{detail:{text:String(text).slice(0,1100)}}));};
 const panel=document.createElement('details');panel.className='chat-workday-context';panel.hidden=true;
 const title=document.createElement('summary');title.textContent='Active dough batch';
 const body=document.createElement('div');panel.append(title,body);history.parentNode.insertBefore(panel,history);
 const node=(tag,text)=>{const x=document.createElement(tag);if(text!==undefined)x.textContent=String(text);return x;};
 const card=(title,message)=>{
  const box=node('article');box.className='coach-command-card';box.append(node('strong',title),node('p',message));
  history.append(box);box.scrollIntoView({block:'nearest',behavior:'smooth'});return box;
 };
 const buttons=(where,defs)=>{
  const bar=node('div');bar.className='coach-command-list';
  for(const [label,fn] of defs){const b=node('button',label);b.type='button';b.addEventListener('click',fn);bar.append(b);}
  where.append(bar);return bar;
 };
 const request=async (action,payload=null)=>{
  const opt={credentials:'same-origin',cache:'no-store'};
  let url='api.php?action='+encodeURIComponent(action);
  if(payload!==null){
   if(action==='chatBatchPlan'||action==='chatBatchSession')url+='&'+new URLSearchParams(payload);
   else{url='api.php';opt.method='POST';opt.headers={'Content-Type':'application/json','X-CSRF-Token':window.PIZZA_CHAT_CSRF};opt.body=JSON.stringify({action,...payload});}
  }
  const res=await fetch(url,opt),value=await res.json();
  if(!res.ok||!value.ok)throw Error(value.error||'Kitchen action unavailable');
  return value;
 };
 const uuid=()=>window.crypto?.randomUUID?.()||('chat_batch_'+Date.now()+'_'+Math.random().toString(36).slice(2));
 const ingredientText=s=>{
  const labels={flour_g:'Flour',water_g:'Water',salt_g:'Salt',oil_g:'Oil',honey_g:'Honey',yeast_g:'Yeast'};
  return Object.entries(labels).map(([key,name])=>name+': '+(s.ingredients[key]===null?'not specified':Number(s.ingredients[key]).toLocaleString()+' g')).join(' · ');
 };
 function showSession(s,expand=true){
  if(!session||session.session_id!==s.session_id||session.next_step_index!==s.next_step_index||s.status!=='active')pendingStep=null;
  session=s;panel.hidden=false;panel.open=expand;body.replaceChildren();
  title.textContent='Dough batch '+s.batch_code+' · '+s.recipe_name+' · '+(s.status==='completed'?'completed':'step '+(s.next_step_index+1)+' of '+s.step_count);
  body.append(node('p',ingredientText(s)));
  if(s.status==='completed'){body.append(node('p','All planned steps have been confirmed. Actual proof, temperatures and timing remain whatever was recorded.'));return;}
  const step=s.current_step;
  if(!step){body.append(node('p','Step plan unavailable. Check your batch record.'));return;}
  body.append(node('strong',step.title),node('p',step.instructions||'Follow the saved recipe instructions.'));
  if(step.target_min_minutes!==null&&step.target_min_minutes!==undefined){
   body.append(node('p','Recipe duration guidance: '+step.target_min_minutes+
     (step.target_max_minutes!==null?'–'+step.target_max_minutes:'')+' minutes. This does not start a timer.'));
  }
  buttons(body,[['Read this step aloud',()=>speak(s.batch_code+'. Step '+(s.next_step_index+1)+'. '+step.title+'. '+(step.instructions||'Follow the saved recipe instructions.'))],
   ['Read ingredient amounts',()=>speak('Ingredients for '+s.recipe_name+'. '+ingredientText(s))],
   ['Confirm this step completed',()=>confirmStep(s)],['Refresh progress',()=>openSession(s.session_id,true)]]);
 }
 async function openSession(id,expand=true){
  const v=await request('chatBatchSession',{session_id:id});showSession(v.session,expand);
 }
 async function getSessions(){return (await request('chatBatchSessions')).sessions||[];}
 function chooseSessions(items,message){
  const box=card('Choose an active dough session',message);
  buttons(box,items.map(s=>[s.batch_code+' · '+s.recipe_name,()=>openSession(s.session_id,true).catch(e=>card('Cannot resume',e.message))]));
 }
 async function resume(code=null,expand=true){
  const all=await getSessions();
  const choices=code?all.filter(x=>x.batch_code.toLowerCase()===code.toLowerCase()):all;
  if(!choices.length){card('No matching active batch','Start a new dough batch or check the saved batch history.');return;}
  if(choices.length===1){await openSession(choices[0].session_id,expand);return;}
  chooseSessions(choices,'Several dough sessions are active. Select one so I do not guess.');
 }
 async function confirmStep(s){
  const step=s.current_step;
  if(!step)return;
  pendingStep=null;
  const box=card('Confirm physical step',s.batch_code+' · '+step.title+'. This records your statement only, not a measurement or photo proof.');
  const label=node('label','Optional actual notes or proof description');
  const field=node('textarea');field.rows=2;field.maxLength=1000;field.placeholder='What did you observe? (optional)';
  box.append(label,field);
  const intent={sessionId:s.session_id,stepKey:step.step_key,batchCode:s.batch_code,
    eventId:uuid(),source,createdAt:Date.now(),card:box,field,bar:null,busy:false};
  pendingStep=intent;
  const bar=buttons(box,[['Confirm completed',()=>finishStep(intent)],['Cancel',()=>cancelStep(intent)]]);
  intent.bar=bar;
  box.append(node('p','To confirm by voice, press the microphone and say "confirm step" within two minutes. "Yes" alone does nothing.'));
  if(source==='voice_command')speak('Step '+(s.next_step_index+1)+' marked for confirmation. Say confirm step, or cancel step confirmation. Nothing has been recorded yet.');
 }
 function cancelStep(intent){
  if(pendingStep===intent)pendingStep=null;
  intent.bar?.remove();intent.card.append(node('p','Cancelled. No step completion recorded.'));
 }
 async function finishStep(intent){
  if(pendingStep!==intent||intent.busy)return;
  if(document.visibilityState==='hidden'||Date.now()-intent.createdAt>CONFIRM_WINDOW_MS){
   cancelStep(intent);intent.card.append(node('p','Confirmation expired. Ask to complete the step again.'));return;
  }
  intent.busy=true;const buttons=[...(intent.bar?.querySelectorAll('button')||[])];buttons.forEach(x=>x.disabled=true);
  try{
   const current=(await request('chatBatchSession',{session_id:intent.sessionId})).session;
   if(current.status!=='active'||current.current_step?.step_key!==intent.stepKey||current.batch_code!==intent.batchCode){
    pendingStep=null;intent.bar?.remove();intent.card.append(node('p','Another device changed this step. Refreshed to current progress.'));
    showSession(current,true);return;
   }
   const data=await request('chatBatchStep',{
     session_id:intent.sessionId,step_key:intent.stepKey,client_event_id:intent.eventId,
     confirmed:true,source:intent.source,note:intent.field.value
   });
   intent.bar?.remove();pendingStep=null;
   intent.card.append(node('p',data.existing?'Already recorded; no duplicate.':'Step recorded. Current batch refreshed.'));
   showSession(data.session,true);
   if(source==='voice_command'){
    if(data.session.status==='active')speak('Step saved. Next step: '+data.session.current_step.title+'. '+(data.session.current_step.instructions||''));
    else speak('All planned steps are confirmed for batch '+data.session.batch_code+'.');
   }
  }catch(e){
   intent.card.append(node('p',e.message+' You can retry or refresh this batch.'));
   buttons.forEach(x=>x.disabled=false);
  }finally{intent.busy=false;}
 }
 async function startPlan(term){
  proposal=null;pendingBatch=null;
  const raw=term.replace(/^(?:the )?/,'').trim();
  const wantsMain=/^(main(?: recipe)?|current(?: recipe)?|default(?: recipe)?)$/.test(raw);
  let plan;
  if(wantsMain)plan=(await request('chatBatchPlan')).plan;
  else {
   const all=window.PIZZA_CHAT_RECIPE_LIST||[];
   const matches=all.filter(r=>r.name.toLowerCase()===raw);
   if(matches.length===1)plan=(await request('chatBatchPlan',{recipe_id:matches[0].id})).plan;
   else{
    const candidates=all.filter(r=>r.name.toLowerCase().includes(raw)&&raw.length>=3);
    const box=card('Choose the recipe',candidates.length?'Select the exact saved formula. No batch has been created.':'No exact recipe match. Select a saved recipe.');
    buttons(box,(candidates.length?candidates:all).slice(0,25).map(r=>[r.name,()=>startById(r.id).catch(e=>card('Recipe unavailable',e.message))]));
    return;
   }
  }
  if(plan.requires_selection){
   const box=card('Select the main recipe',plan.reason);
   buttons(box,plan.recipes.map(r=>[r.name+' · '+r.status,()=>startById(r.id).catch(e=>card('Recipe unavailable',e.message))]));
   return;
  }
  showPreview(plan);
 }
 async function startById(id){showPreview((await request('chatBatchPlan',{recipe_id:id})).plan);}
 function showPreview(plan){
  const intent={plan,client_request_id:uuid(),source,createdAt:Date.now(),busy:false,card:null,bar:null};
  proposal=intent;pendingBatch=intent;
  const box=card('Start a dough batch?',plan.recipe_name+' · '+plan.step_count+' planned steps. Recipe measurements are read from the saved formula.');
  box.append(node('p',ingredientText(plan)));
  const bar=buttons(box,[['Create batch and show first step',()=>finishBatch(intent)],['Cancel',()=>cancelBatch(intent)]]);
  intent.card=box;intent.bar=bar;
  box.append(node('p','Press the microphone and say "confirm batch creation" to approve within two minutes. No batch exists yet.'));
  if(source==='voice_command')speak('I found '+plan.recipe_name+'. Please review the ingredient weights. Say confirm batch creation to create the batch, or cancel batch creation.');
 }
 function cancelBatch(intent){
  if(pendingBatch===intent){proposal=null;pendingBatch=null;}
  intent.bar?.remove();intent.card.append(node('p','Cancelled. No batch created.'));
 }
 async function finishBatch(intent){
  if(pendingBatch!==intent||proposal!==intent||intent.busy)return;
  if(document.visibilityState==='hidden'||Date.now()-intent.createdAt>CONFIRM_WINDOW_MS){
   cancelBatch(intent);intent.card.append(node('p','Confirmation expired. Ask to start a batch again.'));return;
  }
  intent.busy=true;const bs=[...(intent.bar?.querySelectorAll('button')||[])];bs.forEach(x=>x.disabled=true);
  try{
   const value=await request('chatBatchStart',{recipe_id:intent.plan.recipe_id,fingerprint:intent.plan.fingerprint,
     client_request_id:intent.client_request_id,confirmed:true});
   proposal=null;pendingBatch=null;intent.bar?.remove();
   intent.card.append(node('p',(value.existing?'Existing batch recovered: ':'Batch created: ')+value.session.batch_code));
   showSession(value.session,true);
   if(source==='voice_command'){
    const step=value.session.current_step;
    speak('Created batch '+value.session.batch_code+'. '+(step?'First step: '+step.title+'. '+(step.instructions||''):'Review the saved batch.'));
   }
  }catch(e){intent.card.append(node('p',e.message));bs.forEach(x=>x.disabled=false);}
  finally{intent.busy=false;}
 }
 async function handle(raw,inputSource){
  const cmd=parseBatchCommand(raw);if(!cmd)return false;
  source=inputSource==='voice_command'?'voice_command':'manual';
  if(cmd.type==='confirm_step'){
    if(!pendingStep){card('No step awaiting confirmation','Say "step done" for an active batch first. Nothing was saved.');return true;}
    await finishStep(pendingStep);return true;
  }
  if(cmd.type==='cancel_step'){if(pendingStep)cancelStep(pendingStep);else card('No pending step','Nothing was awaiting confirmation.');return true;}
  if(cmd.type==='confirm_batch'){
    if(!pendingBatch){card('No batch awaiting confirmation','Ask me to create a batch and review the recipe first.');return true;}
    await finishBatch(pendingBatch);return true;
  }
  if(cmd.type==='cancel_batch'){if(pendingBatch)cancelBatch(pendingBatch);else card('No pending batch','Nothing was awaiting confirmation.');return true;}
  if(cmd.type==='start'){await startPlan(cmd.recipe);return true;}
  if(cmd.type==='list'){const list=await getSessions();if(list.length)chooseSessions(list,'Choose a batch to view its current step.');else card('No active dough sessions','You can start a batch whenever you want.');return true;}
  if(cmd.type==='resume'){await resume(cmd.code);return true;}
  if(cmd.type==='current'||cmd.type==='complete'||cmd.type==='read_step'||cmd.type==='read_ingredients'){
   if(!session||session.status!=='active'){const all=await getSessions();if(all.length!==1){if(all.length)chooseSessions(all,'Choose the active batch before continuing.');else card('No active dough session','Start a batch or resume a saved one.');return true;}await openSession(all[0].session_id,true);}
   else await openSession(session.session_id,true);
   if(cmd.type==='complete'&&session?.status==='active')await confirmStep(session);
   if(session?.status==='active'&&(cmd.type==='read_step'||(cmd.type==='current'&&source==='voice_command'))){
      const st=session.current_step;
      speak(session.batch_code+'. Step '+(session.next_step_index+1)+' of '+session.step_count+'. '+st.title+'. '+(st.instructions||''));
   }
   if(session&&(cmd.type==='read_ingredients'))speak('Ingredients for '+session.recipe_name+'. '+ingredientText(session));
   return true;
  }
  return false;
 }
 window.pizzaChatBatchCommand=async (value,inputSource)=>{
  const parsed=parseBatchCommand(value);if(!parsed)return false;
  try{await handle(value,inputSource);}catch(e){card('Dough assistant unavailable',e.message);}
  return true;
 };
 function discardPendingForInterruption(){
   // Do not authorize a mutating voice confirmation that spans visibility changes.
   if(pendingStep){cancelStep(pendingStep);pendingStep=null;}
   if(pendingBatch){cancelBatch(pendingBatch);pendingBatch=null;}
 }
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')discardPendingForInterruption();else if(session?.status==='active')openSession(session.session_id,false).catch(()=>{});});
 window.addEventListener('pagehide',discardPendingForInterruption);
 window.addEventListener('pageshow',()=>{if(document.visibilityState!=='hidden'&&session?.status==='active')openSession(session.session_id,false).catch(()=>{});});
 // Only show an unobtrusive resumable summary on reload. No forced wizard.
 getSessions().then(items=>{if(items.length===1)return openSession(items[0].session_id,false);}).catch(()=>{});
})();