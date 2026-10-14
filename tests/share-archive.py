"""Isolated local regression for share privacy, selection and PDF archive integrity."""
import io,zipfile
import urllib.request,urllib.error,urllib.parse,http.cookiejar,json,uuid,base64,hashlib,sqlite3
from pathlib import Path
BASE='http://127.0.0.1:5921'
class Client:
 def __init__(self):
  self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=self.call('/api/auth')[1]['csrf']
  assert self.call('/api/auth',{'action':'login','email':'owner@example.test','password':'local-clipping-test-password'})[0]==200
  self.csrf=self.call('/api/auth')[1]['csrf']
 def call(self,path,data=None):
  req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers={'Origin':BASE,'X-CSRF-Token':getattr(self,'csrf',''),'Content-Type':'application/json'})
  try:r=self.opener.open(req)
  except urllib.error.HTTPError as e:r=e
  return r.status,json.loads(r.read())
 def action(self,data):
  status,j=self.call('/api/library',data);assert status==200,(status,j);return j
c=Client();collection=c.action({'action':'collection','name':'Isolated sharing test '+str(uuid.uuid4())})['id']
def clip(title,col=collection):return c.action({'action':'clip','url':'https://example.test/'+str(uuid.uuid4()),'title':title,'type':'article','content':'A public archived passage.','note':'PRIVATE NOTE TEST','collectionId':col})['id']
a=clip('First clip')
preview=c.action({'action':'sharePreview','id':collection,'kind':'collection','includeNote':False,'scope':'fixed'});assert preview['preview']['note']==''
assert c.call('/api/library',{'action':'sharePreview','id':collection,'kind':'collection','previewClipId':'outside'})[0]==404
fixed=c.action({'action':'share','id':collection,'kind':'collection','scope':'fixed','includeNote':False})['token']
dynamic=c.action({'action':'share','id':collection,'kind':'collection','includeNote':True})['token'];b=clip('Later clip')
def shared(token):return c.call('/api/share?token='+token)[1]['clips']
assert [x['id'] for x in shared(fixed)]==[a];assert shared(fixed)[0]['note']==''
assert len(shared(dynamic))==2 and all(x['note']=='PRIVATE NOTE TEST' for x in shared(dynamic))
other=c.action({'action':'collection','name':'Moved test clips'})['id'];detail=c.call('/api/shared?id='+a)[1]
c.action({'action':'edit','id':a,'revision':detail['edit_revision'],'title':detail['title'],'url':detail['url'],'content':detail['content'],'note':detail['note'],'collectionId':other,'tags':[]})
assert shared(fixed)[0]['id']==a;assert [x['id'] for x in shared(dynamic)]==[b]
fixed_only=c.action({'action':'collection','name':'Fixed-only selection'})['id'];old=clip('Selected document',fixed_only)
c.action({'action':'share','id':fixed_only,'kind':'collection','scope':'fixed'})
future_title='Unshared future '+str(uuid.uuid4());future=clip(future_title,fixed_only)
assert c.call('/api/library?list=1&view=shared&q='+urllib.parse.quote(future_title))[1]['totalResults']==0
legacy=c.action({'action':'share','id':a,'kind':'clip'})['token']
db=sqlite3.connect(Path(__file__).resolve().parents[2]/'work/clip-api-test/private/test.sqlite');db.execute('DELETE FROM share_options WHERE share_id IN (SELECT id FROM shares WHERE hash=?)',(hashlib.sha256(legacy.encode()).hexdigest(),));db.commit();assert shared(legacy)[0]['note']=='PRIVATE NOTE TEST';db.close()
raw=(Path(__file__).parent/'fixtures/digital.pdf').read_bytes()
def pdf(bytes,title='PDF integrity test',**kwargs):return {'action':'pdf','title':title,'url':'https://example.test/'+str(uuid.uuid4()),'content':'Seite 1\nDigital PDF test content','note':'Private PDF note','collectionId':collection,'metadata':{'filename':'identical-name.pdf','pages':1},'file':base64.b64encode(bytes).decode(),**kwargs}
first=c.action(pdf(raw,allowDuplicate=True))['id'];dup=c.call('/api/library',pdf(raw));assert dup[0]==409 and dup[1]['duplicates']
assert c.call('/api/library?duplicates=1&pdfHash='+hashlib.sha256(raw).hexdigest())[1]['duplicates']
second=c.action(pdf(raw.replace(b'%%EOF',('% Revised test file '+str(uuid.uuid4())+'\n%%EOF').encode()),previousVersionId=first))['id']
versions=c.call('/api/shared?id='+second)[1]['versions'];assert {v['id'] for v in versions}=={first,second}
pub=c.action({'action':'share','id':second,'kind':'clip'})['token'];public=c.call('/api/shared?token='+pub+'&id='+second)[1];assert public['note']=='' and 'versions' not in public and 'previousId' not in public['pdf']
original=c.opener.open(BASE+'/api/shared?id='+first+'&pdf=1').read();assert original==raw
foreign=c.call('/api/shared?token='+pub+'&id='+first);assert foreign[0]==404
share_id=next(s['id'] for s in c.call('/api/library?list=1')[1]['shares'] if s['target_id']==second)
c.action({'action':'revokeShare','id':second,'kind':'clip','shareId':share_id});assert c.call('/api/share?token='+pub)[0]==404
print('PASS: preview privacy, fixed/dynamic selection, legacy notes, PDF hash duplicates, linked versions, immutable originals, scoped access and revocation.')

request=urllib.request.Request(BASE+'/api/library',data=json.dumps({'action':'export','id':collection}).encode(),headers={'Origin':BASE,'X-CSRF-Token':c.csrf,'Content-Type':'application/json'})
archive=zipfile.ZipFile(io.BytesIO(c.opener.open(request).read()));manifest=json.loads(archive.read('sammlung.json'));entry=next(x for x in manifest['clips'] if x['id']==second);assert entry['previousVersionId']==first
assert any(name.endswith('.pdf') for name in archive.namelist())
print('PASS: export retains PDF originals and version relationships.')
html='<article><h1>Original test archive</h1><p>Preserved text</p><script>window.untrusted=true</script></article>'
html_id=c.action({'action':'clip','url':'https://example.test/'+str(uuid.uuid4()),'title':'HTML export fixture','type':'article','content':'Preserved text','archive':html,'collectionId':collection})['id']
request=urllib.request.Request(BASE+'/api/library',data=json.dumps({'action':'export','id':'all'}).encode(),headers={'Origin':BASE,'X-CSRF-Token':c.csrf,'Content-Type':'application/json'})
full=zipfile.ZipFile(io.BytesIO(c.opener.open(request).read()));assert full.read('originale/'+html_id+'.html.txt').decode()==html
assert full.read('seiten/'+html_id+'.html').decode().startswith('<meta http-equiv="Content-Security-Policy"')
assert json.loads(full.read('sammlung.json'))['collection']=='Bibliothek'
print('PASS: library export, exact HTML originals and protected HTML reading copies.')
