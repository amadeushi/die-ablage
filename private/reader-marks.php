<?php
declare(strict_types=1);
function reader_marks_list(array $m,string $id):array{
 if(!sql('SELECT c.id FROM clips c WHERE c.id=? AND '.active_clip_condition(),[$id])->fetch())problem('Dieser Clip ist nicht verfügbar.',404);
 return sql('SELECT id,section,quote_text AS quote,prefix_text AS prefix,suffix_text AS suffix FROM reader_marks WHERE clip_id=? AND member_email=? ORDER BY created_at,id',[$id,$m['email']])->fetchAll();
}
function reader_marks_change(array $m,array $v):array{
 $id=field($v,'id',36,true);reader_marks_list($m,$id);
 if(($v['action']??'')==='removeMark'){sql('DELETE FROM reader_marks WHERE id=? AND clip_id=? AND member_email=?',[field($v,'markId',36,true),$id,$m['email']]);return ['ok'=>true];}
 $section=field($v,'section',16,true);if(!in_array($section,['content','note'],true))problem('Bitte den Textbereich prüfen.');
 $quote=field($v,'quote',2000,true);if(trim($quote)==='')problem('Bitte eine Textstelle auswählen.');
 $prefix=field($v,'prefix',64);$suffix=field($v,'suffix',64);
 db()->beginTransaction();try{
 sql('SELECT email FROM members WHERE email=?'.(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$m['email']])->fetch();
 if((int)sql('SELECT COUNT(*) FROM reader_marks WHERE clip_id=? AND member_email=?',[$id,$m['email']])->fetchColumn()>=100)problem('Maximal 100 Markierungen pro Clip. Bitte zuerst eine entfernen.');
 $markId=uid();sql('INSERT INTO reader_marks(id,clip_id,member_email,section,quote_text,prefix_text,suffix_text,created_at) VALUES(?,?,?,?,?,?,?,?)',[$markId,$id,$m['email'],$section,$quote,$prefix,$suffix,timestamp()]);db()->commit();
 return ['mark'=>['id'=>$markId,'section'=>$section,'quote'=>$quote,'prefix'=>$prefix,'suffix'=>$suffix]];
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
