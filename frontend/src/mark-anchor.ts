export type TextMark={id:string;section:string;quote:string;prefix:string;suffix:string};
export function locateMark(text:string,mark:TextMark):{start:number;end:number}|null{
 if(!mark.quote)return null;
 const candidates:number[]=[];let start=0;
 while(start<=text.length){const at=text.indexOf(mark.quote,start);if(at<0)break;candidates.push(at);start=at+1;}
 if(candidates.length===1)return {start:candidates[0],end:candidates[0]+mark.quote.length};
 const matching=candidates.filter(at=>(!mark.prefix||text.slice(0,at).endsWith(mark.prefix))&&(!mark.suffix||text.slice(at+mark.quote.length).startsWith(mark.suffix)));
 return matching.length===1?{start:matching[0],end:matching[0]+mark.quote.length}:null;
}
