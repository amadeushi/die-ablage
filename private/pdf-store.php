<?php
declare(strict_types=1);
function pdf_metadata(string $key): array {
 if(!preg_match('/^[a-f0-9]{32}\.pdf$/D',$key))return [];
 $path=archive_path($key).'.json';if(!is_file($path))return [];
 $meta=json_decode(file_get_contents($path),true);return is_array($meta)?$meta:[];
}
function pdf_text_coverage(string $content,int $pages): array {
 preg_match_all('/(?:^|\n)Seite (\d+)\n/u',$content,$matches);$found=[];
 foreach($matches[1] as $page){$page=(int)$page;if($page>=1&&$page<=$pages)$found[$page]=true;}
 return ['textPages'=>count($found),'coverageKnown'=>(bool)$found];
}
function pdf_index_prefix(array $meta): string {
 return "[PDF-Dokumentdaten]\n".implode("\n",array_map(fn($key)=>$key.': '.($meta[$key]??''),['filename','title','author','subject','keywords','creationDate','modificationDate','pages']))."\n\n[PDF-Lesetext]\n";
}
function pdf_duplicates(string $hash):array {
 if(!preg_match('/^[a-f0-9]{64}$/D',$hash))problem('Bitte den PDF-Prüfwert prüfen.');$found=[];
 foreach(sql("SELECT c.id,c.title,c.url,c.type,c.archive_key,c.created_at FROM clips c WHERE c.type='pdf' AND ".active_clip_condition()." ORDER BY c.created_at DESC")->fetchAll() as $clip){$meta=pdf_metadata($clip['archive_key']??'');if(($meta['sha256']??'')===$hash){unset($clip['archive_key']);$found[]=$clip;}}
 return array_slice($found,0,5);
}
function pdf_versions(array $clip):array {
 if(($clip['type']??'')!=='pdf')return [];$nodes=[];$links=[];
 foreach(sql("SELECT c.id,c.title,c.url,c.type,c.archive_key,c.created_at FROM clips c WHERE c.type='pdf' AND ".active_clip_condition())->fetchAll() as $item){$previous=pdf_metadata($item['archive_key']??'')['previousId']??'';unset($item['archive_key']);$nodes[$item['id']]=$item;if($previous){$links[$previous][]=$item['id'];$links[$item['id']][]=$previous;}}
 $seen=[];$queue=[$clip['id']];while($queue&&count($seen)<100){$id=array_shift($queue);if(isset($seen[$id])||!isset($nodes[$id]))continue;$seen[$id]=true;foreach($links[$id]??[] as $next)if(!isset($seen[$next]))$queue[]=$next;}
 $result=array_values(array_intersect_key($nodes,$seen));usort($result,fn($a,$b)=>$b['created_at']<=>$a['created_at']);return count($result)>1?$result:[];
}
function pdf_upload(array $m,array $v): array {
 $tags=tags_value($v);
 if(str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'multipart/form-data')){
  $file=$_FILES['file']??null;if(!is_array($file)||!isset($file['error'])||!is_int($file['error']))problem('Bitte eine PDF-Datei auswählen.');
  if(in_array($file['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))problem('Die PDF überschreitet die Uploadgrenze des Servers.',413);
  if($file['error']!==UPLOAD_ERR_OK)problem('Die PDF wurde nicht vollständig hochgeladen. Bitte erneut versuchen.');
  if(!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))problem('Der PDF-Upload ist ungültig.');
  $size=filesize($file['tmp_name']);if($size===false||$size>25*1024*1024)problem('Bitte eine PDF mit maximal 25 MB wählen.',413);
  $bytes=file_get_contents($file['tmp_name']);
 }else{
  // Retain the existing small JSON upload for older clients.
  $encoded=field($v,'file',5600000,true);$bytes=base64_decode($encoded,true);
  if($bytes!==false&&strlen($bytes)>4194304)problem('Bitte große PDFs über den Datei-Upload der Web-App hinzufügen.',413);
 }
 if($bytes===false||strlen($bytes)>25*1024*1024||strlen($bytes)<8||!str_contains(substr($bytes,0,1024),'%PDF-')||!str_contains(substr($bytes,-2048),'%%EOF'))problem('Bitte eine gültige PDF-Datei mit maximal 25 MB wählen.');
 $content=field($v,'content',5*1024*1024,true);if(strlen($content)>5*1024*1024)problem('Der extrahierte PDF-Text ist zu groß (maximal 5 MB).');
 $title=trim(field($v,'title',300,true));if(!$title)problem('Bitte einen Titel eingeben.');
 $collection=field($v,'collectionId',36)?:null;if($collection&&!sql('SELECT id FROM collections WHERE id=?',[$collection])->fetch())problem('Diese Sammlung existiert nicht.');
 $id=uid();$url=trim(field($v,'url',10000));if(!$url)$url=config()['origin'].'/api/shared?id='.$id.'&pdf=1';
 $u=parse_url($url);if(!$u||!in_array($u['scheme']??'',['http','https'],true)||empty($u['host'])||isset($u['user'])||isset($u['pass'])||!filter_var($url,FILTER_VALIDATE_URL))problem('Bitte eine gültige Quellenadresse eingeben oder das Feld leer lassen.');
 $raw=$v['metadata']??[];if(!is_array($raw))problem('Bitte die Dokumentangaben prüfen.');$meta=[];
 foreach(['filename','title','author','subject','keywords','creationDate','modificationDate'] as $name)$meta[$name]=field($raw,$name,$name==='filename'?255:2000);
 $meta['filename']=basename(str_replace('\\','/',$meta['filename']))?:'Dokument.pdf';
 $pages=$raw['pages']??0;if(!is_int($pages)||$pages<1||$pages>500)problem('PDFs mit bis zu 500 Seiten werden unterstützt.');
 $matches=pdf_duplicates(hash('sha256',$bytes));if($matches&&($v['allowDuplicate']??false)!==true)respond(['error'=>'Diese PDF-Datei ist bereits abgelegt. Öffne den vorhandenen Clip oder bestätige eine weitere Kopie.','duplicates'=>$matches],409);
 $previous=field($v,'previousVersionId',36);if($previous&&!sql("SELECT c.id FROM clips c WHERE c.id=? AND c.type='pdf' AND ".active_clip_condition(),[$previous])->fetch())problem('Die frühere PDF-Fassung ist nicht verfügbar.',404);if($previous)$meta['previousId']=$previous;
 $meta['textOrder']=($raw['textOrder']??'source')==='layout'?'layout':'source';foreach(['layoutPages','fallbackPages'] as $k)$meta[$k]=is_int($raw[$k]??null)?max(0,min($pages,$raw[$k])):0;
 $meta['pages']=$pages;$meta=array_merge($meta,pdf_text_coverage($content,$pages));$meta['bytes']=strlen($bytes);$meta['sha256']=hash('sha256',$bytes);
 $note=field($v,'note',20000);$key=bin2hex(random_bytes(16)).'.pdf';$dir=__DIR__.'/archives';if(!is_dir($dir)&&!mkdir($dir,0700,true))problem('Die PDF-Datei kann nicht gespeichert werden.',503);
 $path=archive_path($key);
 try{
  if(file_put_contents($path,$bytes,LOCK_EX)===false)problem('Die PDF-Datei kann nicht gespeichert werden.',503);chmod($path,0600);
  if(file_put_contents($path.'.json',json_encode($meta,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX)===false)problem('Die Dokumentangaben können nicht gespeichert werden.',503);chmod($path.'.json',0600);
  db()->beginTransaction();sql('INSERT INTO clips(id,title,url,type,content,note,collection_id,author,archive_key,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$title,$url,'pdf',pdf_index_prefix($meta).$content,$note,$collection,$m['email'],$key,timestamp()]);if(isset($v['newspaper'])){if(!is_array($v['newspaper']))problem('Bitte die Zeitungsangaben prüfen.');newspaper_store($id,$v['newspaper']);}tags_save($id,$tags);sql('UPDATE clips SET note_format=? WHERE id=?',[($v['noteFormat']??'text')==='markdown'?'markdown':'text',$id]);db()->commit();
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(is_file($path))unlink($path);if(is_file($path.'.json'))unlink($path.'.json');throw $e;}
 return ['id'=>$id];
}
function pdf_download(array $clip): never {
 $key=$clip['archive_key']??'';if(($clip['type']!=='pdf'&&!($clip['type']==='article'&&!empty(newspaper_record($clip['id'])['parent_id'])))||!preg_match('/^[a-f0-9]{32}\.pdf$/D',$key)||!is_file(archive_path($key)))problem('Diese PDF-Datei ist nicht verfügbar.',404);
 $meta=pdf_metadata($key);$filename=preg_replace('/[\x00-\x1f\x7f]/u','',$meta['filename']??'Dokument.pdf');
 header('Content-Type: application/pdf');header("Content-Disposition: attachment; filename=\"Dokument.pdf\"; filename*=UTF-8''".rawurlencode($filename));
 header("Content-Security-Policy: sandbox; default-src 'none'; frame-ancestors 'self'");header('X-Robots-Tag: noindex, nofollow, noarchive');header('Content-Length: '.filesize(archive_path($key)));if(session_status()===PHP_SESSION_ACTIVE)session_write_close();readfile(archive_path($key));exit;
}
