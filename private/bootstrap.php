<?php
declare(strict_types=1);
final class AppError extends RuntimeException {}
function problem(string $message, int $code=400): never { throw new AppError($message,$code); }
function config(): array {
 static $c=null;
 if($c!==null)return $c;
 $file=__DIR__.'/config.php';
 if(!is_file($file))problem('Die ABLAGE ist noch nicht eingerichtet. Bitte die Installationsanleitung beachten.',503);
 $c=require $file;
 if(!is_array($c)||!isset($c['dsn'],$c['user'],$c['password'],$c['origin'],$c['setup_key']))problem('Die Konfiguration ist unvollständig.',503);
 $u=parse_url($c['origin']);
 $local=in_array($u['host']??'',['localhost','127.0.0.1'],true);
 if(($u['scheme']??'')!=='https'&&!$local)problem('Bitte HTTPS für die Bibliothek einrichten.',503);
 if(PHP_SAPI!=='cli'&&!$local&& !in_array(strtolower((string)($_SERVER['HTTPS']??'')),['on','1'],true)&&($_SERVER['SERVER_PORT']??'')!=='443')problem('Bitte die Bibliothek über HTTPS öffnen.',403);
 return $c;
}
function db(): PDO {
 static $db=null;if($db)return $db;$c=config();
 $db=new PDO($c['dsn'],$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$db->exec("SET NAMES utf8mb4");
 else $db->exec('PRAGMA foreign_keys=ON');
 return $db;
}
function sql(string $query,array $args=[]): PDOStatement {$q=db()->prepare($query);$q->execute($args);return $q;}
function timestamp(): int{return (int)round(microtime(true)*1000);}
function uid(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
function secret(): string{return bin2hex(random_bytes(32));}
function respond(array $value,int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
function session_start_safe(): void {
 config();if(session_status()===PHP_SESSION_ACTIVE)return;
 $path=__DIR__.'/sessions';if(!is_dir($path)&&!mkdir($path,0700,true))problem('Sitzungen können nicht gespeichert werden.',503);
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.gc_maxlifetime','28800');session_save_path($path);session_name('ablage_session');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>str_starts_with(config()['origin'],'https://'),'httponly'=>true,'samesite'=>'Lax']);session_start();
 if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>28800){$_SESSION=[];session_regenerate_id(true);}
 $_SESSION['last_seen']=time();if(empty($_SESSION['csrf']))$_SESSION['csrf']=secret();
}
function member(bool $required=true): ?array {
 session_start_safe();$m=isset($_SESSION['email'])?sql('SELECT email,name,role,password_hash FROM members WHERE email=?',[$_SESSION['email']])->fetch():false;
 if(!$m||!$m['password_hash']||!hash_equals($_SESSION['auth_version']??'',hash('sha256',$m['password_hash']))){if($required)problem('Bitte anmelden, um die Bibliothek zu öffnen.',401);return null;}
 unset($m['password_hash']);return $m;
}
function input(): array {
 session_start_safe();$origin=$_SERVER['HTTP_ORIGIN']??'';
 if($origin!==config()['origin'])problem('Diese Anfrage stammt nicht von der Bibliothek.',403);
 $token=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf']??'');
 if(!is_string($token)||!hash_equals($_SESSION['csrf'],$token))problem('Die Sitzung ist abgelaufen. Bitte die Seite neu laden.',403);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>12000000)problem('Der Clip ist zu groß. Bitte weniger Inhalt auswählen.',413);
 $raw=file_get_contents('php://input',false,null,0,12000001);
 if(strlen($raw)>12000000)problem('Der Clip ist zu groß.',413);
 try{$v=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){problem('Die Eingabe konnte nicht gelesen werden.');}
 if(!is_array($v))problem('Ungültige Eingabe.');return $v;
}
function field(array $v,string $key,int $max,bool $required=false): string {
 $s=$v[$key]??'';if(!is_string($s)||mb_strlen($s)>$max)problem('Bitte das Feld '.$key.' prüfen.');if($required&&!trim($s))problem('Bitte das Feld '.$key.' ausfüllen.');return $s;
}
function owner(array $m): void{if($m['role']!=='owner')problem('Nur der Inhaber kann das Team verwalten.',403);}
function lock_team(): void {db()->beginTransaction();sql('SELECT id FROM team_lock WHERE id=1'.(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetch();}
function rate_limit(string $scope): void {
 $dir=__DIR__.'/limits';if(!is_dir($dir)&&!mkdir($dir,0700,true))problem('Die Anmeldung ist vorübergehend nicht möglich.',503);
 // Keine vom Client gelieferten Proxy-Header: die tatsächliche Verbindungsadresse verwenden.
 $key=hash('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??''));$path=$dir.'/'.$key;
 $f=fopen($path,'c+');if(!$f||!flock($f,LOCK_EX))problem('Bitte die Anmeldung später erneut versuchen.',503);
 $state=json_decode(stream_get_contents($f),true)?:['start'=>time(),'count'=>0];
 if(time()-$state['start']>=900)$state=['start'=>time(),'count'=>0];
 if($state['count']>=30){flock($f,LOCK_UN);fclose($f);problem('Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.',429);}
 $state['count']++;rewind($f);ftruncate($f,0);fwrite($f,json_encode($state));flock($f,LOCK_UN);fclose($f);
}
function password_value(array $v): string {$p=field($v,'password',1024,true);if(mb_strlen($p)<12)problem('Bitte ein Passwort mit mindestens 12 Zeichen wählen.');return $p;}
function password_digest(string $p): string {return password_hash(hash('sha256',$p),PASSWORD_DEFAULT);}
function password_matches(string $p,string $hash): bool {return password_verify(hash('sha256',$p),$hash);}
require_once __DIR__.'/pdf-store.php';
function public_clip(array $c): array { if(($c['type']??'')==='pdf'){ $c['pdf']=pdf_metadata($c['archive_key']??'');$prefix=pdf_index_prefix($c['pdf']);if(isset($c['summary']))$c['summary']=($c['pdf']['filename']??'PDF-Dokument').' · '.($c['pdf']['pages']??'?').' Seiten';foreach(['content','summary'] as $field)if(isset($c[$field])&&str_starts_with($c[$field],$prefix))$c[$field]=substr($c[$field],strlen($prefix)); } $c['created_at']=(int)$c['created_at'];$c['archive_key']=$c['archive_key']?'stored':null;return $c; }
function shared_data(string $token): array {
 if(!preg_match('/^[a-f0-9]{64}$/D',$token))problem('Der Leselink ist ungültig oder wurde deaktiviert.',404);
 $s=sql('SELECT target_id,kind FROM shares WHERE hash=?',[hash('sha256',$token)])->fetch();if(!$s)problem('Der Leselink ist ungültig oder wurde deaktiviert.',404);
 if($s['kind']==='clip'){$clips=sql('SELECT * FROM clips WHERE id=?',[$s['target_id']])->fetchAll();$title=$clips[0]['title']??null;}
 else{$col=sql('SELECT name FROM collections WHERE id=?',[$s['target_id']])->fetch();$title=$col['name']??null;$clips=sql('SELECT * FROM clips WHERE collection_id=? ORDER BY created_at DESC',[$s['target_id']])->fetchAll();}
 if($title===null)problem('Der geteilte Inhalt ist nicht mehr verfügbar.',404);return ['title'=>$title,'clips'=>$clips];
}
function archive_path(string $key): string {if(!preg_match('/^[a-f0-9]{32}\.(?:html|pdf)$/D',$key))problem('Die Seitenkopie ist nicht verfügbar.',404);return __DIR__.'/archives/'.$key;}
function handle_error(Throwable $e): never {
 if(db_active_transaction())db()->rollBack();$status=$e instanceof AppError&&$e->getCode()>=400&&$e->getCode()<=599?(int)$e->getCode():503;
 if($status===503)error_log('Die ABLAGE: '.$e->getMessage());
 respond(['error'=>$status===503&&!($e instanceof AppError)?'Die Bibliothek ist vorübergehend nicht erreichbar. Bitte Konfiguration und Datenbank prüfen.':$e->getMessage()],$status);
}
function db_active_transaction(): bool {try{return db()->inTransaction();}catch(Throwable){return false;}}
