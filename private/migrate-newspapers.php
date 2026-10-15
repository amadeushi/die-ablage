<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
db()->exec('CREATE TABLE IF NOT EXISTS newspaper_records(clip_id VARCHAR(36) PRIMARY KEY,publication VARCHAR(190) NOT NULL DEFAULT "",issue_date VARCHAR(10) NOT NULL DEFAULT "",edition VARCHAR(190) NOT NULL DEFAULT "",section VARCHAR(190) NOT NULL DEFAULT "",parent_id VARCHAR(36) NULL,page_from INTEGER NULL,page_until INTEGER NULL,text_order VARCHAR(16) NOT NULL DEFAULT "",layout_pages INTEGER NOT NULL DEFAULT 0,fallback_pages INTEGER NOT NULL DEFAULT 0,INDEX(publication,issue_date),INDEX(parent_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Zeitungsarchiv bereit.\n";
