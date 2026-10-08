<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
db()->exec('CREATE TABLE IF NOT EXISTS clip_tags(clip_id VARCHAR(36) NOT NULL, tag VARCHAR(80) NOT NULL, PRIMARY KEY(clip_id,tag), INDEX(tag), FOREIGN KEY(clip_id) REFERENCES clips(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
echo "Tags-Speicherung bereit.\n";
