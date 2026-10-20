'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const nodes={},listeners={};let permission, pendingUploadSignal;
const element=id=>nodes[id]||(nodes[id]={
 textContent:'',value:id==='#evidence'?'42':'',disabled:false,attrs:{},handlers:{},style:{},
 addEventListener(type,fn){this.handlers[type]=fn;},
 setAttribute(k,v){this.attrs[k]=v;},
 getBoundingClientRect(){return {left:0,top:0,width:100,height:100};},
 setPointerCapture(){}
});
const video=element('#camera');video.readyState=0;video.videoWidth=640;video.videoHeight=480;video.play=async()=>{};
const doc={visibilityState:'visible',querySelector:element,addEventListener(k,fn){listeners[k]=fn;},createElement(tag){
 if(tag==='canvas')return{width:0,height:0,getContext(){return{drawImage(){},getImageData(){return{data:new Uint8ClampedArray(160*120*4)}}};},toBlob(fn){fn({jpeg:true});}};
 return element('#'+tag);
}};
function makeStream(){
 const track={stopped:false,onended:null,stop(){this.stopped=true;}};
 return {track,getTracks(){return [track];},getVideoTracks(){return [track];}};
}
const win={PIZZA_TRACKER:{csrf:'fake',wake:'pizza',embedded:false},addEventListener(k,fn){listeners[k]=fn;},speechSynthesis:null,location:{origin:'https://pizza.example'}};
const navigator={mediaDevices:{getUserMedia:()=>new Promise(resolve=>{permission=resolve;})}};
const ctx={window:win,document:doc,navigator,console,Date,Math,Number,String,Array,Uint8ClampedArray,AbortController,DOMException,
 FormData:class{constructor(){this.data={}}append(k,v){this.data[k]=v;}set(k,v){this.data[k]=v;}},
 File:class{constructor(items,name,config){this.name=name;this.type=config.type;}},
 fetch:(url,opt)=>{pendingUploadSignal=opt.signal;return new Promise((resolve,reject)=>{opt.signal?.addEventListener('abort',()=>reject(new DOMException('cancelled','AbortError')));});},
 setInterval:()=>1,clearInterval:()=>{},setTimeout:()=>0};
vm.runInNewContext(fs.readFileSync('assets/pizza_tracker.js','utf8'),ctx);
const tick=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 // A late camera permission may not reactivate the already-closed tracker.
 element('#startCamera').handlers.click();element('#stopCamera').handlers.click();
 const old=makeStream();permission(old);await tick();
 assert(old.track.stopped,'late getUserMedia stream must be stopped');
 assert.equal(video.srcObject,null,'late permission must not reopen camera');
 // A physical camera disconnect should stop recording and offer manual restart.
 element('#startCamera').handlers.click();const live=makeStream();permission(live);await tick();
 assert.equal(video.srcObject,live,'camera should start after explicit tap');
 live.track.onended();assert(live.track.stopped,'lost camera track closes session');
 assert.match(element('#trackerStatus').textContent,/disconnected/i);
 // Abort an in-progress private upload when the camera closes.
 element('#startCamera').handlers.click();const capture=makeStream();permission(capture);await tick();
 video.readyState=2;element('#takePhoto').handlers.click();await tick();
 assert(pendingUploadSignal,'capture must provide an abort signal');
 element('#stopCamera').handlers.click();assert(pendingUploadSignal.aborted,'closing camera must abort in-flight fetch');
 await tick();assert.equal(element('#evidence').disabled,false,'capture selector unlocked after cancellation');
 console.log('PASS: delayed camera permission, camera loss, cancelled upload and UI recovery');
})().catch(e=>{console.error(e);process.exit(1);});
