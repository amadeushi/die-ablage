"""Isolated public-share regression tests. No production config, database or credentials.
Run from all-inkl: python3 tests/share-preview.py
"""
from pathlib import Path
import hashlib, html, json, re, shutil, socket, sqlite3, subprocess, tempfile, time
import urllib.request, urllib.error
source=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='ablage-preview-') as tmp:
 root=Path(tmp); (root/'private').mkdir(); (root/'public').mkdir()
 for name in ['bootstrap.php','share-preview.php']:shutil.copyfile(source/'private'/name,root/'private'/name)
 for name in ['index.html','api.php','share.php']:shutil.copyfile(source/'public'/name,root/'public'/name)
 shutil.copyfile(source/'router.php',root/'router.php')
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 base=f'http://127.0.0.1:{port}'; dbfile=root/'private/test.sqlite'
 config={'dsn':'sqlite:'+str(dbfile),'user':'','password':'','origin':base,'setup_key':'isolated-test'}
 (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
 (root/'private/installed.lock').touch();db=sqlite3.connect(dbfile)
 db.executescript('CREATE TABLE clips(id TEXT,title TEXT,url TEXT,type TEXT,content TEXT,note TEXT,collection_id TEXT,author TEXT,archive_key TEXT,created_at INTEGER);CREATE TABLE collections(id TEXT,name TEXT);CREATE TABLE shares(id TEXT,hash TEXT,target_id TEXT,kind TEXT,created_at INTEGER);')
 title='Recherche $1 & "<script>alert(1)</script>" ä'
 excerpt='Öffentliche Testquelle mit Umlauten und ausreichend Inhalt. '*8
 key='a'*32+'.html';(root/'private/archives').mkdir();(root/'private/archives'/key).write_text('<h1>Gespeicherte Testquelle</h1><script>alert(1)</script>')
 db.execute('INSERT INTO collections VALUES (?,?)',('collection','Testsammlung'));db.execute('INSERT INTO collections VALUES (?,?)',('empty','Leere Sammlung'))
 db.execute('INSERT INTO clips VALUES (?,?,?,?,?,?,?,?,?,?)',('clip',title,'https://example.com','page',title+'\n\n'+excerpt,'Private Testnotiz','collection','private@example.test',key,1))
 db.execute('INSERT INTO clips VALUES (?,?,?,?,?,?,?,?,?,?)',('other','Nicht freigegeben','https://example.com','article','UNSHARED_SECRET','','other','private@example.test',None,2))
 tokens={kind:hashlib.sha256(kind.encode()).hexdigest() for kind in ['clip','collection','empty']}
 for kind,token in tokens.items():db.execute('INSERT INTO shares VALUES (?,?,?,?,?)',(kind,hashlib.sha256(token.encode()).hexdigest(),kind,'clip' if kind=='clip' else 'collection',1))
 db.commit()
 server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(root/'public'),str(root/'router.php')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 def request(path):
  try:response=urllib.request.urlopen(base+path)
  except urllib.error.HTTPError as e:response=e
  return response.status,response.read().decode(),response.headers
 try:
  for _ in range(40):
   try:request('/');break
   except urllib.error.URLError:time.sleep(.05)
  status,body,headers=request('/s/'+tokens['clip']);assert status==200
  assert html.escape(title,quote=True) in body and '$1' in body
  assert '<script>alert(1)</script>' not in body
  assert 'property="og:title"' in body and 'property="og:image"' in body and 'name="twitter:card"' in body
  assert base+'/share-preview.png' in body and base+'/s/'+tokens['clip'] in body
  assert 'UNSHARED_SECRET' not in body and 'private@example.test' not in body and 'Private Testnotiz' not in body
  description=html.unescape(re.search(r'name="description" content="([^"]+)"',body).group(1));assert len(description)<=160 and title not in description and description.endswith('…')
  assert headers['Cache-Control']=='no-store' and headers['X-Robots-Tag'].startswith('noindex') and not headers.get('Set-Cookie')
  assert request('/s/'+tokens['collection'])[0]==200
  assert 'Diese Sammlung ist noch leer' in request('/s/'+tokens['empty'])[1]
  status,archive,headers=request('/api/shared?token='+tokens['clip']+'&id=clip&archive=1');assert status==200 and 'viewport' in archive and 'sandbox;' in headers['Content-Security-Policy'] and 'frame-ancestors' in headers['Content-Security-Policy']
  assert request('/api/shared?token='+tokens['clip']+'&id=other&archive=1')[0]==404
  db.execute('DELETE FROM shares WHERE id=?',('clip',));db.commit()
  for path in ['/s/'+tokens['clip'],'/s/'+'0'*64,'/s/invalid','/s/']:
   status,body,headers=request(path);assert status==404 and 'og:title' not in body and 'Öffentliche Testquelle' not in body
  assert request('/api/share?token='+tokens['clip'])[0]==404
  assert request('/api/shared?token='+tokens['clip']+'&id=clip&archive=1')[0]==404
  print('PASS: initial HTML preview, escaped titles, Unicode excerpts, collection/empty, no privileged data, no session cookie, no-store/noindex, sandboxed archive, share scope and revocation.')
 finally:server.terminate();server.wait(timeout=5);db.close()
