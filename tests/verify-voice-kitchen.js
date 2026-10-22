'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
const nodes={},listeners={},wlisteners={},played=[],timers=new Map();let nextTimer=1,submits=0;
function node(id){return nodes[id]||(nodes[id]={value:'',textContent:'',attrs:{},dataset:{},classList:{toggle(){}},
  addEventListener(type,fn){this['on_'+type]=fn;},setAttribute(k,v){this.attrs[k]=v;}});}
node('coachForm').requestSubmit=()=>{submits++;};
class Recognition{constructor(){Recognition.instances.push(this);}start(){this.onstart?.();}abort(){this.aborted=true;}}
Recognition.instances=[];
class Utterance{constructor(text){this.text=text;}}
const synth={cancelCount:0,cancel(){this.cancelCount++;},speak(u){played.push(u);},getVoices(){return[];}};
const win={SpeechRecognition:Recognition,speechSynthesis:synth,isSecureContext:true,PIZZA_CHAT_VOICE:{voice_rate:.95},
   addEventListener(type,fn){wlisteners[type]=fn;},dispatchEvent(e){wlisteners[e.type]?.(e);}};
const doc={visibilityState:'visible',getElementById:node,addEventListener(type,fn){listeners[type]=fn}};
const setTimer=fn=>{const id=nextTimer++;timers.set(id,fn);return id;};
const clearTimer=id=>timers.delete(id);
function tick(){const due=[...timers.values()];timers.clear();for(const fn of due)fn();}
vm.runInNewContext(fs.readFileSync('assets/agent_chat_voice.js','utf8'),
 {window:win,document:doc,setTimeout:setTimer,clearTimeout:clearTimer,Event:class{constructor(type){this.type=type;}},SpeechSynthesisUtterance:Utterance});
function request(text){wlisteners['pizza-chat-speak-request']({detail:{text}});}
request('Mix flour and water.');
assert.equal(played.length,1,'First user-requested narration should play');
const old=played[0],stale=old.onend;
request('Now add the salt.');
assert.equal(played.length,2,'Second request should replace playback');
stale();assert.equal(node('chatMic').dataset.voiceState,'speaking','Old speech completion cannot overwrite new playback');
node('chatMic').on_click();assert.equal(node('chatMic').dataset.voiceState,'listening','Mic should interrupt speech');
const rec=Recognition.instances[0];
rec.onresult({results:[{isFinal:true,0:{transcript:'Start timer'}}]});
assert.equal(submits,1,'Final voice transcript auto-submits without pressing Send');
assert.equal(node('question').value,'Start timer','Recognized command reaches chat input');
assert.equal(node('chatMic').dataset.voiceState,'listening','Listening mode persists while processing');
assert(rec.aborted,'Recognition pauses while command runs');
wlisteners['pizza-chat-response-start']();wlisteners['pizza-chat-local-command']();wlisteners['pizza-chat-response-complete']();tick();
assert.equal(Recognition.instances.length,2,'Listening resumes automatically after local command');
request('Read the next dough step.');
assert.equal(played.length,3,'Spoken instructions temporarily pause listening');
assert(Recognition.instances[1].aborted,'Listening pauses to avoid hearing its own narration');
played[2].onend();tick();
assert.equal(Recognition.instances.length,3,'Listening resumes after narration finishes');
doc.visibilityState='hidden';listeners.visibilitychange();
assert(Recognition.instances[2].aborted,'Hidden microphone must stop');
assert.equal(node('chatMic').dataset.voiceState,'idle','Hidden page cannot keep listening active');
request('Cannot speak while backgrounded.');
assert.equal(played.length,3,'No spoken instruction while page hidden');
doc.visibilityState='visible';node('chatMic').on_click();
const fourth=Recognition.instances[3],end=fourth.onend;
node('chatMic').on_click();assert(fourth.aborted,'Second mic tap should stop persistent listening');
end();tick();assert.equal(node('chatMic').dataset.voiceState,'idle','Stale recognizer end safely ignored');
request('Read step after restoring page.');
assert.equal(played.length,4,'Foreground narrated instructions recover after return');
wlisteners.pagehide();assert(synth.cancelCount>=2,'Unload must stop pending playback');
console.log('PASS: auto-submitting persistent listening, narrated instructions, mic interruption, foreground guards and stale callback recovery');
