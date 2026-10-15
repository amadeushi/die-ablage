<?php
declare(strict_types=1);
function article_clip(string $id):array{$c=sql('SELECT c.* FROM clips c WHERE c.id=? AND '.active_clip_condition(),[$id])->fetch();if(!$c||!in_array($c['type'],['article','page'],true))problem('Versionsvergleich ist für Artikel und gespeicherte Webseiten verfügbar.',404);return $c;}
function article_snapshot(array $c,string $origin,string $email):void{
 if(!in_array($c['type'],['article','page'],true))return;
 $last=sql('SELECT title,content,content_format,origin FROM article_versions WHERE clip_id=? ORDER BY created_at DESC,id DESC LIMIT 1',[$c['id']])->fetch();
 if($last&&$last['title']===$c['title']&&$last['content']===$c['content']&&$last['content_format']===($c['content_format']??'text')&&($last['origin']!=='manual'||$origin==='manual'))return;
 if((int)sql('SELECT COUNT(*) FROM article_versions WHERE clip_id=?',[$c['id']])->fetchColumn()>=100)problem('Für diesen Artikel sind 100 Fassungen gespeichert. Es wurde nichts überschrieben.');
 $now=max(timestamp(),1+(int)sql('SELECT MAX(created_at) FROM article_versions WHERE clip_id=?',[$c['id']])->fetchColumn());
 sql('INSERT INTO article_versions(id,clip_id,title,content,content_format,archive_key,origin,author,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[uid(),$c['id'],$c['title'],$c['content'],$c['content_format']??'text',$c['archive_key'],$origin,$email,$now]);
}
function article_context(array $m,array $v):array{
 $c=article_clip(field($v,'id',36,true));$versions=sql('SELECT id,title,origin,author,created_at FROM article_versions WHERE clip_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$c['id']])->fetchAll();
 $baseline=sql("SELECT id,title,content,content_format,origin,created_at FROM article_versions WHERE clip_id=? AND origin<>'manual' ORDER BY created_at DESC,id DESC LIMIT 1",[$c['id']])->fetch()?:['title'=>$c['title'],'content'=>$c['content'],'content_format'=>$c['content_format'],'origin'=>'legacy','created_at'=>$c['created_at']];
 return ['current'=>['id'=>$c['id'],'title'=>$c['title'],'url'=>$c['url'],'content'=>$c['content'],'content_format'=>$c['content_format'],'revision'=>clip_edit_revision($c)],'versions'=>$versions,'baseline'=>$baseline,'canEdit'=>clip_edit_allowed($m,$c)];
}
function article_apply(array $m,array $v):array{
 db()->beginTransaction();try{$id=field($v,'id',36,true);$suffix=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';sql('SELECT id FROM clips WHERE id=?'.$suffix,[$id])->fetch();$c=article_clip($id);
 if(!clip_edit_allowed($m,$c))problem('Nur der Ersteller und der Bibliotheksinhaber dürfen Fassungen übernehmen.',403);
 if(!hash_equals(clip_edit_revision($c),field($v,'revision',64,true)))problem('Der Artikel wurde inzwischen geändert. Bitte den Vergleich neu öffnen.',409);
 $url=field($v,'url',10000,true);$normalize=fn($u)=>preg_replace('/#.*$/','',$u);if($normalize($url)!==$normalize($c['url']))problem('Die neue Fassung gehört zu einer anderen Quellenadresse.');
 $title=trim(field($v,'title',300,true));$content=field($v,'content',1000000,true);if(strlen($content)>1000000)problem('Der Artikeltext ist zu groß.');$format=($v['contentFormat']??'text')==='markdown'?'markdown':'text';$origin=($v['origin']??'recapture')==='check'?'check':'recapture';
 article_snapshot($c,sql('SELECT COUNT(*) FROM article_versions WHERE clip_id=?',[$id])->fetchColumn()?'manual':'basis',$m['email']);$key=null;$archive=field($v,'archive',5000000);if(strlen($archive)>5000000)problem('Die Seitenkopie ist zu groß.');
 if($archive){if(!is_dir(__DIR__.'/archives')&&!mkdir(__DIR__.'/archives',0700,true))problem('Die Seitenkopie kann nicht gespeichert werden.',503);$key=bin2hex(random_bytes(16)).'.html';if(file_put_contents(archive_path($key),$archive,LOCK_EX)===false)problem('Die neue Seitenkopie konnte nicht gespeichert werden.',503);chmod(archive_path($key),0600);}
 sql('UPDATE clips SET title=?,content=?,content_format=?,archive_key=? WHERE id=?',[$title,$content,$format,$key,$id]);$updated=article_clip($id);article_snapshot($updated,$origin,$m['email']);search_index_refresh($id);db()->commit();return ['id'=>$id];
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(isset($key)&&$key&&is_file(archive_path($key)))unlink(archive_path($key));throw $e;}
}
