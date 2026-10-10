(()=>{
 const id=new URL(location.href).searchParams.get('capture');if(!id||!/^[a-f0-9-]{36}$/i.test(id))return;
 const key='capture:'+id;let accepted=false,tries=0,timer,updates=Promise.resolve();
 async function send(){if(accepted)return;const pending=(await chrome.storage.local.get(key))[key];if(!pending){clearInterval(timer);return;}if(Date.now()-pending.createdAt>86400000){await chrome.storage.local.remove(key);clearInterval(timer);return;}window.postMessage({channel:'lesewerk-capture',clip:pending.clip,id},location.origin);if(++tries>600)clearInterval(timer);}
 window.addEventListener('message',e=>{
  if(e.source!==window||e.origin!==location.origin||e.data?.id!==id)return;
  if(e.data.channel==='lesewerk-received'){accepted=true;clearInterval(timer);}
  updates=updates.then(async()=>{if(e.data.channel==='lesewerk-draft'){const pending=(await chrome.storage.local.get(key))[key];const clip=e.data.clip;if(pending&&clip&&typeof clip.content==='string'&&typeof clip.url==='string')await chrome.storage.local.set({[key]:{...pending,clip}});}
  if(['lesewerk-saved','lesewerk-discarded'].includes(e.data.channel)){clearInterval(timer);await chrome.storage.local.remove(key);history.replaceState(null,'',location.pathname);}}).catch(()=>window.postMessage({channel:'lesewerk-draft-error',id},location.origin));
 });
 window.addEventListener('message',e=>{if(e.source===window&&e.origin===location.origin&&e.data?.channel==='lesewerk-ready')send();});
 timer=setInterval(send,1000);send();
})();
