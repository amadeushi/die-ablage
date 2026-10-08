<?php
declare(strict_types=1);
function library_page(array $params): array {
 $q=trim((string)($params['q']??''));if(mb_strlen($q)>300)problem('Bitte einen kürzeren Suchbegriff verwenden.');
 $view=(string)($params['view']??'all');$type=(string)($params['type']??'all');$page=max(0,min(100000,(int)($params['page']??0)));$size=40;
 $terms=search_terms($q);$score=[];$scoreArgs=[];
 $where=[];$args=[];
 if(!in_array($view,['all','shared'],true)){$where[]='c.collection_id=?';$args[]=$view;}
 if($view==='shared')$where[]="EXISTS (SELECT 1 FROM shares s WHERE (s.kind='clip' AND s.target_id=c.id) OR (s.kind='collection' AND s.target_id=c.collection_id))";
 if($type!=='all'){if(!in_array($type,['article','page','link','pdf'],true))problem('Bitte die Clip-Art prüfen.');$where[]='c.type=?';$args[]=$type;}
 foreach(['positive','negative'] as $kind)foreach($terms[$kind] as $term){$where[]=($kind==='negative'?'NOT ':'').search_condition();$pattern=search_pattern($term);array_push($args,$pattern,$pattern,$pattern,$pattern,$pattern);if($kind==='positive'){foreach(['title'=>12,'note'=>4,'content'=>3,'url'=>1] as $field=>$weight){$score[]="CASE WHEN c.$field LIKE ? ESCAPE '!' THEN $weight ELSE 0 END";$scoreArgs[]=$pattern;}$score[]="CASE WHEN EXISTS (SELECT 1 FROM clip_tags t WHERE t.clip_id=c.id AND t.tag LIKE ? ESCAPE '!') THEN 8 ELSE 0 END";$scoreArgs[]=$pattern;}}
 $tag=trim((string)($params['tag']??''));if(mb_strlen($tag)>80)problem('Bitte den Tag prüfen.');if($tag!==''){$where[]='EXISTS (SELECT 1 FROM clip_tags t WHERE t.clip_id=c.id AND t.tag=?)';$args[]=$tag;}
 $author=trim((string)($params['author']??''));if(mb_strlen($author)>190)problem('Bitte den Ersteller prüfen.');if($author!==''){$where[]='c.author=?';$args[]=$author;}
 $source=mb_strtolower(trim((string)($params['source']??'')));if($source!==''&&(strlen($source)>253||!preg_match('/^[a-z0-9.\-:\[\]]+$/D',$source)))problem('Bitte die Quelle prüfen.');
 if($source!==''){$parts=[];foreach(['https://','http://'] as $scheme){$parts[]='LOWER(c.url)=?';$args[]=$scheme.$source;foreach(['/','?', '#',':'] as $boundary){$parts[]="LOWER(c.url) LIKE ? ESCAPE '!'";$args[]=$scheme.$source.$boundary.'%';}}$where[]='('.implode(' OR ',$parts).')';}
 $dates=[];foreach(['from','until'] as $key){$v=(string)($params[$key]??'');if($v==='')continue;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d')!==$v)problem('Bitte ein gültiges Datum wählen.');$dates[$key]=$date;if($key==='until')$date=$date->modify('+1 day');$where[]='c.created_at'.($key==='from'?'>=':'<').'?';$args[]=$date->getTimestamp()*1000;}
 if(isset($dates['from'],$dates['until'])&&$dates['from']>$dates['until'])problem('Das Enddatum muss nach dem Startdatum liegen.');
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 $total=(int)sql('SELECT COUNT(*) FROM clips c'.$filter,$args)->fetchColumn();
 $page=min($page,max(0,(int)ceil($total/$size)-1));
 $substring=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'SUBSTRING':'SUBSTR';
 $rank=$score?'('.implode('+',$score).')':'0';
 $items=sql('SELECT '.$rank.' AS relevance,c.id,c.title,c.url,c.type,'.$substring.'(c.content,1,220) AS summary,'.($q!==''?'c.content,c.note,':'').'c.collection_id,c.author,c.archive_key,c.created_at FROM clips c'.$filter.' ORDER BY relevance DESC,c.created_at DESC,c.id DESC LIMIT '.$size.' OFFSET '.($page*$size),array_merge($scoreArgs,$args))->fetchAll();
 $counts=[];foreach(sql('SELECT collection_id,COUNT(*) AS total FROM clips GROUP BY collection_id')->fetchAll() as $row)$counts[$row['collection_id']??'']=(int)$row['total'];
  $clips=[];foreach($items as $item){$clip=public_clip($item);if($q!==''){$item['tags']=$clip['tags']??[];$clip['hit']=search_hit($item,$q);unset($clip['content'],$clip['note']);}$clips[]=$clip;}
 return ['clips'=>$clips,'totalResults'=>$total,'totalClips'=>(int)sql('SELECT COUNT(*) FROM clips')->fetchColumn(),'collectionCounts'=>$counts,'page'=>$page,'pageSize'=>$size,'searchTerms'=>$terms['positive']];
}
