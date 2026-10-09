<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
$columns=sql('SHOW COLUMNS FROM clips')->fetchAll(PDO::FETCH_COLUMN);
foreach(['content_format','note_format'] as $name)if(!in_array($name,$columns,true))db()->exec("ALTER TABLE clips ADD COLUMN $name VARCHAR(16) NOT NULL DEFAULT 'text'");
echo "Lesetext- und Notizformate bereit.\n";
