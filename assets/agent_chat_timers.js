/* Pizza Lab v1.5.16B: foreground kitchen awareness; server owns all time and dedupe. */
(()=>{'use strict';
 const chat=document.getElementById('coachHistory');
 if(!chat)return;
 const panel=document.createElement('section');
 panel.className='chat-workday-context';panel.setAttribute('aria-label','My active kitchen timers');
 const title=document.createElement('strong');title.textContent='My active kitchen timers';
 const speechButton=document.createElement('button');speechButton.type='button';
 const preferenceKey='pizza_chat_proactive_alerts_'+String(window.PIZZA_CHAT_USER_ID||'chef');
 let speakAlerts=true;
 try{speakAlerts=window.localStorage?.getItem(preferenceKey)!=='off';}catch{}
 function speechLabel(){speechButton.textContent='Speak kitchen alerts: '+(speakAlerts?'on':'off');speechButton.setAttribute('aria-pressed',speakAlerts?'true':'false');}
 speechLabel();
 speechButton.addEventListener('click',()=>{speakAlerts=!speakAlerts;speechLabel();try{window.localStorage?.setItem(preferenceKey,speakAlerts?'on':'off');}catch{}});
 const timerContent=document.createElement('div');
 panel.append(title,speechButton,timerContent);
 chat.parentNode.insertBefore(panel,chat);
 const alerts=document.createElement('section');alerts.className='chat-workday-context';
 alerts.setAttribute('aria-label','Kitchen reminders');alerts.setAttribute('aria-live','polite');alerts.hidden=true;
 chat.parentNode.insertBefore(alerts,chat);
 let timers=[],lastSync=0,loading=false,polling=false;
 const seen=new Set();
 const fmt=ms=>{const sec=Math.floor(Math.max(0,ms)/1000);return String(Math.floor(sec/60)).padStart(2,'0')+':'+String(sec%60).padStart(2,'0');};
 function render(){
  panel.hidden=!timers.length;
  timerContent.replaceChildren();
  for(const timer of timers){
   const div=document.createElement('div');div.className='ctx-tip';
   const effective=timer.elapsed_ms+(timer.status==='running'?Math.max(0,Date.now()-lastSync):0);
   const target=timer.target_seconds;
   div.textContent='Pizza #'+timer.evidence_id+' · '+timer.label+' · '+timer.status+' · '+fmt(effective)
       +(target===null?' · no reminder target': ' / '+fmt(target*1000)+(effective>=target*1000?' · planned time reached':''));
   timerContent.append(div);
  }
 }
 function speak(message){
  if(!speakAlerts||document.visibilityState==='hidden')return;
  // One shared speaker pauses persistent recognition, preventing the agent
  // from transcribing its own reminders as new commands.
  window.dispatchEvent(new CustomEvent('pizza-chat-speak-request',{detail:{text:message}}));
 }
 function showReminder(item){
  if(seen.has(item.id))return;
  seen.add(item.id);
  alerts.hidden=false;
  const div=document.createElement('article');div.className='coach-message coach-answer';
  const label=document.createElement('strong');label.textContent='Kitchen check · Pizza #'+item.evidence_id;
  const message=document.createElement('p');message.textContent=item.message;
  const small=document.createElement('small');small.textContent='Agent reminder only · No physical action recorded';
  const dismiss=document.createElement('button');dismiss.type='button';dismiss.textContent='Dismiss';
  dismiss.addEventListener('click',()=>{div.remove();if(!alerts.children.length)alerts.hidden=true;});
  div.append(label,message,small,dismiss);alerts.append(div);
  speak(item.message);
 }
 async function pollReminders(){
  if(polling||document.visibilityState==='hidden'||!timers.some(t=>t.status==='running' && t.target_seconds!==null))return;
  polling=true;
  try{
   const res=await fetch('api.php',{method:'POST',credentials:'same-origin',cache:'no-store',
      headers:{'Content-Type':'application/json','X-CSRF-Token':window.PIZZA_CHAT_CSRF},
      body:JSON.stringify({action:'kitchenReminderPoll',mine:true})});
   const data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'Kitchen reminders unavailable');
   for(const item of data.reminders||[])showReminder(item);
  }catch(err){const status=document.getElementById('coachStatus');if(status)status.textContent='Kitchen reminder connection unavailable: '+err.message;}
  finally{polling=false;}
 }
 async function refresh(){
  if(loading||document.visibilityState==='hidden')return;
  loading=true;
  try{
   const res=await fetch('api.php?action=kitchenTimers&mine=1',{credentials:'same-origin',cache:'no-store'});
   const data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'Timers unavailable');
   timers=data.timers||[];lastSync=Date.now();render();
   await pollReminders();
  }catch(err){timerContent.textContent='Timer sync unavailable — reconnect to check kitchen activity.';panel.hidden=false;}
  finally{loading=false;}
 }
 window.addEventListener('pizza-timers-updated',refresh);
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refresh();});
 window.addEventListener('pageshow',refresh);
 window.setInterval(()=>{if(document.visibilityState==='visible')render();},1000);
 window.setInterval(refresh,4000);
 refresh();
})();
