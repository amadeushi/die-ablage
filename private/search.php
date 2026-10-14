<?php
declare(strict_types=1);
require_once __DIR__.'/search-index.php';
function search_terms(string $q):array {
 if(mb_strlen($q)>300)problem('Bitte einen kürzeren Suchbegriff verwenden.');
 $q=search_join_words(str_replace(['„','“','”'],'"',$q));
 if(substr_count($q,'"')%2)problem('Bitte die Wortfolge mit einem zweiten Anführungszeichen abschließen.');
 preg_match_all('/(-?)"([^"\r\n]*)"|([()])|([^\s()"]+)/u',$q,$matches,PREG_SET_ORDER);$tokens=[];$positive=[];$negative=[];$leaves=0;
 foreach($matches as $m){$quoted=isset($m[2])&&($m[0][0]==='"'||str_starts_with($m[0],'-"'));$raw=$quoted?$m[2]:($m[3]?:($m[4]??''));if($quoted&&trim($raw)==='')problem('Bitte zwischen den Anführungszeichen eine Wortfolge eingeben.');
  if(!$quoted&&in_array($raw,['(',')','AND','UND','OR','ODER'],true)){$tokens[]=['op'=>match($raw){'AND','UND'=>'and','OR','ODER'=>'or',default=>$raw}];continue;}
  $exclude=$quoted?$m[1]==='-':str_starts_with($raw,'-');$term=trim(!$quoted&&$exclude?substr($raw,1):$raw);if($term==='')problem('Bitte einen Suchbegriff nach dem Minuszeichen eingeben.');if(++$leaves>12)problem('Bitte maximal zwölf Suchbegriffe verwenden.');
  $tokens[]=['term'=>$term,'negative'=>$exclude];$key=search_normalize($term);if($exclude)$negative[$key]=$term;else $positive[$key]=$term;
 }
 $i=0;$factor=null;$and=null;$or=null;
 $factor=function(int $depth=0)use(&$factor,&$or,&$i,$tokens):array{if($depth>8)problem('Bitte weniger verschachtelte Klammern verwenden.');$t=$tokens[$i]??null;if(!$t)problem('Bitte einen Suchbegriff nach dem Operator eingeben.');if(isset($t['term'])){$i++;return $t;}if(($t['op']??'')==='('){$i++;$node=$or($depth+1);if(($tokens[$i]['op']??'')!==')')problem('Bitte die Suchklammer schließen.');$i++;return $node;}problem('Bitte die UND/ODER-Verknüpfung prüfen.');};
 $and=function(int $depth=0)use(&$factor,&$i,$tokens):array{$nodes=[$factor($depth)];while($i<count($tokens)&&!in_array($tokens[$i]['op']??'',['or',')'],true)){if(($tokens[$i]['op']??'')==='and')$i++;$nodes[]=$factor($depth);}return count($nodes)===1?$nodes[0]:['op'=>'and','nodes'=>$nodes];};
 $or=function(int $depth=0)use(&$and,&$i,$tokens):array{$nodes=[$and($depth)];while(($tokens[$i]['op']??'')==='or'){$i++;$nodes[]=$and($depth);}return count($nodes)===1?$nodes[0]:['op'=>'or','nodes'=>$nodes];};
 $expression=$tokens?$or():null;if($i!==count($tokens))problem('Bitte die Suchklammern prüfen.');return ['positive'=>array_values($positive),'negative'=>array_values($negative),'expression'=>$expression];
}
function search_sql_text(string $field):string {
 if(str_starts_with($field,'c.'))return 'si.'.substr($field,2);
 $pairs=[["-\r\n",''],["-\n",''],["\u{00ad}",''],['Ä','ae'],['ä','ae'],['Ö','oe'],['ö','oe'],['Ü','ue'],['ü','ue'],['ẞ','ss'],['ß','ss'],['ﬀ','ff'],['ﬁ','fi'],['ﬂ','fl'],['ﬃ','ffi'],['ﬄ','ffl'],['‐','-'],['‑','-'],["\r",' '],["\n",' '],["\t",' '],["\u{00a0}",' ']];
 foreach($pairs as [$from,$to])$field="REPLACE($field,'".str_replace("'","''",$from)."','$to')";
 for($i=0;$i<6;$i++)$field="REPLACE($field,'  ',' ')";return "LOWER($field)";
}
function search_pattern(string $term):string{return '%'.str_replace(['!','%','_'],['!!','!%','!_'],search_normalize($term)).'%';}
function search_condition():string{$fields=[];foreach(['c.title','c.url','c.content','c.note'] as $field)$fields[]=search_sql_text($field)." LIKE ? ESCAPE '!'";$fields[]="EXISTS (SELECT 1 FROM clip_tags t WHERE t.clip_id=c.id AND ".search_sql_text('t.tag')." LIKE ? ESCAPE '!')";return '('.implode(' OR ',$fields).')';}
function search_expression_sql(array $node,array &$args):string {
 if(isset($node['term'])){$pattern=search_pattern($node['term']);array_push($args,$pattern,$pattern,$pattern,$pattern,$pattern);return (!empty($node['negative'])?'NOT ':'').search_condition();}
 $parts=[];foreach($node['nodes'] as $child)$parts[]=search_expression_sql($child,$args);return '('.implode($node['op']==='or'?' OR ':' AND ',$parts).')';
}
function search_regex(string $term):string {
 $term=search_normalize($term);$chars=preg_split('//u',$term,-1,PREG_SPLIT_NO_EMPTY);$parts=[];$join='(?:[ \t]*[-‐‑]\r?\n[ \t]*|\x{00ad})*';
 for($i=0;$i<count($chars);$i++){$pair=$chars[$i].($chars[$i+1]??'');$triple=$pair.($chars[$i+2]??'');if(in_array($triple,['ffi','ffl'],true)){$last=$chars[$i+2];$parts[]='(?:f'.$join.'f'.$join.$last.'|'.($triple==='ffi'?'ﬃ':'ﬄ').'|ﬀ'.$join.$last.'|f'.$join.($last==='i'?'ﬁ':'ﬂ').')';$i+=2;continue;}if(in_array($pair,['ff','fi','fl'],true)){$parts[]='(?:'.$chars[$i].$join.$chars[++$i].'|'.['ff'=>'ﬀ','fi'=>'ﬁ','fl'=>'ﬂ'][$pair].')';continue;}if(in_array($pair,['ae','oe','ue','ss'],true)){$special=['ae'=>'ä','oe'=>'ö','ue'=>'ü','ss'=>'ß'][$pair];$parts[]='(?:'.preg_quote($chars[$i],'~').$join.preg_quote($chars[++$i],'~').'|'.$special.')';}else $parts[]=$chars[$i]===' '?'\s+':($chars[$i]==='-'?'[-‐‑]':preg_quote($chars[$i],'~'));}
 return '~'.implode($join,$parts).'~iu';
}
function search_first_hit(array $c,array $terms):?array {
 foreach(['title'=>'Titel','content'=>'Inhalt','note'=>'Notiz','url'=>'Quelle'] as $field=>$label){$text=$c[$field]??'';if($field==='content'&&$c['type']==='pdf'){$prefix=pdf_index_prefix(pdf_metadata($c['archive_key']??''));if(str_starts_with($text,$prefix))$text=substr($text,strlen($prefix));}
 $pos=null;$match='';foreach($terms as $candidate)if(preg_match(search_regex($candidate),$text,$found,PREG_OFFSET_CAPTURE)){$offset=mb_strlen(substr($text,0,$found[0][1]));if($pos===null||$offset<$pos){$pos=$offset;$match=$found[0][0];}}if($pos===null)continue;$start=max(0,$pos-70);$page=null;if($field==='content'&&$c['type']==='pdf'){preg_match_all('/(?:^|\n)Seite (\d+)\n/u',mb_substr($text,0,$pos),$pages);$page=$pages[1]?(int)end($pages[1]):null;}
 return ['label'=>$label,'before'=>($start?'…':'').mb_substr($text,$start,$pos-$start),'match'=>$match,'after'=>mb_substr($text,$pos+mb_strlen($match),130).(mb_strlen($text)>$pos+mb_strlen($match)+130?'…':''),'page'=>$page];}
 foreach($c['tags']??[] as $tag)foreach($terms as $term)if(preg_match(search_regex($term),$tag))return ['label'=>'Tag','before'=>'','match'=>$tag,'after'=>'','page'=>null];
 if($c['type']==='pdf')foreach(pdf_metadata($c['archive_key']??'') as $value)foreach($terms as $term)if(is_string($value)&&preg_match(search_regex($term),$value))return ['label'=>'Dokumentangaben','before'=>'','match'=>$value,'after'=>'','page'=>null];return null;
}
