import {searchPattern} from './search-pattern';
export function pdfHitBoxes(items:any[],viewport:any,terms:string[]){
 let text='';const spans:{item:any;start:number;end:number}[]=[];
 for(const item of items){if(typeof item.str!=='string')continue;const start=text.length;text+=item.str;spans.push({item,start,end:text.length});text+=item.hasEOL?'\n':' ';}
 const boxes:{left:number;top:number;width:number;height:number;hit:number}[]=[];const v=viewport.transform;
 const patterns=terms.filter(t=>t.trim()).map(t=>searchPattern(t).source).filter(Boolean);let matchIndex=0;for(const pattern of patterns.length?[patterns.join('|')]:[]){const re=new RegExp(pattern,'giu');for(const hit of text.matchAll(re)){if(!hit[0])continue;if(boxes.length>=500)return boxes;const start=hit.index!,end=start+hit[0].length;
  for(const span of spans){if(span.end<=start||span.start>=end||!span.item.str.length)continue;const i=span.item,t=i.transform;if(!t||t.length!==6)continue;
   const dx=v[0]*t[0]+v[2]*t[1],dy=v[1]*t[0]+v[3]*t[1];if(dx<=0||Math.abs(dy)>Math.abs(dx)*.15)continue;
   const x=v[0]*t[4]+v[2]*t[5]+v[4],y=v[1]*t[4]+v[3]*t[5]+v[5],h=Math.hypot(v[0]*t[2]+v[2]*t[3],v[1]*t[2]+v[3]*t[3]);
   const a=Math.max(0,start-span.start)/i.str.length,b=Math.min(i.str.length,end-span.start)/i.str.length,w=i.width*viewport.scale;
   boxes.push({hit:matchIndex,left:100*(x+w*a)/viewport.width,top:100*(y-h)/viewport.height,width:100*w*(b-a)/viewport.width,height:100*h/viewport.height});
  }matchIndex++;
 }}return boxes;
}
