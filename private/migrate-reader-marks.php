<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
db()->exec("CREATE TABLE IF NOT EXISTS reader_marks (id VARCHAR(36) PRIMARY KEY,clip_id VARCHAR(36) NOT NULL,member_email VARCHAR(190) NOT NULL,section VARCHAR(16) NOT NULL,quote_text TEXT NOT NULL,prefix_text VARCHAR(64) NOT NULL,suffix_text VARCHAR(64) NOT NULL,created_at BIGINT NOT NULL,INDEX(clip_id,member_email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "Persönliche Textmarkierungen bereit.\n";
