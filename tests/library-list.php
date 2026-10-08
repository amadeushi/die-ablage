<?php
// Isolated in-memory regression: no application config or production database.
declare(strict_types=1);
final class AppError extends RuntimeException {}
function problem(string $message,int $code=400):never{throw new AppError($message,$code);}
function db():PDO{static $db=null;return $db??=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
function sql(string $query,array $args=[]):PDOStatement{$stmt=db()->prepare($query);$stmt->execute($args);return $stmt;}
function public_clip(array $clip):array{$clip['tags']=clip_tags($clip['id']);$clip['created_at']=(int)$clip['created_at'];$clip['archive_key']=$clip['archive_key']?'stored':null;return $clip;}
require dirname(__DIR__).'/private/tags.php';
require dirname(__DIR__).'/private/search.php';
require dirname(__DIR__).'/private/library-list.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
db()->exec('CREATE TABLE clip_tags(clip_id TEXT,tag TEXT);CREATE TABLE clips(id TEXT,title TEXT,url TEXT,type TEXT,content TEXT,note TEXT,collection_id TEXT,author TEXT,archive_key TEXT,created_at INTEGER);CREATE TABLE shares(target_id TEXT,kind TEXT);');
for($i=0;$i<95;$i++)sql('INSERT INTO clips VALUES(?,?,?,?,?,?,?,?,?,?)',[(string)$i,'Clip '.$i,'https://example.com/'.$i,$i%2?'article':'page',str_repeat('Langer Testinhalt ',1000).($i===5?' SUCHWORT':'') ,$i===6?'Notizfund 100% _ !':'',$i<50?'collection-a':'collection-b','test@example.test',$i===5?str_repeat('a',32).'.html':null,$i]);
sql('INSERT INTO shares VALUES(?,?)',['5','clip']);sql('INSERT INTO shares VALUES(?,?)',['collection-b','collection']);
$a=library_page([]);check(count($a['clips'])===40&&$a['totalResults']===95&&$a['totalClips']===95,'Pagination/count');check(!array_key_exists('content',$a['clips'][0])&&!array_key_exists('note',$a['clips'][0]),'No full text');check(mb_strlen($a['clips'][0]['summary'])<=220,'Bounded summary');
$b=library_page(['page'=>1]);check($a['clips'][39]['id']!==$b['clips'][0]['id']&&count($b['clips'])===40,'Distinct second page');check(count(library_page(['page'=>2])['clips'])===15,'Last page');check(library_page(['page'=>999])['page']===2,'Clamp page');
check(library_page(['q'=>'SUCHWORT'])['clips'][0]['id']==='5','Search full text beyond summary');check(library_page(['q'=>'Notizfund'])['clips'][0]['id']==='6','Search notes');check(library_page(['q'=>'100% _ !'])['totalResults']===1,'Literal wildcard escaping');
check(library_page(['view'=>'collection-a'])['totalResults']===50,'Collection filter');check(library_page(['view'=>'shared'])['totalResults']===46,'Clip and collection shares');check(library_page(['type'=>'page'])['totalResults']===48,'Type filter');check(library_page(['view'=>'collection-a','type'=>'article'])['totalResults']===25,'Combined filters');check(library_page(['q'=>'missing'])['totalResults']===0,'Empty result');
check($a['collectionCounts']['collection-a']===50,'Sidebar counts');
try{library_page(['type'=>'bad']);throw new RuntimeException('Invalid type accepted');}catch(AppError){}
sql('INSERT INTO clip_tags VALUES(?,?)',['5','Verkehr']);
check(library_page(['tag'=>'Verkehr'])['totalResults']===1,'Tag filter');
check(library_page(['tag'=>'Verkehr','source'=>'example.com','author'=>'test@example.test','type'=>'article'])['totalResults']===1,'Combined tag/source/person/type');
check(library_page(['source'=>'other.example.com'])['totalResults']===0,'Exact source');
$hit=library_page(['q'=>'SUCHWORT'])['clips'][0]['hit'];check($hit['match']==='SUCHWORT'&&$hit['label']==='Inhalt','Actual excerpt beyond first 220 characters');
check(library_page(['q'=>'Verkehr'])['clips'][0]['hit']['label']==='Tag','Search tag');
check(library_page(['from'=>'1970-01-02'])['totalResults']===0,'Date lower bound');
check(library_page(['until'=>'1970-01-01'])['totalResults']===95,'Inclusive date upper bound');
foreach([['from'=>'2026-02-31'],['from'=>'2026-10-09','until'=>'2026-10-08'],['source'=>'example.com%']] as $bad){try{library_page($bad);throw new RuntimeException('Invalid filters accepted');}catch(AppError){}}
sql('UPDATE clips SET url=? WHERE id=?',['https://example.com?query=value','0']);sql('UPDATE clips SET url=? WHERE id=?',['https://example.com:8443/path','1']);
check(library_page(['source'=>'example.com'])['totalResults']===95,'Source with root query and explicit port');
sql('UPDATE clips SET title=?,content=? WHERE id=?',['Bahnhof Datenschutz','Ältere wichtige Quelle ohne Nebenthema','2']);
sql('UPDATE clips SET title=?,content=? WHERE id=?',['Neuester Artikel','Bahnhof: weitere Angaben zum Datenschutz und Parkplatz','94']);
$r=library_page(['q'=>'Bahnhof Datenschutz']);check($r['totalResults']===2&&$r['clips'][0]['id']==='2','Separated words and title relevance outrank newer body hit');
check(library_page(['q'=>'Bahnhof Datenschutz -Parkplatz'])['totalResults']===1,'Exclude term across all fields');
check(library_page(['q'=>'"Bahnhof Datenschutz"'])['totalResults']===1,'Exact phrase');
check(library_page(['q'=>'Bahnhof -"weitere Angaben"'])['totalResults']===1,'Excluded phrase');
check(library_page(['q'=>'Bahnhof Datenschutz','page'=>999])['page']===0,'Relevance pagination bounded');
try{library_page(['q'=>'"unfinished']);throw new RuntimeException('Unfinished phrase accepted');}catch(AppError){}
echo "PASS: 95 clips, bounded summaries, 40-item pagination, stable pages, full-text/note search, literal wildcards, collection/type/share filters and counts.\n";
