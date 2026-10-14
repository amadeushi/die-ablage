"""Isolated API regression: boolean search, citation-only grants, dossier revisions and exports."""
import urllib.request,urllib.error,http.cookiejar,json,uuid,io,zipfile
BASE='http://127.0.0.1:5921'
class Client:
 def __init__(self,email='owner@example.test'):
  self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=self.call('/api/auth')[1]['csrf']
  assert self.call('/api/auth',{'action':'login','email':email,'password':'local-clipping-test-password'})[0]==200
  self.csrf=self.call('/api/auth')[1]['csrf']
 def call(self,path,data=None):
  request=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers={'Origin':BASE,'X-CSRF-Token':getattr(self,'csrf',''),'Content-Type':'application/json'})
  try:r=self.opener.open(request)
  except urllib.error.HTTPError as e:r=e
  return r.status,json.loads(r.read())
 def action(self,data):
  status,result=self.call('/api/library',data);assert status==200,(status,result);return result
owner=Client();member=Client('member@example.test');suffix=str(uuid.uuid4())
def clip(title,content):return owner.action({'action':'clip','title':title,'content':content,'type':'article','url':'https://example.test/'+str(uuid.uuid4()),'note':'PRIVATE DOSSIER TEST NOTE'})['id']
a=clip('Dossierquelle '+suffix,'Ein Bericht zum Daten-\nschutz bei Müller. Eine ausgewählte Passage.');b=clip('Dossierdokument '+suffix,'Ein vollständiges Dokument mit Einordnung.')
q=urllib.parse.quote('(Datenschutz ODER Muelller) UND "Dossierquelle '+suffix+'"');assert owner.call('/api/library?list=1&q='+q)[1]['totalResults']==1
source=owner.call('/api/shared?id='+a)[1];owner.action({'action':'note','id':a,'note':'Geänderte Notiz mit Strasse','noteFormat':'text'});assert owner.call('/api/library?list=1&q='+urllib.parse.quote('Strasse "Dossierquelle '+suffix+'"'))[1]['totalResults']==1
entry={'id':str(uuid.uuid4()),'clipId':a,'kind':'quote','quote':'Eine ausgewählte Passage.','comment':'Warum dieses Zitat wichtig ist.','page':0}
d=owner.action({'action':'saveDossier','title':'Dossier API test '+suffix,'intro':'## Einordnung\nEine überprüfbare Recherche.','entries':[entry]})
token=owner.action({'action':'share','id':d['id'],'kind':'dossier'})['token'];public=owner.call('/api/share?token='+token)[1]
assert 'summary' not in public['dossier']['entries'][0]['source'] and 'pdf' not in public['dossier']['entries'][0]['source'] and 'sourceTitle' not in public['dossier']['entries'][0]
assert public['clips']==[] and public['dossier']['entries'][0]['quote']==entry['quote'];assert 'PRIVATE DOSSIER TEST NOTE' not in json.dumps(public)
assert owner.call('/api/shared?token='+token+'&id='+a)[0]==404,'Quote-only must not grant source PDF/HTML/text'
assert member.call('/api/library',{'action':'saveDossier',**d,'title':'Unauthorized'})[0]==403
changed=owner.action({'action':'saveDossier',**d,'entries':[entry,{'id':str(uuid.uuid4()),'clipId':b,'kind':'document','quote':'','comment':'Dokument zur Vertiefung.','page':0}]})
assert owner.call('/api/library',{'action':'saveDossier',**d,'intro':'Stale edit'})[0]==409
assert owner.call('/api/shared?token='+token+'&id='+b)[1]['note']==''
assert owner.call('/api/library?list=1&view=shared&q='+urllib.parse.quote('Dossierdokument '+suffix))[1]['totalResults']==1
assert owner.call('/api/library?list=1&view=shared&q='+urllib.parse.quote('Dossierquelle '+suffix))[1]['totalResults']==0
preview=owner.action({'action':'sharePreview','id':d['id'],'kind':'dossier','includeNote':False,'scope':'dynamic'});assert len(preview['dossier']['entries'])==2
owner.action({'action':'delete','id':a});public=owner.call('/api/share?token='+token)[1];assert not public['dossier']['entries'][0]['available'] and 'quote' not in public['dossier']['entries'][0]
owner.action({'action':'restore','id':a});assert owner.call('/api/share?token='+token)[1]['dossier']['entries'][0]['quote']==entry['quote']
export=owner.action({'action':'exportDossier','id':d['id']});assert export['entries'][0]['quote']==entry['quote'] and 'revision' not in export
request=urllib.request.Request(BASE+'/api/library',data=json.dumps({'action':'export','id':'all'}).encode(),headers={'Origin':BASE,'X-CSRF-Token':owner.csrf,'Content-Type':'application/json'})
archive=zipfile.ZipFile(io.BytesIO(owner.opener.open(request).read()));assert any(x['id']==d['id'] for x in json.loads(archive.read('dossiers.json')))
print('PASS: indexed note updates, boolean search, citation-only access, document grants, private notes, revision conflicts, edit permissions, trash/restore and dossier exports.')
