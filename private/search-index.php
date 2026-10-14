<?php
declare(strict_types=1);
function search_join_words(string $text):string{return preg_replace('/(\p{L})[ \t]*(?:-|‐|‑)\r?\n[ \t]*(?=\p{L})/u','$1',$text);}
function search_normalize(string $text):string {
 $text=search_join_words($text);
 $text=str_replace(["-\r\n","-\n","\u{00ad}",'Ä','ä','Ö','ö','Ü','ü','ẞ','ß'],['','','','ae','ae','oe','oe','ue','ue','ss','ss'],$text);
 $text=str_replace(['ﬀ','ﬁ','ﬂ','ﬃ','ﬄ','‐','‑'],['ff','fi','fl','ffi','ffl','-','-'],$text);
 return mb_strtolower(preg_replace('/\s+/u',' ',$text));
}
function search_index_refresh(string $id):void {
 $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&db()->inTransaction()?' FOR UPDATE':'';$clip=sql('SELECT id,title,url,content,note FROM clips WHERE id=?'.$lock,[$id])->fetch();if(!$clip){sql('DELETE FROM search_index WHERE id=?',[$id]);return;}
 $values=[$id];foreach(['title','url','content','note'] as $field)$values[]=search_normalize($clip[$field]??'');
 $suffix=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ON DUPLICATE KEY UPDATE title=VALUES(title),url=VALUES(url),content=VALUES(content),note=VALUES(note)':' ON CONFLICT(id) DO UPDATE SET title=excluded.title,url=excluded.url,content=excluded.content,note=excluded.note';
 sql('INSERT INTO search_index(id,title,url,content,note) VALUES(?,?,?,?,?)'.$suffix,$values);
}
