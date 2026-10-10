importScripts('queue.js');
chrome.runtime.onMessage.addListener((message,sender,reply)=>{
 if(message?.action!=='ablage-region-capture')return;
 (async()=>{
  if(!sender.tab||!/^https?:\/\//.test(sender.tab.url||''))throw Error('Diese Seite kann nicht erfasst werden.');
  const {libraryOrigin}=await chrome.storage.local.get('libraryOrigin');const app=new URL(libraryOrigin);
  if(app.protocol!=='https:'&&!(app.protocol==='http:'&&['localhost','127.0.0.1'].includes(app.hostname)))throw Error('Bitte die Bibliotheksadresse in der Erweiterung erneut einrichten.');
  const clip=message.clip;if(!clip||clip.type!=='article'||typeof clip.content!=='string'||typeof clip.archive!=='string'||new TextEncoder().encode(clip.content).length>1000000||new TextEncoder().encode(clip.archive).length>5000000||!/^https?:\/\//.test(clip.url||''))throw Error('Die Auswahl ist ungültig oder zu groß.');
  const id=await AblageQueue.save(clip);
  try{await chrome.tabs.create({url:app.origin+'/?capture='+id});}catch(error){await chrome.storage.local.remove('capture:'+id);throw error;}
  return{ok:true};
 })().then(reply,error=>reply({error:error.message||'Übergabe fehlgeschlagen. Bitte erneut versuchen.'}));return true;
});
