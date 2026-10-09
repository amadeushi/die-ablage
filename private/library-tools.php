<?php
declare(strict_types=1);
function active_clip_condition(string $alias='c'):string{return "NOT EXISTS (SELECT 1 FROM clip_trash tr WHERE tr.clip_id=$alias.id)";}
function library_personal(array $m):array {
 $saved=sql('SELECT id,name,params FROM saved_searches WHERE member_email=? ORDER BY created_at DESC',[$m['email']])->fetchAll();foreach($saved as &$s)$s['params']=json_decode($s['params'],true);unset($s);
 return ['savedSearches'=>$saved,'favoriteCount'=>(int)sql('SELECT COUNT(*) FROM clip_favorites f JOIN clips c ON c.id=f.clip_id WHERE f.member_email=? AND '.active_clip_condition(),[$m['email']])->fetchColumn(),'trashCount'=>(int)sql('SELECT COUNT(*) FROM clip_trash')->fetchColumn()];
}
function library_search_save(array $m,array $v):array {
 $name=trim(field($v,'name',100,true));$raw=$v['params']??null;if(!is_array($raw))problem('Bitte die Suchfilter prüfen.');$p=[];
 foreach(['q'=>300,'view'=>36,'type'=>16,'tag'=>80,'source'=>253,'author'=>190,'from'=>10,'until'=>10] as $key=>$max)$p[$key]=field($raw,$key,$max);
 $p['view']=$p['view']?:'all';$p['type']=$p['type']?:'all';if($p['view']==='trash')problem('Papierkorb-Suchen können nicht gespeichert werden.');
 if(!in_array($p['view'],['all','shared','favorites'],true)&&!sql('SELECT id FROM collections WHERE id=?',[$p['view']])->fetch())problem('Diese Sammlung existiert nicht.');
 require_once __DIR__.'/library-list.php';library_page($p,$m['email']);
 if((int)sql('SELECT COUNT(*) FROM saved_searches WHERE member_email=?',[$m['email']])->fetchColumn()>=25)problem('Du hast bereits 25 Suchen gespeichert. Entferne zuerst eine Suche.');
 $id=uid();sql('INSERT INTO saved_searches(id,member_email,name,params,created_at) VALUES(?,?,?,?,?)',[$id,$m['email'],$name,json_encode($p,JSON_UNESCAPED_UNICODE),timestamp()]);return ['id'=>$id];
}
function clip_duplicates(string $url):array {
 if(!$url)return [];$normalize=function(string $url):string{$p=parse_url($url);if(!$p||empty($p['host']))return '';return strtolower($p['scheme']??'https').'://'.strtolower($p['host']).(isset($p['port'])?':'.$p['port']:'').(($p['path']??'')?:'/').(isset($p['query'])?'?'.$p['query']:'');};
 $target=$normalize($url);if(!$target)return [];$matches=[];
 foreach(sql('SELECT c.id,c.title,c.url,c.created_at FROM clips c WHERE '.active_clip_condition().' ORDER BY c.created_at DESC')->fetchAll() as $c)if($normalize($c['url'])===$target){$matches[]=$c;if(count($matches)>=5)break;}
 return $matches;
}
function duplicate_gate(array $v):void{if(($v['allowDuplicate']??false)===true)return;$found=clip_duplicates(field($v,'url',10000));if($found)respond(['error'=>'Diese Quelle ist bereits abgelegt. Öffne den vorhandenen Clip oder bestätige eine weitere Kopie.','duplicates'=>$found],409);}
function merged_clip_tags(string $id,array $new):array{$unique=[];foreach(array_merge(clip_tags($id),$new) as $tag)$unique[mb_strtolower($tag)]=$tag;return tags_value(['tags'=>array_values($unique)]);}
function library_bulk(array $m,array $v):array {
 $ids=$v['ids']??null;if(!is_array($ids)||!count($ids)||count($ids)>100)problem('Bitte 1 bis 100 Clips auswählen.');foreach($ids as $id)if(!is_string($id)||strlen($id)>36)problem('Bitte die Auswahl prüfen.');$ids=array_values(array_unique($ids));$operation=field($v,'operation',16,true);if(!in_array($operation,['collection','tags'],true))problem('Bitte eine Aktion wählen.');
 $collection=field($v,'collectionId',36)?:null;if($operation==='collection'&&$collection&&!sql('SELECT id FROM collections WHERE id=?',[$collection])->fetch())problem('Diese Sammlung existiert nicht.');$tags=$operation==='tags'?tags_value($v):[];if($operation==='tags'&&!$tags)problem('Bitte mindestens einen Tag eingeben.');
 db()->beginTransaction();$suffix=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$clips=sql('SELECT c.* FROM clips c WHERE c.id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND '.active_clip_condition().$suffix,$ids)->fetchAll();
 if(count($clips)!==count($ids))problem('Ein ausgewählter Clip ist nicht mehr verfügbar. Lade die Liste neu.',409);
 foreach($clips as $c){if(!clip_edit_allowed($m,$c))problem('Die Auswahl enthält fremde Clips. Nur Ersteller und Inhaber dürfen sie ändern.',403);if($operation==='tags')merged_clip_tags($c['id'],$tags);}
 foreach($clips as $c)if($operation==='collection')sql('UPDATE clips SET collection_id=? WHERE id=?',[$collection,$c['id']]);else tags_save($c['id'],merged_clip_tags($c['id'],$tags));db()->commit();return ['ok'=>true,'count'=>count($ids)];
}
function clip_trash_change(array $m,array $v,bool $restore):array {
 $id=field($v,'id',36,true);db()->beginTransaction();$suffix=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$c=sql('SELECT * FROM clips WHERE id=?'.$suffix,[$id])->fetch();if(!$c)problem('Dieser Clip ist nicht verfügbar.',404);if(!clip_edit_allowed($m,$c))problem('Nur Ersteller und Inhaber dürfen diesen Clip löschen oder wiederherstellen.',403);
 if($restore){sql('DELETE FROM clip_trash WHERE clip_id=?',[$id]);}
 else{if(!sql('SELECT clip_id FROM clip_trash WHERE clip_id=?',[$id])->fetch())sql('INSERT INTO clip_trash(clip_id,deleted_at,deleted_by) VALUES(?,?,?)',[$id,timestamp(),$m['email']]);sql("DELETE FROM shares WHERE target_id=? AND kind='clip'",[$id]);}
 db()->commit();return ['ok'=>true];
}
function collection_export(array $v):never {
 $id=field($v,'id',36,true);$col=sql('SELECT name FROM collections WHERE id=?',[$id])->fetch();if(!$col)problem('Diese Sammlung existiert nicht.',404);if(!class_exists('ZipArchive'))problem('Der ZIP-Export ist auf diesem Server nicht verfügbar.',503);
 $clips=sql('SELECT c.* FROM clips c WHERE c.collection_id=? AND '.active_clip_condition().' ORDER BY c.created_at,c.id',[$id])->fetchAll();if(count($clips)>500)problem('Bitte eine Sammlung mit maximal 500 Clips exportieren.');
 $path=tempnam(sys_get_temp_dir(),'ablage-export-');if(!$path)problem('Der Export konnte nicht erstellt werden.',503);$zip=new ZipArchive();
 try{if($zip->open($path,ZipArchive::OVERWRITE)!==true)problem('Der Export konnte nicht erstellt werden.',503);$size=0;$manifest=['collection'=>$col['name'],'exported_at'=>gmdate('c'),'clips'=>[]];
 foreach($clips as $c){$item=public_clip($c);unset($item['archive_key']);$manifest['clips'][]=$item;$text='# '.$c['title']."\n\nQuelle: ".$c['url']."\nGespeichert: ".gmdate('c',(int)($c['created_at']/1000))."\nTags: ".implode(', ',$item['tags'])."\n\n".($item['content']??'')."\n\n## Notiz\n\n".$c['note']."\n";$size+=strlen($text);$zip->addFromString('clips/'.$c['id'].'.md',$text);
 if($c['type']==='pdf'&&$c['archive_key']){$f=archive_path($c['archive_key']);if(!is_file($f))problem('Eine Original-PDF fehlt. Bitte den Clip prüfen.',409);$size+=filesize($f);$zip->addFile($f,'pdf/'.$c['id'].'.pdf');}if($size>100000000)problem('Diese Sammlung überschreitet die Exportgrenze von 100 MB.');}
 $zip->addFromString('sammlung.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$zip->addFromString('README.txt',"Die ABLAGE – Sammlungsexport\nTexte und Notizen: clips/*.md\nOriginal-PDFs: pdf/*.pdf\nMetadaten und Quellen: sammlung.json\nDer Export enthält keine Leselinks oder Zugangsdaten.\n");if(!$zip->close())problem('Der Export konnte nicht abgeschlossen werden.',503);
 header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="die-ablage-sammlung.zip"');header('Content-Length: '.filesize($path));readfile($path);
 }finally{if(is_file($path))unlink($path);}exit;
}
