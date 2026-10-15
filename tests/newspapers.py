"""Disposable API tests: newspaper fields, search, article access boundaries and re-indexing."""
import urllib.request,urllib.error,http.cookiejar,json,uuid,base64,hashlib,zipfile,io
from pathlib import Path
BASE='http://127.0.0.1:5922'
class Client:
 def __init__(self,email='owner@example.test'):
  self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=self.call('/api/auth')[1]['csrf'];assert self.call('/api/auth',{'action':'login','email':email,'password':'local-clipping-test-password'})[0]==200;self.csrf=self.call('/api/auth')[1]['csrf']
 def call(self,path,v=None):
  req=urllib.request.Request(BASE+path,data=json.dumps(v).encode() if v is not None else None,headers={'Origin':BASE,'Content-Type':'application/json','X-CSRF-Token':self.csrf if hasattr(self,'csrf') else ''})
  try:r=self.opener.open(req)
  except urllib.error.HTTPError as e:r=e
  b=r.read();return r.status,json.loads(b) if 'json' in r.headers.get('Content-Type','') else b
 def action(self,v):
  status,result=self.call('/api/library',v);assert status==200,(status,result);return result
c=Client();member=Client('member@example.test');unique=uuid.uuid4().hex;raw=(Path(__file__).parent/'fixtures/two-columns.pdf').read_bytes();news={'publication':'Tageszeitung '+unique,'issueDate':'2026-09-15','edition':'Hildesheim','section':'Politik'}
source=c.action({'action':'pdf','allowDuplicate':True,'title':'Ausgabe '+unique,'file':base64.b64encode(raw).decode(),'content':'Seite 1\nEin ausgewählter Artikel zum Datenschutz. PRIVATE OTHER ARTICLE.','metadata':{'filename':'test.pdf','pages':1},'newspaper':news})['id'];detail=c.call('/api/shared?id='+source)[1];assert detail['newspaper']['publication']==news['publication']
for query in ['publication='+urllib.parse.quote(news['publication'])+'&issueFrom=2026-09-01&issueUntil=2026-09-30','q='+urllib.parse.quote(news['publication'])]:assert c.call('/api/library?list=1&'+query)[1]['totalResults']==1
assert c.call('/api/library?list=1&publication='+urllib.parse.quote(news['publication'])+'&issueFrom=2026-10-01')[1]['totalResults']==0
assert c.call('/api/library',{'action':'newspaper','id':source,'revision':detail['edit_revision'],'newspaper':{**news,'issueDate':'2026-02-30'}})[0]==400
assert member.call('/api/library',{'action':'newspaper','id':source,'revision':detail['edit_revision'],'newspaper':news})[0]==403
article=c.action({'action':'newspaperArticle','parentId':source,'revision':detail['edit_revision'],'title':'Einzelartikel '+unique,'content':'Ein ausgewählter Artikel zum Datenschutz.','note':'PRIVATE ARTICLE NOTE','pageFrom':1,'pageUntil':1,'newspaper':news})['id']
token=c.action({'action':'share','id':article,'kind':'clip'})['token'];shared=c.call('/api/share?token='+token)[1];assert len(shared['clips'])==1 and shared['clips'][0]['archive_key'] is None;assert shared['clips'][0]['note']=='';assert shared['clips'][0]['newspaper']['parentId']==source;assert 'PRIVATE OTHER ARTICLE' not in json.dumps(shared)
assert c.call('/api/shared?token='+token+'&id='+source+'&pdf=1')[0]==404;assert c.call('/api/shared?token='+token+'&id='+article+'&pdf=1')[0]==404
assert c.call('/api/shared?token='+token+'&id='+article+'&archive=1')[0]==404
assert c.call('/api/library',{'action':'newspaperArticle','parentId':source,'revision':detail['edit_revision'],'title':'Bad range','content':'text','pageFrom':1,'pageUntil':2})[0]==400
before=c.call('/api/shared?id='+source+'&pdf=1')[1];c.action({'action':'edit','id':source,'revision':detail['edit_revision'],'title':detail['title'],'url':detail['url'],'content':'Seite 1\nNeu ausgewerteter Zeitungstext '+unique,'note':detail['note'],'tags':detail['tags'],'pdfExtraction':{'textOrder':'layout','layoutPages':1,'fallbackPages':0}});updated=c.call('/api/shared?id='+source)[1];assert updated['pdf']['textOrder']=='layout' and updated['pdf']['layoutPages']==1;assert c.call('/api/shared?id='+source+'&pdf=1')[1]==before
assert c.call('/api/library',{'action':'newspaper','id':source,'revision':detail['edit_revision'],'newspaper':news})[0]==409
assert c.call('/api/library?list=1&q='+urllib.parse.quote('ausgewerteter '+unique))[1]['totalResults']==1
assert c.call('/api/share?token='+token)[1]['clips'][0]['content']=='Ein ausgewählter Artikel zum Datenschutz.'
status,export=c.call('/api/library',{'action':'export','id':'all'});assert status==200;manifest=json.loads(zipfile.ZipFile(io.BytesIO(export)).read('sammlung.json'));assert next(x for x in manifest['clips'] if x['id']==article)['newspaper']['parentId']==source
print('PASS: newspaper/date filters, metadata search, edit authorization, invalid dates/pages, article-only shares, source PDF denial, unchanged original, revised extraction, stale edit guard and export relationships.')
print('LOCAL_ARTICLE_LINK='+BASE+'/s/'+token)
