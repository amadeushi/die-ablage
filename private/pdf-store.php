<?php
declare(strict_types=1);
function pdf_metadata(string $key): array {
 if(!preg_match('/^[a-f0-9]{32}\.pdf$/D',$key))return [];
 $path=archive_path($key).'.json';if(!is_file($path))return [];
 $meta=json_decode(file_get_contents($path),true);return is_array($meta)?$meta:[];
}
function pdf_index_prefix(array $meta): string {
 return "[PDF-Dokumentdaten]\n".implode("\n",array_map(fn($key)=>$key.': '.($meta[$key]??''),['filename','title','author','subject','keywords','creationDate','modificationDate','pages']))."\n\n[PDF-Lesetext]\n";
}
function pdf_upload(array $m,array $v): array {
 $tags=tags_value($v);$encoded=field($v,'file',5600000,true);$bytes=base64_decode($encoded,true);
 if($bytes===false||strlen($bytes)>4194304||strlen($bytes)<8||!str_contains(substr($bytes,0,1024),'%PDF-')||!str_contains(substr($bytes,-2048),'%%EOF'))problem('Bitte eine gültige PDF-Datei mit maximal 4 MB wählen.');
 $content=field($v,'content',1000000,true);if(strlen($content)>1000000)problem('Der extrahierte PDF-Text ist zu groß (maximal 1 MB).');
 $title=trim(field($v,'title',300,true));if(!$title)problem('Bitte einen Titel eingeben.');
 $collection=field($v,'collectionId',36)?:null;if($collection&&!sql('SELECT id FROM collections WHERE id=?',[$collection])->fetch())problem('Diese Sammlung existiert nicht.');
 $id=uid();$url=trim(field($v,'url',10000));if(!$url)$url=config()['origin'].'/api/shared?id='.$id.'&pdf=1';
 $u=parse_url($url);if(!$u||!in_array($u['scheme']??'',['http','https'],true)||empty($u['host'])||isset($u['user'])||isset($u['pass'])||!filter_var($url,FILTER_VALIDATE_URL))problem('Bitte eine gültige Quellenadresse eingeben oder das Feld leer lassen.');
 $raw=$v['metadata']??[];if(!is_array($raw))problem('Bitte die Dokumentangaben prüfen.');$meta=[];
 foreach(['filename','title','author','subject','keywords','creationDate','modificationDate'] as $name)$meta[$name]=field($raw,$name,$name==='filename'?255:2000);
 $meta['filename']=basename(str_replace('\\','/',$meta['filename']))?:'Dokument.pdf';
 $pages=$raw['pages']??0;if(!is_int($pages)||$pages<1||$pages>500)problem('PDFs mit bis zu 500 Seiten werden unterstützt.');
 $meta['pages']=$pages;$meta['bytes']=strlen($bytes);$meta['sha256']=hash('sha256',$bytes);
 $note=field($v,'note',20000);$key=bin2hex(random_bytes(16)).'.pdf';$dir=__DIR__.'/archives';if(!is_dir($dir)&&!mkdir($dir,0700,true))problem('Die PDF-Datei kann nicht gespeichert werden.',503);
 $path=archive_path($key);
 try{
  if(file_put_contents($path,$bytes,LOCK_EX)===false)problem('Die PDF-Datei kann nicht gespeichert werden.',503);chmod($path,0600);
  if(file_put_contents($path.'.json',json_encode($meta,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX)===false)problem('Die Dokumentangaben können nicht gespeichert werden.',503);chmod($path.'.json',0600);
  db()->beginTransaction();sql('INSERT INTO clips(id,title,url,type,content,note,collection_id,author,archive_key,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$title,$url,'pdf',pdf_index_prefix($meta).$content,$note,$collection,$m['email'],$key,timestamp()]);tags_save($id,$tags);sql('UPDATE clips SET note_format=? WHERE id=?',[($v['noteFormat']??'text')==='markdown'?'markdown':'text',$id]);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(is_file($path))unlink($path);if(is_file($path.'.json'))unlink($path.'.json');throw $e;}
 return ['id'=>$id];
}
function pdf_download(array $clip): never {
 $key=$clip['archive_key']??'';if($clip['type']!=='pdf'||!preg_match('/^[a-f0-9]{32}\.pdf$/D',$key)||!is_file(archive_path($key)))problem('Diese PDF-Datei ist nicht verfügbar.',404);
 $meta=pdf_metadata($key);$filename=preg_replace('/[\x00-\x1f\x7f]/u','',$meta['filename']??'Dokument.pdf');
 header('Content-Type: application/pdf');header("Content-Disposition: attachment; filename=\"Dokument.pdf\"; filename*=UTF-8''".rawurlencode($filename));
 header("Content-Security-Policy: sandbox; default-src 'none'; frame-ancestors 'self'");header('X-Robots-Tag: noindex, nofollow, noarchive');header('Content-Length: '.filesize(archive_path($key)));if(session_status()===PHP_SESSION_ACTIVE)session_write_close();readfile(archive_path($key));exit;
}
