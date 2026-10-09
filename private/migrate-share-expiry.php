<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
$columns=sql('SHOW COLUMNS FROM shares')->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('expires_at',$columns,true))db()->exec('ALTER TABLE shares ADD COLUMN expires_at BIGINT NULL');
echo "Freigabelaufzeiten bereit.\n";
