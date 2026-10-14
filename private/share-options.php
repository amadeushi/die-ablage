<?php
declare(strict_types=1);
function share_options_value(array $v,string $kind):array {
 $include=$v['includeNote']??false;if(!is_bool($include))problem('Bitte die Notizfreigabe prüfen.');
 $scope=$v['scope']??'dynamic';if(!in_array($scope,['dynamic','fixed'],true)||($kind==='clip'&&$scope!=='dynamic'))problem('Bitte den Freigabeumfang prüfen.');
 return ['includeNote'=>$include,'scope'=>$scope];
}
function share_target_clips(string $id,string $kind,string $scope='dynamic'):array {
 if($kind==='clip')return sql('SELECT c.* FROM clips c WHERE c.id=? AND '.active_clip_condition(),[$id])->fetchAll();
 if($kind!=='collection'||!sql('SELECT id FROM collections WHERE id=?',[$id])->fetch())problem('Diese Sammlung ist nicht verfügbar.',404);
 $clips=sql('SELECT c.* FROM clips c WHERE c.collection_id=? AND '.active_clip_condition().' ORDER BY c.created_at DESC,c.id DESC',[$id])->fetchAll();
 if($scope==='fixed'&&count($clips)>500)problem('Eine feste Auswahl kann maximal 500 Clips enthalten.');return $clips;
}
function share_options_filter(array $s,array $clips):array {
 $options=$s['options_present']!==null?['include_note'=>$s['include_note']]:null;
 // Existing links retain their original note visibility.
 if($options&&!(bool)$options['include_note'])foreach($clips as &$clip){$clip['note']='';}unset($clip);
 return $clips;
}
function share_preview_data(array $v):array {
 $id=field($v,'id',36,true);$kind=field($v,'kind',16,true);$options=share_options_value($v,$kind);$clips=share_target_clips($id,$kind,$options['scope']);
 if($kind==='clip'&&!$clips)problem('Dieser Clip ist nicht verfügbar.',404);
 $chosen=field($v,'previewClipId',36)?:($clips[0]['id']??'');$preview=null;
 foreach($clips as $clip)if($clip['id']===$chosen){if(!$options['includeNote'])$clip['note']='';$preview=public_clip($clip);break;}
 if($chosen&&!$preview)problem('Dieser Clip gehört nicht zur Freigabe.',404);
 return ['clips'=>array_map(fn($clip)=>['id'=>$clip['id'],'title'=>$clip['title']],$clips),'preview'=>$preview];
}

function library_share_list():array {
 $items=sql("SELECT s.id,s.target_id,s.kind,s.created_at,s.expires_at,COALESCE(o.include_note,1) AS include_note,o.clip_ids FROM shares s LEFT JOIN share_options o ON o.share_id=s.id")->fetchAll();
 foreach($items as &$item){$item['scope']=$item['clip_ids']===null?'dynamic':'fixed';$item['clipIds']=$item['clip_ids']===null?null:json_decode($item['clip_ids'],true);unset($item['clip_ids']);}unset($item);return $items;
}
