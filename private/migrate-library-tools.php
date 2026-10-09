<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require ($argv[1]??__DIR__).'/bootstrap.php';
foreach([
 'CREATE TABLE IF NOT EXISTS clip_trash(clip_id VARCHAR(36) PRIMARY KEY,deleted_at BIGINT NOT NULL,deleted_by VARCHAR(190) NOT NULL,FOREIGN KEY(clip_id) REFERENCES clips(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
 'CREATE TABLE IF NOT EXISTS clip_favorites(clip_id VARCHAR(36) NOT NULL,member_email VARCHAR(190) NOT NULL,PRIMARY KEY(clip_id,member_email),FOREIGN KEY(clip_id) REFERENCES clips(id) ON DELETE CASCADE,FOREIGN KEY(member_email) REFERENCES members(email) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
 'CREATE TABLE IF NOT EXISTS saved_searches(id VARCHAR(36) PRIMARY KEY,member_email VARCHAR(190) NOT NULL,name VARCHAR(100) NOT NULL,params TEXT NOT NULL,created_at BIGINT NOT NULL,FOREIGN KEY(member_email) REFERENCES members(email) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
] as $query)db()->exec($query);
echo "Favoriten, Suchablagen und Papierkorb bereit.\n";
