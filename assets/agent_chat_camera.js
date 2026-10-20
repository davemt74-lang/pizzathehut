/* Same-origin in-chat camera transport. Camera stays inside Agent Chat. */
(()=>{'use strict';
const trigger=document.getElementById('chatCamera');
const panel=document.getElementById('chatCameraPanel');
const frame=document.getElementById('chatCameraFrame');
const closeButton=document.getElementById('chatCameraClose');
const status=document.getElementById('coachStatus');
const feed=document.getElementById('coachHistory');
if(!trigger||!panel||!frame||!closeButton||!feed)return;
const cards=new Map();
const setStatus=message=>{status.textContent=message;};
const say=(parent,tag,text)=>{const e=document.createElement(tag);e.textContent=String(text);parent.append(e);return e;};
function open(){
 if(!panel.hidden)return;
 panel.hidden=false;trigger.setAttribute('aria-expanded','true');
 const url=new URL('pizza_tracker.php',window.location.href);
 url.searchParams.set('embedded','1');
 frame.src=url.toString();
 closeButton.focus();
 setStatus('Camera opened in chat. Start tracking, then select a pizza test.');
}
function close(){
 if(panel.hidden)return;
 // Release camera and voice resources; pagehide also stops all media tracks.
 try{frame.contentWindow?.postMessage({channel:'pizza-lab:chat-control',type:'stop'},window.location.origin);}catch{}
 frame.removeAttribute('src');panel.hidden=true;
 trigger.setAttribute('aria-expanded','false');trigger.focus();
 setStatus('Camera closed. Pizza photos remain saved in the selected evidence record.');
}
function addPhoto(data){
 const media=Number(data.media_id),id=Number(data.evidence_id);
 if(!Number.isSafeInteger(media)||media<1||!Number.isSafeInteger(id)||id<1)return;
 if(cards.has(media))return;
 const card=document.createElement('article');card.className='coach-message';
 say(card,'small','Pizza #'+id+' · Camera evidence');
 const img=document.createElement('img');img.src='media.php?id='+media;img.alt='Captured pizza test #'+id;
 img.loading='lazy';img.style.cssText='max-width:min(100%,440px);height:auto;display:block;border-radius:12px;margin:10px 0';card.append(img);
 say(card,'p','Photo saved privately. Bounding box recorded with '+String(data.confidence||'unknown').slice(0,30)+' confidence.');
 feed.append(card);cards.set(media,card);card.scrollIntoView({block:'end',behavior:'smooth'});
 setStatus('Pizza photo saved. Waiting for visual analysis.');
}
function showAnalysis(data){
 const media=Number(data.media_id);const card=cards.get(media);if(!card)return;
 const isError=data.type==='analysis-error';
 say(card,'h3',isError?'Analysis unavailable':'Claude photo observation');
 say(card,'p',String(isError?data.error:data.summary||'No summary').slice(0,2500));
 if(!isError)say(card,'small','Visual confidence: '+String(data.confidence||'unknown').slice(0,30)+'. A photo cannot measure stone temperature or physical crispness.');
 setStatus(isError?'Photo saved. Claude analysis unavailable.':'Photo and Claude observation added to chat.');
}
window.addEventListener('message',event=>{
 if(event.origin!==window.location.origin||event.source!==frame.contentWindow)return;
 const data=event.data;
 if(!data||data.channel!=='pizza-lab:camera'||panel.hidden)return;
 if(data.type==='photo')addPhoto(data);
 else if(data.type==='analysis'||data.type==='analysis-error')showAnalysis(data);
});
window.addEventListener('pizza-chat-camera-toggle',()=>panel.hidden?open():close());
window.addEventListener('pizza-chat-photo-command',()=>{
 if(panel.hidden){setStatus('Open the camera, select a pizza test, and start tracking before saying “Pizza, take photo”.');return;}
 frame.contentWindow?.postMessage({channel:'pizza-lab:chat-control',type:'take-photo'},window.location.origin);
 setStatus('Voice photo command sent to the active camera.');
});
window.addEventListener('pizza-chat-mic-start',()=>{if(!panel.hidden)frame.contentWindow?.postMessage({channel:'pizza-lab:chat-control',type:'pause-voice'},window.location.origin);});
closeButton.addEventListener('click',close);
window.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden)close();});
window.addEventListener('pagehide',()=>{if(!panel.hidden)close();});
})();
