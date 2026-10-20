/* Read-only current workday awareness. User picks context; no inferred links or automatic writes. */
(()=>{'use strict';
const panel=document.getElementById('chatWorkdayContext');if(!panel)return;
const $=id=>document.getElementById(id), text=(tag,value)=>{const n=document.createElement(tag);n.textContent=String(value);return n;};
const localDay=()=>{const d=new Date();return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');};
const today=localDay();
const button=(label,handler)=>{const el=text('button',label);el.type='button';el.addEventListener('click',handler);return el;};
function ask(command){const field=$('question'),form=$('coachForm');if(!field||!form)return;field.value=command;form.requestSubmit();}
function chooseBatch(batch){const scope=$('scope'),target=$('target');if(!scope||!target)return;
 scope.value='batch';if(typeof choose==='function')choose(String(batch.id));
 if(typeof loadHistory==='function')loadHistory().catch(e=>{$('coachStatus').textContent=e.message;});
}
function render(data){
 panel.replaceChildren();panel.hidden=false;
 const title=text('strong',"Today's workday");panel.append(title);
 if(data.state==='none'){
  panel.append(text('span',' · No workday recorded for today. '));
  panel.append(button('Create workday',()=>ask('Create workday today')));
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
 if(data.pizzas.length)panel.append(text('small',data.pizzas.length+' linked pizza test(s) · Crispness '+(data.avg_crispness===null?'not rated':data.avg_crispness+'/10 from '+data.rated_crispness+' rated')));
}
async function load(selectedId){
 const url=new URL('api.php',window.location.href);url.searchParams.set('action','chatWorkdayContext');url.searchParams.set('date',today);
 if(selectedId)url.searchParams.set('workday_id',String(selectedId));
 try{const response=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store'}),json=await response.json();
 if(!response.ok||!json.ok)throw Error(json.error||'Workday unavailable');
 render(json.context);
 }catch(err){panel.hidden=false;panel.replaceChildren();panel.append(text('span','Workday context unavailable. '));panel.append(button('Retry',()=>load()));}
}
window.pizzaChatRefreshWorkday=()=>load();
load();
})();