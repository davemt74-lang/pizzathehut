/* One shared kitchen workday per local calendar day; the server owns creation and deduplication. */
(()=>{'use strict';
const panel=document.getElementById('chatWorkdayContext');if(!panel)return;
const $=id=>document.getElementById(id), text=(tag,value)=>{const n=document.createElement(tag);n.textContent=String(value);return n;};
const localDay=()=>{const d=new Date();return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');};
let activeDate=null,loading=null;
const button=(label,handler)=>{const el=text('button',label);el.type='button';el.addEventListener('click',handler);return el;};
function ask(command){const field=$('question'),form=$('coachForm');if(!field||!form)return;field.value=command;form.requestSubmit();}
function chooseBatch(batch){const scope=$('scope'),target=$('target');if(!scope||!target)return;
 scope.value='batch';if(typeof choose==='function')choose(String(batch.id));
 if(typeof loadHistory==='function')loadHistory().catch(e=>{$('coachStatus').textContent=e.message;});
}
function render(data){
 window.pizzaChatSelectedWorkdayId=data.state==='single'?Number(data.day.id):null;
 panel.replaceChildren();panel.hidden=false;
 const title=text('strong',"Today's workday");panel.append(title);
 if(data.state==='none'){
  panel.append(text('span',' · Daily workday unavailable.'));
  panel.append(button('Retry',()=>load()));
  return;
 }
 if(data.state==='multiple'){
  panel.append(text('span',' · Multiple workdays recorded. Choose one: '));
  for(const d of data.days)panel.append(button(d.title+' (#'+d.id+')',()=>load(d.id)));
  return;
 }
 const day=data.day;panel.append(text('span',' · '+day.title+' · '+day.work_date));
 const action=text('div','');action.className='ctx-action';
 action.append(button('Open workday',()=>window.location.assign('workdays.php?id='+encodeURIComponent(day.id))));
 if(data.batches.length===0)action.append(text('span','No batch activity recorded.'));
 else for(const b of data.batches)action.append(button('Use '+b.batch_code,()=>chooseBatch(b)));
 panel.append(action);
 const tip=data.coaching;
 if(tip?.note){
   const n=text('div','Suggestion · '+String(tip.note));n.className='ctx-tip';panel.append(n);
   if(tip.question&&tip.batch_id)panel.append(button('Ask agent',()=>{const found=(window.BATCHES||BATCHES).find(v=>Number(v.id)===Number(tip.batch_id));if(!found)return;chooseBatch(found);ask(String(tip.question));}));
 }
 if(data.pizzas.length)panel.append(text('small',data.pizzas.length+' linked pizza test(s) · Crispness '+(data.avg_crispness===null?'not rated':data.avg_crispness+'/10 from '+data.rated_crispness+' rated')));
}
async function load(selectedId){
 if(loading)return loading;
 const date=localDay();
 loading=(async()=>{
  try{
   // Idempotent across accounts, tabs and concurrent requests, including midnight changes.
   const created=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.PIZZA_CHAT_CSRF},body:JSON.stringify({action:'chatEnsureWorkday',work_date:date})});
   const saved=await created.json();if(!created.ok||!saved.ok)throw Error(saved.error||'Could not activate today’s workday');
   const url=new URL('api.php',window.location.href);url.searchParams.set('action','chatWorkdayContext');url.searchParams.set('date',date);
   url.searchParams.set('workday_id',String(selectedId||saved.day.id));
   const response=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store'}),json=await response.json();
   if(!response.ok||!json.ok)throw Error(json.error||'Workday unavailable');
   activeDate=date;render(json.context);
  }catch(err){panel.hidden=false;panel.replaceChildren();panel.append(text('span','Workday context unavailable. '));panel.append(button('Retry',()=>load()));}
 })();
 try{await loading;}finally{loading=null;}
}
window.pizzaChatLocalWorkdayDate=localDay;
window.pizzaChatSelectedWorkdayId=null;
window.pizzaChatRefreshWorkday=selectedId=>load(selectedId);
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')load();});
// Keep an open kitchen terminal on the correct day even without navigation or reload.
setInterval(()=>{if(document.visibilityState==='visible'&&localDay()!==activeDate)load();},60000);
load();
})();
