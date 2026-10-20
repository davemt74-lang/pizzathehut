'use strict';
const $=s=>document.querySelector(s), status=s=>{$('#trackerStatus').textContent=s;}, esc=s=>String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let stream=null,voice=null,voiceEnabled=false,working=false,detectorTimer=null,suppressUntil=0,cameraEpoch=0,captureController=null;
let box={x:.26,y:.25,w:.48,h:.48,source:'manual',confidence:'low'};
const video=$('#camera');
function notifyChat(payload){
 if(!window.PIZZA_TRACKER.embedded||window.parent===window)return;
 window.parent.postMessage({channel:'pizza-lab:camera',...payload},window.location.origin);
}
// Only trusted same-origin chat may request capture or shutdown; no unattended photos.
window.addEventListener('message',e=>{
 if(!window.PIZZA_TRACKER.embedded||e.source!==window.parent||e.origin!==window.location.origin)return;
 if(e.data?.channel!=='pizza-lab:chat-control')return;
 if(e.data.type==='take-photo')takePhoto('voice_command');
 else if(e.data.type==='stop')stop();
 else if(e.data.type==='pause-voice')stopVoice();
});
function setBox(b){box={...box,...b};const target=$('#target');target.setAttribute('x',box.x*1000);target.setAttribute('y',box.y*1000);target.setAttribute('width',box.w*1000);target.setAttribute('height',box.h*1000);target.setAttribute('stroke',box.source==='local'?'#c8f1a5':'#ffd39e');const bw=$('#boxWidth'),bh=$('#boxHeight');if(bw&&document.activeElement!==bw)bw.value=Math.round(box.w*100);if(bh&&document.activeElement!==bh)bh.value=Math.round(box.h*100);}
function heard(msg){status(msg);}
function announce(msg){if(window.speechSynthesis){suppressUntil=Date.now()+5000;window.speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(msg);u.rate=.95;window.speechSynthesis.speak(u);}}
// Low-cost color/edge candidate detection: deliberately unverified. A human may adjust the box.
function estimatePizza(){if(!stream||video.readyState<2)return null;const c=document.createElement('canvas');c.width=160;c.height=120;const ctx=c.getContext('2d',{willReadFrequently:true});ctx.drawImage(video,0,0,160,120);const data=ctx.getImageData(0,0,160,120).data;let minX=160,minY=120,maxX=0,maxY=0,n=0;
for(let y=10;y<110;y+=2)for(let x=8;x<152;x+=2){const i=(y*160+x)*4,r=data[i],g=data[i+1],b=data[i+2],mx=Math.max(r,g,b),mn=Math.min(r,g,b);if(r>80 && r>g*1.06 && g>b*.96 && g>40 && mx-mn>24){minX=Math.min(minX,x);minY=Math.min(minY,y);maxX=Math.max(maxX,x);maxY=Math.max(maxY,y);n++;}}
if(n<190 || maxX-minX<24 || maxY-minY<20)return null;const w=Math.min(1,(maxX-minX+12)/160),h=Math.min(1,(maxY-minY+12)/120);if(w/h>2.8||h/w>2.8)return null;const x=Math.max(0,(minX-6)/160),y=Math.max(0,(minY-6)/120);return{x,y,w:Math.min(w,1-x),h:Math.min(h,1-y),source:'local',confidence:'low'};}
function detect(){const found=estimatePizza();if(found){setBox(found);status('Possible pizza located (low-confidence color estimate). Drag the box if needed.');}else status('No reliable pizza candidate. Move closer or drag the guide box onto the pizza.');}
async function start(){
 if(stream)return;
 if(!navigator.mediaDevices?.getUserMedia)throw Error('Camera requires HTTPS and permissions.');
 const epoch=++cameraEpoch;
 let pending=null;
 try {
   pending=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});
   // Ignore a late permission grant after the chef closed the camera.
   if(epoch!==cameraEpoch){pending.getTracks().forEach(t=>t.stop());return;}
   stream=pending;video.srcObject=stream;
   for(const track of stream.getVideoTracks())track.onended=()=>{if(stream===pending){stop();status('Camera disconnected. Tap Start pizza tracker to reconnect.');}};
   await video.play();
   if(epoch!==cameraEpoch)return;
   $('#indicator').textContent='● Camera tracking';detect();
   detectorTimer=setInterval(()=>{if(document.visibilityState==='visible'&&box.source==='local')detect();},3500);
   status('Tracker active. Say “Pizza, take photo” after enabling the microphone.');
 } catch(e) {if(pending&&stream!==pending)pending.getTracks().forEach(t=>t.stop());if(epoch===cameraEpoch)stop();throw e;}
}
function stop(){
 cameraEpoch++;
 captureController?.abort();
 clearInterval(detectorTimer);detectorTimer=null;
 if(stream){stream.getTracks().forEach(t=>{t.onended=null;t.stop();});stream=null;}
 video.srcObject=null;stopVoice();$('#indicator').textContent='Camera off';status('Tracker stopped.');
}
async function api(action,body,signal){const opts={method:'POST',headers:{'X-CSRF-Token':window.PIZZA_TRACKER.csrf},signal};if(body instanceof FormData){body.set('action',action);opts.body=body;}else{opts.headers['Content-Type']='application/json';opts.body=JSON.stringify({action,...body});}const res=await fetch('api.php',opts);const json=await res.json();if(!res.ok||!json.ok)throw Error(json.error||'Request failed');return json;}
async function recordTrackerActivity(type,source,id,options={}){
 const client_event_id=window.crypto?.randomUUID?.()||('pizzalab_'+Date.now()+'_'+Math.random().toString(36).slice(2)+Math.random().toString(36).slice(2));
 return api('cookActivity',{evidence_id:id,event_type:type,source,client_event_id,...options});
}
async function takePhoto(trigger='manual'){if(working)return;if(!stream||video.readyState<2){heard('Start the pizza tracker before capturing.');return;}const id=+$('#evidence').value;if(!id){heard('Choose an active pizza test before taking a photo.');return;}working=true;const controller=new AbortController();captureController=controller;$('#evidence').disabled=true;$('#takePhoto').disabled=true;$('#captureStatus').textContent='Capturing...';try{
 const canvas=document.createElement('canvas');canvas.width=Math.min(1280,video.videoWidth);canvas.height=Math.round(canvas.width*video.videoHeight/video.videoWidth);canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);
 const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',.82));if(!blob)throw Error('Camera frame capture failed.');if(controller.signal.aborted)throw new DOMException('Camera closed','AbortError');
 const fd=new FormData();fd.append('evidence_id',id);fd.append('kind','scan');fd.append('file',new File([blob],`pizza-tracker-${Date.now()}.jpg`,{type:'image/jpeg'}));const upload=await api('upload',fd,controller.signal);
 await api('saveTrackerBox',{media_id:upload.media_id,evidence_id:id,x:box.x,y:box.y,width:box.w,height:box.h,source:box.source,confidence:box.confidence},controller.signal);
 try{await recordTrackerActivity('photo_captured',trigger==='voice_command'?'voice_command':'camera',id,{media_id:upload.media_id});}catch(e){status('Photo saved but activity log failed: '+e.message);}
 $('#lastPhoto').innerHTML=`<img alt="Captured pizza evidence" src="media.php?id=${upload.media_id}" style="max-width:100%;border-radius:12px">`;
 $('#captureStatus').textContent=`Photo saved to pizza #${id} (box: ${box.source}, ${box.confidence} confidence).`;
 notifyChat({type:'photo',media_id:upload.media_id,evidence_id:id,source:trigger==='voice_command'?'voice_command':'camera',confidence:box.confidence});
 announce('Pizza photo captured. Analysis starting.');$('#analysis').textContent='Analyzing with Claude…';
 try{const a=await api('analyze',{evidence_id:id,kind:'live',media_id:upload.media_id},controller.signal);let result=a.analysis||{};try{await recordTrackerActivity('analysis_complete','system',id);if(result.turn_now)await recordTrackerActivity('visual_turn_suggestion','ai_suggestion',id);}catch(e){status('Analysis saved but activity log failed: '+e.message);}$('#analysis').innerHTML=`<h3>Claude observation</h3><p>${esc(result.summary||'No summary')}</p><p class="small">Confidence: ${esc(result.confidence||'unknown')}</p><ul>${(result.suggestions||[]).map(x=>`<li>${esc(x)}</li>`).join('')}</ul>`;notifyChat({type:'analysis',media_id:upload.media_id,evidence_id:id,summary:String(result.summary||''),confidence:String(result.confidence||'unknown')});announce('Pizza analysis complete.');}
 catch(e){$('#analysis').textContent='Photo saved. Analysis not completed: '+e.message;notifyChat({type:'analysis-error',media_id:upload.media_id,evidence_id:id,error:String(e.message||'Unavailable')});announce('Photo saved. Analysis unavailable.');}
 }catch(e){const cancelled=e?.name==='AbortError'||controller.signal.aborted;$('#captureStatus').textContent=cancelled?'Capture cancelled. If upload already completed, review this pizza’s saved photos before retrying.':'Capture failed: '+e.message;if(!cancelled){heard(e.message);notifyChat({type:'capture-error',error:String(e.message||'Capture unavailable').slice(0,320)});announce('Pizza photo could not be saved.');}}finally{if(captureController===controller)captureController=null;working=false;$('#evidence').disabled=false;$('#takePhoto').disabled=false;}}
function interpretVoice(transcript){const v=String(transcript||'').toLowerCase().replace(/[^a-z ]/g,' ').replace(/\s+/g,' ').trim();if(!stream||Date.now()<suppressUntil)return;const wake=String(window.PIZZA_TRACKER.wake||'pizza').toLowerCase().trim();if(v.startsWith(wake+' ') && /^(take (a )?photo|capture (a )?photo|take (a )?picture)$/.test(v.slice(wake.length).trim())){takePhoto('voice_command');}}
function startVoice(){if(!stream){heard('Start the pizza tracker first.');return;}const Recognition=window.SpeechRecognition||window.webkitSpeechRecognition;if(!Recognition){heard('Voice recognition unavailable; use Take photo + analyze.');return;}if(voiceEnabled)return;voiceEnabled=true;voice=new Recognition();voice.lang='en-US';voice.continuous=true;voice.interimResults=false;voice.onresult=e=>{for(let i=e.resultIndex;i<e.results.length;i++)if(e.results[i].isFinal)interpretVoice(e.results[i][0].transcript);};voice.onend=()=>{if(voiceEnabled){voiceEnabled=false;heard('Microphone stopped. Tap Enable voice commands to listen again.');}};voice.onerror=e=>{if(['not-allowed','service-not-allowed','audio-capture'].includes(e.error)){voiceEnabled=false;heard('Microphone denied or unavailable.');}};try{voice.start();heard('Listening for “Pizza, take photo”.');}catch(e){voiceEnabled=false;heard('Cannot enable microphone: '+e.message);}}
function stopVoice(){voiceEnabled=false;try{voice?.abort();}catch{}voice=null;}
let dragging=false,offset={x:0,y:0};$('#boxWidth').addEventListener('input',e=>{const w=+e.target.value/100;setBox({w,x:Math.min(box.x,1-w),source:'manual',confidence:'low'});});$('#boxHeight').addEventListener('input',e=>{const h=+e.target.value/100;setBox({h,y:Math.min(box.y,1-h),source:'manual',confidence:'low'});});$('#overlay').addEventListener('pointerdown',e=>{const r=$('#overlay').getBoundingClientRect();const x=(e.clientX-r.left)/r.width,y=(e.clientY-r.top)/r.height;dragging=true;offset={x:x-box.x,y:y-box.y};$('#overlay').setPointerCapture(e.pointerId);});$('#overlay').addEventListener('pointermove',e=>{if(!dragging)return;const r=$('#overlay').getBoundingClientRect(),x=(e.clientX-r.left)/r.width,y=(e.clientY-r.top)/r.height;setBox({x:Math.max(0,Math.min(1-box.w,x-offset.x)),y:Math.max(0,Math.min(1-box.h,y-offset.y)),source:'manual',confidence:'low'});});$('#overlay').addEventListener('pointerup',()=>dragging=false);$('#overlay').addEventListener('pointercancel',()=>dragging=false);
$('#startCamera').addEventListener('click',()=>start().catch(e=>heard(e.message)));$('#stopCamera').addEventListener('click',stop);$('#detect').addEventListener('click',detect);$('#takePhoto').addEventListener('click',takePhoto);$('#voiceOn').addEventListener('click',startVoice);$('#voiceOff').addEventListener('click',stopVoice);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden'&&stream){stop();status('Camera paused while Pizza Lab is in the background. Tap Start to resume.');}});window.addEventListener('pagehide',stop);setBox(box);
