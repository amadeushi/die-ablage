const api=globalThis.browser||globalThis.chrome;
const button=document.getElementById('capture'),status=document.getElementById('status'),openLink=document.getElementById('open');
button.onclick=async()=>{
 button.disabled=true;openLink.hidden=true;status.textContent='';
 try{
  const [tab]=await api.tabs.query({active:true,currentWindow:true});
  if(!tab?.id||!/^https?:/.test(tab.url||''))throw Error('Bitte eine normale Webseite in Safari öffnen.');
  const [check]=await api.scripting.executeScript({target:{tabId:tab.id},func:()=>[...document.querySelectorAll('input[type=password]')].some(el=>el.getClientRects().length)});
  if(check?.result)throw Error('Bitte zuerst auf der Quellseite anmelden, danach die Erweiterung erneut öffnen.');
  await api.scripting.executeScript({target:{tabId:tab.id},files:['capture.js']});
  const type=document.getElementById('type').value;
  if(type==='regions'){await api.scripting.executeScript({target:{tabId:tab.id},files:['region-picker.js']});window.close();return;}
  const [result]=await api.scripting.executeScript({target:{tabId:tab.id},func:type=>globalThis.AblageCapture.capture(type),args:[type]});
  const reply=await api.runtime.sendNativeMessage('de.partei.hildesheim.ablage',{action:'queueCapture',clip:result.result});
  if(!reply?.ok)throw Error(reply?.error||'Übergabe fehlgeschlagen.');
  openLink.href=reply.launchURL;openLink.hidden=false;status.textContent='Bereit. Innerhalb von zehn Minuten in der App öffnen und dort prüfen.';
 }catch(error){status.textContent=error.message||'Clipping fehlgeschlagen.';}finally{button.disabled=false;}
};
