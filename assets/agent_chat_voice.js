/* Pizza Lab v1.5.16D: one voice controller for chat and user-requested kitchen narration. */
(()=>{'use strict';
 const mic=document.getElementById('chatMic'),camera=document.getElementById('chatCamera');
 const input=document.getElementById('question'),form=document.getElementById('coachForm'),status=document.getElementById('coachStatus');
 if(!mic||!camera||!input||!form||!status)return;
 let recognizer=null,active=false,wantsListen=false,busy=false,voiceQuestion=false,speaking=false,utteranceActive=null,restartTimer=null;
 const SpeechRecognition=window.SpeechRecognition||window.webkitSpeechRecognition;
 function label(message){status.textContent=message;mic.setAttribute('aria-pressed',wantsListen?'true':'false');mic.setAttribute('aria-label',wantsListen?'Stop listening':speaking?'Interrupt voice playback':'Start listening');mic.dataset.voiceState=speaking?'speaking':wantsListen?'listening':'idle';mic.classList.toggle('listening',wantsListen);}
 function pauseRecognizer(){
   const old=recognizer;recognizer=null;active=false;
   if(old){old.onresult=null;old.onend=null;old.onerror=null;old.onstart=null;try{old.abort();}catch{}}
 }
 function stop(message='Microphone off · Tap to listen'){
   wantsListen=false;busy=false;clearTimeout(restartTimer);restartTimer=null;
   pauseRecognizer();voiceQuestion=false;
   label(message);
 }
 function interruptSpeech(){
   utteranceActive=null;speaking=false;
   if(window.speechSynthesis)try{window.speechSynthesis.cancel();}catch{}
 }
 function resumeSoon(delay=250){
  clearTimeout(restartTimer);restartTimer=null;
  if(!wantsListen||active||busy||speaking||document.visibilityState==='hidden')return;
  restartTimer=setTimeout(()=>{restartTimer=null;arm();},delay);
 }
 function speakText(raw){
  const text=String(raw||'').trim().slice(0,1100);
  if(!text)return false;
  if(document.visibilityState==='hidden'||!window.speechSynthesis||typeof SpeechSynthesisUtterance!=='function'){
   label('Spoken instructions unavailable. Read the saved step in chat.');return false;
  }
  try{
   pauseRecognizer();clearTimeout(restartTimer);restartTimer=null;
   interruptSpeech();
   const u=new SpeechSynthesisUtterance(text),settings=window.PIZZA_CHAT_VOICE||{};
   u.rate=Math.max(.6,Math.min(1.5,Number(settings.voice_rate)||.95));
   const preferred=(window.speechSynthesis.getVoices?.()||[]).find(v=>v.voiceURI===settings.voice_uri);
   if(preferred)u.voice=preferred;
   utteranceActive=u;speaking=true;label('Agent speaking · Tap mic to interrupt.');
   u.onend=()=>{if(utteranceActive!==u)return;utteranceActive=null;speaking=false;label(wantsListen?'Listening · Speak when ready':'Ready · Tap mic to listen');resumeSoon();};
   u.onerror=()=>{if(utteranceActive!==u)return;utteranceActive=null;speaking=false;label('Speech playback unavailable · Instructions remain in chat');resumeSoon();};
   window.speechSynthesis.speak(u);
   return true;
  }catch{interruptSpeech();label('Speech playback unavailable · Instructions remain in chat');resumeSoon();return false;}
 }
 function arm(){
  if(!wantsListen||busy||speaking||active||document.visibilityState==='hidden')return;
  const rec=new SpeechRecognition();recognizer=rec;active=true;
  rec.lang=window.PIZZA_CHAT_LANGUAGE||'en-US';rec.continuous=false;rec.interimResults=false;rec.maxAlternatives=1;
  rec.onstart=()=>{if(rec===recognizer)label('Listening · Speak naturally; your message sends automatically.');};
  rec.onresult=e=>{
    if(rec!==recognizer)return;
    const text=Array.from(e.results).filter(r=>r.isFinal).map(r=>r[0].transcript).join(' ').trim();
    if(!text)return;
    const normalized=text.toLowerCase().replace(/[^a-z ]/g,' ').replace(/\s+/g,' ').trim();
    const wake=String(window.PIZZA_CHAT_WAKE||'pizza').toLowerCase().trim();
    const command=normalized.startsWith(wake+' ')?normalized.slice(wake.length+1):'';
    if(/^(take (a )?photo|capture (a )?photo|take (a )?picture)$/.test(command)){
      pauseRecognizer();voiceQuestion=false;label('Voice photo command received.');window.dispatchEvent(new Event('pizza-chat-photo-command'));resumeSoon();return;
    }
    pauseRecognizer();voiceQuestion=true;busy=true;
    window.pizzaChatNextInputSource='voice_command';input.value=text;
    label('Processing your spoken message…');form.requestSubmit();
  };
  rec.onerror=e=>{
    if(rec!==recognizer)return;
    pauseRecognizer();
    const reason=e.error||'unavailable';
    if(['not-allowed','service-not-allowed','audio-capture'].includes(reason)){stop('Mic: '+reason+'. Check microphone permissions.');return;}
    label('Mic: '+reason+'. Retrying listening…');resumeSoon(900);
  };
  rec.onend=()=>{if(rec!==recognizer)return;pauseRecognizer();resumeSoon(350);};
  try{rec.start();}catch(e){pauseRecognizer();stop('Unable to start microphone: '+e.message);}
 }
 function start(){
  if(speaking){interruptSpeech();if(wantsListen){label('Listening · Speak when ready');resumeSoon(0);return;}}
  if(wantsListen){stop();interruptSpeech();return;}
  if(!SpeechRecognition){label('Speech recognition is unavailable here; type a question instead.');return;}
  if(!window.isSecureContext){label('A secure HTTPS connection is needed for microphone access.');return;}
  if(document.visibilityState==='hidden'){label('Open the app to use the microphone.');return;}
  if(speaking)interruptSpeech();
  window.dispatchEvent(new Event('pizza-chat-mic-start'));
  wantsListen=true;voiceQuestion=false;label('Starting listening mode…');arm();
 }
 mic.addEventListener('click',start);
 camera.addEventListener('click',()=>{if(wantsListen)stop();window.dispatchEvent(new Event('pizza-chat-camera-toggle'));});
 window.addEventListener('pizza-chat-local-command',()=>{voiceQuestion=false;});
 window.addEventListener('pizza-chat-response-start',()=>{busy=true;pauseRecognizer();});
 window.addEventListener('pizza-chat-response-complete',()=>{busy=false;resumeSoon();});
 window.addEventListener('pizza-chat-reply-error',()=>{voiceQuestion=false;label('Unable to answer. Listening can continue.');});
 window.addEventListener('pizza-chat-speak-request',e=>{voiceQuestion=false;speakText(e.detail?.text||'');});
 window.addEventListener('pizza-chat-reply',e=>{
   if(!voiceQuestion)return;voiceQuestion=false;
   if(!speakText(e.detail?.text||''))resumeSoon();
 });
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden'){if(wantsListen)stop('Mic paused. Tap to resume when you return.');interruptSpeech();}});
 window.addEventListener('pagehide',()=>{stop();interruptSpeech();});
 window.addEventListener('pizza-chat-camera-open',interruptSpeech);
})();
