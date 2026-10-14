import {pdfReadingOrder,pdfSourceText} from './pdf-reading-order';
export async function pdfLibrary(){
 const lib=await import('pdfjs-dist/legacy/build/pdf.mjs');
 lib.GlobalWorkerOptions.workerSrc=(await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url')).default;
 return lib;
}
export async function readPdf(file:File,progress:(value:string)=>void,signal:AbortSignal,textOrder:'layout'|'source'='layout'){
 if(file.size>25*1024*1024)throw Error('Bitte eine PDF mit maximal 25 MB wählen.');
 const buffer=await file.arrayBuffer();if(signal.aborted)throw new DOMException('Abgebrochen','AbortError');
 const lib=await pdfLibrary();const digest=await crypto.subtle.digest('SHA-256',buffer);const hash=Array.from(new Uint8Array(digest)).map(b=>b.toString(16).padStart(2,'0')).join('');const task=lib.getDocument({data:new Uint8Array(buffer),enableXfa:false,stopAtErrors:true});
 let locked=false;task.onPassword=()=>{locked=true;void task.destroy();};
 const abort=()=>{void task.destroy();};signal.addEventListener('abort',abort,{once:true});
 try{
  const pdf=await task.promise;if(pdf.numPages>500)throw Error('PDFs mit bis zu 500 Seiten werden unterstützt.');
  const details=await pdf.getMetadata();const info:any=details.info,metadata=details.metadata;const clean=(value:unknown)=>typeof value==='string'?value.replace(/\u0000/g,'').slice(0,2000):'';
  const meta={filename:file.name.slice(0,255),title:clean(info.Title||metadata?.get('dc:title')),author:clean(info.Author||metadata?.get('dc:creator')),subject:clean(info.Subject),keywords:clean(info.Keywords),creationDate:clean(info.CreationDate),modificationDate:clean(info.ModDate),pages:pdf.numPages,bytes:file.size,textPages:0,coverageKnown:true,textOrder,layoutPages:0,fallbackPages:0};
  const parts:string[]=[];let size=0,thumbnail='';
  for(let i=1;i<=pdf.numPages;i++){
   if(signal.aborted)throw new DOMException('Abgebrochen','AbortError');progress(`Text wird gelesen · Seite ${i} von ${pdf.numPages}`);
   const page=await pdf.getPage(i),text=await page.getTextContent();const order=textOrder==='layout'?pdfReadingOrder(text.items,page.getViewport({scale:1})):null;const lines=order?.text??pdfSourceText(text.items);if(order?.columns)meta.layoutPages++;if(order?.fallback)meta.fallbackPages++;
   if(lines){const part=`Seite ${i}\n${lines}`;size+=new TextEncoder().encode(part).length+2;if(size>5*1024*1024)throw Error('Der PDF-Text ist zu groß (maximal 5 MB).');parts.push(part);}
   if(i===1){const vp=page.getViewport({scale:1}),canvas=document.createElement('canvas'),viewport=page.getViewport({scale:Math.min(1,480/vp.width,640/vp.height)});canvas.width=Math.ceil(viewport.width);canvas.height=Math.ceil(viewport.height);await page.render({canvas,viewport}).promise;thumbnail=canvas.toDataURL('image/jpeg',.75);}
   page.cleanup();
  }
  if(!parts.length)throw Error('Kein durchsuchbarer Text gefunden. Scans ohne Textschicht benötigen OCR und werden noch nicht unterstützt.');
  meta.textPages=parts.length;
  progress('PDF ist bereit zur Prüfung.');
  if(signal.aborted)throw new DOMException('Abgebrochen','AbortError');return {hash,metadata:meta,content:parts.join('\n\n'),file,thumbnail};
 }catch(error){if(signal.aborted)throw new DOMException('Abgebrochen','AbortError');if(locked)throw Error('Passwortgeschützte PDFs werden vorerst nicht unterstützt.');throw error;}finally{signal.removeEventListener('abort',abort);await task.destroy();}
}
