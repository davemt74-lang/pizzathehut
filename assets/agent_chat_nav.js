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
drawer.addEventListener('click',e=>{if(e.target.closest('a'))close();});
document.addEventListener('keydown',e=>{if(drawer.hidden)return;if(e.key==='Escape'){e.preventDefault();close();return;}if(e.key==='Tab'){const items=[...drawer.querySelectorAll('button:not(:disabled),a[href]')];if(!items.length)return;const first=items[0],last=items[items.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
window.addEventListener('pizza-chat-nav',open);
window.addEventListener('pagehide',close);
})();
