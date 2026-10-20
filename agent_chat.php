<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
auth();
$recipes=query('SELECT id,name FROM recipes ORDER BY name')->fetchAll();
$batches=query('SELECT b.id,b.batch_code,r.name AS recipe_name FROM dough_batches b JOIN recipes r ON r.id=b.recipe_id ORDER BY b.id DESC LIMIT 250')->fetchAll();
$scope=in_array(($_GET['scope']??''),['all','recipe','batch'],true)?$_GET['scope']:'all';
$target=(string)($_GET['id']??'');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#10151c"><title>Agent Chat · Pizza Lab</title><link rel="stylesheet" href="assets/style.css?v=1.5.2"><style>
:root{--edge:rgba(148,175,189,.18)}
body{margin:0}.coach{max-width:930px;margin:0 auto;padding:12px 14px 140px;min-height:100dvh}
.coach-top{display:flex;align-items:center;gap:12px;justify-content:space-between;padding:8px 0 12px}
.coach-top h1{font-size:1.12rem;margin:0}.coach-top a{font-size:.86rem}
.coach-intro{font-size:.86rem;opacity:.7;margin:3px 0 14px}.coach-context{border:1px solid var(--edge);border-radius:12px;padding:10px 13px;margin-bottom:18px;color:var(--muted)}
.coach-context summary{cursor:pointer;list-style:auto;font-size:.88rem}
.coach-context .coach-row{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap}
.coach-row label{flex:1 1 200px}.coach select{width:100%;max-width:none}
.coach-context p{font-size:.78rem;opacity:.8}
.coach-log{display:flex;flex-direction:column;gap:12px;padding:12px 0 24px;min-height:42vh}
.coach-message{border:1px solid var(--edge);background:rgba(105,145,165,.06);padding:15px 18px;border-radius:17px;max-width:100%;overflow-wrap:anywhere}
.coach-message h3{font-size:.91rem;margin:14px 0 6px}
.coach-message p,.coach-message li{line-height:1.55}
.coach-message ul{padding-left:20px}.coach-message small{opacity:.6}
.coach-form{position:fixed;z-index:5;bottom:0;left:0;right:0;margin:0 auto;max-width:930px;padding:12px 14px max(12px,env(safe-area-inset-bottom));background:linear-gradient(0deg,#10151c 80%,#10151ced 100%);border-top:1px solid var(--edge)}
.coach-inputbar{display:flex;align-items:end;gap:8px;border:1px solid #52616d;border-radius:21px;padding:7px 8px;background:#1a2530}
.coach-inputbar textarea{flex:1;min-width:0;max-height:125px;min-height:40px;resize:vertical;border:0;outline:none;background:transparent;color:#fff;padding:9px 4px}
.coach-action{min-width:42px;width:42px;height:42px;border-radius:50%;display:grid;place-items:center;padding:0;font-size:1.12rem}
.coach-action.listening{outline:2px solid #8bdbb9;background:#29453f}.coach-action.primary{font-size:1.2rem}
.coach-status{font-size:.75rem;opacity:.8;min-height:17px;padding:4px 8px}
.coach-muted{font-size:.82rem;opacity:.7}
@media(max-width:600px){.coach{padding:9px 10px 142px}.coach-top{gap:6px}.coach-top a{font-size:.78rem}.coach-inputbar{gap:5px}.coach-message{padding:13px}}
</style></head><body><main class="coach">
<header class="coach-top"><a href="index.php?workspace=1">☷ Lab tools</a><h1>Pizza Lab · Agent</h1><a href="cooking_patterns.php">Cooking patterns ↗</a></header>
<p class="coach-intro">Ask about your dough, cooking technique, or any recipe. Answers use saved evidence when available.</p>
<details class="coach-context"><summary>Evidence scope · all recipes by default</summary><div class="coach-row"><label>Analyze<select id="scope"><option value="all" <?=$scope==='all'?'selected':''?>>All recipes & batches</option><option value="recipe" <?=$scope==='recipe'?'selected':''?>>One recipe or variation</option><option value="batch" <?=$scope==='batch'?'selected':''?>>One batch</option></select></label><label id="targetRow">Recipe / batch<select id="target"></select></label></div>
<p class="coach-muted">Recent batch snapshots, mixing and balling, container temperatures, pizza tests, stone readings, sauce, cheese, usage and recorded waste. No visual photo interpretation on this screen: use the pizza scanner for image analysis. Unknown measurements remain unknown.</p></details>
<section id="coachHistory" class="coach-log" aria-live="polite"></section>
<form id="coachForm" class="coach-form">
 <div class="coach-inputbar">
  <button id="chatMic" type="button" class="coach-action" aria-label="Speak to the agent" aria-pressed="false" title="Speak to the agent">🎙</button>
  <button id="chatCamera" type="button" class="coach-action" aria-label="Open pizza camera tracker" title="Open camera tracker">📷</button>
  <textarea id="question" rows="1" maxlength="600" required aria-label="Message Pizza Lab" placeholder="Ask Pizza Lab…"></textarea>
  <button id="send" type="submit" class="primary coach-action" aria-label="Send message" title="Send">➤</button>
 </div>
 <div class="coach-status" id="coachStatus" role="status" aria-live="polite">Tap microphone to ask a question, or camera to track a pizza.</div>
</form>
</main><script>
const CSRF=<?=json_encode(csrf(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
const RECIPES=<?=json_encode($recipes,JSON_INVALID_UTF8_SUBSTITUTE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
const BATCHES=<?=json_encode($batches,JSON_INVALID_UTF8_SUBSTITUTE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
const FIRST=<?=json_encode($target,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
const el=id=>document.getElementById(id);
function context(){return {scope:el('scope').value,target_id:el('scope').value==='all'?null:el('target').value};}
function choose(preset){const t=el('target'),scope=el('scope').value,items=scope==='batch'?BATCHES:RECIPES;t.replaceChildren();for(const x of items){const opt=document.createElement('option');opt.value=x.id;opt.textContent=scope==='batch'?x.batch_code+' · '+x.recipe_name:x.name;t.append(opt);}if(preset&&items.some(x=>String(x.id)===preset))t.value=preset;el('targetRow').hidden=scope==='all';el('coachHistory').replaceChildren();}
function addText(parent,tag,value){const node=document.createElement(tag);node.textContent=String(value??'');parent.append(node);return node;}
function show(report){const a=report.analysis||{},article=document.createElement('article');article.className='coach-message';addText(article,'small',String(report.created_at||'New analysis')+' · '+(report.model||'Claude')+' · confidence '+(a.confidence||'low'));addText(article,'h3','You asked');addText(article,'p',report.question||'');addText(article,'h3','Agent recommendation');addText(article,'p',a.summary||'No summary');
for(const group of [['Measured observations','measured_findings'],['Possible mechanisms (not proven)','hypotheses'],['Suggested controlled experiments','next_tests'],['Missing evidence / caveats','limitations'],['Supporting batch codes and evidence','evidence_references']]){if(!Array.isArray(a[group[1]])||!a[group[1]].length)continue;addText(article,'h3',group[0]);const ul=document.createElement('ul');for(const v of a[group[1]])addText(ul,'li',v);article.append(ul);}el('coachHistory').append(article);}
async function loadHistory(){const c=context(),qs=new URLSearchParams({action:'batchCoachHistory',scope:c.scope});if(c.target_id)qs.set('target_id',c.target_id);const r=await fetch('api.php?'+qs,{credentials:'same-origin'}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'History unavailable');el('coachHistory').replaceChildren();if(!d.reviews.length)addText(el('coachHistory'),'p','No reviews for this selection. Ask Claude to compare recorded measurements.');for(const x of d.reviews)show(x);}
el('scope').addEventListener('change',()=>{choose();loadHistory().catch(e=>el('coachStatus').textContent=e.message);});
el('target').addEventListener('change',()=>loadHistory().catch(e=>el('coachStatus').textContent=e.message));
el('coachForm').addEventListener('submit',async ev=>{ev.preventDefault();const button=el('send');button.disabled=true;el('coachStatus').textContent='Analyzing available evidence…';try{const r=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({action:'batchCoachAsk',...context(),question:el('question').value})});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Claude request failed');show(d.review);window.dispatchEvent(new CustomEvent('pizza-chat-reply',{detail:{text:d.review?.analysis?.summary||''}}));el('question').value='';el('coachStatus').textContent='Report saved from '+d.review.snapshot_batches+' batch(es). No automatic changes.';window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});}catch(e){el('coachStatus').textContent=e.message;}finally{button.disabled=false;}});
choose(FIRST);loadHistory().catch(e=>el('coachStatus').textContent=e.message);
</script><script defer src="assets/agent_chat_voice.js?v=1.5.8"></script></body></html>
