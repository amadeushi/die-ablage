<?php
// Optionaler Import aus der bisherigen lokalen Ausgabe. Nur SSH/CLI.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
$written=[];
try{
 $path=$argv[1]??'';if(!$path||!is_file($path))throw new AppError('Aufruf: php private/import-library.php /privater/pfad/bibliothek.json',400);
 $v=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
 if(($v['format']??'')!=='die-ablage-export-v1')problem('Unbekanntes Exportformat.');
 lock_team();if((int)sql('SELECT COUNT(*) FROM clips')->fetchColumn()||(int)sql('SELECT COUNT(*) FROM collections')->fetchColumn()||(int)sql('SELECT COUNT(*) FROM shares')->fetchColumn())problem('Import nur in eine leere Bibliothek. Bitte zuerst eine Sicherung erstellen.');
 foreach($v['collections'] as $c)sql('INSERT INTO collections(id,name,created_at) VALUES(?,?,?)',[$c['id'],$c['name'],$c['created_at']]);
 foreach($v['clips'] as $c){
  $key=null;
  if(!empty($c['archive'])){$dir=__DIR__.'/archives';if(!is_dir($dir)&&!mkdir($dir,0700,true))problem('Archivordner nicht beschreibbar.',503);$key=bin2hex(random_bytes(16)).'.html';$dest=archive_path($key);if(file_put_contents($dest,$c['archive'],LOCK_EX)===false)problem('Seitenkopie konnte nicht importiert werden.',503);chmod($dest,0600);$written[]=$dest;}
  sql('INSERT INTO clips(id,title,url,type,content,note,collection_id,author,archive_key,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$c['id'],$c['title'],$c['url'],$c['type'],$c['content'],$c['note'],$c['collection_id'],$c['author'],$key,$c['created_at']]);
 }
 foreach($v['shares'] as $s)sql('INSERT INTO shares(id,hash,target_id,kind,created_at) VALUES(?,?,?,?,?)',[$s['id'],$s['hash'],$s['target_id'],$s['kind'],$s['created_at']]);
 db()->commit();fwrite(STDOUT,count($v['clips'])." Clips und ".count($v['collections'])." Sammlungen importiert. Konten werden separat eingeladen.\n");
}catch(Throwable $e){if(db_active_transaction())db()->rollBack();foreach($written as $f)if(is_file($f))unlink($f);fwrite(STDERR,'Import fehlgeschlagen: '.($e instanceof AppError?$e->getMessage():'Bitte Exportformat, Datenbank und Ordnerrechte prüfen.')."\n");exit(1);}
