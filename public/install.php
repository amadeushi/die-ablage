<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
$error='';$done=false;
try {
 $c=config();if(is_file(dirname(__DIR__).'/private/installed.lock')){http_response_code(404);exit('Die Installation ist bereits abgeschlossen.');}
 session_start_safe();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  rate_limit('install');
  if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??'')))problem('Bitte die Seite neu laden.');
  if(strlen($c['setup_key'])<32||str_contains($c['setup_key'],'BITTE_')||!hash_equals($c['setup_key'],(string)($_POST['setup_key']??'')))problem('Das Installationskennwort stimmt nicht. Bitte die Konfiguration prüfen.',403);
  $email=strtolower(trim((string)($_POST['email']??'')));if(strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))problem('Bitte eine gültige E-Mail-Adresse eingeben.');
  $password=password_value($_POST);$name=trim(field($_POST,'name',100,true));
  if(db()->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')problem('Für die Installation bitte eine MariaDB-/MySQL-Datenbank konfigurieren.');
  // Serverseitig definierte SQL-Datei; keine SQL-Eingabe aus dem Browser.
  foreach(explode(';',file_get_contents(dirname(__DIR__).'/private/schema.sql')) as $statement)if(trim($statement))db()->exec($statement);
  lock_team();if((int)sql('SELECT COUNT(*) FROM members')->fetchColumn()>0)problem('Es existiert bereits ein Konto. Die Installation wurde aus Sicherheitsgründen gestoppt.',409);
  sql('INSERT INTO members(email,name,role,password_hash,created_at) VALUES(?,?,?,?,?)',[$email,$name,'owner',password_digest($password),timestamp()]);
  if(file_put_contents(dirname(__DIR__).'/private/installed.lock',date(DATE_ATOM),LOCK_EX)===false)problem('Die Installationssperre kann nicht geschrieben werden. Bitte die Ordnerrechte prüfen.',503);
  db()->commit();$done=true;
 }
}catch(Throwable $e){if(db_active_transaction())db()->rollBack();$error=$e instanceof AppError?$e->getMessage():'Die Datenbankverbindung oder Einrichtung ist fehlgeschlagen. Bitte die Zugangsdaten und Datenbankrechte prüfen.';http_response_code($e->getCode()>=400&&$e->getCode()<600?$e->getCode():503);error_log('Die ABLAGE Installation: '.$e->getMessage());}
function esc(string $s): string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Die ABLAGE einrichten</title><style>@font-face{font-family:Barlow;src:url('/fonts/barlow-400.ttf')}body{font:18px/1.5 Barlow,Arial,sans-serif;color:#202022;background:#f4f4f2;margin:0;padding:clamp(24px,6vw,80px)}main{max-width:600px}img{width:210px;height:auto}h1{font-size:42px;line-height:1.1}form{display:grid;gap:18px}label{display:grid;gap:6px}input{font:inherit;padding:12px;border:1px solid #aaa;border-radius:4px}button,a{font:inherit}button{background:#b31229;color:#fff;padding:14px;border:0;border-radius:4px;cursor:pointer}.error{color:#a30d23}a{color:#a30d23}input:focus-visible,button:focus-visible,a:focus-visible{outline:3px solid #b31229;outline-offset:3px}</style></head><body><main><img src="/partei-logo.png" alt="Die PARTEI"><h1>Die ABLAGE einrichten.</h1><?php if($error):?><p role="alert" class="error"><?=esc($error)?></p><?php endif;?><?php if($done):?><p>Die Bibliothek wurde eingerichtet. Melde dich jetzt mit deinem neuen Konto an.</p><a href="/">Zur Anmeldung</a><?php elseif(isset($_SESSION['csrf'])):?><p>Lege das erste Konto an. Dieses Konto verwaltet euer Team.</p><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>Installationskennwort aus config.php<input type="password" name="setup_key" required autocomplete="off"></label><label>Dein Name<input name="name" maxlength="100" required autocomplete="name"></label><label>E-Mail-Adresse<input name="email" type="email" maxlength="190" required autocomplete="username"></label><label>Passwort (mindestens 12 Zeichen)<input name="password" type="password" minlength="12" maxlength="1024" required autocomplete="new-password"></label><button>Bibliothek einrichten</button></form><?php endif;?></main></body></html>
