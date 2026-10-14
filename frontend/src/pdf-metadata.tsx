import {HighlightedText} from './search-matches';
export default function PdfMetadata({value,terms=[]}:{value:any;terms?:string[]}){
 if(!value)return null;const date=(v:string)=>{const m=/^D:(\d{4})(\d{2})(\d{2})/.exec(v||'');return m?`${m[3]}.${m[2]}.${m[1]}`:v;};
 const coverage=value.coverageKnown?(value.textPages===value.pages?'Lesetext für alle Seiten vorhanden':`Lesetext für ${value.textPages} von ${value.pages} Seiten vorhanden`):'Seitenabdeckung unbekannt';
 return <dl className="pdf-metadata">{[['Durchsuchbarkeit',coverage],['Hinweis',value.coverageKnown&&value.textPages<value.pages?'Seiten ohne Lesetext können leer sein oder Scans enthalten. Bilder werden nicht durchsucht.':null],['Datei',value.filename],['Umfang',`${value.pages} Seiten · ${Math.max(.01,value.bytes/1024/1024).toLocaleString('de-DE',{maximumFractionDigits:2})} MB`],['Dokumenttitel',value.title],['Autor',value.author],['Thema',value.subject],['Schlagwörter',value.keywords],['Erstellt',date(value.creationDate)],['Geändert',date(value.modificationDate)]].filter(([,v])=>v).map(([key,v])=><div key={key}><dt>{key}</dt><dd><HighlightedText text={String(v)} terms={terms}/></dd></div>)}</dl>;
}
