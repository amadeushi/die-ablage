import {useEffect,useLayoutEffect,useRef,useState,type RefObject} from 'react';

const focusable='button:not(:disabled),a[href],input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]';
export function useOverlay(ref:RefObject<HTMLElement|null>,active:boolean,onClose:()=>void,returnFocus?:RefObject<HTMLElement|null>){
  const close=useRef(onClose);close.current=onClose;
  useLayoutEffect(()=>{
    const panel=ref.current;if(!active||!panel)return;
    const origin=returnFocus?.current||(document.activeElement instanceof HTMLElement?document.activeElement:null);
    const previousOverflow=document.body.style.overflow;
    const hidden:Array<[HTMLElement,boolean]>=[];
    let branch:HTMLElement=panel;
    while(branch.parentElement&&branch.parentElement!==document.body){
      for(const sibling of branch.parentElement.children){if(sibling!==branch&&sibling instanceof HTMLElement){hidden.push([sibling,sibling.inert]);sibling.inert=true;}}
      branch=branch.parentElement;
    }
    document.body.style.overflow='hidden';
    const nodes=()=>Array.from(panel.querySelectorAll<HTMLElement>(focusable)).filter(n=>n.getClientRects().length&&!n.closest('[inert]'));
    (panel.contains(document.activeElement)&&document.activeElement instanceof HTMLElement?document.activeElement:nodes()[0]||panel).focus({preventScroll:true});
    function key(e:KeyboardEvent){
      if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close.current();}
      if(e.key==='Tab'){const list=nodes(),first=list[0],last=list[list.length-1];if(!first){e.preventDefault();panel.focus();}else if(!panel.contains(document.activeElement)||(e.shiftKey&&document.activeElement===first)||(!e.shiftKey&&document.activeElement===last)){e.preventDefault();(e.shiftKey?last:first).focus();}}
    }
    function contain(e:FocusEvent){if(e.target instanceof Node&&!panel.contains(e.target))(nodes()[0]||panel).focus({preventScroll:true});}
    document.addEventListener('keydown',key,true);document.addEventListener('focusin',contain);
    return()=>{document.removeEventListener('keydown',key,true);document.removeEventListener('focusin',contain);for(const [node,inert] of hidden)node.inert=inert;document.body.style.overflow=previousOverflow;if(origin?.isConnected&&!origin.closest('[inert]'))origin.focus({preventScroll:true});};
  },[active,ref]);
}
export function useMediaQuery(query:string){
  const [matches,setMatches]=useState(()=>window.matchMedia(query).matches);
  useEffect(()=>{const media=window.matchMedia(query),update=()=>setMatches(media.matches);media.addEventListener('change',update);update();return()=>media.removeEventListener('change',update);},[query]);
  return matches;
}
