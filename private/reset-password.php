<?php
// Nur über SSH/CLI ausführbar. Nicht in public/ hochladen.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
try{
 $email=strtolower(trim($argv[1]??''));if(!$email||!sql('SELECT email FROM members WHERE email=?',[$email])->fetch())throw new RuntimeException('Aufruf: php private/reset-password.php bekannte-email@example.de');
 fwrite(STDOUT,"Neues Passwort (mindestens 12 Zeichen; Eingabe wird sichtbar, wenn stty fehlt): ");
 if(function_exists('shell_exec'))shell_exec('stty -echo 2>/dev/null');
 try{$password=rtrim(fgets(STDIN),"\r\n");}finally{if(function_exists('shell_exec'))shell_exec('stty echo 2>/dev/null');fwrite(STDOUT,"\n");}
 $password=password_value(['password'=>$password]);sql('UPDATE members SET password_hash=? WHERE email=?',[password_digest($password),$email]);sql('DELETE FROM invitations WHERE email=?',[$email]);
 fwrite(STDOUT,"Passwort aktualisiert. Bisherige Sitzungen dieses Kontos sind ungültig.\n");
}catch(Throwable $e){fwrite(STDERR,"Passwort konnte nicht aktualisiert werden. ".($e instanceof AppError?$e->getMessage():'Bitte E-Mail-Adresse und Datenbankkonfiguration prüfen.')."\n");exit(1);}
