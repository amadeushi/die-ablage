"""Isolated public-share regression tests. No production config, database or credentials.
Run from all-inkl: python3 tests/share-preview.py
"""
from pathlib import Path
import hashlib, html, json, re, shutil, socket, sqlite3, subprocess, tempfile, time
import urllib.request, urllib.error
source=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='ablage-preview-') as tmp:
 root=Path(tmp); (root/'private').mkdir(); (root/'public').mkdir()
 for name in ['bootstrap.php','tags.php','pdf-store.php','clip-edit.php','share-preview.php','library-list.php']:shutil.copyfile(source/'private'/name,root/'private'/name)
 for name in ['index.html','api.php','share.php']:shutil.copyfile(source/'public'/name,root/'public'/name)
 shutil.copyfile(source/'router.php',root/'router.php')
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 base=f'http://127.0.0.1:{port}'; dbfile=root/'private/test.sqlite'
 config={'dsn':'sqlite:'+str(dbfile),'user':'','password':'','origin':base,'setup_key':'isolated-test'}
 (root/'private/config.php').write_text('<?php return json_decode('+json.dumps(json.dumps(config))+',true);')
 (root/'private/installed.lock').touch();db=sqlite3.connect(dbfile)
 db.executescript('CREATE TABLE clip_tags(clip_id TEXT,tag TEXT,PRIMARY KEY(clip_id,tag));CREATE TABLE clips(id TEXT,title TEXT,url TEXT,type TEXT,content TEXT,note TEXT,collection_id TEXT,author TEXT,archive_key TEXT,created_at INTEGER);CREATE TABLE collections(id TEXT,name TEXT,created_at INTEGER);CREATE TABLE members(email TEXT,name TEXT,role TEXT,password_hash TEXT,created_at INTEGER);CREATE TABLE shares(id TEXT,hash TEXT,target_id TEXT,kind TEXT,created_at INTEGER);')
 title='Recherche $1 & "<script>alert(1)</script>" ä'
 excerpt='Öffentliche Testquelle mit Umlauten und ausreichend Inhalt. '*8
 key='a'*32+'.html';(root/'private/archives').mkdir();(root/'private/archives'/key).write_text('<h1>Gespeicherte Testquelle</h1><script>alert(1)</script>')
 db.execute('INSERT INTO collections VALUES (?,?,?)',('collection','Testsammlung',1));db.execute('INSERT INTO collections VALUES (?,?,?)',('empty','Leere Sammlung',2))
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
  assert request('/api/library?list=1')[0]==401
  # Authenticate a synthetic owner in the isolated SQLite database.
  import http.cookiejar
  digest=subprocess.check_output(['php','-r',"echo password_hash(hash('sha256','test-only-password'),PASSWORD_DEFAULT);"]).decode()
  db.execute('INSERT INTO members VALUES (?,?,?,?,?)',('test-owner@example.test','Testinhaber','owner',digest,1));db.commit()
  opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  auth=json.loads(opener.open(base+'/api/auth').read())
  login=urllib.request.Request(base+'/api/auth',data=json.dumps({'action':'login','email':'test-owner@example.test','password':'test-only-password'}).encode(),headers={'Origin':base,'X-CSRF-Token':auth['csrf'],'Content-Type':'application/json'})
  assert opener.open(login).status==200
  listing=json.loads(opener.open(base+'/api/library?list=1').read())
  assert listing['totalClips']==2 and listing['pageSize']==40 and len(listing['clips'])==2
  assert all('content' not in c and 'note' not in c for c in listing['clips'])
  detail=json.loads(opener.open(base+'/api/shared?id=clip').read());assert detail['content']==title+'\n\n'+excerpt
  search=json.loads(opener.open(base+'/api/library?list=1&q=UNSHARED_SECRET').read());assert search['totalResults']==1 and search['clips'][0]['id']=='other'
  db.execute('INSERT INTO clip_tags VALUES (?,?)',('clip','Verkehr'));db.commit()
  assert json.loads(opener.open(base+'/api/library?list=1&tag=Verkehr&source=example.com').read())['totalResults']==1
  assert json.loads(opener.open(base+'/api/library?list=1&q=Verkehr').read())['clips'][0]['hit']['label']=='Tag'
  assert 'Verkehr' in json.loads(opener.open(base+'/api/shared?id=clip').read())['tags']
  assert json.loads(opener.open(base+'/api/library?list=1').read())['tagSuggestions']==['Verkehr']
  detail=json.loads(opener.open(base+'/api/shared?id=clip').read())
  # Editing preserves original archives and existing share tokens; enforce rights/conflicts.
  csrf=json.loads(opener.open(base+'/api/auth').read())['csrf']
  def post(payload,client=opener,csrf_token=csrf):
   req=urllib.request.Request(base+'/api/library',data=json.dumps(payload).encode(),headers={'Origin':base,'X-CSRF-Token':csrf_token,'Content-Type':'application/json'})
   try:r=client.open(req)
   except urllib.error.HTTPError as e:r=e
   return r.status,json.loads(r.read())
  # Digital PDFs: protected original, metadata/full-text search and revocable shares.
  import base64
  pdf_bytes=(source/'tests/fixtures/digital.pdf').read_bytes()
  pdf_payload={'action':'pdf','title':'Digitales Testdokument','url':'','file':base64.b64encode(pdf_bytes).decode(),'content':'Seite 2\nVolltextfundwort am Dokumentende.','note':'PDF-Testnotiz','collectionId':'','tags':['PDF','Verkehr'],'metadata':{'filename':'digital.pdf','title':'Digitales Testdokument','author':'Testautor','keywords':'Metadatenfundwort','pages':2}}
  status,pdf_result=post(pdf_payload);assert status==200
  pdf_id=pdf_result['id'];pdf_detail=json.loads(opener.open(base+'/api/shared?id='+pdf_id).read())
  assert pdf_detail['type']=='pdf' and pdf_detail['pdf']['author']=='Testautor' and pdf_detail['pdf']['bytes']==len(pdf_bytes)
  assert '[PDF-Dokumentdaten]' not in pdf_detail['content']
  for term in ['Volltextfundwort','Metadatenfundwort','digital.pdf']:
   found=json.loads(opener.open(base+'/api/library?list=1&q='+term).read());assert found['totalResults']==1 and found['clips'][0]['id']==pdf_id
  assert json.loads(opener.open(base+'/api/library?list=1&q=Volltextfundwort').read())['clips'][0]['hit']['page']==2
  assert pdf_detail['tags']==['PDF','Verkehr']
  assert post({**pdf_payload,'tags':['Tag'+str(i) for i in range(13)]})[0]==400
  response=opener.open(base+'/api/shared?id='+pdf_id+'&pdf=1');assert response.read()==pdf_bytes and response.headers['Content-Type']=='application/pdf' and response.headers['Content-Disposition'].startswith('attachment')
  assert request('/api/shared?id='+pdf_id+'&pdf=1')[0]==401
  assert request('/api/shared?id='+pdf_id+'&pdf=1&token='+tokens['clip'])[0]==404
  status,pdf_share=post({'action':'share','id':pdf_id,'kind':'clip'});assert status==200
  assert request('/api/shared?id='+pdf_id+'&pdf=1&token='+pdf_share['token'])[0]==200
  status,pdf_updated=post({'action':'edit','id':pdf_id,'revision':pdf_detail['edit_revision'],'title':'PDF korrigiert','url':'','content':'Bearbeiteter PDF-Lesetext','note':'','collectionId':''});assert status==200
  assert opener.open(base+'/api/shared?id='+pdf_id+'&pdf=1').read()==pdf_bytes
  assert json.loads(opener.open(base+'/api/library?list=1&q=Metadatenfundwort').read())['totalResults']==1
  assert post({**pdf_payload,'file':base64.b64encode(b'not-a-pdf').decode()})[0]==400
  assert post({'action':'revoke','id':pdf_id,'kind':'clip'})[0]==200
  assert request('/api/shared?id='+pdf_id+'&pdf=1&token='+pdf_share['token'])[0]==404
  pdf_key=db.execute('SELECT archive_key FROM clips WHERE id=?',(pdf_id,)).fetchone()[0]
  assert post({'action':'delete','id':pdf_id})[0]==200
  assert not (root/'private/archives'/pdf_key).exists() and not (root/'private/archives'/(pdf_key+'.json')).exists()
  edit={'action':'edit','id':'clip','revision':detail['edit_revision'],'title':'Korrigierter Titel','url':'https://example.com/updated','content':'Korrigierter Lesetext','note':'Neue Notiz','collectionId':'empty','archive':'<p>OVERWRITE</p>'}
  status,updated=post(edit);assert status==200 and updated['title']=='Korrigierter Titel' and updated['tags']==['Verkehr']
  assert db.execute('SELECT archive_key,author,created_at FROM clips WHERE id="clip"').fetchone()==(key,'private@example.test',1)
  assert (root/'private/archives'/key).read_text().startswith('<h1>Gespeicherte Testquelle')
  assert request('/api/share?token='+tokens['clip'])[0]==200
  assert json.loads(request('/api/share?token='+tokens['clip'])[1])['clips'][0]['content']=='Korrigierter Lesetext'
  assert post(edit)[0]==409
  bad={**edit,'revision':updated['edit_revision'],'url':'javascript:alert(1)'};assert post(bad)[0]==400
  assert post({**edit,'revision':updated['edit_revision'],'collectionId':'missing'})[0]==400
  # Synthetic creator may edit; a different member cannot, including through note endpoint.
  db.execute('INSERT INTO members VALUES (?,?,?,?,?)',('private@example.test','Ersteller','member',digest,2))
  db.execute('INSERT INTO members VALUES (?,?,?,?,?)',('reader@example.test','Anderes Mitglied','member',digest,3));db.commit()
  def client_for(email):
   client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));a=json.loads(client.open(base+'/api/auth').read())
   req=urllib.request.Request(base+'/api/auth',data=json.dumps({'action':'login','email':email,'password':'test-only-password'}).encode(),headers={'Origin':base,'X-CSRF-Token':a['csrf'],'Content-Type':'application/json'});assert client.open(req).status==200
   return client,json.loads(client.open(base+'/api/auth').read())['csrf']
  reader,rc=client_for('reader@example.test');assert post({**edit,'revision':updated['edit_revision']},reader,rc)[0]==403
  assert post({'action':'note','id':'clip','note':'UNAUTHORIZED'},reader,rc)[0]==403
  creator,cc=client_for('private@example.test');restore={**edit,'revision':updated['edit_revision'],'title':title,'url':'https://example.com','content':title+'\n\n'+excerpt,'note':'Private Testnotiz','collectionId':'collection'}
  assert post(restore,creator,cc)[0]==200
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
  print('PASS: PDF upload/metadata/search/protected original/edit/revoke/delete, authenticated paged library/details/search, anonymous access denied, initial HTML preview, escaped titles, Unicode excerpts, collection/empty, no privileged data, no session cookie, no-store/noindex, sandboxed archive, share scope and revocation.')
 finally:server.terminate();server.wait(timeout=5);db.close()
