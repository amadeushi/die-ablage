<?php
declare(strict_types=1);
function library_page(array $params): array {
 $q=trim((string)($params['q']??''));if(mb_strlen($q)>300)problem('Bitte einen kürzeren Suchbegriff verwenden.');
 $view=(string)($params['view']??'all');$type=(string)($params['type']??'all');$page=max(0,min(100000,(int)($params['page']??0)));$size=40;
 $where=[];$args=[];
 if(!in_array($view,['all','shared'],true)){$where[]='c.collection_id=?';$args[]=$view;}
 if($view==='shared')$where[]="EXISTS (SELECT 1 FROM shares s WHERE (s.kind='clip' AND s.target_id=c.id) OR (s.kind='collection' AND s.target_id=c.collection_id))";
 if($type!=='all'){if(!in_array($type,['article','page','link','pdf'],true))problem('Bitte die Clip-Art prüfen.');$where[]='c.type=?';$args[]=$type;}
 if($q!==''){$search='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';$where[]="(c.title LIKE ? ESCAPE '!' OR c.url LIKE ? ESCAPE '!' OR c.content LIKE ? ESCAPE '!' OR c.note LIKE ? ESCAPE '!')";array_push($args,$search,$search,$search,$search);}
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 $total=(int)sql('SELECT COUNT(*) FROM clips c'.$filter,$args)->fetchColumn();
 $page=min($page,max(0,(int)ceil($total/$size)-1));
 $substring=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'SUBSTRING':'SUBSTR';
 $items=sql('SELECT c.id,c.title,c.url,c.type,'.$substring.'(c.content,1,220) AS summary,c.collection_id,c.author,c.archive_key,c.created_at FROM clips c'.$filter.' ORDER BY c.created_at DESC,c.id DESC LIMIT '.$size.' OFFSET '.($page*$size),$args)->fetchAll();
 $counts=[];foreach(sql('SELECT collection_id,COUNT(*) AS total FROM clips GROUP BY collection_id')->fetchAll() as $row)$counts[$row['collection_id']??'']=(int)$row['total'];
 return ['clips'=>array_map('public_clip',$items),'totalResults'=>$total,'totalClips'=>(int)sql('SELECT COUNT(*) FROM clips')->fetchColumn(),'collectionCounts'=>$counts,'page'=>$page,'pageSize'=>$size];
}
