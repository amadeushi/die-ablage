"""Local-only API regression for selected dossier exports and independent article PDF grants."""
import urllib.request,urllib.error,http.cookiejar,json,uuid,base64,io,zipfile
from pathlib import Path
BASE='http://127.0.0.1:5922'
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));csrf=''
def call(path,value=None):
 req=urllib.request.Request(BASE+path,data=json.dumps(value).encode() if value is not None else None,headers={'Origin':BASE,'X-CSRF-Token':csrf,'Content-Type':'application/json'})
 try:r=opener.open(req)
 except urllib.error.HTTPError as e:r=e
 b=r.read();return r.status,json.loads(b) if 'json' in r.headers.get('Content-Type','') else b
csrf=call('/api/auth')[1]['csrf'];assert call('/api/auth',{'action':'login','email':'owner@example.test','password':'local-clipping-test-password'})[0]==200;csrf=call('/api/auth')[1]['csrf']
def action(v):
 status,result=call('/api/library',v);assert status==200,(status,result);return result
unique=uuid.uuid4().hex;source=action({'action':'pdf','allowDuplicate':True,'title':'SOURCE '+unique,'content':'Seite 1\nPRIVATE OUTSIDE THE ARTICLE','note':'PRIVATE SOURCE NOTE','file':base64.b64encode((Path(__file__).parent/'fixtures/two-columns.pdf').read_bytes()).decode(),'metadata':{'filename':'source.pdf','pages':1}})['id'];detail=call('/api/shared?id='+source)[1]
attachment=(Path(__file__).resolve().parents[2]/'work/pdf-export-tests/raster-excerpt.pdf').read_bytes();article=action({'action':'newspaperArticle','parentId':source,'revision':detail['edit_revision'],'title':'ARTICLE '+unique,'content':'Visible article text','note':'PRIVATE ARTICLE NOTE','pageFrom':1,'pageUntil':1,'articlePdf':base64.b64encode(attachment).decode()})['id'];token=action({'action':'share','id':article,'kind':'clip'})['token']
shared=call('/api/share?token='+token)[1];assert shared['clips'][0]['newspaper']['hasPdf'];assert call('/api/shared?token='+token+'&id='+article+'&pdf=1')[1]==attachment;assert call('/api/shared?token='+token+'&id='+source+'&pdf=1')[0]==404;assert call('/api/shared?token='+token+'&id='+article+'&archive=1')[0]==404;assert 'PRIVATE SOURCE NOTE' not in json.dumps(shared)
entries=[{'id':str(uuid.uuid4()),'clipId':article,'kind':'document','quote':'','comment':'Included comment','page':0},{'id':str(uuid.uuid4()),'clipId':source,'kind':'quote','quote':'EXCLUDED QUOTE SECRET','comment':'EXCLUDED COMMENT','page':1}]
d=action({'action':'saveDossier','title':'Dossier '+unique,'intro':'Introduction','entries':entries});status,z=call('/api/library',{'action':'exportDossier','id':d['id'],'format':'zip','entryIds':[entries[0]['id']]});assert status==200;archive=zipfile.ZipFile(io.BytesIO(z));assert set(archive.namelist())=={'dossier.html','dossier.md','dossier.json','README.txt'}
for f in ['dossier.html','dossier.md','dossier.json']:
 text=archive.read(f).decode();assert 'Visible article text' in text;assert 'EXCLUDED QUOTE SECRET' not in text;assert 'PRIVATE ARTICLE NOTE' not in text;assert 'PRIVATE OUTSIDE THE ARTICLE' not in text
status,with_files=call('/api/library',{'action':'exportDossier','id':d['id'],'format':'zip','entryIds':[entries[0]['id']],'includeArticlePdfs':True});assert status==200;zfiles=zipfile.ZipFile(io.BytesIO(with_files));assert zfiles.read('artikel/'+article+'.pdf')==attachment
subset=action({'action':'exportDossier','id':d['id'],'entryIds':[entries[0]['id']]});assert len(subset['entries'])==1
assert call('/api/library',{'action':'exportDossier','id':d['id'],'format':'zip','entryIds':['bad']})[0]==400
assert call('/api/library',{'action':'exportDossier','id':d['id'],'format':'zip','entryIds':[]})[0]==400
print('PASS: selected dossier ZIP/JSON, excluded entry/note privacy, independent raster PDF delivery and denial of parent original.')
print('LOCAL_ARTICLE_LINK='+BASE+'/s/'+token)
