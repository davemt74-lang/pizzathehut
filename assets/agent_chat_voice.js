/* Pizza Lab v1.5.16D: one voice controller for chat and user-requested kitchen narration. */
(()=>{'use strict';
 const mic=document.getElementById('chatMic'),camera=document.getElementById('chatCamera');
 const input=document.getElementById('question'),form=document.getElementById('coachForm'),status=document.getElementById('coachStatus');
 if(!mic||!camera||!input||!form||!status)return;
 let recognizer=null,active=false,voiceQuestion=false,speaking=false,utteranceActive=null;
 const SpeechRecognition=window.SpeechRecognition||window.webkitSpeechRecognition;
 function label(message){status.textContent=message;mic.setAttribute('aria-pressed',active?'true':'false');mic.setAttribute('aria-label',active?'Stop listening':speaking?'Interrupt voice playback':'Speak to the agent');mic.dataset.voiceState=active?'listening':speaking?'speaking':'idle';mic.classList.toggle('listening',active);}
 function stop(message='Microphone off · Press to speak'){
   const old=recognizer;recognizer=null;active=false;
   if(old){old.onresult=null;old.onend=null;old.onerror=null;old.onstart=null;try{old.abort();}catch{}}
   label(message);
 }
 function interruptSpeech(){
   utteranceActive=null;speaking=false;
   if(window.speechSynthesis)try{window.speechSynthesis.cancel();}catch{}
 }
 function speakText(raw){
  const text=String(raw||'').trim().slice(0,1100);
  if(!text)return false;
  if(document.visibilityState==='hidden'||!window.speechSynthesis||typeof SpeechSynthesisUtterance!=='function'){
   label('Spoken instructions unavailable. Read the saved step in chat.');return false;
  }
  if(active){label('Microphone is listening. Finish speaking before playing instructions.');return false;}
  try{
   interruptSpeech();
   const u=new SpeechSynthesisUtterance(text),settings=window.PIZZA_CHAT_VOICE||{};
   u.rate=Math.max(.6,Math.min(1.5,Number(settings.voice_rate)||.95));
   const preferred=(window.speechSynthesis.getVoices?.()||[]).find(v=>v.voiceURI===settings.voice_uri);
   if(preferred)u.voice=preferred;
   utteranceActive=u;speaking=true;label('Agent speaking · Tap mic to interrupt.');
   u.onend=()=>{if(utteranceActive!==u)return;utteranceActive=null;speaking=false;label('Ready · Tap mic to speak');};
   u.onerror=()=>{if(utteranceActive!==u)return;utteranceActive=null;speaking=false;label('Speech playback unavailable · Instructions remain in chat');};
   window.speechSynthesis.speak(u);
   return true;
  }catch{interruptSpeech();label('Speech playback unavailable · Instructions remain in chat');return false;}
 }
 function start(){
  if(!SpeechRecognition){label('Speech recognition is unavailable here; type a question instead.');return;}
  if(!window.isSecureContext){label('A secure HTTPS connection is needed for microphone access.');return;}
  if(active){stop();return;}
  if(document.visibilityState==='hidden'){label('Open the app to use the microphone.');return;}
  if(speaking)interruptSpeech();
  window.dispatchEvent(new Event('pizza-chat-mic-start'));
  const rec=new SpeechRecognition();recognizer=rec;active=true;voiceQuestion=false;
  rec.lang=window.PIZZA_CHAT_LANGUAGE||'en-US';rec.continuous=false;rec.interimResults=false;rec.maxAlternatives=1;
  rec.onstart=()=>{if(rec===recognizer)label('Listening · Ask about your dough or pizza…');};
  rec.onresult=e=>{
    if(rec!==recognizer)return;
    const text=Array.from(e.results).filter(r=>r.isFinal).map(r=>r[0].transcript).join(' ').trim();
    if(!text)return;
    const normalized=text.toLowerCase().replace(/[^a-z ]/g,' ').replace(/\s+/g,' ').trim();
    const wake=String(window.PIZZA_CHAT_WAKE||'pizza').toLowerCase().trim();
    const command=normalized.startsWith(wake+' ')?normalized.slice(wake.length+1):'';
    if(/^(take (a )?photo|capture (a )?photo|take (a )?picture)$/.test(command)){
      voiceQuestion=false;stop('Voice photo command received.');window.dispatchEvent(new Event('pizza-chat-photo-command'));return;
    }
    voiceQuestion=true;window.pizzaChatNextInputSource='voice_command';input.value=text;stop('Voice question submitted.');if(input.value)form.requestSubmit();
  };
  rec.onerror=e=>{if(rec!==recognizer)return;voiceQuestion=false;stop('Mic: '+(e.error||'unavailable')+'. You can still type.');};
  rec.onend=()=>{if(rec===recognizer)stop('Microphone off · Press to speak');};
  try{rec.start();}catch(e){voiceQuestion=false;stop('Unable to start microphone: '+e.message);}
 }
 mic.addEventListener('click',start);
 camera.addEventListener('click',()=>{if(active)stop();window.dispatchEvent(new Event('pizza-chat-camera-toggle'));});
 window.addEventListener('pizza-chat-local-command',()=>{voiceQuestion=false;});
 window.addEventListener('pizza-chat-reply-error',()=>{voiceQuestion=false;if(active)stop();label('Unable to answer. Tap mic or type to retry.');});
 window.addEventListener('pizza-chat-speak-request',e=>{voiceQuestion=false;speakText(e.detail?.text||'');});
 window.addEventListener('pizza-chat-reply',e=>{
   if(!voiceQuestion)return;voiceQuestion=false;
   speakText(e.detail?.text||'');
 });
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden'){if(active)stop('Mic paused. Return to Pizza Lab to speak again.');interruptSpeech();}});
 window.addEventListener('pagehide',()=>{stop();interruptSpeech();});
 window.addEventListener('pizza-chat-camera-open',interruptSpeech);
})();