'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync('assets/oven_layout.js','utf8');
const nodes=new Map(),events=new Map();
function element(key){
 if(!nodes.has(key))nodes.set(key,{
  value:'',innerHTML:'',textContent:'',dataset:{},
  addEventListener(name,handler){events.set(key+':'+name,handler);},
  setAttribute(name,value){this[name]=value;}
 });
 return nodes.get(key);
}
const svg=element('#ovenCanvas');
svg.createSVGPoint=()=>({x:0,y:0,matrixTransform(){return{x:this.x,y:this.y};}});
svg.getScreenCTM=()=>({inverse(){return{};}});
svg.setPointerCapture=id=>{svg.captured=id;};
svg.hasPointerCapture=id=>svg.captured===id;
svg.releasePointerCapture=()=>{svg.captured=null;};
svg.querySelector=()=>({focus(){svg.focused=true;}});
element('#ns').value='72';element('#ew').value='84';
element('#fireX').value='.78';element('#fireY').value='.48';
const ctx={window:{START_ZONES:[{key:'launch',name:'Launch',x:.5,y:.5,radius_in:12}]},
 document:{querySelector(key){return key.startsWith('#zoneRows [data-z=')?element('#radiusInput'):element(key);}},
 location:{href:''},alert(){throw Error('Unexpected alert');},Date,Math};
vm.createContext(ctx);vm.runInContext(source,ctx);
const zone=()=>vm.runInContext('zones[0]',ctx);
const shape=()=>vm.runInContext('zoneShape(zones[0],72,84)',ctx);
assert.match(element('#mapMarkers').innerHTML,/zone-resize-handle/,'resize handle is rendered');
assert.match(element('#mapMarkers').innerHTML,/aria-valuenow="12"/,'current inches exposed to assistive technology');
assert(Math.abs(shape().rx-12*550/84)<.00001,'physical radius is scaled by east-west oven width');
assert(Math.abs(shape().ry-12*404/72)<.00001,'physical radius is scaled by north-south oven depth');
const handle={dataset:{marker:'zone-resize',i:'0'}};
const fire={dataset:{marker:'fire'}};
const marker=target=>({closest:()=>target});
events.get('#ovenCanvas:pointerdown')({target:marker(handle),pointerType:'touch',pointerId:7,preventDefault(){}});
assert.equal(svg.captured,7,'resize captures pointer for uninterrupted drag');
events.get('#ovenCanvas:pointermove')({pointerId:7,clientX:330+20*550/84,clientY:240});
assert.equal(zone().radius_in,20,'dragging the orange handle updates saved physical radius');
assert.equal(element('#radiusInput').value,20,'radius input reflects handle drag');
assert.match(element('#zonesJson').value,/"radius_in":20/,'radius persists in submitted zone JSON');
events.get('#ovenCanvas:pointerup')({pointerId:7});
events.get('#ovenCanvas:pointermove')({pointerId:7,clientX:500,clientY:240});
assert.equal(zone().radius_in,20,'completed pointer gesture cannot continue resizing');
events.get('#ovenCanvas:pointerdown')({target:marker({dataset:{marker:'zone',i:'0'}}),pointerType:'mouse',button:0,pointerId:8,preventDefault(){}});
events.get('#ovenCanvas:pointermove')({pointerId:8,clientX:385,clientY:240});
assert.equal(zone().x,.6,'dragging the zone center still moves the node');
events.get('#ovenCanvas:pointerup')({pointerId:8});
events.get('#ovenCanvas:keydown')({target:marker(handle),key:'ArrowRight',preventDefault(){}});
assert.equal(zone().radius_in,20.5,'keyboard can resize a selected node');
assert(svg.focused,'keyboard focus returns to the replacement resize handle');
events.get('#ovenCanvas:pointerdown')({target:marker(fire),pointerType:'mouse',button:0,pointerId:9,preventDefault(){}});
events.get('#ovenCanvas:pointermove')({pointerId:9,clientX:440,clientY:240});
assert.equal(element('#fireX').value,.7,'fire position dragging still works');
console.log('PASS: proportional oven nodes, pointer resizing, keyboard resizing, saved inches and fire/zone movement');
