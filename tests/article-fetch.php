<?php
require __DIR__.'/../private/bootstrap.php';require __DIR__.'/../private/article-fetch.php';
foreach(['http://127.0.0.1/x','http://169.254.169.254/x','http://10.0.0.1/','http://100.100.1.1/','http://[::1]/','http://user:pass@example.com/','file:///etc/passwd','http://example.com:22/'] as $url){try{article_public_host($url);throw new RuntimeException('unsafe URL accepted');}catch(AppError $e){}}
$html='<html><head><title>Test</title></head><body><nav>Ignore navigation</nav><article><h1>Eine Meldung</h1><p>'.str_repeat('Ein vollständiger Artikel mit überprüfbaren Fakten. ',10).'</p><script>secretScript()</script><p hidden>Ignore hidden</p></article></body></html>';
$r=article_extract($html);assert($r['title']==='Eine Meldung');assert(!str_contains($r['content'],'Ignore'));assert(!str_contains($r['content'],'secretScript'));
try{article_extract(str_replace('<head>','<head><script type="application/ld+json">{"isAccessibleForFree":false}</script>',$html));throw new RuntimeException('paywall accepted');}catch(AppError $e){}
echo "PASS: private/reserved/credential URL rejection, semantic extraction, hidden/script removal and subscription barrier detection.\n";
