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
 $sources=[];$authors=[];foreach(sql('SELECT DISTINCT url,author FROM clips c WHERE '.active_clip_condition().'')->fetchAll() as $c){$host=mb_strtolower(parse_url($c['url'],PHP_URL_HOST)?:'');if($host)$sources[$host]=true;$authors[$c['author']]=true;}
 $sources=array_keys($sources);$authors=array_keys($authors);sort($sources);sort($authors);return ['tagSuggestions'=>sql('SELECT DISTINCT t.tag FROM clip_tags t JOIN clips c ON c.id=t.clip_id WHERE '.active_clip_condition().' ORDER BY t.tag')->fetchAll(PDO::FETCH_COLUMN),'sourceOptions'=>$sources,'authorOptions'=>$authors];
}
function search_hit(array $c,string $q): ?array {return search_first_hit($c,search_terms($q)['positive']);}
