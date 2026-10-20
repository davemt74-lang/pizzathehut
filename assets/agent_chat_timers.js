/* v1.5.16A: live, server-authoritative kitchen timer snapshots. */
(()=>{'use strict';
 const chat=document.getElementById('coachHistory');
 if(!chat)return;
 const panel=document.createElement('section');
 panel.className='chat-workday-context';
 panel.setAttribute('aria-label','Active kitchen timers');
 const title=document.createElement('strong');title.textContent='Active kitchen timers';
 const content=document.createElement('div');
 panel.append(title,content);
 chat.parentNode.insertBefore(panel,chat);
 let timers=[],lastSync=0,loading=false;
 const fmt=ms=>{const sec=Math.floor(Math.max(0,ms)/1000);return String(Math.floor(sec/60)).padStart(2,'0')+':'+String(sec%60).padStart(2,'0');};
 function render(){
  panel.hidden=!timers.length;
  content.replaceChildren();
  for(const timer of timers){
   const div=document.createElement('div');div.className='ctx-tip';
   const effective=timer.elapsed_ms+(timer.status==='running'?Math.max(0,Date.now()-lastSync):0);
   const target=timer.target_seconds;
   div.textContent='Pizza #'+timer.evidence_id+' · '+timer.label+' · '+timer.status+' · '+fmt(effective)+(target===null?'':' / '+fmt(target*1000)+(effective>=target*1000?' · target reached':''));
   content.append(div);
  }
 }
 async function refresh(){
  if(loading||document.visibilityState==='hidden')return;
  loading=true;
  try{
   const res=await fetch('api.php?action=kitchenTimers',{credentials:'same-origin',cache:'no-store'});
   const data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'Timers unavailable');
   timers=data.timers||[];lastSync=Date.now();render();
  }catch(err){content.textContent='Timer sync unavailable — reconnect to check kitchen activity.';panel.hidden=false;}
  finally{loading=false;}
 }
 window.addEventListener('pizza-timers-updated',refresh);
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refresh();});
 window.addEventListener('pageshow',refresh);
 window.setInterval(()=>{if(document.visibilityState==='visible')render();},1000);
 window.setInterval(refresh,4000);
 refresh();
})();