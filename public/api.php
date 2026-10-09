<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
require dirname(__DIR__).'/private/clip-edit.php';
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
try{
 config();if(!is_file(dirname(__DIR__).'/private/installed.lock'))problem('Bitte zuerst die Installation abschließen.',503);
 $route=$_GET['route']??'';if(preg_match('~^/api/(auth|library|share|shared)$~',parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)?:'', $routeMatch))$route=$routeMatch[1];$method=$_SERVER['REQUEST_METHOD'];
 if(!in_array($method,['GET','POST'],true))problem('Diese Anfrage wird nicht unterstützt.',405);
 if($route==='auth'){
  if($method==='GET'){session_start_safe();respond(['user'=>member(false),'csrf'=>$_SESSION['csrf']]);}
  $v=input();$action=$v['action']??'';
  if($action==='logout'){$_SESSION=[];session_regenerate_id(true);respond(['ok'=>true]);}
  if($action==='login'){
   rate_limit('login');$email=strtolower(trim(field($v,'email',190,true)));$password=field($v,'password',1024,true);
   $m=sql('SELECT email,password_hash FROM members WHERE email=?',[$email])->fetch();
   $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
   $valid=password_matches($password,$m['password_hash']??$dummy);if(!$m||!$m['password_hash']||!$valid)problem('E-Mail-Adresse oder Passwort stimmt nicht.',401);
   session_regenerate_id(true);$_SESSION['email']=$email;$_SESSION['auth_version']=hash('sha256',$m['password_hash']);$_SESSION['csrf']=secret();respond(['ok'=>true]);
  }
  if($action==='accept'){
   rate_limit('invite');$token=field($v,'token',64,true);$password=password_value($v);$name=trim(field($v,'name',100,true));
   lock_team();$i=sql('SELECT email,expires_at FROM invitations WHERE hash=?',[hash('sha256',$token)])->fetch();
   if(!$i||(int)$i['expires_at']<timestamp())problem('Die Einladung ist ungültig oder abgelaufen. Bitte einen neuen Link beim Inhaber anfordern.',404);
   sql('UPDATE members SET name=?,password_hash=? WHERE email=?',[$name,password_digest($password),$i['email']]);sql('DELETE FROM invitations WHERE email=?',[$i['email']]);db()->commit();
   session_regenerate_id(true);$_SESSION['email']=$i['email'];$_SESSION['auth_version']=hash('sha256',sql('SELECT password_hash FROM members WHERE email=?',[$i['email']])->fetchColumn());$_SESSION['csrf']=secret();respond(['ok'=>true]);
  }
  problem('Unbekannte Aktion.');
 }
 if($route==='share'){if($method!=='GET')problem('Nur Lesen ist erlaubt.',405);$s=shared_data((string)($_GET['token']??''));$s['clips']=array_map('public_clip',$s['clips']);respond($s);}
 if($route==='shared'){
  if($method!=='GET')problem('Nur Lesen ist erlaubt.',405);$id=(string)($_GET['id']??'');
  if(isset($_GET['token'])){$s=shared_data((string)$_GET['token']);$clip=null;foreach($s['clips'] as $c)if($c['id']===$id)$clip=$c;}
  else{member();$clip=sql('SELECT c.*,(SELECT deleted_at FROM clip_trash tr WHERE tr.clip_id=c.id) AS deleted_at FROM clips c WHERE id=?',[$id])->fetch();}
  if(!$clip)problem('Dieser Clip ist nicht verfügbar.',404);
  if(isset($_GET['pdf']))pdf_download($clip);
  if(!isset($_GET['archive']))respond(isset($_GET['token'])?public_clip($clip):array_merge(public_clip($clip),['edit_revision'=>clip_edit_revision($clip),'favorite'=>(bool)sql('SELECT COUNT(*) FROM clip_favorites WHERE clip_id=? AND member_email=?',[$id,member()['email']])->fetchColumn()]));
  if($clip['type']==='pdf')problem('Bitte die PDF-Datei über den PDF-Link öffnen.',404);
  if(!$clip['archive_key']||!is_file(archive_path($clip['archive_key'])))problem('Die Seitenkopie ist nicht verfügbar.',404);
  header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow, noarchive"><title>'.htmlspecialchars($clip['title'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').' · Gespeicherte Seitenkopie</title><style>body{font:17px/1.8 Georgia,serif;color:#202022;max-width:72ch;margin:0 auto;padding:24px 20px;overflow-wrap:anywhere}h1{font-size:clamp(26px,5vw,36px);line-height:1.2}img{max-width:100%;height:auto}table{display:block;max-width:100%;overflow:auto}pre{white-space:pre-wrap;overflow-wrap:anywhere}*{box-sizing:border-box}</style></head><body>';
  readfile(archive_path($clip['archive_key']));echo '</body></html>';exit;
 }
 if($route!=='library')problem('Diese Adresse ist nicht verfügbar.',404);
 $m=member();
 if($method==='GET'){
  if(isset($_GET['duplicates']))respond(['duplicates'=>clip_duplicates(field($_GET,'url',10000))]);
  $base=array_merge(library_options(),library_personal($m),['user'=>$m,'csrf'=>$_SESSION['csrf'],'collections'=>sql('SELECT * FROM collections ORDER BY created_at')->fetchAll(),'members'=>sql('SELECT email,name,role,CASE WHEN password_hash IS NULL THEN 1 ELSE 0 END AS pending FROM members ORDER BY created_at')->fetchAll(),'shares'=>sql('SELECT id,target_id,kind,created_at,expires_at FROM shares')->fetchAll()]);
  if(isset($_GET['list'])){require dirname(__DIR__).'/private/library-list.php';respond(array_merge($base,library_page($_GET,$m['email'])));}
  respond(array_merge($base,['clips'=>array_map('public_clip',sql('SELECT c.* FROM clips c WHERE '.active_clip_condition().' ORDER BY created_at DESC')->fetchAll())]));
 }
 $v=input();$action=$v['action']??'';
 if($action==='pdf'){duplicate_gate($v);respond(pdf_upload($m,$v));}
 if($action==='export')collection_export($v);
 if($action==='saveSearch')respond(library_search_save($m,$v));
 if($action==='removeSearch'){sql('DELETE FROM saved_searches WHERE id=? AND member_email=?',[field($v,'id',36,true),$m['email']]);respond(['ok'=>true]);}
 if($action==='bulk')respond(library_bulk($m,$v));
 if($action==='restore')respond(clip_trash_change($m,$v,true));
 if($action==='favorite'){
  $id=field($v,'id',36,true);if(!sql('SELECT c.id FROM clips c WHERE id=? AND '.active_clip_condition(),[$id])->fetch())problem('Dieser Clip ist nicht verfügbar.',404);
  if(($v['favorite']??false)===true){if(!sql('SELECT clip_id FROM clip_favorites WHERE clip_id=? AND member_email=?',[$id,$m['email']])->fetch())sql('INSERT INTO clip_favorites(clip_id,member_email) VALUES(?,?)',[$id,$m['email']]);}else sql('DELETE FROM clip_favorites WHERE clip_id=? AND member_email=?',[$id,$m['email']]);respond(['ok'=>true]);
 }
 if($action==='edit')respond(clip_edit($m,$v));
 if($action==='collection'){$name=trim(field($v,'name',100,true));$id=uid();sql('INSERT INTO collections(id,name,created_at) VALUES(?,?,?)',[$id,$name,timestamp()]);respond(['id'=>$id]);}
 if($action==='clip'){
  duplicate_gate($v);
  $tags=tags_value($v);
  $url=field($v,'url',10000,true);$u=parse_url($url);if(!$u||!in_array($u['scheme']??'',['http','https'],true)||empty($u['host'])||isset($u['user'])||isset($u['pass'])||!filter_var($url,FILTER_VALIDATE_URL))problem('Bitte eine gültige Website-Adresse mit https:// oder http:// eingeben.');
  $title=trim(field($v,'title',300,true));$type=field($v,'type',16,true);if(!in_array($type,['article','page','link'],true))problem('Bitte eine Clip-Art wählen.');
  $content=field($v,'content',1000000,$type!=='link');$note=field($v,'note',20000);$collection=field($v,'collectionId',36)?:null;
  if($collection&&!sql('SELECT id FROM collections WHERE id=?',[$collection])->fetch())problem('Diese Sammlung existiert nicht.');
  $archive=field($v,'archive',5000000);if(strlen($archive)>5000000)problem('Die Seitenkopie ist zu groß. Bitte eine Auswahl clippen.');
  $id=uid();$key=null;
  if($archive&&$type!=='link'){$dir=dirname(__DIR__).'/private/archives';if(!is_dir($dir)&&!mkdir($dir,0700,true))problem('Die Seitenkopie kann nicht gespeichert werden.',503);$key=bin2hex(random_bytes(16)).'.html';if(file_put_contents(archive_path($key),$archive,LOCK_EX)===false)problem('Die Seitenkopie kann nicht gespeichert werden.',503);chmod(archive_path($key),0600);}
  try{db()->beginTransaction();sql('INSERT INTO clips(id,title,url,type,content,note,collection_id,author,archive_key,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$title,$url,$type,$content,$note,$collection,$m['email'],$key,timestamp()]);tags_save($id,$tags);sql('UPDATE clips SET content_format=?,note_format=? WHERE id=?',[($v['contentFormat']??'text')==='markdown'?'markdown':'text',($v['noteFormat']??'text')==='markdown'?'markdown':'text',$id]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($key)unlink(archive_path($key));throw $e;}
  respond(['id'=>$id]);
 }
 if($action==='note'){$id=field($v,'id',36,true);$c=sql('SELECT c.* FROM clips c WHERE id=? AND '.active_clip_condition(),[$id])->fetch();if(!$c)problem('Dieser Clip ist nicht verfügbar.',404);if(!clip_edit_allowed($m,$c))problem('Nur der Ersteller und der Bibliotheksinhaber dürfen die Notiz bearbeiten.',403);sql('UPDATE clips SET note=?,note_format=? WHERE id=?',[field($v,'note',20000),($v['noteFormat']??'text')==='markdown'?'markdown':'text',$id]);respond(['ok'=>true]);}
 if($action==='delete')respond(clip_trash_change($m,$v,false));
 if(in_array($action,['share','revoke','shareExpiry','revokeShare'],true)){
  $id=field($v,'id',36,true);$kind=field($v,'kind',16,true);if(!in_array($kind,['clip','collection'],true))problem('Bitte den Freigabetyp prüfen.');
  if($action==='shareExpiry'||$action==='revokeShare'){
   $shareId=field($v,'shareId',36,true);if(!sql('SELECT id FROM shares WHERE id=? AND target_id=? AND kind=?',[$shareId,$id,$kind])->fetch())problem('Dieser Leselink ist nicht verfügbar.',404);
   if($action==='revokeShare')sql('DELETE FROM shares WHERE id=?',[$shareId]);else sql('UPDATE shares SET expires_at=? WHERE id=?',[share_expiry($v),$shareId]);respond(['ok'=>true]);
  }
  if($action==='revoke'){sql('DELETE FROM shares WHERE target_id=? AND kind=?',[$id,$kind]);respond(['ok'=>true]);}
  $table=$kind==='clip'?'clips':'collections';if(!sql('SELECT id FROM '.$table.' c WHERE id=?'.($kind==='clip'?' AND '.active_clip_condition():''),[$id])->fetch())problem('Dieser Inhalt ist nicht verfügbar.',404);
  $expiry=share_expiry($v);$token=secret();sql('INSERT INTO shares(id,hash,target_id,kind,created_at,expires_at) VALUES(?,?,?,?,?,?)',[uid(),hash('sha256',$token),$id,$kind,timestamp(),$expiry]);respond(['token'=>$token,'expiresAt'=>$expiry]);
 }
 if($action==='member'){
  owner($m);$email=strtolower(trim(field($v,'email',190,true)));if(!filter_var($email,FILTER_VALIDATE_EMAIL))problem('Bitte eine gültige E-Mail-Adresse eingeben.');
  lock_team();$existing=sql('SELECT password_hash FROM members WHERE email=?',[$email])->fetch();if($existing&&$existing['password_hash'])problem('Diese Person ist bereits Mitglied.');
  if(!$existing){if((int)sql('SELECT COUNT(*) FROM members')->fetchColumn()>=10)problem('Alle 10 Teamplätze sind belegt. Bitte zuerst einen Zugriff entfernen.');sql('INSERT INTO members(email,name,role,password_hash,created_at) VALUES(?,?,?,NULL,?)',[$email,explode('@',$email)[0],'member',timestamp()]);}
  sql('DELETE FROM invitations WHERE email=?',[$email]);$token=secret();sql('INSERT INTO invitations(hash,email,expires_at) VALUES(?,?,?)',[hash('sha256',$token),$email,timestamp()+48*3600*1000]);db()->commit();respond(['token'=>$token]);
 }
 if($action==='removeMember'){owner($m);$email=field($v,'email',190,true);lock_team();if($email===$m['email'])problem('Der Inhaberzugriff bleibt bestehen.',403);sql('DELETE FROM invitations WHERE email IN (SELECT email FROM members WHERE email=? AND role<>?)',[$email,'owner']);sql('DELETE FROM clip_favorites WHERE member_email=?',[$email]);sql('DELETE FROM saved_searches WHERE member_email=?',[$email]);sql('DELETE FROM members WHERE email=? AND role<>?',[$email,'owner']);db()->commit();respond(['ok'=>true]);}
 problem('Unbekannte Aktion.');
}catch(Throwable $e){handle_error($e);}
