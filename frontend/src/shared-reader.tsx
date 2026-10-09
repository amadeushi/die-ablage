import {lazy,Suspense,useEffect,useRef,useState} from 'react';
import Brand from './brand';
import TextMarker from './text-marker';
import {HighlightedText} from './search-matches';
import {RichText,ReadingControls,useReadingSettings} from './reading';
import PdfMetadata from './pdf-metadata';
const PdfDocument=lazy(()=>import('./pdf-document'));
import {ArrowLeft,ArrowRight,BookOpen,ExternalLink,LockKeyhole,Search,Highlighter,ChevronUp,ChevronDown,X} from 'lucide-react';

type Clip={id:string;title:string;url:string;content?:string;note?:string;archive_key?:string|null;type?:string;pdf?:any;content_format?:string;note_format?:string};
function source(url:string){try{return new URL(url).hostname;}catch{return 'Gespeicherte Quelle';}}
function readingText(clip:Clip){
  const lines=(clip.content||'').trim().split('\n');
  // Remove only an exact standalone headline at the start, never repeated body passages.
  const normalize=(s:string)=>s.replace(/\s+/g,' ').trim().toLocaleLowerCase('de');
  if(normalize(clip.content_format==='markdown'?(lines[0]||'').replace(/^#{1,6}\s+/,''):lines[0]||'')===normalize(clip.title))lines.shift();
  return lines.join('\n').trim();
}
export default function SharedReader({token,title,clips}:{token:string;title:string;clips:Clip[]}){
  const reading=useReadingSettings();
  const toolsRef=useRef<HTMLDivElement>(null);
  const mobile=()=>window.matchMedia('(max-width:720px)').matches;
  function closeTools(){if(!mobile())return;setSearchOpen(false);setShowMarks(false);toolsRef.current?.querySelectorAll<HTMLDetailsElement>('details[open]').forEach(d=>d.open=false);}
  function activate(tool:string){if(!mobile())return;if(tool!=='search')setSearchOpen(false);if(tool!=='marker')setShowMarks(false);toolsRef.current?.querySelectorAll<HTMLDetailsElement>('details[open]').forEach(d=>{if((tool==='font'&&d.classList.contains('reading-controls'))||(tool==='source'&&d.classList.contains('shared-source-menu')))return;d.open=false;});}
  useEffect(()=>{const outside=(e:PointerEvent)=>{const target=e.target as HTMLElement;if(!toolsRef.current?.contains(target)&&!target.closest('.marker-floating')&&!target.closest('.marker-list'))closeTools();};document.addEventListener('pointerdown',outside);return()=>document.removeEventListener('pointerdown',outside);},[]);
  useEffect(()=>{const tools=toolsRef.current;if(!tools)return;const measure=()=>{const row=tools.querySelector<HTMLElement>('.shared-tool-row');const panel=tools.querySelector<HTMLElement>('.reading-controls[open]>div,.shared-source-menu[open] .shared-source-actions');row?.style.setProperty('--tool-panel-height',panel?panel.getBoundingClientRect().height+8+'px':'0px');};const observer=new ResizeObserver(measure);tools.querySelectorAll<HTMLElement>('.reading-controls>div,.shared-source-actions').forEach(el=>observer.observe(el));tools.addEventListener('toggle',measure,true);window.addEventListener('resize',measure);measure();return()=>{observer.disconnect();tools.removeEventListener('toggle',measure,true);window.removeEventListener('resize',measure);};},[]);
  const article=useRef<HTMLElement>(null),searchInput=useRef<HTMLInputElement>(null);
  const [query,setQuery]=useState(''),[searchOpen,setSearchOpen]=useState(false),[showMarks,setShowMarks]=useState(false),[markerTarget,setMarkerTarget]=useState<HTMLDivElement|null>(null),[hitCount,setHitCount]=useState(0),[hitIndex,setHitIndex]=useState(-1);
  const terms=query.trim()?[query.trim()]:[];

  const [index,setIndex]=useState(0),heading=useRef<HTMLHeadingElement>(null),handoff=useRef(false);
  const selected=clips[index]||clips[0],multiple=clips.length>1,collection=multiple||(selected&&title!==selected.title);
  useEffect(()=>{document.title=`${selected?.title||title} · Die ABLAGE`;},[selected?.title,title]);
  useEffect(()=>{if(handoff.current){heading.current?.focus({preventScroll:true});heading.current?.scrollIntoView({block:'start',behavior:'auto'});handoff.current=false;}},[index]);
  function choose(next:number){if(next===index||next<0||next>=clips.length)return;handoff.current=true;setIndex(next);closeTools();}
  const Heading=collection?'h2':'h1',content=selected?readingText(selected):'';
  const matching=query.trim()?clips.map((c,i)=>({c,i})).filter(({c})=>[c.title,c.content||'',c.note||''].some(t=>t.toLocaleLowerCase('de').includes(query.trim().toLocaleLowerCase('de')))):[];
  useEffect(()=>{const hits=article.current?.querySelectorAll('[data-search-hit]');setHitCount(hits?.length||0);setHitIndex(-1);},[query,selected]);
  useEffect(()=>{if(searchOpen)searchInput.current?.focus();},[searchOpen]);
  useEffect(()=>{const bar=article.current?.querySelector('.shared-statusbar');if(!bar)return;const update=()=>article.current?.style.setProperty('--shared-bar-height',bar.getBoundingClientRect().height+'px');const observer=new ResizeObserver(update);observer.observe(bar);update();return()=>observer.disconnect();},[selected,searchOpen]);
  function moveHit(direction:number){const hits=article.current?.querySelectorAll<HTMLElement>('[data-search-hit]');if(!hits?.length)return;const next=(hitIndex+direction+hits.length)%hits.length;hits.forEach(h=>h.classList.remove('active-search-hit'));hits[next].classList.add('active-search-hit');hits[next].scrollIntoView({block:'center',behavior:'auto'});setHitIndex(next);closeTools();}

  return <div className="shared-shell">
    <header><Brand/><span className="badge"><LockKeyhole size={14} aria-hidden="true"/> Leselink</span></header>
    <main>
      {collection&&<div className="shared-collection-heading"><h1>{title}</h1><p className="muted">{clips.length} {clips.length===1?'Clip':'Clips'} · Zum Lesen freigegeben</p></div>}
      {!selected?<div className="empty"><BookOpen aria-hidden="true"/><h1>{title}</h1><h2>Diese Sammlung ist noch leer.</h2><p>Neue Clips erscheinen hier, sobald sie hinzugefügt werden.</p></div>:<>
        {multiple&&<label id="shared-clip-selection" className="shared-mobile-selector">Clip auswählen · {index+1} von {clips.length}<select value={selected.id} onChange={e=>choose(clips.findIndex(c=>c.id===e.target.value))}>{clips.map((c,i)=><option key={c.id} value={c.id}>{i+1}. {c.title}</option>)}</select></label>}
        <div className={'shared-grid '+(!multiple?'shared-single':'')}>
          {multiple&&<nav className="shared-desktop-list" aria-label="Geteilte Clips">{clips.map((c,i)=><button key={c.id} className={'shared-item '+(index===i?'active':'')} aria-current={index===i?'true':undefined} onClick={()=>choose(i)}>{c.title}<small>{source(c.url)}</small></button>)}</nav>}
          <article ref={article} className="shared-article reading-surface" style={reading.style} aria-labelledby="shared-article-title">
            <Heading ref={heading} tabIndex={-1} id="shared-article-title"><HighlightedText text={selected.title} terms={terms}/></Heading>
            <div ref={toolsRef} className="shared-statusbar" onClick={e=>{const target=e.target as HTMLElement;if(target.closest('.reading-controls button'))closeTools();if(target.closest('.shared-source-menu a'))closeTools();if(target.closest('.shared-marker-actions button'))closeTools();}} onChange={e=>{if((e.target as HTMLElement).closest('.reading-controls'))closeTools();}} role="region" aria-label="Lesewerkzeuge">
              <div className="shared-tool-row"><button className="text-btn" aria-expanded={searchOpen} aria-controls="shared-fulltext-search" onClick={()=>{activate('search');setSearchOpen(!searchOpen);}}><Search size={18}/>{!searchOpen&&query.trim()?`Suche · ${hitCount}`:'Suchen'}</button><div className="shared-font-tool" onClick={e=>{if((e.target as HTMLElement).closest('summary'))activate('font');}}><ReadingControls settings={reading} label="Schrift"/></div><button className="text-btn" aria-pressed={showMarks} onClick={()=>{activate('marker');setShowMarks(!showMarks);}}><Highlighter size={18}/>Textmarker</button><details className="shared-source-menu" onClick={e=>{if((e.target as HTMLElement).closest('summary'))activate('source');}}><summary><ExternalLink size={18}/>Quelle</summary><div className="shared-source-actions">{selected.url&&<a className="source-link" href={selected.url} target="_blank" rel="noopener noreferrer">Original öffnen</a>}{selected.type==='pdf'&&<a className="source-link" href={`/api/shared?token=${encodeURIComponent(token)}&id=${encodeURIComponent(selected.id)}&pdf=1`} target="_blank" rel="noopener noreferrer">Original-PDF öffnen</a>}{selected.archive_key&&selected.type!=='pdf'&&<a className="source-link" href={`/api/shared?token=${encodeURIComponent(token)}&id=${encodeURIComponent(selected.id)}&archive=1`} target="_blank" rel="noopener noreferrer">Originalkopie öffnen</a>}</div></details></div>
              {searchOpen&&<div id="shared-fulltext-search" className="shared-find"><form onSubmit={e=>{e.preventDefault();moveHit(1);}}><label className="sr-only" htmlFor="shared-search-input">Freigegebene Inhalte durchsuchen</label><input ref={searchInput} id="shared-search-input" type="search" maxLength={200} placeholder={multiple?'In dieser Freigabe suchen …':'Im Clip suchen …'} value={query} onChange={e=>setQuery(e.target.value)}/>{query&&<button type="button" className="icon-btn" aria-label="Suche löschen" onClick={()=>setQuery('')}><X size={18}/></button>}</form>{query.trim()&&<><div className="shared-hit-controls"><span role="status">{hitCount?(hitIndex<0?`${hitCount} Treffer im Clip`:`Treffer ${hitIndex+1} von ${hitCount}`):'Keine Treffer im geöffneten Clip'}</span><button className="icon-btn" aria-label="Vorheriger Treffer" disabled={!hitCount} onClick={()=>moveHit(hitIndex<0?1:-1)}><ChevronUp size={19}/></button><button className="icon-btn" aria-label="Nächster Treffer" disabled={!hitCount} onClick={()=>moveHit(1)}><ChevronDown size={19}/></button></div>{multiple&&<label className="shared-search-results">{matching.length} passende Clips<select aria-label="Suchergebnis öffnen" value={matching.some(m=>m.i===index)?String(index):''} onChange={e=>choose(Number(e.target.value))}><option value="" disabled>Clip mit Treffern auswählen</option>{matching.map(({c,i})=><option key={c.id} value={i}>{c.title}</option>)}</select></label>}</>}</div>}
              <div ref={setMarkerTarget} className="shared-marker-slot"/>{showMarks&&<p className="shared-marker-help">Text auswählen und markieren. Gelbe Stelle antippen zum Entfernen. Deine Markierungen bleiben nur in diesem Browser.</p>}
            </div><p className="shared-provenance">{selected.type==='pdf'?'PDF-Dokument':source(selected.url)} · {selected.type==='pdf'?'Gespeicherte Originaldatei':selected.archive_key?'Gespeicherter Seitentext':'Gespeicherter Clip'}{!multiple&&' · Zum Lesen freigegeben'}</p>
            {selected.type==='pdf'&&<><PdfMetadata value={selected.pdf}/><Suspense fallback={<p role="status">PDF-Vorschau wird geladen …</p>}><PdfDocument key={selected.id} src={`/api/shared?token=${encodeURIComponent(token)}&id=${encodeURIComponent(selected.id)}&pdf=1`}/></Suspense></>}
            {content?<div className="article-content"><TextMarker key={selected.id+':content'} clipId={selected.id} section="content" local toolbarTarget={markerTarget} listOpen={showMarks} onListToggle={setShowMarks} onAction={closeTools}><RichText text={content} format={selected.content_format} terms={terms}/></TextMarker></div>:<p className="shared-link-only">{selected.archive_key?'Kein separater Lesetext gespeichert. Öffne die gespeicherte Seitenkopie, um den Inhalt zu lesen.':'Dieser Clip speichert einen Link zur Quelle. Über „Original öffnen“ kannst du ihn lesen.'}</p>}
            {selected.note&&<aside className="note"><h3>Notiz zum Clip</h3><TextMarker key={selected.id+':note'} clipId={selected.id} section="note" local toolbarTarget={markerTarget} listOpen={showMarks} onListToggle={setShowMarks} onAction={closeTools}><RichText text={selected.note} format={selected.note_format} terms={terms}/></TextMarker></aside>}
            {multiple&&<nav className="shared-reading-navigation" aria-label="Weiterlesen in der Sammlung"><p>Clip {index+1} von {clips.length}</p><div><button className="secondary" disabled={index===0} onClick={()=>choose(index-1)}><ArrowLeft size={16} aria-hidden="true"/> Vorheriger</button><button className="secondary" disabled={index===clips.length-1} onClick={()=>choose(index+1)}>Nächster <ArrowRight size={16} aria-hidden="true"/></button></div><a href="#shared-clip-selection" className="shared-return" onClick={e=>{e.preventDefault();const selector=document.querySelector<HTMLSelectElement>('.shared-mobile-selector select');const list=document.querySelector<HTMLButtonElement>('.shared-desktop-list [aria-current]');const target=window.matchMedia('(max-width: 720px)').matches?selector:list;target?.focus({preventScroll:true});target?.scrollIntoView({block:'center'});}}>Zur Clipauswahl</a></nav>}
          </article>
        </div>
      </>}
    </main>
  </div>;
}
