<?php
// Return share-specific metadata in the first HTML response, before JavaScript.
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
require dirname(__DIR__).'/private/share-preview.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$meta=null;$pageTitle='Die ABLAGE · Die PARTEI';
try {
 config();if(!is_file(dirname(__DIR__).'/private/installed.lock'))problem('Die Bibliothek ist noch nicht eingerichtet.',503);
 $path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)?:'';
 if(!preg_match('~^/s/([a-f0-9]{64})/?$~D',$path,$match))problem('Dieser Leselink ist nicht verfügbar.',404);
 $meta=share_preview(shared_data($match[1]),config()['origin'],$match[1]);
 $pageTitle=$meta['title'].' · Die ABLAGE';
} catch(Throwable $e) {
 $status=$e instanceof AppError&&$e->getCode()>=400&&$e->getCode()<=599?(int)$e->getCode():503;
 http_response_code($status);
 $pageTitle=$status===404?'Leselink nicht verfügbar · Die ABLAGE':'Die ABLAGE ist vorübergehend nicht erreichbar';
 if($status===503)error_log('Die ABLAGE Vorschau: '.$e->getMessage());
}
$html=file_get_contents(__DIR__.'/index.html');
if($html===false){http_response_code(503);echo 'Die ABLAGE ist vorübergehend nicht erreichbar.';exit;}
$html=preg_replace_callback('~<title>.*?</title>~s',fn()=>'<title>'.preview_escape($pageTitle).'</title>',$html,1);
$head=$meta?preview_head($meta):'<meta name="robots" content="noindex, nofollow, noarchive"><meta name="description" content="Dieser Leselink ist nicht verfügbar. Bitte einen aktuellen Link anfordern.">';
echo str_replace('</head>',$head.'</head>',$html);
