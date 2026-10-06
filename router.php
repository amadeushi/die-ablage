<?php
// Nur lokaler PHP-Entwicklungsserver; auf ALL-INKL übernimmt .htaccess das Routing.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('~^/api/(auth|library|share|shared)$~',$path,$m)){$_GET['route']=$m[1];require __DIR__.'/public/api.php';return true;}
if(is_file(__DIR__.'/public'.$path))return false;
header('Content-Type: text/html; charset=utf-8');readfile(__DIR__.'/public/index.html');return true;
