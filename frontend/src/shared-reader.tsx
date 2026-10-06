import {useEffect,useRef,useState} from 'react';
import Brand from './brand';
import {ArrowLeft,ArrowRight,BookOpen,ExternalLink,LockKeyhole} from 'lucide-react';

type Clip={id:string;title:string;url:string;content?:string;note?:string;archive_key?:string|null};
function source(url:string){try{return new URL(url).hostname;}catch{return 'Gespeicherte Quelle';}}
function readingText(clip:Clip){
  const lines=(clip.content||'').trim().split('\n');
  // Remove only an exact standalone headline at the start, never repeated body passages.
  const normalize=(s:string)=>s.replace(/\s+/g,' ').trim().toLocaleLowerCase('de');
  if(normalize(lines[0]||'')===normalize(clip.title))lines.shift();
  return lines.join('\n').trim();
}
export default function SharedReader({token,title,clips}:{token:string;title:string;clips:Clip[]}){
  const [index,setIndex]=useState(0),heading=useRef<HTMLHeadingElement>(null),handoff=useRef(false);
  const selected=clips[index]||clips[0],multiple=clips.length>1,collection=multiple||(selected&&title!==selected.title);
  useEffect(()=>{document.title=`${selected?.title||title} · Die ABLAGE`;},[selected?.title,title]);
  useEffect(()=>{if(handoff.current){heading.current?.focus({preventScroll:true});heading.current?.scrollIntoView({block:'start',behavior:'auto'});handoff.current=false;}},[index]);
  function choose(next:number){if(next===index||next<0||next>=clips.length)return;handoff.current=true;setIndex(next);}
  const Heading=collection?'h2':'h1',content=selected?readingText(selected):'';
  return <div className="shared-shell">
    <header><Brand/><span className="badge"><LockKeyhole size={14} aria-hidden="true"/> Leselink</span></header>
    <main>
      {collection&&<div className="shared-collection-heading"><h1>{title}</h1><p className="muted">{clips.length} {clips.length===1?'Clip':'Clips'} · Zum Lesen freigegeben</p></div>}
      {!selected?<div className="empty"><BookOpen aria-hidden="true"/><h1>{title}</h1><h2>Diese Sammlung ist noch leer.</h2><p>Neue Clips erscheinen hier, sobald sie hinzugefügt werden.</p></div>:<>
        {multiple&&<label id="shared-clip-selection" className="shared-mobile-selector">Clip auswählen · {index+1} von {clips.length}<select value={selected.id} onChange={e=>choose(clips.findIndex(c=>c.id===e.target.value))}>{clips.map((c,i)=><option key={c.id} value={c.id}>{i+1}. {c.title}</option>)}</select></label>}
        <div className={'shared-grid '+(!multiple?'shared-single':'')}>
          {multiple&&<nav className="shared-desktop-list" aria-label="Geteilte Clips">{clips.map((c,i)=><button key={c.id} className={'shared-item '+(index===i?'active':'')} aria-current={index===i?'true':undefined} onClick={()=>choose(i)}>{c.title}<small>{source(c.url)}</small></button>)}</nav>}
          <article className="shared-article" aria-labelledby="shared-article-title">
            <Heading ref={heading} tabIndex={-1} id="shared-article-title">{selected.title}</Heading>
            <p className="shared-provenance">{source(selected.url)} · {selected.archive_key?'Gespeicherter Seitentext':'Gespeicherter Clip'}{!multiple&&' · Zum Lesen freigegeben'}</p>
            <div className="shared-source-actions"><a className="source-link" href={selected.url} target="_blank" rel="noopener noreferrer">Original öffnen <ExternalLink size={15} aria-hidden="true"/><span className="sr-only"> (neuer Tab)</span></a>{selected.archive_key&&<a className="source-link" href={`/api/shared?token=${encodeURIComponent(token)}&id=${encodeURIComponent(selected.id)}&archive=1`} target="_blank" rel="noopener noreferrer">Gespeicherte Seitenkopie öffnen <ExternalLink size={15} aria-hidden="true"/><span className="sr-only"> (neuer Tab)</span></a>}</div>
            {content?<div className="article-content">{content}</div>:<p className="shared-link-only">{selected.archive_key?'Kein separater Lesetext gespeichert. Öffne die gespeicherte Seitenkopie, um den Inhalt zu lesen.':'Dieser Clip speichert einen Link zur Quelle. Über „Original öffnen“ kannst du ihn lesen.'}</p>}
            {selected.note&&<aside className="note"><h3>Notiz zum Clip</h3><p>{selected.note}</p></aside>}
            {multiple&&<nav className="shared-reading-navigation" aria-label="Weiterlesen in der Sammlung"><p>Clip {index+1} von {clips.length}</p><div><button className="secondary" disabled={index===0} onClick={()=>choose(index-1)}><ArrowLeft size={16} aria-hidden="true"/> Vorheriger</button><button className="secondary" disabled={index===clips.length-1} onClick={()=>choose(index+1)}>Nächster <ArrowRight size={16} aria-hidden="true"/></button></div><a href="#shared-clip-selection" className="shared-return" onClick={e=>{e.preventDefault();const selector=document.querySelector<HTMLSelectElement>('.shared-mobile-selector select');const list=document.querySelector<HTMLButtonElement>('.shared-desktop-list [aria-current]');const target=window.matchMedia('(max-width: 720px)').matches?selector:list;target?.focus({preventScroll:true});target?.scrollIntoView({block:'center'});}}>Zur Clipauswahl</a></nav>}
          </article>
        </div>
      </>}
    </main>
  </div>;
}
