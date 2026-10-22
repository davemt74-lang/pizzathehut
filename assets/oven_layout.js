'use strict';
const $=s=>document.querySelector(s), esc=x=>String(x).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let zones=Array.isArray(window.START_ZONES)?window.START_ZONES.slice():[], drag=null;
const validPoint=(x,y)=>(4*(x-.5)**2+4*(y-.5)**2)<=1;
const px=x=>55+x*550, py=y=>38+y*404;
const clampRadius=value=>Math.max(1,Math.min(200,Math.round(value*2)/2));
// Zones store real inches. Horizontal and vertical display scales differ on the floor diagram.
const zoneShape=(zone,ns,ew)=>({cx:px(zone.x),cy:py(zone.y),rx:Number(zone.radius_in)*550/ew,ry:Number(zone.radius_in)*404/ns});
const zoneRadiusAtPoint=(zone,point,ns,ew)=>clampRadius(Math.hypot((point.x-px(zone.x))*ew/550,(point.y-py(zone.y))*ns/404));
function updateRows(){let holder=$('#zoneRows');holder.innerHTML=zones.map((z,i)=>`<div class="zone-row"><label>Name<input data-z="${i}" data-key="name" maxlength="80" value="${esc(z.name)}"></label><label>Radius in<input data-z="${i}" data-key="radius_in" type="number" min="1" max="200" step=".5" value="${z.radius_in}"></label><label>Key<input value="${esc(z.key)}" readonly></label><button type="button" data-remove="${i}" aria-label="Remove zone">✕</button></div>`).join('');$('#zonesJson').value=JSON.stringify(zones);}
function draw(){
 const ns=Number($('#ns').value),ew=Number($('#ew').value),validSize=ns>=12&&ew>=12;
 if(validSize){$('#floorStats').textContent=`Largest centered circle: ${Math.min(ns,ew).toFixed(1)} in diameter · Approx. elliptical floor: ${(Math.PI*ns*ew/4).toFixed(0)} sq in`;const min=Math.min(ns,ew);$('#innerCircle').setAttribute('rx',275*min/ew);$('#innerCircle').setAttribute('ry',202*min/ns);}
 const x=Number($('#fireX').value),y=Number($('#fireY').value);
 $('#mapMarkers').innerHTML=`<circle data-marker="fire" class="hot" cx="${px(x)}" cy="${py(y)}" r="17" stroke="#ffe1b0" stroke-width="2"/><text x="${px(x)+23}" y="${py(y)-17}" pointer-events="none">FIRE</text>`+
 zones.map((z,i)=>{
  const shape=zoneShape(z,validSize?ns:72,validSize?ew:72),label=esc(z.name);
  return `<ellipse data-marker="zone" data-i="${i}" class="zone" style="fill:rgba(104,203,154,.20);stroke:#b7f3d5;cursor:move" cx="${shape.cx}" cy="${shape.cy}" rx="${shape.rx}" ry="${shape.ry}"/>`+
   `<circle data-marker="zone" data-i="${i}" class="zone-center" style="fill:#a6e3bc;cursor:move" cx="${shape.cx}" cy="${shape.cy}" r="6"/>`+
   `<circle data-marker="zone-resize" data-i="${i}" class="zone-resize-handle" style="fill:#ffab4b;stroke:#fff1cf;stroke-width:2;cursor:ew-resize" cx="${shape.cx+shape.rx}" cy="${shape.cy}" r="9" tabindex="0" role="slider" aria-label="Resize ${label}" aria-valuemin="1" aria-valuemax="200" aria-valuenow="${z.radius_in}" aria-valuetext="${z.radius_in} inches"><title>Drag to resize ${label}; arrow keys adjust by half an inch</title></circle>`+
   `<text x="${shape.cx+9}" y="${shape.cy-10}" pointer-events="none">${label}</text>`;
 }).join('');
 $('#zonesJson').value=JSON.stringify(zones);
}
function syncRadiusInput(i){const input=$(`#zoneRows [data-z="${i}"][data-key="radius_in"]`);if(input)input.value=zones[i].radius_in;}
$('#ovenChoose').addEventListener('change',e=>location.href=e.target.value?'oven_layout.php?id='+e.target.value:'oven_layout.php?new=1');$('#newOven').addEventListener('click',()=>location.href='oven_layout.php?new=1');
$('#addZone').addEventListener('click',()=>{if(zones.length>=12)return alert('Maximum 12 zones.');const i=zones.length+1;zones.push({key:'zone_'+Date.now().toString(36).slice(-5)+'_'+i,name:'Zone '+i,x:.5,y:.5,radius_in:12});updateRows();draw();});
$('#zoneRows').addEventListener('input',e=>{const i=Number(e.target.dataset.z);if(!Number.isInteger(i)||!zones[i])return;const key=e.target.dataset.key;if(key==='radius_in')zones[i][key]=Number(e.target.value);else if(key==='name')zones[i][key]=e.target.value;draw();});
$('#zoneRows').addEventListener('click',e=>{if(e.target.dataset.remove===undefined)return;zones.splice(+e.target.dataset.remove,1);updateRows();draw();});
for(const name of ['ns','ew','height','fireX','fireY'])$('#'+name).addEventListener('input',draw);
const svg=$('#ovenCanvas');
// Screen CTM accounts for SVG letterboxing at different responsive widths.
function mapPoint(e){const matrix=svg.getScreenCTM();if(!matrix)return null;const p=svg.createSVGPoint();p.x=e.clientX;p.y=e.clientY;return p.matrixTransform(matrix.inverse());}
svg.addEventListener('pointerdown',e=>{
 const marker=e.target.closest('[data-marker]');if(!marker||(e.pointerType==='mouse'&&e.button!==0))return;
 const index=marker.dataset.i===undefined?null:Number(marker.dataset.i);
 if(index!==null&&!zones[index])return;
 drag={kind:marker.dataset.marker,i:index,pointerId:e.pointerId};svg.setPointerCapture(e.pointerId);e.preventDefault();
});
svg.addEventListener('pointermove',e=>{
 if(!drag||drag.pointerId!==e.pointerId)return;
 const point=mapPoint(e);if(!point)return;
 if(drag.kind==='zone-resize'){
  const ns=Number($('#ns').value),ew=Number($('#ew').value);
  if(ns<12||ew<12)return;
  zones[drag.i].radius_in=zoneRadiusAtPoint(zones[drag.i],point,ns,ew);
  syncRadiusInput(drag.i);draw();return;
 }
 const x=+Math.max(0,Math.min(1,(point.x-55)/550)).toFixed(4),y=+Math.max(0,Math.min(1,(point.y-38)/404)).toFixed(4);
 if(!validPoint(x,y))return;
 if(drag.kind==='fire'){$('#fireX').value=x;$('#fireY').value=y;}else{zones[drag.i].x=x;zones[drag.i].y=y;}
 draw();
});
function endDrag(e){if(!drag||drag.pointerId!==e.pointerId)return;drag=null;if(svg.hasPointerCapture(e.pointerId))svg.releasePointerCapture(e.pointerId);}
svg.addEventListener('pointerup',endDrag);svg.addEventListener('pointercancel',endDrag);svg.addEventListener('lostpointercapture',()=>{drag=null;});
svg.addEventListener('keydown',e=>{
 const marker=e.target.closest('[data-marker="zone-resize"]');if(!marker)return;
 const i=Number(marker.dataset.i);if(!zones[i])return;
 const step=({ArrowRight:.5,ArrowUp:.5,ArrowLeft:-.5,ArrowDown:-.5,PageUp:5,PageDown:-5})[e.key];
 if(step===undefined)return;
 e.preventDefault();zones[i].radius_in=clampRadius(Number(zones[i].radius_in)+step);syncRadiusInput(i);draw();
 svg.querySelector(`[data-marker="zone-resize"][data-i="${i}"]`)?.focus();
});
$('#ovenForm').addEventListener('submit',e=>{const x=+$('#fireX').value,y=+$('#fireY').value;if(!validPoint(x,y)||zones.some(z=>!validPoint(z.x,z.y))){e.preventDefault();alert('Fire and zones must be inside the oven ellipse.');return;}$('#zonesJson').value=JSON.stringify(zones);});
updateRows();draw();
const ovenHint=svg.nextElementSibling;
if(ovenHint?.matches?.('p.small'))ovenHint.textContent='Drag the fire or green zone center to move it. Drag the orange handle at the edge of a zone to resize its radius, or focus the handle and use arrow keys. Save oven layout when finished.';
