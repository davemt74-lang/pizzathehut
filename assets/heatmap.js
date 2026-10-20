'use strict';
(() => {
  const cfg=window.OVEN_HEATMAP;
  if(!cfg)return;
  const svg=document.querySelector('#heatmapCanvas');
  const zoneLayer=document.querySelector('#heatZones'),pathLayer=document.querySelector('#cookingPath');
  const picker=document.querySelector('#sessionPicker'),metric=document.querySelector('#metric'),zoneInput=document.querySelector('#eventZone');
  const xInput=document.querySelector('#posX'),yInput=document.querySelector('#posY'),status=document.querySelector('#pickedPosition');
  const NS='http://www.w3.org/2000/svg';
  const add=(parent,name,props={},value)=>{const el=document.createElementNS(NS,name);for(const [k,v] of Object.entries(props))el.setAttribute(k,String(v));if(value!==undefined)el.textContent=String(value);parent.appendChild(el);return el;};
  const point=({x,y})=>({x:55+550*Number(x),y:38+404*Number(y)});
  const inside=(x,y)=>x>=0&&x<=1&&y>=0&&y<=1&&4*(x-.5)**2+4*(y-.5)**2<=1;
  const nearestZone=(x,y)=>{let best=null,dist=Infinity;for(const z of cfg.zones){const d=Math.hypot((x-Number(z.x))*cfg.ew,(y-Number(z.y))*cfg.ns);if(d<=Number(z.radius_in)+1 && d<dist){best=z;dist=d;}}return best;};
  const tint=(value,mode)=>{
    if(value===null||value===undefined||!Number.isFinite(Number(value)))return '#627079';
    const v=Number(value);
    if(mode==='temp')return v>=650?'#e17b44':v>=600?'#dfa950':'#6daabc';
    return v>=8?'#65b99c':v>=5?'#d9b057':'#cb765b';
  };
  let picked={x:.5,y:.5};
  function choose(x,y){
    if(!inside(x,y))return;
    picked={x:Math.max(0,Math.min(1,x)),y:Math.max(0,Math.min(1,y))};
    if(xInput)xInput.value=picked.x.toFixed(4);
    if(yInput)yInput.value=picked.y.toFixed(4);
    const z=nearestZone(picked.x,picked.y);
    if(zoneInput)zoneInput.value=z?.key||'';
    if(status)status.textContent=`Selected position: ${Math.round(picked.x*100)}% west→east / ${Math.round(picked.y*100)}% north→south · ${z?.name||'Outside named zones'}. Submit to save.`;
    draw();
  }
  function draw(){
    zoneLayer.replaceChildren();pathLayer.replaceChildren();
    const mode=metric?.value||'temp';
    for(const z of cfg.zones){
      const p=point(z),value=mode==='temp'?z.last_stone_f:z[`${mode}_avg`];
      const rx=Number(z.radius_in)/cfg.ew*550,ry=Number(z.radius_in)/cfg.ns*404;
      const g=add(zoneLayer,'g');
      add(g,'ellipse',{cx:p.x,cy:p.y,rx:Math.max(6,rx),ry:Math.max(6,ry),fill:tint(value,mode),'fill-opacity':'.31',stroke:tint(value,mode),'stroke-width':'2'});
      add(g,'circle',{cx:p.x,cy:p.y,r:6,fill:tint(value,mode)});
      const num=value===null?'Not recorded':mode==='temp'?`${Number(value).toFixed(1)}°F`:`${Number(value).toFixed(1)}/10 (n=${z[mode+'_n']})`;
      add(g,'text',{x:p.x,y:p.y-12,'text-anchor':'middle'},z.name);
      add(g,'text',{x:p.x,y:p.y+22,'text-anchor':'middle'},num);
      add(g,'title',{},`${z.name}: ${num}`);
      g.addEventListener('click',()=>choose(Number(z.x),Number(z.y)));
    }
    const fire=point(cfg.fire);add(zoneLayer,'text',{x:fire.x,y:fire.y,'text-anchor':'middle','font-size':'25'},'🔥');
    const events=cfg.trajectory||[];
    const coords=events.map(e=>point({x:e.x_norm,y:e.y_norm}));
    if(coords.length>1)add(pathLayer,'polyline',{points:coords.map(p=>p.x+','+p.y).join(' '),stroke:'#e3efb3','stroke-width':3,fill:'none','stroke-linejoin':'round','stroke-dasharray':'6 4'});
    coords.forEach((p,i)=>{add(pathLayer,'circle',{cx:p.x,cy:p.y,r:9,fill:'#d6ef9c',stroke:'#203726','stroke-width':2});add(pathLayer,'text',{x:p.x,y:p.y+4,'text-anchor':'middle',fill:'#1c2926'},i+1);add(pathLayer,'title',{},`${i+1}. ${events[i].phase} — ${events[i].elapsed_seconds??'unknown'} seconds, ${events[i].stone_f??'unmeasured'}°F`);});
    if(xInput){const p=point(picked);add(pathLayer,'circle',{cx:p.x,cy:p.y,r:11,fill:'none',stroke:'#f7f0cf','stroke-width':3,'stroke-dasharray':'2 3'});}
  }
  metric?.addEventListener('change',draw);
  picker?.addEventListener('change',e=>{const u=new URL(window.location.href);if(e.target.value)u.searchParams.set('session_id',e.target.value);else u.searchParams.delete('session_id');window.location.assign(u.toString());});
  zoneInput?.addEventListener('change',e=>{const z=cfg.zones.find(z=>z.key===e.target.value);if(z)choose(Number(z.x),Number(z.y));});
  svg?.addEventListener('click',e=>{
    if(!xInput||e.target.closest('g')?.id==='heatZones')return;
    const pt=svg.createSVGPoint();pt.x=e.clientX;pt.y=e.clientY;
    const ctm=svg.getScreenCTM();if(!ctm)return;const p=pt.matrixTransform(ctm.inverse());
    choose((p.x-55)/550,(p.y-38)/404);
  });
  if(cfg.zones.length){choose(Number(cfg.zones[0].x),Number(cfg.zones[0].y));}else{draw();}
  document.querySelector('#mapLegend').textContent='IR zone colors: below 600°F blue, 600–649.9°F amber, 650°F+ orange. Ratings use green/amber/coral bands. Gray means not recorded. No interpolation. Historical readings are not predicted temperatures.';
})();
