import {searchPattern} from './search-pattern';
export function pdfSearchText(items:any[]):string{return items.filter(i=>typeof i?.str==='string').map(i=>i.str+(i.hasEOL?'\n':' ')).join('');}
export function pdfPageMatches(items:any[],terms:string[],limit=2000):number{
 const text=pdfSearchText(items);if(!terms.some(t=>t.trim()))return 0;const patterns=terms.filter(t=>t.trim()).map(t=>searchPattern(t).source).filter(Boolean);if(!patterns.length)return 0;const re=new RegExp(patterns.join('|'),'giu');let count=0;for(const hit of text.matchAll(re)){if(!hit[0])continue;if(++count>=limit)break;}return count;
}
export async function scanPdfMatches(pdf:any,terms:string[],signal:AbortSignal,progress:(hits:{page:number;index:number}[],done:number)=>void,limit=2000){
 const hits:{page:number;index:number}[]=[];
 for(let page=1;page<=pdf.numPages;page++){if(signal.aborted)return;const p=await pdf.getPage(page),text=await p.getTextContent();if(signal.aborted)return;const count=pdfPageMatches(text.items,terms,limit-hits.length);for(let i=0;i<count;i++)hits.push({page,index:i});progress([...hits],page);if(hits.length>=limit)return;await new Promise(resolve=>setTimeout(resolve,0));}
}
