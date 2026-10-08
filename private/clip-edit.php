<?php
declare(strict_types=1);
function clip_edit_revision(array $c): string {return hash('sha256',json_encode(array_map(fn($key)=>$c[$key]??null,['title','url','content','note','collection_id','type','archive_key']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
function clip_edit_allowed(array $m,array $c): bool {return $m['role']==='owner'||$m['email']===$c['author'];}
function clip_edit(array $m,array $v): array {
 $id=field($v,'id',36,true);db()->beginTransaction();
 $suffix=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
 $c=sql('SELECT * FROM clips WHERE id=?'.$suffix,[$id])->fetch();
 if(!$c)problem('Dieser Clip ist nicht verfügbar.',404);
 if(!clip_edit_allowed($m,$c))problem('Nur der Ersteller und der Bibliotheksinhaber dürfen diesen Clip bearbeiten.',403);
 if(!hash_equals(clip_edit_revision($c),field($v,'revision',64,true)))problem('Der Clip wurde inzwischen geändert. Schließe die Bearbeitung und öffne den Clip erneut.',409);
 $title=trim(field($v,'title',300,true));if($title==='')problem('Bitte einen Titel eingeben.');
 $url=field($v,'url',10000,true);$u=parse_url($url);if(!$u||!in_array($u['scheme']??'',['http','https'],true)||empty($u['host'])||isset($u['user'])||isset($u['pass'])||!filter_var($url,FILTER_VALIDATE_URL))problem('Bitte eine gültige Website-Adresse mit https:// oder http:// eingeben.');
 $content=field($v,'content',1000000,$c['type']!=='link');$note=field($v,'note',20000);$collection=field($v,'collectionId',36)?:null;
 if($collection&&!sql('SELECT id FROM collections WHERE id=?',[$collection])->fetch())problem('Diese Sammlung existiert nicht.');
 // Keep identity, author, timestamp, clip type, original archive and share tokens intact.
 sql('UPDATE clips SET title=?,url=?,content=?,note=?,collection_id=? WHERE id=?',[$title,$url,$content,$note,$collection,$id]);
 $updated=sql('SELECT * FROM clips WHERE id=?',[$id])->fetch();db()->commit();
 return array_merge(public_clip($updated),['edit_revision'=>clip_edit_revision($updated)]);
}
