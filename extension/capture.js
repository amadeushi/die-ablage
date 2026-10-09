/* Runs in Chrome's isolated world; never imports page scripts or credentials. */
(()=>{
const tags=new Set('P DIV SECTION ARTICLE MAIN HEADER FOOTER NAV ASIDE H1 H2 H3 H4 H5 H6 UL OL LI BLOCKQUOTE PRE CODE TABLE THEAD TBODY TR TH TD STRONG EM B I SPAN BR HR FIGURE FIGCAPTION'.split(' '));
const drop=new Set('SCRIPT STYLE NOSCRIPT IFRAME OBJECT EMBED FORM INPUT TEXTAREA SELECT BUTTON META LINK TEMPLATE SVG CANVAS VIDEO AUDIO'.split(' '));
function capture(type,regions){
 let total=0;
 function clone(node){
  if(++total>40000)throw Error('Die Auswahl ist zu groß. Bitte weniger Bereiche auswählen.');
  if(node.nodeType===Node.TEXT_NODE)return document.createTextNode(node.textContent||'');
  if(node.nodeType!==Node.ELEMENT_NODE||drop.has(node.tagName)||node.hidden||node.getAttribute('aria-hidden')==='true'||node.hasAttribute('data-ablage-picker'))return document.createTextNode('');
  const css=getComputedStyle(node);if(css.display==='none'||css.visibility==='hidden')return document.createTextNode('');
  if(node.tagName==='IMG'){
   try{if(node.complete&&node.naturalWidth>0){const canvas=document.createElement('canvas'),scale=Math.min(1,1000/node.naturalWidth,Math.sqrt(1500000/(node.naturalWidth*node.naturalHeight)));canvas.width=Math.max(1,Math.round(node.naturalWidth*scale));canvas.height=Math.max(1,Math.round(node.naturalHeight*scale));canvas.getContext('2d').drawImage(node,0,0,canvas.width,canvas.height);const img=document.createElement('img');img.src=canvas.toDataURL('image/jpeg',.7);img.alt=node.alt||'';return img;}}catch{}
   return document.createTextNode('[Bild nicht kopierbar'+(node.alt?': '+node.alt:'')+']');
  }
  const el=document.createElement(tags.has(node.tagName)?node.tagName.toLowerCase():'span');for(const child of node.childNodes)el.appendChild(clone(child));return el;
 }
 const url=location.href,title=document.title;
 if(type==='link')return{url,title,type,content:'',note:'',archive:''};
 const selection=!regions&&type==='article'?getSelection()?.toString().trim():'';
 const roots=regions||[type==='article'?(document.querySelector('article')||document.querySelector('main')||document.body):document.body];
 if(roots.some(node=>!node.isConnected))throw Error('Die Seite hat sich verändert. Bitte die Bereiche erneut auswählen.');
 const wrapper=document.createElement('div');roots.forEach(node=>wrapper.appendChild(clone(node)));
 // Read only the sanitized copy, including image alternatives, never form values.
 const holder=document.createElement('div');holder.dataset.ablagePicker='';holder.style.cssText='position:fixed;left:-100000px;top:0;width:800px;pointer-events:none';const textCopy=wrapper.cloneNode(true);for(const img of textCopy.querySelectorAll('img'))img.replaceWith(document.createTextNode('[Bild'+(img.alt?': '+img.alt:'')+']'));holder.appendChild(textCopy);document.documentElement.appendChild(holder);
 let content;try{content=selection||textCopy.innerText;}finally{holder.remove();}
 // Preserve semantic blocks as Markdown; selected plain text keeps its original format.
 const escape=text=>text.replace(/\\/g,'\\\\').replace(/([`*_{}\[\]<>#!|~])/g,'\\$1').replace(/^(\s*)(\d+)\./gm,'$1$2\\.');
 function markdown(node,depth=0){
  if(depth>40)return '';
  if(node.nodeType===Node.TEXT_NODE)return escape((node.textContent||'').replace(/\s+/g,' '));
  const children=()=>Array.from(node.childNodes).map(n=>markdown(n,depth+1)).join('');const name=node.tagName;
  if(/^H[1-6]$/.test(name))return '\n\n'+'#'.repeat(Math.min(6,Math.max(2,Number(name.slice(1)))))+' '+children().trim()+'\n\n';
  if(name==='P')return '\n\n'+children().trim()+'\n\n';
  if(name==='BR')return '  \n';
  if(name==='STRONG'||name==='B')return '**'+children().trim()+'**';
  if(name==='EM'||name==='I')return '*'+children().trim()+'*';
  if(name==='PRE'){const text=node.textContent||'';const fence='`'.repeat(Math.max(3,...(text.match(/`+/g)||[]).map(s=>s.length+1)));return '\n\n'+fence+'\n'+text+'\n'+fence+'\n\n';}
  if(name==='CODE')return '`'+(node.textContent||'').replace(/`/g,"'")+'`';
  if(name==='BLOCKQUOTE')return '\n\n'+children().trim().split('\n').map(s=>'> '+s).join('\n')+'\n\n';
  if(name==='UL'||name==='OL')return '\n\n'+Array.from(node.children).filter(n=>n.tagName==='LI').map((n,i)=>(name==='OL'?(i+1)+'. ':'- ')+markdown(n,depth+1).trim().replace(/\n/g,'\n  ')).join('\n')+'\n\n';
  if(name==='HR')return '\n\n---\n\n';
  if(name==='IMG')return '\n\n'+escape('[Bild'+(node.alt?': '+node.alt:'')+']')+'\n\n';
  const text=children();return ['DIV','SECTION','ARTICLE','MAIN','HEADER','FOOTER','FIGURE','FIGCAPTION','TR'].includes(name)?'\n\n'+text.trim()+'\n\n':text;
 }
 const contentFormat=selection?'text':'markdown';if(!selection)content=markdown(wrapper).replace(/\n{3,}/g,'\n\n').trim();
 const archive=regions||type==='page'||(!selection&&wrapper.querySelector('img'))?wrapper.outerHTML:'';
 if(new TextEncoder().encode(content).length>1000000||new TextEncoder().encode(archive).length>5000000)throw Error('Der Inhalt ist zu groß. Bitte weniger Bereiche auswählen.');
 return{url,title,type,content,contentFormat,note:'',archive};
}
globalThis.AblageCapture={capture};
})();
