/* Pizza Lab v1.5.16C: optional conversational dough production, not a wizard. */
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
   if(/^(?:step (?:is )?(?:done|complete|completed)|i (?:have )?(?:finished|completed) (?:that |the |this )?step|we (?:have )?(?:finished|completed) (?:that |the |this )?step)$/.test(s))return{type:'complete'};
   return null;
 }
 if(typeof module!=='undefined'&&module.exports)module.exports={parseBatchCommand};
 if(typeof document==='undefined')return;
 const history=document.getElementById('coachHistory'),status=document.getElementById('coachStatus');
 if(!history||!status)return;
 let session=null,proposal=null,source='manual';
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
  buttons(body,[['Confirm this step completed',()=>confirmStep(s)],['Refresh progress',()=>openSession(s.session_id,true)]]);
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
  const box=card('Confirm physical step',s.batch_code+' · '+step.title+'. This records your confirmation; it does not prove the step by itself.');
  const label=node('label','Optional actual notes or proof description');
  const field=node('textarea');field.rows=2;field.maxLength=1000;field.placeholder='What did you observe? (optional)';
  box.append(label,field);
  const eventId=uuid(),sourceAtCommand=source;
  const bar=buttons(box,[['Confirm completed',async()=>{
    const btns=[...bar.querySelectorAll('button')];btns.forEach(x=>x.disabled=true);
    try{
     const v=await request('chatBatchStep',{session_id:s.session_id,step_key:step.step_key,
       client_event_id:eventId,confirmed:true,source:sourceAtCommand,note:field.value});
     box.append(node('p',v.existing?'Already recorded; no duplicate.':'Step recorded. Next instruction is available.'));
     bar.remove();showSession(v.session,true);
    }catch(e){box.append(node('p',e.message));btns.forEach(x=>x.disabled=false);}
  }],['Cancel',()=>{bar.remove();box.append(node('p','No step completion recorded.'));}]]);
 }
 async function startPlan(term){
  proposal=null;
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
  const intent={plan,client_request_id:uuid(),source};proposal=intent;
  const box=card('Start a dough batch?',plan.recipe_name+' · '+plan.step_count+' planned steps. Recipe measurements are read from the saved formula.');
  box.append(node('p',ingredientText(plan)));
  const bar=buttons(box,[['Create batch and show first step',async()=>{
   if(proposal!==intent){box.append(node('p','This preview is no longer current. Start again.'));bar.remove();return;}
   const btns=[...bar.querySelectorAll('button')];btns.forEach(x=>x.disabled=true);
   try{
    const value=await request('chatBatchStart',{recipe_id:plan.recipe_id,fingerprint:plan.fingerprint,
      client_request_id:intent.client_request_id,confirmed:true});
    proposal=null;bar.remove();
    box.append(node('p',(value.existing?'Existing batch recovered: ':'Batch created: ')+value.session.batch_code));
    showSession(value.session,true);
   }catch(e){box.append(node('p',e.message));btns.forEach(x=>x.disabled=false);}
  }],['Cancel',()=>{if(proposal===intent)proposal=null;bar.remove();box.append(node('p','Cancelled. No batch created.'));}]]);
 }
 async function handle(raw,inputSource){
  const cmd=parseBatchCommand(raw);if(!cmd)return false;
  source=inputSource==='voice_command'?'voice_command':'manual';
  if(cmd.type==='start'){await startPlan(cmd.recipe);return true;}
  if(cmd.type==='list'){const list=await getSessions();if(list.length)chooseSessions(list,'Choose a batch to view its current step.');else card('No active dough sessions','You can start a batch whenever you want.');return true;}
  if(cmd.type==='resume'){await resume(cmd.code);return true;}
  if(cmd.type==='current'||cmd.type==='complete'){
   if(!session||session.status!=='active'){const all=await getSessions();if(all.length!==1){if(all.length)chooseSessions(all,'Choose the active batch before continuing.');else card('No active dough session','Start a batch or resume a saved one.');return true;}await openSession(all[0].session_id,true);}
   else await openSession(session.session_id,true);
   if(cmd.type==='complete'&&session?.status==='active')await confirmStep(session);
   return true;
  }
  return false;
 }
 window.pizzaChatBatchCommand=async (value,inputSource)=>{
  const parsed=parseBatchCommand(value);if(!parsed)return false;
  try{await handle(value,inputSource);}catch(e){card('Dough assistant unavailable',e.message);}
  return true;
 };
 // Only show an unobtrusive resumable summary on reload. No forced wizard.
 getSessions().then(items=>{if(items.length===1)return openSession(items[0].session_id,false);}).catch(()=>{});
})();