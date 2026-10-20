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
  const rec=new SpeechRecognition();recognizer=rec;active=true;voiceQuestion=false;
  rec.lang='en-US';rec.continuous=false;rec.interimResults=false;rec.maxAlternatives=1;
  rec.onstart=()=>label('Listening · Ask about your dough or pizza…');
  rec.onresult=e=>{
    const text=Array.from(e.results).filter(r=>r.isFinal).map(r=>r[0].transcript).join(' ').trim();
    if(!text)return;
    voiceQuestion=true;input.value=text;stop();if(input.value)form.requestSubmit();
  };
  rec.onerror=e=>{voiceQuestion=false;label('Mic: '+(e.error||'unavailable')+'. You can still type.');active=false;};
  rec.onend=()=>{if(active){active=false;label('Microphone off · Press to speak');}};
  try{rec.start();}catch(e){active=false;label('Unable to start microphone: '+e.message);}
 }
 mic.addEventListener('click',start);
 camera.addEventListener('click',()=>{stop();window.location.assign('pizza_tracker.php');});
 window.addEventListener('pizza-chat-reply',e=>{
   if(!voiceQuestion)return;voiceQuestion=false;
   const text=String(e.detail?.text||'').trim().slice(0,1100);if(!text)return;
   if(!window.speechSynthesis){label('Reply saved · Speech playback unavailable');return;}
   try{
     window.speechSynthesis.cancel();
     const utterance=new SpeechSynthesisUtterance(text);utterance.rate=.95;
     speaking=true;utterance.onend=()=>{speaking=false;label('Ready · Press mic to ask again');};
     utterance.onerror=()=>{speaking=false;label('Reply saved · Playback unavailable');};
     window.speechSynthesis.speak(utterance);
   }catch{speaking=false;label('Reply saved · Playback unavailable');}
 });
 window.addEventListener('pagehide',stop);
})();