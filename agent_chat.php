<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
auth();
$recipes=query('SELECT id,name FROM recipes ORDER BY name')->fetchAll();
$batches=query('SELECT b.id,b.batch_code,r.name AS recipe_name FROM dough_batches b JOIN recipes r ON r.id=b.recipe_id ORDER BY b.id DESC LIMIT 250')->fetchAll();
$batchEvidence=query('SELECT batch_id,id FROM evidence WHERE batch_id IS NOT NULL ORDER BY id DESC LIMIT 1000')->fetchAll();
$pizzaIds=array_map('intval',query('SELECT id FROM evidence ORDER BY id DESC LIMIT 1000')->fetchAll(PDO::FETCH_COLUMN));
$scope=in_array(($_GET['scope']??''),['all','recipe','batch'],true)?$_GET['scope']:'all';
$target=(string)($_GET['id']??'');
$user=auth_user();
$voiceProfile=query('SELECT profile_name,wake_phrase,language,voice_uri,voice_rate,require_wake,start_alias FROM voice_profiles WHERE user_id=?',[auth()])->fetch() ?: [];
$account=['display_name'=>$user['display_name'],'email'=>$user['email'],'role'=>$user['role']];
$initial=strtoupper(function_exists('mb_substr') ? mb_substr((string)$user['display_name'],0,1) : substr((string)$user['display_name'],0,1));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#10151c"><title>Agent Chat · Pizza Lab</title><link rel="stylesheet" href="assets/style.css?v=1.5.12"><style>
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
.coach-message ul{padding-left:20px}.coach-message small{opacity:.6}.coach-user{align-self:flex-end;max-width:min(82%,670px);background:#283b46;border-radius:17px 17px 4px 17px;padding:11px 16px}.coach-user p{margin:2px 0}.coach-answer{align-self:flex-start;max-width:100%}.coach-answer details{margin-top:10px}.coach-answer summary{cursor:pointer;color:#bedacb}
.coach-form{position:fixed;z-index:5;bottom:0;left:0;right:0;margin:0 auto;max-width:930px;padding:12px 14px max(12px,env(safe-area-inset-bottom));background:linear-gradient(0deg,#10151c 80%,#10151ced 100%);border-top:1px solid var(--edge)}
.coach-inputbar{display:flex;align-items:end;gap:8px;border:1px solid #52616d;border-radius:21px;padding:7px 8px;background:#1a2530}
.coach-inputbar textarea{flex:1;min-width:0;max-height:125px;min-height:40px;resize:vertical;border:0;outline:none;background:transparent;color:#fff;padding:9px 4px}
.coach-action{min-width:42px;width:42px;height:42px;border-radius:50%;display:grid;place-items:center;padding:0;font-size:1.12rem}
.coach-action.listening{outline:2px solid #8bdbb9;background:#29453f}.coach-action.primary{font-size:1.2rem}
.coach-status{font-size:.75rem;opacity:.8;min-height:17px;padding:4px 8px}
.coach-muted{font-size:.82rem;opacity:.7}
.chat-workday-context[hidden]{display:none!important}
.chat-workday-context{border:1px solid var(--edge);border-radius:13px;padding:9px 12px;margin:6px 0 10px;color:var(--muted);font-size:.82rem}
.chat-workday-context strong{color:var(--ink)}
.chat-workday-context button{font-size:.76rem;min-height:36px;padding:6px 10px;margin:4px 6px 2px 0}
.chat-workday-context .ctx-action{display:inline-flex;flex-wrap:wrap;gap:4px;align-items:center}
.chat-workday-context .ctx-tip{margin-top:5px;font-size:.77rem;color:var(--muted)}
.coach-camera-panel[hidden]{display:none!important}
.coach-camera-panel{position:fixed;z-index:9;inset:10px max(10px,calc((100vw - 930px)/2)) 92px;background:#111b24;border:1px solid #506578;border-radius:19px;display:flex;flex-direction:column;box-shadow:0 12px 45px #000b;overflow:hidden}
.coach-camera-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 13px;border-bottom:1px solid var(--edge)}
.coach-camera-head a{font-size:.78rem}.coach-camera-head button{min-height:36px;padding:6px 12px}
.coach-camera-frame{flex:1;display:block;width:100%;height:100%;border:0;background:#111b24}
.coach-action[aria-expanded=true]{outline:2px solid var(--accent)}
@media(max-width:600px){.coach-camera-panel{inset:6px 6px 104px;border-radius:14px}.coach-camera-head{padding:7px 10px}}
@media(max-width:600px){.coach{padding:9px 10px 142px}.coach-top{gap:6px}.coach-top a{font-size:.78rem}.coach-inputbar{gap:5px}.coach-message{padding:13px}}

.chat-menu-toggle{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;font-size:1.4rem;flex:none}
.chat-nav-backdrop[hidden],.chat-nav-drawer[hidden]{display:none!important}
.chat-nav-backdrop{position:fixed;inset:0;background:#000a;z-index:20}
.chat-nav-drawer{position:fixed;inset:0 auto 0 0;width:min(340px,87vw);height:100dvh;overflow-y:auto;z-index:21;background:#141e28;box-shadow:12px 0 42px #0008;padding:16px 14px max(18px,env(safe-area-inset-bottom));display:flex;flex-direction:column;gap:6px}
.chat-nav-header{display:flex;align-items:center;justify-content:space-between;padding:6px 5px 12px;border-bottom:1px solid var(--edge);margin-bottom:8px}
.chat-nav-close{min-height:42px;min-width:42px;padding:8px;font-size:1.25rem}
.chat-nav-heading{font-size:.68rem;text-transform:uppercase;letter-spacing:.14em;opacity:.65;margin:15px 12px 5px}
.chat-nav-link{display:flex;align-items:center;gap:12px;min-height:43px;padding:10px 13px;border-radius:10px;text-decoration:none;color:#e7f0f2;font-size:.94rem}
.chat-nav-link:hover,.chat-nav-link:focus-visible,.chat-nav-link[aria-current=page]{background:#263542;color:#fff}
.chat-nav-link span:first-child{width:22px;text-align:center}
.chat-nav-footer{margin-top:auto;border-top:1px solid var(--edge);padding:12px 8px;color:var(--muted);font-size:.8rem}
body.chat-nav-open{overflow:hidden}
.coach-command-card{border:1px solid var(--edge);background:#182630;border-radius:14px;padding:13px 15px;margin-top:6px}
.coach-command-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.coach-command-list button{font-weight:500;font-size:.85rem;max-width:100%;overflow-wrap:anywhere}
.coach-confirm{border:1px solid #a77c47;background:#31281d;border-radius:12px;padding:12px;margin-top:12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.coach-confirm button{min-height:42px}

/* Compact account button; only the chat, nav and selected dialog are visible. */
.coach-top{gap:10px}.coach-top h1{white-space:nowrap;font-size:1.02rem}
.chat-account{position:relative;margin-left:auto}
.chat-account-toggle{height:44px;min-height:44px;display:flex;align-items:center;gap:8px;border:1px solid var(--edge);background:transparent;padding:4px 8px;border-radius:24px;font-weight:600}
.chat-account-initial{width:30px;height:30px;border-radius:50%;background:#334b51;display:grid;place-items:center;color:#f2f6f2}
.chat-account-name{max-width:145px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:.83rem}
.chat-account-menu[hidden],.chat-account-form[hidden]{display:none!important}
.chat-account-menu{position:absolute;top:calc(100% + 7px);right:0;width:226px;padding:6px;background:#1a2730;border:1px solid #42545e;border-radius:15px;box-shadow:0 14px 35px #0009;z-index:25}
.chat-account-menu button{display:block;text-align:left;width:100%;border:0;border-radius:9px;background:transparent;min-height:42px;font-size:.91rem;font-weight:500;padding:10px}
.chat-account-menu button:hover,.chat-account-menu button:focus-visible{background:#2c4049}
.chat-account-menu form{margin:3px 0 0;border-top:1px solid var(--edge);padding-top:3px}
.chat-account-menu form button{color:#ffb7a2}
.chat-account-dialog{background:#14212b;color:#eff5f5;width:min(460px,calc(100vw - 28px));max-height:min(86dvh,740px);border:1px solid #50616b;border-radius:18px;padding:0;box-shadow:0 25px 70px #000b;overflow:auto}
.chat-account-dialog::backdrop{background:#050c11c9}
.chat-dialog-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;border-bottom:1px solid var(--edge)}
.chat-dialog-head h2{font-size:1.15rem}
.chat-dialog-head button{min-width:40px;min-height:40px;padding:6px}
.chat-dialog-inner{padding:20px;display:flex;flex-direction:column;gap:14px}
.chat-account-form{display:flex;flex-direction:column;gap:13px}
.chat-account-form label{font-size:.84rem}
.chat-account-form .small{margin:0}
.chat-account-form input[type=checkbox]{min-height:18px;width:18px;margin:0}
.chat-account-form input[type=range]{min-height:26px}
.chat-dialog-status{min-height:1.2em;font-size:.82rem;color:#9bd7b6}
.chat-dialog-status[data-error=true]{color:#ffb4a4}
.coach-intro{display:none}
@media(max-width:560px){.chat-account-name{display:none}.chat-account-toggle{gap:3px;padding:4px 7px}.coach-top h1{font-size:1rem}}
</style></head><body>
<div id="chatNavBackdrop" class="chat-nav-backdrop" hidden></div>
<aside id="chatNav" class="chat-nav-drawer" aria-label="Main navigation" aria-hidden="true" inert hidden>
 <header class="chat-nav-header"><strong>◉ &nbsp; Pizza Lab</strong><button type="button" id="chatNavClose" class="chat-nav-close" aria-label="Close navigation">✕</button></header>
 <nav aria-label="Pizza Lab main menu">
  <p class="chat-nav-heading">Main</p>
  <a class="chat-nav-link" href="agent_chat.php" aria-current="page"><span>◉</span> Agent Chat</a>
  <a class="chat-nav-link" href="workdays.php"><span>◫</span> Workdays</a>
  <a class="chat-nav-link" href="index.php?workspace=1#recipes"><span>▤</span> Recipes &amp; variations</a>
  <a class="chat-nav-link" href="index.php?workspace=1#batches"><span>▣</span> Dough batches</a>
  <a class="chat-nav-link" href="index.php?workspace=1#evidence"><span>◎</span> Pizza evidence</a>
  <a class="chat-nav-link" href="index.php?workspace=1#live"><span>●</span> Live oven &amp; timers</a>
  <a class="chat-nav-link" href="gallery.php"><span>▧</span> Image gallery</a>
  <p class="chat-nav-heading">Analysis &amp; equipment</p>
  <a class="chat-nav-link" href="index.php?workspace=1#experiments"><span>⚗</span> Experiments</a>
  <a class="chat-nav-link" href="proposals.php"><span>✧</span> AI proposals</a>
  <a class="chat-nav-link" href="batch_insights.php"><span>▥</span> Batch reports</a>
  <a class="chat-nav-link" href="readiness.php"><span>◷</span> Dough readiness</a>
  <a class="chat-nav-link" href="oven_layout.php"><span>▦</span> Oven builder</a>
  <a class="chat-nav-link" href="heatmap.php"><span>◉</span> Heat map</a>
  <a class="chat-nav-link" href="cooking_patterns.php"><span>↝</span> Cooking patterns</a>
  <a class="chat-nav-link" href="visual_compare.php"><span>◈</span> Crust comparison</a>
  <a class="chat-nav-link" href="pizza_tracker.php"><span>▣</span> Full-screen tracker</a>
  <p class="chat-nav-heading">Account</p>
  <a class="chat-nav-link" href="index.php?workspace=1#settings"><span>⚙</span> Voice &amp; settings</a>
  <?php if(is_super_admin()):?><a class="chat-nav-link" href="index.php?workspace=1#team"><span>♙</span> Team administration</a><?php endif;?>
 </nav>
 <div class="chat-nav-footer">Signed in as <span id="chatSignedInAs"><?=htmlspecialchars((string)$user['display_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></span></div>
</aside>
<dialog id="chatAccountDialog" class="chat-account-dialog" aria-labelledby="chatAccountTitle"><header class="chat-dialog-head"><h2 id="chatAccountTitle">My profile</h2><button type="button" id="chatAccountClose" aria-label="Close account settings">✕</button></header><div class="chat-dialog-inner">
 <form id="chatProfileForm" class="chat-account-form" autocomplete="on"><label>Display name<input name="display_name" type="text" maxlength="120" required autocomplete="name"></label><label>Email / sign-in address<input name="email" type="email" maxlength="190" required autocomplete="email"></label><label>Current password (only needed to change email)<input name="current_password" type="password" autocomplete="current-password"></label><p class="small coach-muted">Role: <?=htmlspecialchars($user['role']==='super_admin'?'Super Admin':'Team Member',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>. Email changes take effect immediately; only an administrator can manage roles.</p><button type="submit" class="primary">Save profile</button></form>
 <form id="chatVoiceForm" class="chat-account-form" hidden><label>Voice profile name<input name="profile_name" type="text" maxlength="80" required></label><label>Wake phrase<input name="wake_phrase" type="text" maxlength="40" required placeholder="pizza"></label><label>Recognition language<select name="language"><option value="en-US">English (US)</option><option value="en-GB">English (UK)</option><option value="en-AU">English (AU)</option></select></label><label>Spoken response voice<select name="voice_uri"><option value="">Device default</option></select></label><label>Speaking speed <output id="chatRateValue">0.95×</output><input name="voice_rate" type="range" min="0.6" max="1.5" step="0.05" value="0.95"></label><label>Optional timer start alias<input name="start_alias" type="text" maxlength="70" placeholder="start bake"></label><label style="display:flex;align-items:center;gap:10px"><input name="require_wake" type="checkbox" checked> Require wake phrase for timer commands</label><p class="small coach-muted">Voice profiles are preferences, not biometric identity. Browser microphone access requires permission and an active HTTPS page.</p><button type="submit" class="primary">Save voice settings</button></form>
 <form id="chatPasswordForm" class="chat-account-form" hidden><label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label><label>New password (12+ characters)<input name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="1024" required></label><label>Confirm new password<input name="confirm_password" type="password" autocomplete="new-password" minlength="12" maxlength="1024" required></label><button type="submit" class="primary">Change password</button></form>
 <div id="chatAccountStatus" class="chat-dialog-status" role="status" aria-live="polite"></div></div></dialog>
<main class="coach">
<header class="coach-top"><button type="button" id="chatNavToggle" class="chat-menu-toggle" aria-label="Open main menu" aria-controls="chatNav" aria-expanded="false">☰</button><h1>Pizza Lab</h1>
 <div class="chat-account"><button type="button" id="chatAccountToggle" class="chat-account-toggle" aria-label="Account menu" aria-haspopup="menu" aria-expanded="false" aria-controls="chatAccountMenu"><span id="chatAccountInitial" class="chat-account-initial"><?=htmlspecialchars($initial,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></span><span id="chatAccountName" class="chat-account-name"><?=htmlspecialchars((string)$user['display_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></span><span aria-hidden="true">⌄</span></button>
 <div id="chatAccountMenu" class="chat-account-menu" role="menu" aria-label="Account" hidden><button type="button" role="menuitem" data-account-panel="profile">My profile</button><button type="button" role="menuitem" data-account-panel="voice">Voice settings</button><button type="button" role="menuitem" data-account-panel="password">Change password</button><form method="post" action="auth.php?logout=1"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><button type="submit" role="menuitem">Log out</button></form></div></div></header>
<p class="coach-intro">Ask about your dough, cooking technique, or any recipe. Answers use saved evidence when available.</p>
<section id="chatWorkdayContext" class="chat-workday-context" aria-live="polite" aria-label="Today's recorded workday" hidden></section>
<details class="coach-context"><summary>Evidence scope · all recipes by default</summary><div class="coach-row"><label>Analyze<select id="scope"><option value="all" <?=$scope==='all'?'selected':''?>>All recipes & batches</option><option value="recipe" <?=$scope==='recipe'?'selected':''?>>One recipe or variation</option><option value="batch" <?=$scope==='batch'?'selected':''?>>One batch</option></select></label><label id="targetRow">Recipe / batch<select id="target"></select></label></div>
<p class="coach-muted">Recent batch snapshots, dough temperatures, pizza tests, stone readings, and usage. Tap the footer camera to collect private pizza photos without leaving the chat. Missing measurements remain unknown.</p></details>
<section id="coachHistory" class="coach-log" aria-live="polite"></section>
<section id="chatCameraPanel" class="coach-camera-panel" aria-label="In-chat pizza camera" hidden>
 <header class="coach-camera-head"><strong>Live pizza camera</strong><a href="pizza_tracker.php" target="_blank" rel="noopener">Open full screen ↗</a><button type="button" id="chatCameraClose" aria-label="Close pizza camera">✕ Close</button></header>
 <iframe id="chatCameraFrame" class="coach-camera-frame" title="Pizza tracker and image analysis" allow="camera; microphone" referrerpolicy="same-origin"></iframe>
</section>
<form id="coachForm" class="coach-form">
 <div class="coach-inputbar">
  <button id="chatMic" type="button" class="coach-action" aria-label="Speak to the agent" aria-pressed="false" title="Speak to the agent">🎙</button>
  <button id="chatCamera" type="button" class="coach-action" aria-label="Toggle in-chat pizza camera" aria-expanded="false" aria-controls="chatCameraPanel" title="Open pizza tracker in chat">📷</button>
  <textarea id="question" rows="1" maxlength="600" required aria-label="Message Pizza Lab" placeholder="Ask Pizza Lab…"></textarea>
  <button id="send" type="submit" class="primary coach-action" aria-label="Send message" title="Send">➤</button>
 </div>
 <div class="coach-status" id="coachStatus" role="status" aria-live="polite">Tap microphone to ask a question, or camera to track a pizza.</div>
</form>
</main><script>
const CSRF=<?=json_encode(csrf(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
const RECIPES=<?=json_encode($recipes,JSON_INVALID_UTF8_SUBSTITUTE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
window.PIZZA_CHAT_RECIPE_LIST=RECIPES;
const BATCHES=<?=json_encode($batches,JSON_INVALID_UTF8_SUBSTITUTE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
window.PIZZA_CHAT_BATCH_EVIDENCE=<?=json_encode($batchEvidence,JSON_INVALID_UTF8_SUBSTITUTE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
window.PIZZA_CHAT_PIZZA_IDS=<?=json_encode($pizzaIds,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
const FIRST=<?=json_encode($target,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
window.PIZZA_CHAT_ACCOUNT=<?=json_encode($account,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
window.PIZZA_CHAT_CSRF=CSRF;
window.PIZZA_CHAT_VOICE=<?=json_encode($voiceProfile,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
window.PIZZA_CHAT_WAKE=<?=json_encode($voiceProfile['wake_phrase']??'pizza',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
window.PIZZA_CHAT_LANGUAGE=<?=json_encode($voiceProfile['language']??'en-US',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?>;
const el=id=>document.getElementById(id);
function context(){return {scope:el('scope').value,target_id:el('scope').value==='all'?null:el('target').value};}
function choose(preset){const t=el('target'),scope=el('scope').value,items=scope==='batch'?BATCHES:RECIPES;t.replaceChildren();for(const x of items){const opt=document.createElement('option');opt.value=x.id;opt.textContent=scope==='batch'?x.batch_code+' · '+x.recipe_name:x.name;t.append(opt);}if(preset&&items.some(x=>String(x.id)===preset))t.value=preset;el('targetRow').hidden=scope==='all';el('coachHistory').replaceChildren();}
function addText(parent,tag,value){const node=document.createElement(tag);node.textContent=String(value??'');parent.append(node);return node;}
function show(report){
 const a=report.analysis||{},log=el('coachHistory');
 const user=document.createElement('article');user.className='coach-message coach-user';addText(user,'p',report.question||'');log.append(user);
 const article=document.createElement('article');article.className='coach-message coach-answer';
 addText(article,'small',String(report.created_at||'New analysis')+' · confidence '+(a.confidence||'low'));
 addText(article,'p',a.summary||'No summary');
 const details=document.createElement('details');const summary=document.createElement('summary');summary.textContent='Evidence and reasoning';details.append(summary);
 for(const group of [['Measured observations','measured_findings'],['Possible mechanisms (not proven)','hypotheses'],['Suggested controlled experiments','next_tests'],['Missing evidence / caveats','limitations'],['Supporting batch codes and evidence','evidence_references']]){
  if(!Array.isArray(a[group[1]])||!a[group[1]].length)continue;
  addText(details,'h3',group[0]);const ul=document.createElement('ul');for(const v of a[group[1]])addText(ul,'li',v);details.append(ul);
 }
 if(details.children.length>1)article.append(details);
 log.append(article);
}
async function loadHistory(){const c=context(),qs=new URLSearchParams({action:'batchCoachHistory',scope:c.scope});if(c.target_id)qs.set('target_id',c.target_id);const r=await fetch('api.php?'+qs,{credentials:'same-origin'}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'History unavailable');el('coachHistory').replaceChildren();if(!d.reviews.length)addText(el('coachHistory'),'p','Ask me about any dough batch, recipe, or cooking result.');for(const x of d.reviews)show(x);}
el('scope').addEventListener('change',()=>{choose();loadHistory().catch(e=>el('coachStatus').textContent=e.message);});
el('target').addEventListener('change',()=>loadHistory().catch(e=>el('coachStatus').textContent=e.message));
el('coachForm').addEventListener('submit',async ev=>{ev.preventDefault();
if(await window.pizzaChatCommands?.(el('question').value)){el('question').value='';return;}
const button=el('send');button.disabled=true;el('coachStatus').textContent='Analyzing available evidence…';try{const r=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify({action:'batchCoachAsk',...context(),question:el('question').value,workday_date:window.pizzaChatLocalWorkdayDate?.()||null,workday_id:window.pizzaChatSelectedWorkdayId??null})});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Claude request failed');show(d.review);window.dispatchEvent(new CustomEvent('pizza-chat-reply',{detail:{text:d.review?.analysis?.summary||''}}));el('question').value='';el('coachStatus').textContent='Report saved from '+d.review.snapshot_batches+' batch(es). No automatic changes.';window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});}catch(e){el('coachStatus').textContent=e.message;window.dispatchEvent(new Event('pizza-chat-reply-error'));}finally{button.disabled=false;}});
choose(FIRST);loadHistory().catch(e=>el('coachStatus').textContent=e.message);
</script><script defer src="assets/agent_chat_timers.js?v=1.5.16b"></script><script defer src="assets/agent_chat_workday.js?v=1.5.14"></script><script defer src="assets/agent_chat_nav.js?v=1.5.12"></script><script defer src="assets/agent_chat_batch.js?v=1.5.16d"></script><script defer src="assets/agent_chat_commands.js?v=1.5.16d"></script><script defer src="assets/agent_chat_camera.js?v=1.5.12"></script><script defer src="assets/agent_chat_voice.js?v=1.5.16d"></script><script defer src="assets/agent_chat_account.js?v=1.5.12"></script></body></html>
