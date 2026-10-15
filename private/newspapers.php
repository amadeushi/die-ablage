<?php
declare(strict_types=1);
function newspaper_record(string $id):array{return sql('SELECT * FROM newspaper_records WHERE clip_id=?',[$id])->fetch()?:[];}
function newspaper_values(array $v):array{
 $out=[];foreach(['publication'=>190,'issueDate'=>10,'edition'=>190,'section'=>190] as $key=>$max)$out[$key]=trim(field($v,$key,$max));
 if($out['issueDate']!==''){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$out['issueDate']);if(!$d||$d->format('Y-m-d')!==$out['issueDate'])problem('Bitte ein gültiges Ausgabedatum eingeben.');}return $out;
}
function newspaper_store(string $id,array $v,?array $relation=null,?array $extraction=null):void{
 $old=newspaper_record($id);$fields=newspaper_values($v);$parent=$relation['parentId']??($old['parent_id']??null);$first=$relation['pageFrom']??($old['page_from']??null);$last=$relation['pageUntil']??($old['page_until']??null);
 $order=$old['text_order']??'';$layout=(int)($old['layout_pages']??0);$fallback=(int)($old['fallback_pages']??0);
 if($extraction!==null){$order=($extraction['textOrder']??'')==='layout'?'layout':'source';$layout=max(0,min(500,(int)($extraction['layoutPages']??0)));$fallback=max(0,min(500,(int)($extraction['fallbackPages']??0)));}
 $suffix=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ON DUPLICATE KEY UPDATE publication=VALUES(publication),issue_date=VALUES(issue_date),edition=VALUES(edition),section=VALUES(section),parent_id=VALUES(parent_id),page_from=VALUES(page_from),page_until=VALUES(page_until),text_order=VALUES(text_order),layout_pages=VALUES(layout_pages),fallback_pages=VALUES(fallback_pages)':' ON CONFLICT(clip_id) DO UPDATE SET publication=excluded.publication,issue_date=excluded.issue_date,edition=excluded.edition,section=excluded.section,parent_id=excluded.parent_id,page_from=excluded.page_from,page_until=excluded.page_until,text_order=excluded.text_order,layout_pages=excluded.layout_pages,fallback_pages=excluded.fallback_pages';
 sql('INSERT INTO newspaper_records(clip_id,publication,issue_date,edition,section,parent_id,page_from,page_until,text_order,layout_pages,fallback_pages) VALUES(?,?,?,?,?,?,?,?,?,?,?)'.$suffix,[$id,$fields['publication'],$fields['issueDate'],$fields['edition'],$fields['section'],$parent,$first,$last,$order,$layout,$fallback]);
}
function newspaper_public(string $id):?array{$r=newspaper_record($id);if(!$r)return null;return ['publication'=>$r['publication'],'issueDate'=>$r['issue_date'],'edition'=>$r['edition'],'section'=>$r['section'],'parentId'=>$r['parent_id'],'pageFrom'=>$r['page_from']?(int)$r['page_from']:null,'pageUntil'=>$r['page_until']?(int)$r['page_until']:null];}
function newspaper_citation(array $r):string{$parts=array_filter([$r['publication']??'',$r['issueDate']??'',$r['edition']??'',$r['section']??'']);if(!empty($r['pageFrom']))$parts[]='Seite '.$r['pageFrom'].(($r['pageUntil']??$r['pageFrom'])!=$r['pageFrom']?'–'.$r['pageUntil']:'');return implode(' · ',$parts);}
function newspaper_change(array $m,array $v):array{
 $id=field($v,'id',36,true);db()->beginTransaction();$clip=sql('SELECT c.* FROM clips c WHERE c.id=? AND '.active_clip_condition().(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id])->fetch();if(!$clip)problem('Dieser Clip ist nicht verfügbar.',404);if(!clip_edit_allowed($m,$clip))problem('Nur Ersteller und Inhaber dürfen die Zeitungsangaben ändern.',403);
 if(!hash_equals(clip_edit_revision($clip),field($v,'revision',64,true)))problem('Der Clip wurde inzwischen geändert. Bitte erneut öffnen.',409);
 if($clip['type']!=='pdf'&&!newspaper_record($id))problem('Dieser Clip gehört noch zu keiner Zeitung.');$fields=$v['newspaper']??[];if(!is_array($fields))problem('Bitte die Zeitungsangaben prüfen.');newspaper_store($id,$fields);search_index_refresh($id);db()->commit();return ['ok'=>true];
}
function newspaper_article(array $m,array $v):array{
 $parent=field($v,'parentId',36,true);$title=trim(field($v,'title',300,true));$text=field($v,'content',1000000,true);if(!$title||strlen($text)>1000000)problem('Bitte Titel und Artikeltext prüfen.');$note=field($v,'note',20000);$tags=tags_value($v);
 db()->beginTransaction();$source=sql("SELECT c.* FROM clips c WHERE c.id=? AND c.type='pdf' AND ".active_clip_condition().(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$parent])->fetch();if(!$source)problem('Die Zeitungsausgabe ist nicht verfügbar.',404);
 if(!hash_equals(clip_edit_revision($source),field($v,'revision',64,true)))problem('Die Ausgabe wurde inzwischen geändert. Bitte erneut öffnen.',409);
 $pages=(int)(pdf_metadata($source['archive_key'])['pages']??0);$first=$v['pageFrom']??0;$last=$v['pageUntil']??0;if(!is_int($first)||!is_int($last)||$first<1||$last<$first||$last>$pages)problem('Bitte gültige PDF-Seitennummern wählen.');
 $id=uid();$url=$source['url'];if(str_starts_with($url,config()['origin'].'/api/'))$url=config()['origin'].'/';
 // Independent article has no archive_key: its grants never include the parent PDF.
 sql('INSERT INTO clips(id,title,url,type,content,note,collection_id,author,archive_key,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$title,$url,'article',$text,$note,null,$m['email'],null,timestamp()]);
 $fields=$v['newspaper']??newspaper_public($parent)??[];if(!is_array($fields))problem('Bitte die Zeitungsangaben prüfen.');newspaper_store($id,$fields,['parentId'=>$parent,'pageFrom'=>$first,'pageUntil'=>$last]);tags_save($id,$tags);db()->commit();return ['id'=>$id];
}
