/* Pizza Lab chat footer. Push-to-talk, not continuous speaker recognition. */
(()=>{'use strict';
 const mic=document.getElementById('chatMic'),camera=document.getElementById('chatCamera');
 const input=document.getElementById('question'),form=document.getElementById('coachForm');
 const status=document.getElementById('coachStatus');
 if(!mic||!camera||!input||!form)return;
 let recognizer=null,active=false,voiceQuestion=false,speaking=false;
 const SpeechRecognition=window.SpeechRecognition||window.webkitSpeechRecognition;
 function label(message){status.textContent=message;mic.setAttribute('aria-pressed',active?'true':'false');mic.classList.toggle('listening',active);}
 function stop(){active=false;if(recognizer){try{recognizer.abort();}catch{}recognizer=null;}label('Microphone off · Press to speak');}
 function start(){
  if(!SpeechRecognition){label('Speech recognition is unavailable here; type a question instead.');return;}
  if(!window.isSecureContext){label('A secure HTTPS connection is needed for microphone access.');return;}
  if(active){stop();return;}
  if(speaking&&window.speechSynthesis){window.speechSynthesis.cancel();speaking=false;}
  window.dispatchEvent(new Event('pizza-chat-mic-start'));
  const rec=new SpeechRecognition();recognizer=rec;active=true;voiceQuestion=false;
  rec.lang=window.PIZZA_CHAT_LANGUAGE||'en-US';rec.continuous=false;rec.interimResults=false;rec.maxAlternatives=1;
  rec.onstart=()=>label('Listening · Ask about your dough or pizza…');
  rec.onresult=e=>{
    const text=Array.from(e.results).filter(r=>r.isFinal).map(r=>r[0].transcript).join(' ').trim();
    if(!text)return;
    const normalized=text.toLowerCase().replace(/[^a-z ]/g,' ').replace(/\s+/g,' ').trim();
    const wake=String(window.PIZZA_CHAT_WAKE||'pizza').toLowerCase().trim();
    const command=normalized.startsWith(wake+' ')?normalized.slice(wake.length+1):'';
    if(/^(take (a )?photo|capture (a )?photo|take (a )?picture)$/.test(command)){
      voiceQuestion=false;stop();window.dispatchEvent(new Event('pizza-chat-photo-command'));return;
    }
    voiceQuestion=true;input.value=text;stop();if(input.value)form.requestSubmit();
  };
  rec.onerror=e=>{voiceQuestion=false;label('Mic: '+(e.error||'unavailable')+'. You can still type.');active=false;};
  rec.onend=()=>{if(active){active=false;label('Microphone off · Press to speak');}};
  try{rec.start();}catch(e){active=false;label('Unable to start microphone: '+e.message);}
 }
 mic.addEventListener('click',start);
 camera.addEventListener('click',()=>{stop();window.dispatchEvent(new Event('pizza-chat-camera-toggle'));});
 window.addEventListener('pizza-chat-local-command',()=>{voiceQuestion=false;});
 window.addEventListener('pizza-chat-reply',e=>{
   if(!voiceQuestion)return;voiceQuestion=false;
   const text=String(e.detail?.text||'').trim().slice(0,1100);if(!text)return;
   if(!window.speechSynthesis){label('Reply saved · Speech playback unavailable');return;}
   try{
     window.speechSynthesis.cancel();
     const utterance=new SpeechSynthesisUtterance(text);
     const settings=window.PIZZA_CHAT_VOICE||{};utterance.rate=Math.max(.6,Math.min(1.5,Number(settings.voice_rate)||.95));
     const preferred=(window.speechSynthesis.getVoices?.()||[]).find(v=>v.voiceURI===settings.voice_uri);if(preferred)utterance.voice=preferred;
     speaking=true;utterance.onend=()=>{speaking=false;label('Ready · Press mic to ask again');};
     utterance.onerror=()=>{speaking=false;label('Reply saved · Playback unavailable');};
     window.speechSynthesis.speak(utterance);
   }catch{speaking=false;label('Reply saved · Playback unavailable');}
 });
 window.addEventListener('pagehide',stop);
})();