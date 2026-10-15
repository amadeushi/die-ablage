<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
$tail=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
db()->exec('CREATE TABLE IF NOT EXISTS article_versions(id VARCHAR(36) PRIMARY KEY,clip_id VARCHAR(36) NOT NULL,title VARCHAR(300) NOT NULL,content MEDIUMTEXT NOT NULL,content_format VARCHAR(16) NOT NULL,archive_key VARCHAR(40) NULL,origin VARCHAR(16) NOT NULL,author VARCHAR(190) NOT NULL,created_at BIGINT NOT NULL)'.$tail);
try{db()->exec('CREATE INDEX article_versions_clip ON article_versions(clip_id,created_at)');}catch(PDOException $e){if(!in_array((string)$e->getCode(),['42000','HY000'],true))throw $e;}
echo "Artikelversionen bereit.\n";
