'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
const nodes={},listeners={},wlisteners={},played=[];
function node(id){return nodes[id]||(nodes[id]={value:'',textContent:'',attrs:{},dataset:{},classList:{toggle(){}},
  addEventListener(type,fn){this['on_'+type]=fn;},setAttribute(k,v){this.attrs[k]=v;}});}
node('coachForm').requestSubmit=()=>{};
class Recognition{constructor(){Recognition.instances.push(this);}start(){this.onstart?.();}abort(){this.aborted=true;}}
Recognition.instances=[];
class Utterance{constructor(text){this.text=text;}}
const synth={cancelCount:0,cancel(){this.cancelCount++;},speak(u){played.push(u);},getVoices(){return[];}};
const win={SpeechRecognition:Recognition,speechSynthesis:synth,isSecureContext:true,PIZZA_CHAT_VOICE:{voice_rate:.95},
   addEventListener(type,fn){wlisteners[type]=fn;},dispatchEvent(e){wlisteners[e.type]?.(e);}};
const doc={visibilityState:'visible',getElementById:node,addEventListener(type,fn){listeners[type]=fn}};
vm.runInNewContext(fs.readFileSync('assets/agent_chat_voice.js','utf8'),
 {window:win,document:doc,Event:class{constructor(type){this.type=type;}},SpeechSynthesisUtterance:Utterance});
function request(text){wlisteners['pizza-chat-speak-request']({detail:{text}});}
request('Mix flour and water.');
assert.equal(played.length,1,'First user-requested narration should play');
const old=played[0],stale=old.onend;
request('Now add the salt.');
assert.equal(played.length,2,'Second request should replace playback');
stale();assert.equal(node('chatMic').dataset.voiceState,'speaking','Old speech completion cannot overwrite new playback');
node('chatMic').on_click();assert.equal(node('chatMic').dataset.voiceState,'listening','Mic should interrupt speech');
request('Cannot speak over live microphone.');
assert.equal(played.length,2,'Do not narrate while mic is recording');
const rec=Recognition.instances[0];
doc.visibilityState='hidden';listeners.visibilitychange();
assert(rec.aborted,'Hidden microphone must stop');
request('Cannot speak while backgrounded.');
assert.equal(played.length,2,'No spoken instruction while page hidden');
doc.visibilityState='visible';node('chatMic').on_click();
const second=Recognition.instances[1],end=second.onend;
node('chatMic').on_click();assert(second.aborted,'Second mic tap should stop');
end();assert.equal(node('chatMic').dataset.voiceState,'idle','Stale recognizer end safely ignored');
request('Read step after restoring page.');
assert.equal(played.length,3,'Foreground narrated instructions recover after return');
wlisteners.pagehide();assert(synth.cancelCount>=2,'Unload must stop pending playback');
console.log('PASS: narrated instructions share voice controller, no mic overlap, foreground guards and stale callback recovery');
