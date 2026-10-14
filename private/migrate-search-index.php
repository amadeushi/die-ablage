<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
if(!function_exists('search_index_refresh')) require_once __DIR__.'/search-index.php';
db()->exec('CREATE TABLE IF NOT EXISTS search_index(id VARCHAR(36) PRIMARY KEY,title TEXT NOT NULL,url TEXT NOT NULL,content MEDIUMTEXT NOT NULL,note MEDIUMTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin');
$ids=sql('SELECT id FROM clips')->fetchAll(PDO::FETCH_COLUMN);foreach($ids as $id){db()->beginTransaction();search_index_refresh($id);db()->commit();}
echo "Normalisierter Suchindex bereit.\n";
