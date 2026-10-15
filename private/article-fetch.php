<?php
declare(strict_types=1);
function article_public_host(string $url):array{
 $p=parse_url($url);if(!$p||!in_array($p['scheme']??'',['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['port'])&&!in_array($p['port'],[80,443],true))problem('Diese Quellenadresse wird nicht unterstützt.');
 $host=strtolower($p['host']);if(!preg_match('/^[a-z0-9.-]+$/D',$host)||!str_contains($host,'.'))problem('Nur öffentliche Quellseiten können geprüft werden.');
 $ips=filter_var($host,FILTER_VALIDATE_IP)?[$host]:gethostbynamel($host);if(!$ips)problem('Die Quellenadresse konnte nicht aufgelöst werden.');foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)||str_starts_with($ip,'169.254.')||(ip2long($ip)>=ip2long('100.64.0.0')&&ip2long($ip)<=ip2long('100.127.255.255')))problem('Nur öffentliche Quellseiten können geprüft werden.');return [$host,$ips[0],$p['port']??($p['scheme']==='https'?443:80)];
}
function article_fetch_html(string $url):string{
 if(!function_exists('curl_init'))problem('Direktprüfung ist auf diesem Server nicht verfügbar. Bitte erneut clippen.',503);
 $deadline=microtime(true)+22;
 for($hop=0;$hop<4;$hop++){
  [$host,$ip,$port]=article_public_host($url);$html='';$location='';$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>max(1,(int)($deadline-microtime(true))),CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_RESOLVE=>["$host:$port:$ip"],CURLOPT_USERAGENT=>'Die-ABLAGE-SourceCheck/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml'],CURLOPT_ENCODING=>'',CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$html){if(strlen($html)+strlen($chunk)>3000000)return 0;$html.=$chunk;return strlen($chunk);},CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$location){if(stripos($line,'Location:')===0)$location=trim(substr($line,9));return strlen($line);}]);
  $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=curl_getinfo($ch,CURLINFO_CONTENT_TYPE)?:'';unset($ch);
  if(!$ok||microtime(true)>$deadline)problem('Quelle nicht vollständig abrufbar. Bitte im Browser erneut clippen.');
  if(in_array($status,[301,302,303,307,308],true)&&$location){if(str_starts_with($location,'//'))$location=parse_url($url,PHP_URL_SCHEME).':'.$location;elseif(str_starts_with($location,'/'))$location=parse_url($url,PHP_URL_SCHEME).'://'.parse_url($url,PHP_URL_HOST).($port===80||$port===443?'':':'.$port).$location;elseif(!preg_match('~^https?://~i',$location))problem('Diese Weiterleitung bitte im Browser öffnen und erneut clippen.');$url=$location;continue;}
  if($status!==200||!preg_match('~(?:text/html|application/xhtml\+xml)~i',$type))problem('Die Quelle erlaubt keinen direkten Artikelabruf. Bitte angemeldet erneut clippen.');return $html;
 }problem('Zu viele Weiterleitungen. Bitte erneut clippen.');
}
function article_extract(string $html):array{
 $doc=new DOMDocument();$old=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($old);$xp=new DOMXPath($doc);
 // Subscription barriers must never become an article update.
 foreach($xp->query('//script[@type="application/ld+json"]') as $script)if(preg_match('/"isAccessibleForFree"\s*:\s*(?:false|"false")/i',$script->textContent))problem('Die Quelle kennzeichnet den Artikel als zugangsbeschränkt. Bitte angemeldet erneut clippen.');
 // Publisher-specific truncation and schema microdata supplement JSON-LD.
 $blocked='Der Artikel ist ohne Anmeldung nicht vollständig lesbar. Bitte auf der Quellseite anmelden, den vollständigen Artikel erneut clippen und „Neue Fassung vergleichen“ wählen.';
 foreach($xp->query('//*[@itemprop="isAccessibleForFree"]') as $node){$value=strtolower(trim($node->getAttribute('content')?:$node->textContent));if(in_array($value,['false','0'],true))problem($blocked);}
 $bodies=$xp->query('//*[contains(concat(" ",normalize-space(@itemprop)," ")," articleBody ")]');
 foreach($bodies as $body){$nodes=[$body];foreach($body->getElementsByTagName('*') as $node)$nodes[]=$node;foreach($nodes as $node){$classes=preg_split('/\s+/',strtolower($node->getAttribute('class')));if(array_intersect($classes,['u-blur-crop','paywall','login-wall','subscription-wall','article-paywall'])||in_array(strtolower($node->getAttribute('data-access')),['restricted','denied','login'],true))problem($blocked);}
  $text=preg_replace('/\s+/u',' ',trim($body->textContent));if(mb_strlen($text)<1500&&preg_match('/(?:um (?:diesen|den vollständigen) artikel (?:zu|weiter)|(?:artikel|weiterlesen).{0,90}(?:anmelden|einloggen|abonnieren)|(?:anmelden|einloggen).{0,90}(?:artikel|weiterlesen)|sign in to (?:continue|read)|subscribe to (?:continue|read))/iu',$text))problem($blocked);
 }
 foreach(iterator_to_array($xp->query('//script|//style|//noscript|//nav|//aside|//footer|//form|//button|//header[not(ancestor::article)]|//*[@hidden or @aria-hidden="true"]')) as $node)$node->parentNode?->removeChild($node);
 $best=null;$score=0;foreach($xp->query('//article|//main|//*[@role="main"]') as $node){$text=trim($node->textContent);$s=mb_strlen($text)+$node->getElementsByTagName('p')->length*100;foreach($node->getElementsByTagName('a') as $a)$s-=mb_strlen($a->textContent)*2;if($s>$score){$score=$s;$best=$node;}}
 // Explicit article bodies take precedence over wrappers containing comments or teasers.
 if($bodies->length){$best=null;$score=0;foreach($bodies as $body){$length=mb_strlen(trim($body->textContent));if($length>$score){$score=$length;$best=$body;}}}
 if(!$best)problem('Kein verlässlicher Artikelbereich erkannt. Bitte mit der Erweiterung erneut clippen.');
 $walk=function(DOMNode $n)use(&$walk):string{if($n instanceof DOMText)return preg_replace('/\s+/u',' ',$n->textContent);$t=strtolower($n->nodeName);$out='';foreach($n->childNodes as $child)$out.=$walk($child);if(preg_match('/^h[1-6]$/',$t))return "\n\n".str_repeat('#',max(2,(int)substr($t,1))).' '.trim($out)."\n\n";if(in_array($t,['p','div','section','blockquote','ul','ol','table','tr']))return "\n\n".trim($out)."\n\n";if($t==='li')return "\n- ".trim($out);if($t==='br')return "\n";return $out;};
 $content=trim(preg_replace('/\n[ \t]*\n(?:[ \t]*\n)+/u',"\n\n",$walk($best)));if(mb_strlen($content)<200||strlen($content)>1000000)problem('Der erkannte Artikel ist zu kurz oder zu groß. Bitte erneut clippen.');$h=$best->getElementsByTagName('h1')->item(0)??$xp->query('//article//h1')->item(0)??$doc->getElementsByTagName('h1')->item(0);$title=trim($h?->textContent??$doc->getElementsByTagName('title')->item(0)?->textContent??'Artikel');return ['title'=>mb_substr($title,0,300),'content'=>$content,'contentFormat'=>'markdown','origin'=>'check','archive'=>''];
}
