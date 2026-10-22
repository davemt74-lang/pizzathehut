/* Header hamburger navigation. Focus-managed modal drawer, no camera or chat teardown. */
(()=>{'use strict';
const toggle=document.getElementById('chatNavToggle');
const drawer=document.getElementById('chatNav');
const backdrop=document.getElementById('chatNavBackdrop');
const closeButton=document.getElementById('chatNavClose');
if(!toggle||!drawer||!backdrop||!closeButton)return;
let lastFocus=null;
function close(){if(drawer.hidden)return;drawer.hidden=true;drawer.inert=true;drawer.setAttribute('aria-hidden','true');backdrop.hidden=true;toggle.setAttribute('aria-expanded','false');document.body.classList.remove('chat-nav-open');(lastFocus?.isConnected?lastFocus:toggle).focus();}
function open(){if(!drawer.hidden)return;lastFocus=document.activeElement;drawer.hidden=false;drawer.inert=false;drawer.setAttribute('aria-hidden','false');backdrop.hidden=false;toggle.setAttribute('aria-expanded','true');document.body.classList.add('chat-nav-open');closeButton.focus();}
toggle.addEventListener('click',()=>drawer.hidden?open():close());
closeButton.addEventListener('click',close);
backdrop.addEventListener('click',close);
// Existing feature pages remain backed by their tested PHP endpoints, but open
// inside the Agent Chat canvas instead of replacing the conversation.
const toolPanel=document.getElementById('chatToolPanel');
const toolFrame=document.getElementById('chatToolFrame');
const toolTitle=document.getElementById('chatToolTitle');
const toolClose=document.getElementById('chatToolClose');
const allowedTools=new Set(['index.php','workdays.php','gallery.php','proposals.php','batch_insights.php','readiness.php','oven_layout.php','heatmap.php','cooking_patterns.php','visual_compare.php','pizza_tracker.php']);
function closeTool(){if(!toolPanel||toolPanel.hidden)return;toolPanel.hidden=true;toolFrame.removeAttribute('src');toggle.focus();}
function openTool(href,title){
 if(!toolPanel||!toolFrame)return;
 const url=new URL(href,window.location.href);
 if(url.origin!==window.location.origin||!allowedTools.has(url.pathname.split('/').pop()))return;
 url.searchParams.set('embedded','1');
 toolTitle.textContent=title||'Pizza Lab tool';toolFrame.src=url.href;toolPanel.hidden=false;toolClose.focus();
}
drawer.addEventListener('click',e=>{
 const anchor=e.target.closest('a[href]');if(!anchor)return;
 if(e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||e.button!==0){close();return;}
 if(anchor.getAttribute('href')!=='agent_chat.php'){
  e.preventDefault();close();openTool(anchor.getAttribute('href'),anchor.textContent.trim());return;
 }
 close();closeTool();
});
toolClose?.addEventListener('click',closeTool);
toolFrame?.addEventListener('load',()=>{
 try{
  if(!toolFrame.getAttribute('src'))return;
  const doc=toolFrame.contentDocument;if(!doc?.head)return;
  const style=doc.createElement('style');
  style.textContent='.view{max-width:none!important;padding:14px!important}.view>p:first-child{display:none!important}.sidebar{display:none!important}.content{margin-left:0!important}.topbar{height:54px!important}';
  doc.head.append(style);
 }catch{/* Same-origin embed may have redirected to a login page; never bypass access checks. */}
});
window.addEventListener('pizza-chat-tool-open',e=>{close();openTool(e.detail?.href||'',e.detail?.title||'Pizza Lab tool');});
window.addEventListener('pizza-chat-camera-open',closeTool);
window.addEventListener('pizza-chat-photo-command',closeTool);
document.addEventListener('keydown',e=>{if(drawer.hidden)return;if(e.key==='Escape'){e.preventDefault();close();return;}if(e.key==='Tab'){const items=[...drawer.querySelectorAll('button:not(:disabled),a[href]')];if(!items.length)return;const first=items[0],last=items[items.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
window.addEventListener('pizza-chat-nav',open);
window.addEventListener('pagehide',()=>{close();closeTool();});
})();
