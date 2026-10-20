'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert');
const listeners={},wlisteners={},buttons={};
function node(id){return buttons[id]||(buttons[id]={value:'',textContent:'',dataset:{},attrs:{},listeners:{},classList:{toggle(){}},setAttribute(k,v){this.attrs[k]=v;},addEventListener(k,fn){this.listeners[k]=fn;}});}
let submitted=0;
node('coachForm').requestSubmit=()=>{submitted++;};
class Rec{
 constructor(){this.aborted=false;Rec.instances.push(this);}
 start(){this.onstart?.();}
 abort(){this.aborted=true;}
}
Rec.instances=[];
const doc={visibilityState:'visible',getElementById:node,addEventListener(k,fn){listeners[k]=fn;}};
const win={SpeechRecognition:Rec,isSecureContext:true,PIZZA_CHAT_WAKE:'pizza',addEventListener(k,fn){wlisteners[k]=fn;},dispatchEvent(e){wlisteners[e.type]?.(e);}};
const globals={window:win,document:doc,Event:class{constructor(type){this.type=type;}},SpeechSynthesisUtterance:class{constructor(text){this.text=text;}},Number,String,Array};
vm.runInNewContext(fs.readFileSync('assets/agent_chat_voice.js','utf8'),globals);
node('chatMic').listeners.click();assert.equal(Rec.instances.length,1);
const first=Rec.instances[0];const oldEnd=first.onend;
assert.equal(node('chatMic').attrs['aria-pressed'],'true');
first.onresult({results:[{isFinal:true,0:{transcript:'How is the dough today?'}}]});
assert.equal(submitted,1);assert.equal(node('chatMic').attrs['aria-pressed'],'false');
node('chatMic').listeners.click();
const second=Rec.instances[1];oldEnd();assert.equal(node('chatMic').attrs['aria-pressed'],'true','stale onend cancelled newer recognizer');
doc.visibilityState='hidden';listeners.visibilitychange();assert(second.aborted,'hidden-page microphone stopped');
assert.equal(node('chatMic').attrs['aria-pressed'],'false');
doc.visibilityState='visible';node('chatMic').listeners.click();const third=Rec.instances[2];
third.onerror({error:'not-allowed'});assert.equal(node('chatMic').attrs['aria-pressed'],'false');
node('chatMic').listeners.click();const fourth=Rec.instances[3];
fourth.onresult({results:[{isFinal:true,0:{transcript:'Pizza take photo'}}]});
assert.equal(submitted,1,'camera voice command does not submit a question');
console.log('PASS: stale recognizers, page hiding, mic permission errors and photo voice routing');
