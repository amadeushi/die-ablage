<?php
declare(strict_types=1);
function share_is_active(array $share,?int $now=null):bool{return !isset($share['expires_at'])||(int)$share['expires_at']>($now??timestamp());}
function share_expiry(array $v):?int {
 $days=$v['durationDays']??7;
 if($days==='custom'){$expiry=$v['expiresAt']??null;if(!is_int($expiry)||$expiry<=timestamp()||$expiry>timestamp()+366*86400000)problem('Bitte einen zukünftigen Ablaufzeitpunkt innerhalb eines Jahres wählen.');return $expiry;}
 if(!is_int($days)||!in_array($days,[0,1,7,30,90],true))problem('Bitte eine gültige Laufzeit wählen.');
 return $days===0?null:timestamp()+$days*86400000;
}
function share_by_token(string $token):array {
 if(!preg_match('/^[a-f0-9]{64}$/D',$token))problem('Der Leselink ist ungültig oder wurde deaktiviert.',404);
 $s=sql('SELECT target_id,kind,expires_at FROM shares WHERE hash=?',[hash('sha256',$token)])->fetch();if(!$s)problem('Der Leselink ist ungültig oder wurde deaktiviert.',404);
 if(!share_is_active($s))problem('Dieser Leselink ist abgelaufen. Bitte beim Absender eine neue Freigabe anfordern.',410);
 return $s;
}
