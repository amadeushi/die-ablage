<?php
declare(strict_types=1);
function library_page(array $params,string $email=''): array {
 $q=trim((string)($params['q']??''));if(mb_strlen($q)>300)problem('Bitte einen kürzeren Suchbegriff verwenden.');
 $view=(string)($params['view']??'all');$type=(string)($params['type']??'all');$page=max(0,min(100000,(int)($params['page']??0)));$size=40;
 $sort=(string)($params['sort']??'relevance');if(!in_array($sort,['relevance','newest','oldest'],true))problem('Bitte die Sortierung prüfen.');
 $terms=search_terms($q);$score=[];$scoreArgs=[];
 $where=[($view==='trash'?'EXISTS':'NOT EXISTS').' (SELECT 1 FROM clip_trash tr WHERE tr.clip_id=c.id)'];$args=[];
 if($view==='favorites'){$where[]='EXISTS (SELECT 1 FROM clip_favorites f WHERE f.clip_id=c.id AND f.member_email=?)';$args[]=$email;}
 if(!in_array($view,['all','shared','favorites','trash'],true)){$where[]='c.collection_id=?';$args[]=$view;}
 if($view==='shared'){$fixed=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?"JSON_CONTAINS(o.clip_ids,JSON_QUOTE(c.id))":"EXISTS (SELECT 1 FROM json_each(o.clip_ids) WHERE value=c.id)";$dossierMatch=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?"JSON_CONTAINS(d.entries,JSON_OBJECT('clipId',c.id,'kind','document'))":"EXISTS (SELECT 1 FROM json_each(d.entries) WHERE json_extract(value,'$.clipId')=c.id AND json_extract(value,'$.kind')='document')";$where[]="EXISTS (SELECT 1 FROM shares s LEFT JOIN share_options o ON o.share_id=s.id LEFT JOIN dossiers d ON s.kind='dossier' AND d.id=s.target_id WHERE ((s.kind='dossier' AND $dossierMatch) OR (s.kind='clip' AND s.target_id=c.id) OR (s.kind='collection' AND ((o.clip_ids IS NULL AND s.target_id=c.collection_id) OR (o.clip_ids IS NOT NULL AND $fixed)))) AND (s.expires_at IS NULL OR s.expires_at>".timestamp()."))";}

 if($type!=='all'){if(!in_array($type,['article','page','link','pdf'],true))problem('Bitte die Clip-Art prüfen.');$where[]='c.type=?';$args[]=$type;}
 if($terms['expression'])$where[]=search_expression_sql($terms['expression'],$args);
 foreach($terms['positive'] as $term){$pattern=search_pattern($term);foreach(['title'=>12,'note'=>4,'content'=>3,'url'=>1] as $field=>$weight){$score[]="CASE WHEN ".search_sql_text("c.$field")." LIKE ? ESCAPE '!' THEN $weight ELSE 0 END";$scoreArgs[]=$pattern;}$score[]="CASE WHEN EXISTS (SELECT 1 FROM clip_tags t WHERE t.clip_id=c.id AND ".search_sql_text('t.tag')." LIKE ? ESCAPE '!') THEN 8 ELSE 0 END";$scoreArgs[]=$pattern;}
 if($terms['positive']){$phrase=implode(' ',$terms['positive']);$score[]="CASE WHEN ".search_sql_text('c.title')."=? THEN 40 WHEN ".search_sql_text('c.title')." LIKE ? ESCAPE '!' THEN 20 ELSE 0 END";array_push($scoreArgs,search_normalize($phrase),search_pattern($phrase));}

 $tag=trim((string)($params['tag']??''));if(mb_strlen($tag)>80)problem('Bitte den Tag prüfen.');if($tag!==''){$where[]='EXISTS (SELECT 1 FROM clip_tags t WHERE t.clip_id=c.id AND t.tag=?)';$args[]=$tag;}
 $author=trim((string)($params['author']??''));if(mb_strlen($author)>190)problem('Bitte den Ersteller prüfen.');if($author!==''){$where[]='c.author=?';$args[]=$author;}
 $source=mb_strtolower(trim((string)($params['source']??'')));if($source!==''&&(strlen($source)>253||!preg_match('/^[a-z0-9.\-:\[\]]+$/D',$source)))problem('Bitte die Quelle prüfen.');
 if($source!==''){$parts=[];foreach(['https://','http://'] as $scheme){$parts[]='LOWER(c.url)=?';$args[]=$scheme.$source;foreach(['/','?', '#',':'] as $boundary){$parts[]="LOWER(c.url) LIKE ? ESCAPE '!'";$args[]=$scheme.$source.$boundary.'%';}}$where[]='('.implode(' OR ',$parts).')';}
 $dates=[];foreach(['from','until'] as $key){$v=(string)($params[$key]??'');if($v==='')continue;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d')!==$v)problem('Bitte ein gültiges Datum wählen.');$dates[$key]=$date;if($key==='until')$date=$date->modify('+1 day');$where[]='c.created_at'.($key==='from'?'>=':'<').'?';$args[]=$date->getTimestamp()*1000;}
 if(isset($dates['from'],$dates['until'])&&$dates['from']>$dates['until'])problem('Das Enddatum muss nach dem Startdatum liegen.');
 $searchJoin=$q!==''?' JOIN search_index si ON si.id=c.id':'';
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 $total=(int)sql('SELECT COUNT(*) FROM clips c'.$searchJoin.$filter,$args)->fetchColumn();
 $page=min($page,max(0,(int)ceil($total/$size)-1));
 $substring=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'SUBSTRING':'SUBSTR';
 $rank=$score?'('.implode('+',$score).')':'0';
 $order=$sort==='oldest'?'c.created_at ASC,c.id ASC':($sort==='newest'?'c.created_at DESC,c.id DESC':'relevance DESC,c.created_at DESC,c.id DESC');
 $items=sql('SELECT '.$rank.' AS relevance,(SELECT COUNT(*) FROM clip_favorites f WHERE f.clip_id=c.id AND f.member_email=?) AS favorite,(SELECT deleted_at FROM clip_trash tr WHERE tr.clip_id=c.id) AS deleted_at,c.id,c.title,c.url,c.type,c.content_format,c.note_format,'.$substring.'(c.content,1,220) AS summary,'.($q!==''?'c.content,c.note,':'').'c.collection_id,c.author,c.archive_key,c.created_at FROM clips c'.$searchJoin.$filter.' ORDER BY '.$order.' LIMIT '.$size.' OFFSET '.($page*$size),array_merge($scoreArgs,[$email],$args))->fetchAll();
 $counts=[];foreach(sql('SELECT collection_id,COUNT(*) AS total FROM clips c WHERE '.active_clip_condition().' GROUP BY collection_id')->fetchAll() as $row)$counts[$row['collection_id']??'']=(int)$row['total'];
  $clips=[];foreach($items as $item){$clip=public_clip($item);if($q!==''){$item['tags']=$clip['tags']??[];$clip['hit']=search_hit($item,$q);unset($clip['content'],$clip['note']);}$clips[]=$clip;}
 return ['clips'=>$clips,'totalResults'=>$total,'totalClips'=>(int)sql('SELECT COUNT(*) FROM clips c WHERE '.active_clip_condition())->fetchColumn(),'collectionCounts'=>$counts,'page'=>$page,'pageSize'=>$size,'searchTerms'=>$terms['positive']];
}
