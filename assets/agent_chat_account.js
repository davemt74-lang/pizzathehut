/* Small accessible account menu + on-page profile/voice/password forms.
 * All changes go through the signed-in, CSRF-checked API. */
(()=>{'use strict';
const byId=id=>document.getElementById(id);
const toggle=byId('chatAccountToggle'),menu=byId('chatAccountMenu'),dialog=byId('chatAccountDialog');
const close=byId('chatAccountClose'),heading=byId('chatAccountTitle'),status=byId('chatAccountStatus');
if(!toggle||!menu||!dialog||!close||!heading||!status)return;
let profile={...window.PIZZA_CHAT_ACCOUNT},voice={...window.PIZZA_CHAT_VOICE},ai={...window.PIZZA_CHAT_AI};
let focusReturn=null;
const panes={profile:byId('chatProfileForm'),voice:byId('chatVoiceForm'),password:byId('chatPasswordForm')};
if(byId('chatAiForm'))panes.ai=byId('chatAiForm');
const errors={profile:'Unable to update profile',voice:'Unable to save voice settings',password:'Unable to change password',ai:'Unable to save Claude settings'};
function setMenu(open){menu.hidden=!open;toggle.setAttribute('aria-expanded',String(open));if(open){menu.querySelector('button')?.focus();}}
function updateHeader(){byId('chatAccountName').textContent=profile.display_name;byId('chatAccountInitial').textContent=Array.from(profile.display_name||'?')[0].toUpperCase();byId('chatSignedInAs').textContent=profile.display_name;}
function fillProfile(){panes.profile.elements.display_name.value=profile.display_name;panes.profile.elements.email.value=profile.email;panes.profile.elements.current_password.value='';}
function listVoices(){const select=panes.voice.elements.voice_uri;if(!select)return;const previous=voice.voice_uri||'';select.replaceChildren();const make=(value,label)=>{const opt=new Option(label,value);select.add(opt);};make('','Device default');const voices=window.speechSynthesis?.getVoices?.()||[];if(previous && !voices.some(v=>v.voiceURI===previous))make(previous,'Saved voice (not available on this device)');for(const v of voices)make(v.voiceURI,v.name+' · '+v.lang);select.value=previous;}
function fillVoice(){const f=panes.voice;f.elements.profile_name.value=voice.profile_name||profile.display_name;f.elements.wake_phrase.value=voice.wake_phrase||'pizza';f.elements.start_alias.value=voice.start_alias||'';f.elements.language.value=voice.language||'en-US';f.elements.voice_rate.value=String(voice.voice_rate||.95);byId('chatRateValue').textContent=Number(voice.voice_rate||.95).toFixed(2)+'×';f.elements.require_wake.checked=voice.require_wake===undefined||Number(voice.require_wake)===1;listVoices();}
function fillAi(){const f=panes.ai;if(!f)return;f.elements.model.value=ai.model||'claude-haiku-5-5';f.elements.api_key.value='';f.elements.remove_key.checked=false;byId('chatAiConfigured').textContent=ai.key_configured?'API key configured':'No API key saved yet';}
function openPane(name){if(!panes[name])return;setMenu(false);document.getElementById('chatNavClose')?.click?.();focusReturn=toggle;for(const [key,pane] of Object.entries(panes))pane.hidden=key!==name;
 heading.textContent={profile:'My profile',voice:'Voice settings',password:'Change password',ai:'Claude API & model'}[name];status.textContent='';if(name==='profile')fillProfile();if(name==='voice')fillVoice();if(name==='password')panes.password.reset();if(name==='ai')fillAi();dialog.showModal();panes[name].querySelector('input:not([type=hidden]),select')?.focus();}
function closePane(){dialog.close();}
function showStatus(message,bad=false){status.textContent=message;status.dataset.error=String(bad);}
async function post(action,data){const response=await fetch('api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.PIZZA_CHAT_CSRF},body:JSON.stringify({action,...data})});let result;try{result=await response.json();}catch{throw Error('Unexpected server response');}if(!response.ok||!result.ok)throw Error(result.error||'Request failed');return result;}
async function save(event,name){event.preventDefault();const form=panes[name],button=form.querySelector('[type=submit]');if(button.disabled)return;const data=Object.fromEntries(new FormData(form).entries());if(name==='profile'){
 if(String(data.email).toLowerCase()!==profile.email.toLowerCase()&&!String(data.current_password||'')){showStatus('Enter your current password to change your email.',true);return;}
 }else if(name==='password'){
 if(data.new_password!==data.confirm_password){showStatus('New passwords do not match.',true);return;}
 delete data.confirm_password;
 }else if(name==='voice'){data.require_wake=form.elements.require_wake.checked?1:0;}
 else if(name==='ai'){
  if(form.elements.remove_key.checked&&data.api_key){showStatus('Choose either a replacement key or remove the saved key.',true);return;}
  data.remove_key=form.elements.remove_key.checked?1:0;
 }
 button.disabled=true;showStatus('Saving…');try{
 const result=await post({profile:'updateMyProfile',voice:'saveVoiceProfile',password:'changeMyPassword',ai:'saveSettings'}[name],data);
 if(name==='profile'){profile={...profile,...result.profile};updateHeader();fillProfile();}
 if(name==='voice'){voice={...voice,...data};window.PIZZA_CHAT_VOICE={...voice};window.PIZZA_CHAT_WAKE=voice.wake_phrase;window.PIZZA_CHAT_LANGUAGE=voice.language;}
 if(name==='ai'){ai={model:data.model,key_configured:!data.remove_key&&(!!data.api_key||ai.key_configured)};window.PIZZA_CHAT_AI={...ai};fillAi();}
 if(name==='password')form.reset();
 showStatus({profile:'Profile saved.',voice:'Voice settings saved.',password:'Password changed.',ai:'Claude settings saved.'}[name]);
 }catch(error){showStatus(error.message||errors[name],true);}finally{button.disabled=false;}}
toggle.addEventListener('click',()=>setMenu(menu.hidden));
menu.addEventListener('click',event=>{const btn=event.target.closest('button[data-account-panel]');if(btn)openPane(btn.dataset.accountPanel);});
byId('chatNav')?.addEventListener('click',event=>{const btn=event.target.closest('button[data-account-panel]');if(btn)openPane(btn.dataset.accountPanel);});
window.addEventListener('pizza-chat-ai-settings',()=>openPane('ai'));
document.addEventListener('click',event=>{if(!menu.hidden&&!menu.contains(event.target)&&!toggle.contains(event.target))setMenu(false);});
document.addEventListener('keydown',event=>{if(menu.hidden)return;if(event.key==='Escape'){event.preventDefault();setMenu(false);toggle.focus();}else if(event.key==='Tab')setMenu(false);else if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();const actions=[...menu.querySelectorAll('button')];const current=actions.indexOf(document.activeElement);const offset=event.key==='ArrowDown'?1:-1;actions[(current+offset+actions.length)%actions.length]?.focus();}});
for(const [name,pane] of Object.entries(panes))pane.addEventListener('submit',event=>save(event,name));
close.addEventListener('click',closePane);
dialog.addEventListener('close',()=>{status.textContent='';if(focusReturn?.isConnected)focusReturn.focus();});
dialog.addEventListener('click',event=>{if(event.target===dialog)closePane();});
panes.voice.elements.voice_rate.addEventListener('input',event=>{byId('chatRateValue').textContent=Number(event.target.value).toFixed(2)+'×';});
window.speechSynthesis?.addEventListener?.('voiceschanged',()=>{if(dialog.open&&!panes.voice.hidden)listVoices();});
window.addEventListener('pagehide',()=>setMenu(false));
})();
