<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
db()->exec('CREATE TABLE IF NOT EXISTS dossiers(id VARCHAR(36) PRIMARY KEY,title VARCHAR(300) NOT NULL,intro MEDIUMTEXT NOT NULL,entries MEDIUMTEXT NOT NULL,author VARCHAR(190) NOT NULL,created_at BIGINT NOT NULL,updated_at BIGINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Dossiers bereit.\n";
