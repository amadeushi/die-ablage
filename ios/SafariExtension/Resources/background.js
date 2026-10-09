const api=globalThis.browser||globalThis.chrome;
api.runtime.onMessage.addListener((message,sender,reply)=>{
 if(message.action!=='ablage-region-capture')return;
 if(!sender.tab){reply({ok:false,error:'Keine Quellseite gefunden.'});return;}
 api.runtime.sendNativeMessage('de.partei.hildesheim.ablage',{action:'queueCapture',clip:message.clip}).then(reply).catch(()=>reply({ok:false,error:'Die App konnte die Auswahl nicht übernehmen. Prüfe die Einrichtung.'}));
 return true;
});
