globalThis.AblageQueue={
 async prune(){const all=await chrome.storage.local.get(null);const expired=Object.entries(all).filter(([k,v])=>(k.startsWith('capture:')||k.startsWith('shot:'))&&(!v?.createdAt||Date.now()-v.createdAt>(k.startsWith('shot:')?600000:86400000))).map(([k])=>k);if(expired.length)await chrome.storage.local.remove(expired);},
 async save(clip){await this.prune();const all=await chrome.storage.local.get(null);if(Object.keys(all).filter(k=>k.startsWith('capture:')).length>=20)throw Error('20 Entwürfe warten auf Speicherung. Öffne oder verwerfe zuerst einen vorhandenen Entwurf.');const id=crypto.randomUUID();await chrome.storage.local.set({['capture:'+id]:{clip,createdAt:Date.now()}});return id;}
};
