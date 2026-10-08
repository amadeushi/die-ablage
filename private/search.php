<?php
declare(strict_types=1);
function search_terms(string $q): array {
 if(mb_strlen($q)>300)problem('Bitte einen kürzeren Suchbegriff verwenden.');
 if(str_contains($q,'""'))problem('Bitte zwischen den Anführungszeichen eine Wortfolge eingeben.');
 if(substr_count($q,'"')%2)problem('Bitte die Wortfolge mit einem zweiten Anführungszeichen abschließen.');
 preg_match_all('/(-?)(?:"([^"\r\n]+)"|([^\s"]+))/u',$q,$matches,PREG_SET_ORDER);$positive=[];$negative=[];
 foreach($matches as $m){$term=trim($m[2]?:($m[3]??''));if($term===''||$term==='-')problem('Bitte nach dem Minuszeichen einen Suchbegriff angeben.');$key=mb_strtolower($term);if($m[1]==='-')$negative[$key]=$term;else $positive[$key]=$term;}
 if(count($positive)+count($negative)>12)problem('Bitte maximal zwölf Suchbegriffe verwenden.');return ['positive'=>array_values($positive),'negative'=>array_values($negative)];
}
function search_pattern(string $term): string {return '%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';}
function search_condition(): string {return "(c.title LIKE ? ESCAPE '!' OR c.url LIKE ? ESCAPE '!' OR c.content LIKE ? ESCAPE '!' OR c.note LIKE ? ESCAPE '!' OR EXISTS (SELECT 1 FROM clip_tags t WHERE t.clip_id=c.id AND t.tag LIKE ? ESCAPE '!'))";}
function search_first_hit(array $c,array $terms): ?array {
 foreach(['title'=>'Titel','content'=>'Inhalt','note'=>'Notiz','url'=>'Quelle'] as $field=>$label){$text=$c[$field]??'';if($field==='content'&&$c['type']==='pdf'){$prefix=pdf_index_prefix(pdf_metadata($c['archive_key']??''));if(str_starts_with($text,$prefix))$text=substr($text,strlen($prefix));}
 $pos=null;$term='';foreach($terms as $candidate){$found=mb_stripos($text,$candidate);if($found!==false&&($pos===null||$found<$pos)){$pos=$found;$term=$candidate;}}if($pos===null)continue;$start=max(0,$pos-70);$page=null;if($field==='content'&&$c['type']==='pdf'){preg_match_all('/(?:^|\n)Seite (\d+)\n/u',mb_substr($text,0,$pos),$pages);$page=$pages[1]?(int)end($pages[1]):null;}
 return ['label'=>$label,'before'=>($start?'…':'').mb_substr($text,$start,$pos-$start),'match'=>mb_substr($text,$pos,mb_strlen($term)),'after'=>mb_substr($text,$pos+mb_strlen($term),130).(mb_strlen($text)>$pos+mb_strlen($term)+130?'…':''),'page'=>$page];}
 foreach($c['tags']??[] as $tag)foreach($terms as $term)if(mb_stripos($tag,$term)!==false)return ['label'=>'Tag','before'=>'','match'=>$tag,'after'=>'','page'=>null];
 if($c['type']==='pdf')foreach(pdf_metadata($c['archive_key']??'') as $value)foreach($terms as $term)if(is_string($value)&&mb_stripos($value,$term)!==false)return ['label'=>'Dokumentangaben','before'=>'','match'=>$value,'after'=>'','page'=>null];return null;
}
