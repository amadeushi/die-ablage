<?php
declare(strict_types=1);
function dossier_revision(array $d):string{return hash('sha256',$d['title']."\n".$d['intro']."\n".$d['entries']."\n".$d['updated_at']);}
function dossier_row(string $id):array{$d=sql('SELECT * FROM dossiers WHERE id=?',[$id])->fetch();if(!$d)problem('Dieses Dossier ist nicht verfügbar.',404);return $d;}
function dossier_sources(array $entries):array {
 $ids=array_values(array_unique(array_column($entries,'clipId')));if(!$ids)return [];
 $rows=sql('SELECT c.id,c.title,c.url,c.type,c.archive_key,c.created_at,SUBSTR(c.content,1,220) AS summary,c.content_format FROM clips c WHERE c.id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND '.active_clip_condition(),$ids)->fetchAll();return array_column($rows,null,'id');
}
function dossier_data(array $d,bool $private=false):array {
 $entries=json_decode($d['entries'],true);if(!is_array($entries))problem('Dieses Dossier kann nicht gelesen werden.',503);$sources=dossier_sources($entries);$result=[];
 foreach($entries as $entry){$source=$sources[$entry['clipId']]??null;$entry['available']=(bool)$source;
  if($source){$entry['source']=$entry['kind']==='quote'?array_intersect_key($source,array_flip(['id','title','url','type','created_at'])):public_clip($source);$entry['source']['newspaper']=newspaper_public($source['id']);if(!$private)unset($entry['sourceTitle']);if(str_starts_with($entry['source']['url'],config()['origin'].'/api/'))$entry['source']['url']='';}
  elseif(!$private){$entry=['id'=>$entry['id'],'kind'=>$entry['kind'],'available'=>false];}
  $result[]=$entry;
 }
 $data=['id'=>$d['id'],'title'=>$d['title'],'intro'=>$d['intro'],'entries'=>$result,'updatedAt'=>(int)$d['updated_at']];if($private){$data['revision']=dossier_revision($d);$data['author']=$d['author'];}return $data;
}
function dossier_save(array $m,array $v):array {
 $id=field($v,'id',36);$title=trim(field($v,'title',300,true));if(!$title)problem('Bitte einen Dossiertitel eingeben.');$intro=field($v,'intro',20000);$raw=$v['entries']??[];if(!is_array($raw)||count($raw)>50)problem('Ein Dossier kann maximal 50 Einträge enthalten.');
 $entries=[];$used=[];foreach($raw as $item){if(!is_array($item))problem('Bitte die Dossiereinträge prüfen.');$entryId=field($item,'id',36)?:uid();if(isset($used[$entryId]))problem('Ein Eintrag ist doppelt vorhanden.');$used[$entryId]=true;$kind=field($item,'kind',16,true);if(!in_array($kind,['document','quote'],true))problem('Bitte Dokument oder Zitat wählen.');$quote=field($item,'quote',8000);if($kind==='quote'&&!trim($quote))problem('Bitte einen Zitattext eingeben.');$page=$item['page']??0;if(!is_int($page)||$page<0||$page>500)problem('Bitte die Seitenzahl prüfen.');$entries[]=['id'=>$entryId,'kind'=>$kind,'clipId'=>field($item,'clipId',36,true),'quote'=>$kind==='quote'?$quote:'','comment'=>field($item,'comment',10000),'page'=>$kind==='quote'?$page:0];}
 $sources=dossier_sources($entries);foreach($entries as &$entry){$source=$sources[$entry['clipId']]??null;if(!$source)problem('Eine Quelle ist nicht verfügbar. Entferne den betroffenen Eintrag vor dem Speichern.',409);if($entry['page']&&($source['type']!=='pdf'||$entry['page']>(int)(pdf_metadata($source['archive_key']??'')['pages']??0)))problem('Die Seitenzahl passt nicht zur PDF-Quelle.');$entry['sourceTitle']=$source['title'];}unset($entry);
 $json=json_encode($entries,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(strlen($json)>1000000)problem('Dieses Dossier ist zu groß.');db()->beginTransaction();$now=timestamp();
 if($id){$suffix=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$old=sql('SELECT * FROM dossiers WHERE id=?'.$suffix,[$id])->fetch();if(!$old)problem('Dieses Dossier ist nicht verfügbar.',404);if($m['role']!=='owner'&&$old['author']!==$m['email'])problem('Nur der Ersteller und der Inhaber dürfen das Dossier bearbeiten.',403);if(!hash_equals(dossier_revision($old),field($v,'revision',64,true)))problem('Das Dossier wurde inzwischen geändert. Lade es erneut, bevor du speicherst.',409);sql('UPDATE dossiers SET title=?,intro=?,entries=?,updated_at=? WHERE id=?',[$title,$intro,$json,$now,$id]);}
 else{if((int)sql('SELECT COUNT(*) FROM dossiers')->fetchColumn()>=200)problem('Die Grenze von 200 Dossiers ist erreicht.');$id=uid();sql('INSERT INTO dossiers(id,title,intro,entries,author,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$id,$title,$intro,$json,$m['email'],$now,$now]);}
 db()->commit();return dossier_data(dossier_row($id),true);
}
function dossier_shared(string $id):array {
 $d=dossier_row($id);$data=dossier_data($d);$ids=[];foreach($data['entries'] as $entry)if($entry['available']&&$entry['kind']==='document')$ids[]=$entry['clipId'];$sources=dossier_sources(array_map(fn($id)=>['clipId'=>$id],array_unique($ids)));
 return ['title'=>$data['title'],'clips'=>array_values($sources),'dossier'=>$data];
}
function dossier_export(string $id):never {
 $d=dossier_data(dossier_row($id),true);unset($d['revision'],$d['author']);header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="die-ablage-dossier.json"');echo json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}

function dossier_document_ids(string $id):array {$entries=json_decode(dossier_row($id)['entries'],true);return array_keys(dossier_sources(array_values(array_filter($entries,fn($entry)=>$entry['kind']==='document'))));}
