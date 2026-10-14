<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
db()->exec('CREATE TABLE IF NOT EXISTS share_options(share_id VARCHAR(36) PRIMARY KEY,include_note INTEGER NOT NULL DEFAULT 0,clip_ids TEXT NULL)');
echo "Freigabeoptionen bereit.\n";
