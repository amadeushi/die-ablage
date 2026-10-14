/** Geometry-based reading order for horizontal, left-to-right digital PDF text.
 * Never changes the PDF. Unsupported scripts/rotations and table-like pages retain
 * their source order. Whitespace cuts preserve spanning headings above columns.
 */
export type PdfTextItem={str:string;width:number;height:number;transform:number[];dir?:string;hasEOL?:boolean};
type Box={x:number;y:number;right:number;bottom:number;baseline:number;height:number;text:string};
export function pdfSourceText(items:unknown[]):string{return items.filter((i:any)=>typeof i?.str==='string').map((i:any)=>i.str+(i.hasEOL?'\n':' ')).join('').trim();}
const median=(values:number[])=>{const a=[...values].sort((a,b)=>a-b);return a[Math.floor(a.length/2)]||10;};
export function pdfReadingOrder(items:unknown[],viewport:{transform:number[];scale:number}):{text:string;columns:boolean;fallback:boolean}{
 const original=pdfSourceText(items),raw=items.filter((i:any)=>typeof i?.str==='string'&&i.str.trim()) as PdfTextItem[];
 const fallback=()=>({text:original,columns:false,fallback:true});if(!raw.length)return {text:'',columns:false,fallback:false};
 const v=viewport.transform;if(v.length!==6||!v.every(Number.isFinite)||raw.length>40000)return fallback();
 const boxes:Box[]=[];
 for(const item of raw){const t=item.transform;if(t?.length!==6||!t.every(Number.isFinite)||!Number.isFinite(item.width)||item.width<0||item.dir==='rtl'||item.dir==='ttb')return fallback();
  const dx=v[0]*t[0]+v[2]*t[1],dy=v[1]*t[0]+v[3]*t[1];if(dx<=0||Math.abs(dy)>Math.abs(dx)*.15)return fallback();
  const x=v[0]*t[4]+v[2]*t[5]+v[4],baseline=v[1]*t[4]+v[3]*t[5]+v[5];
  const height=Math.hypot(v[0]*t[2]+v[2]*t[3],v[1]*t[2]+v[3]*t[3]);if(!Number.isFinite(height)||height<.1)return fallback();
  boxes.push({x,y:baseline-height,right:x+item.width*viewport.scale,bottom:baseline,baseline,height,text:item.str});
 }
 const height=median(boxes.map(b=>b.height));const rows:Box[][]=[];
 for(const box of boxes.sort((a,b)=>a.baseline-b.baseline||a.x-b.x)){const row=rows[rows.length-1];if(row&&Math.abs(box.baseline-row[0].baseline)<=Math.min(box.height,row[0].height)*.3)row.push(box);else rows.push([box]);}
 const segments:Box[]=[];const rowCounts:number[]=[];
 for(const row of rows){row.sort((a,b)=>a.x-b.x);let segment:Box|null=null,count=0;
  for(const box of row){const gap=segment?box.x-segment.right:Infinity;
   if(!segment||gap>Math.max(8,height*.8)){if(segment)segments.push(segment);segment={...box};count++;}
   else{const space=gap>Math.min(segment.height,box.height)*.12&&!/\s$/.test(segment.text)&&!/^\s/.test(box.text)?' ':'';segment.text+=space+box.text;segment.right=Math.max(segment.right,box.right);segment.y=Math.min(segment.y,box.y);segment.bottom=Math.max(segment.bottom,box.bottom);}
  }if(segment)segments.push(segment);rowCounts.push(count);
 }
 // Repeated small cells are more likely a table than newspaper prose.
 if(rows.length>=4&&median(rowCounts)>=3&&median(segments.map(s=>s.text.trim().length))<16)return fallback();
 let columns=false;
 const cut=(list:Box[],axis:'x'|'y',minGap:number)=>{
  const end=axis==='x'?'right':'bottom';const sorted=[...list].sort((a,b)=>a[axis]-b[axis]);let edge=sorted[0][end],best=-1,gap=0;
  for(let i=1;i<sorted.length;i++){const g=sorted[i][axis]-edge;if(g>=minGap&&g>gap){gap=g;best=i;}edge=Math.max(edge,sorted[i][end]);}
  return best<0?null:[sorted.slice(0,best),sorted.slice(best)] as [Box[],Box[]];
 };
 const flow=(list:Box[],depth=0):string=>{
  if(depth<16&&list.length>1){
   // Large shared horizontal gaps isolate mastheads and page footers before columns.
   const band=cut(list,'y',height*2.5);if(band)return band.map(side=>flow(side,depth+1)).join('\n\n');
   const vertical=cut(list,'x',Math.max(8,height*.8));
   // Require several lines on both sides to avoid treating an isolated label as a column.
   if(vertical&&vertical.every(side=>side.length>=3&&Math.max(...side.map(b=>b.bottom))-Math.min(...side.map(b=>b.y))>=height*3)){columns=true;return vertical.map(side=>flow(side,depth+1)).join('\n\n');}
   const horizontal=cut(list,'y',height*.7);if(horizontal)return horizontal.map(side=>flow(side,depth+1)).join('\n\n');
  }
  const lines:{baseline:number;text:string}[]=[];
  for(const box of [...list].sort((a,b)=>a.baseline-b.baseline||a.x-b.x)){const last=lines[lines.length-1];if(last&&Math.abs(last.baseline-box.baseline)<=height*.3)last.text+=' '+box.text.trim();else lines.push({baseline:box.baseline,text:box.text.trim()});}
  return lines.map(line=>line.text).join('\n');
 };
 const text=flow(segments);return {text,columns,fallback:false};
}
