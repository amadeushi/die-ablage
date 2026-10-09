<?php
declare(strict_types=1);

function preview_escape(string $value): string {return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function preview_excerpt(string $content,string $title,int $limit=160,string $format='text'): string {
 if($format==='markdown')$content=preg_replace('/\[([^\]]+)\]\([^)]*\)|(?:^|\n)\s*(?:#{1,6} |[-*>] |[0-9]+\. )|[*`_]/u','$1',strip_tags($content));
 $lines=preg_split('/\R/u',trim($content))?:[];
 if($lines&&mb_strtolower(trim($lines[0]))===mb_strtolower(trim($title)))array_shift($lines);
 $text=trim(preg_replace('/\s+/u',' ',implode(' ',$lines))??'');
 if(mb_strlen($text)<=$limit)return $text;
 $cut=mb_substr($text,0,$limit-1);$space=mb_strrpos($cut,' ');
 if($space!==false&&$space>$limit*0.7)$cut=mb_substr($cut,0,$space);
 return rtrim($cut).'…';
}
function share_preview(array $share,string $origin,string $token): array {
 $clips=$share['clips'];$first=$clips[0]??null;
 $excerpt=$first?preview_excerpt((string)($first['content']??''),(string)$first['title'],160,(string)($first['content_format']??'text')):'';
 $description=$excerpt?:($first?'Zum Lesen geteilt: '.$first['title']:'Diese Sammlung ist noch leer. Neue Clips erscheinen, sobald sie hinzugefügt werden.');
 if(count($clips)>1)$description=count($clips).' Clips · '.$description;
 return ['title'=>(string)$share['title'],'description'=>$description,'url'=>rtrim($origin,'/').'/s/'.$token,'image'=>rtrim($origin,'/').'/share-preview.png','type'=>count($clips)===1?'article':'website'];
}
function preview_head(array $meta): string {
 $tags=['description'=>$meta['description'],'robots'=>'noindex, nofollow, noarchive','og:title'=>$meta['title'],'og:description'=>$meta['description'],'og:type'=>$meta['type'],'og:url'=>$meta['url'],'og:site_name'=>'Die ABLAGE','og:locale'=>'de_DE','og:image'=>$meta['image'],'og:image:secure_url'=>$meta['image'],'og:image:type'=>'image/png','og:image:width'=>'1200','og:image:height'=>'630','og:image:alt'=>'Die ABLAGE – Wissen ist Macht. Ablage auch. Ein Projekt von Die PARTEI.','twitter:card'=>'summary_large_image','twitter:title'=>$meta['title'],'twitter:description'=>$meta['description'],'twitter:image'=>$meta['image']];
 $html='';foreach($tags as $key=>$value)$html.='<meta '.(str_starts_with($key,'og:')?'property':'name').'="'.preview_escape($key).'" content="'.preview_escape((string)$value).'">'."\n";
 return $html;
}
