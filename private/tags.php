<?php
declare(strict_types=1);
function clip_tags(string $id): array {return sql('SELECT tag FROM clip_tags WHERE clip_id=? ORDER BY tag',[$id])->fetchAll(PDO::FETCH_COLUMN);}
function tags_value(array $v): array {
 $raw=$v['tags']??[];if(!is_array($raw)||count($raw)>12)problem('Bitte maximal 12 Tags verwenden.');$tags=[];
 foreach($raw as $tag){if(!is_string($tag))problem('Bitte die Tags prüfen.');$tag=preg_replace('/\s+/u',' ',trim($tag));if(!$tag)continue;if(mb_strlen($tag)>80||preg_match('/[\x00-\x1f\x7f,]/u',$tag))problem('Tags dürfen bis zu 80 Zeichen lang sein und keine Kommas enthalten.');$tags[mb_strtolower($tag)]=$tag;}
 return array_values($tags);
}
function tags_save(string $id,array $tags): void {sql('DELETE FROM clip_tags WHERE clip_id=?',[$id]);foreach($tags as $tag)sql('INSERT INTO clip_tags(clip_id,tag) VALUES(?,?)',[$id,$tag]);}
function library_options(): array {
 $sources=[];$authors=[];foreach(sql('SELECT DISTINCT url,author FROM clips')->fetchAll() as $c){$host=mb_strtolower(parse_url($c['url'],PHP_URL_HOST)?:'');if($host)$sources[$host]=true;$authors[$c['author']]=true;}
 $sources=array_keys($sources);$authors=array_keys($authors);sort($sources);sort($authors);return ['tagSuggestions'=>sql('SELECT DISTINCT tag FROM clip_tags ORDER BY tag')->fetchAll(PDO::FETCH_COLUMN),'sourceOptions'=>$sources,'authorOptions'=>$authors];
}
function search_hit(array $c,string $q): ?array {
 foreach(['title'=>'Titel','content'=>'Inhalt','note'=>'Notiz','url'=>'Quelle'] as $field=>$label){$text=$c[$field]??'';if($field==='content'&&$c['type']==='pdf'){$prefix=pdf_index_prefix(pdf_metadata($c['archive_key']??''));if(str_starts_with($text,$prefix))$text=substr($text,strlen($prefix));}$pos=mb_stripos($text,$q);if($pos===false)continue;$start=max(0,$pos-70);$page=null;if($field==='content'&&$c['type']==='pdf'){preg_match_all('/(?:^|\n)Seite (\d+)\n/u',mb_substr($text,0,$pos),$pages);$page=$pages[1]? (int)end($pages[1]):null;}
 return ['label'=>$label,'before'=>($start?'…':'').mb_substr($text,$start,$pos-$start),'match'=>mb_substr($text,$pos,mb_strlen($q)),'after'=>mb_substr($text,$pos+mb_strlen($q),130).(mb_strlen($text)>$pos+mb_strlen($q)+130?'…':''),'page'=>$page];}
 foreach($c['tags']??[] as $tag)if(mb_stripos($tag,$q)!==false)return ['label'=>'Tag','before'=>'','match'=>$tag,'after'=>'','page'=>null];
 if($c['type']==='pdf')foreach(pdf_metadata($c['archive_key']??'') as $value)if(is_string($value)&&mb_stripos($value,$q)!==false)return ['label'=>'Dokumentangaben','before'=>'','match'=>$value,'after'=>'','page'=>null];return null;
}
