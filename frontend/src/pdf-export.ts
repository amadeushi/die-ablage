import {PDFDocument,PDFName,rgb} from 'pdf-lib';
export type PdfMark={page:number;left:number;top:number;width:number;height:number};
export type ArticleRegion={file:string;preview:string;page:number;text:string};
export function parsePdfPages(value:string,total:number):number[]{
 const pages=new Set<number>();if(!value.trim())throw Error('Bitte PDF-Seiten wählen, z. B. 1–3, 7.');
 for(const part of value.replace(/[–—]/g,'-').split(',')){const m=/^\s*(\d+)\s*(?:-\s*(\d+))?\s*$/.exec(part);if(!m)throw Error('Bitte Seiten als 1–3, 7 eingeben.');const first=Number(m[1]),last=Number(m[2]||m[1]);if(first<1||last<first||last>total)throw Error(`Bitte Seiten zwischen 1 und ${total} wählen.`);for(let i=first;i<=last;i++)pages.add(i);}
 return [...pages].sort((a,b)=>a-b);
}
export async function exportPdfPages(bytes:Uint8Array,pages:number[],marks:PdfMark[]=[]):Promise<Uint8Array>{
 const source=await PDFDocument.load(bytes),out=await PDFDocument.create();if(!pages.length||pages.length>500||pages.some(p=>!Number.isInteger(p)||p<1||p>source.getPageCount()))throw Error('Ungültige Seitenauswahl.');
 // Omit interactive destinations that can reference non-selected page objects.
 for(const p of source.getPages())p.node.delete(PDFName.of('Annots'));
 const copies=await out.copyPages(source,pages.map(p=>p-1));copies.forEach((p,index)=>{out.addPage(p);const {width,height}=p.getSize();for(const m of marks.filter(m=>m.page===pages[index])){const l=m.left/100,t=m.top/100,w=m.width/100,h=m.height/100,r=((p.getRotation().angle%360)+360)%360;const rect=r===90?{x:t*width,y:l*height,width:h*width,height:w*height}:r===180?{x:(1-l-w)*width,y:t*height,width:w*width,height:h*height}:r===270?{x:(1-t-h)*width,y:(1-l-w)*height,width:h*width,height:w*height}:{x:l*width,y:(1-t-h)*height,width:w*width,height:h*height};p.drawRectangle({...rect,color:rgb(1,.85,.1),opacity:.28,borderColor:rgb(.7,.07,.16),borderWidth:.8});}});
 out.setTitle('Die ABLAGE – ausgewählte PDF-Seiten');return out.save();
}
export async function imageArticlePdf(png:string):Promise<Uint8Array>{const doc=await PDFDocument.create(),image=await doc.embedPng(png),page=doc.addPage([image.width*.5,image.height*.5]);page.drawImage(image,{x:0,y:0,width:page.getWidth(),height:page.getHeight()});doc.setTitle('Die ABLAGE – ausgewählter Artikelausschnitt');return doc.save();}
export function bytesBase64(bytes:Uint8Array){let result='';for(let i=0;i<bytes.length;i+=32768)result+=String.fromCharCode(...bytes.subarray(i,i+32768));return btoa(result);}
export function downloadFile(bytes:Uint8Array,name:string,type='application/pdf'){const url=URL.createObjectURL(new Blob([new Uint8Array(bytes)],{type}));const a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),60000);}
