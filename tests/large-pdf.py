"""Disposable local HTTP regression for large direct PDF uploads; never run against production."""
import urllib.request,urllib.error,http.cookiejar,json,uuid,hashlib
from pathlib import Path
BASE='http://127.0.0.1:5922'
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path,data=None,headers=None):
 req=urllib.request.Request(BASE+path,data=data,headers={'Origin':BASE,**(headers or {})})
 try:r=client.open(req)
 except urllib.error.HTTPError as e:r=e
 raw=r.read();return r.status,json.loads(raw) if 'json' in r.headers.get('Content-Type','') else raw
csrf=call('/api/auth')[1]['csrf']
def action(value):return call('/api/library',json.dumps(value).encode(),{'Content-Type':'application/json','X-CSRF-Token':csrf})
assert call('/api/auth',json.dumps({'action':'login','email':'owner@example.test','password':'local-clipping-test-password'}).encode(),{'Content-Type':'application/json','X-CSRF-Token':csrf})[0]==200
csrf=call('/api/auth')[1]['csrf']
def document(target):
 objects=[b'<< /Type /Catalog /Pages 2 0 R >>',b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 600 800] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',b'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>']
 text=('BT /F1 18 Tf 40 740 Td (Digital newspaper upload test '+uuid.uuid4().hex+') Tj ET').encode();objects.append(b'<< /Length '+str(len(text)).encode()+b' >>\nstream\n'+text+b'\nendstream')
 padding=b'x'*(target-2048);objects.append(b'<< /Length '+str(len(padding)).encode()+b' >>\nstream\n'+padding+b'\nendstream');raw=b'%PDF-1.4\n';offsets=[]
 for i,obj in enumerate(objects,1):offsets.append(len(raw));raw+=str(i).encode()+b' 0 obj\n'+obj+b'\nendobj\n'
 xref=len(raw);raw+=b'xref\n0 7\n0000000000 65535 f \n'+b''.join(('%010d 00000 n \n'%n).encode() for n in offsets)+b'trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n'+str(xref).encode()+b'\n%%EOF\n';return raw
raw=document(16*1024*1024);fixture=Path(__file__).resolve().parents[2]/'work/pdf-large-test/newspaper-16mb.pdf';fixture.write_bytes(raw)
marker='Zeitungsarchiv-'+str(uuid.uuid4());text='Seite 1\n'+marker+' Müller Datenschutz\n'+('Zeitungstext. '*95000)
value={'action':'pdf','title':marker,'content':text,'metadata':{'filename':'newspaper-16mb.pdf','pages':1},'tags':['Zeitung']}
def upload(value,file=raw,valid=True,with_file=True):
 boundary='ablage-'+uuid.uuid4().hex
 body=b'--'+boundary.encode()+b'\r\nContent-Disposition: form-data; name="payload"\r\n\r\n'+json.dumps(value,ensure_ascii=False).encode()+b'\r\n'
 if with_file:body+=b'--'+boundary.encode()+b'\r\nContent-Disposition: form-data; name="file"; filename="newspaper.pdf"\r\nContent-Type: application/pdf\r\n\r\n'+file+b'\r\n'
 body+=b'--'+boundary.encode()+b'--\r\n'
 return call('/api/library',body,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':csrf if valid else 'wrong'})
assert upload(value,valid=False)[0]==403
assert upload({**value,'action':'clip'})[0]==400
assert upload(value,with_file=False)[0]==400
assert upload(value,file=b'not a PDF')[0]==400
status,result=upload(value);assert status==200,(status,result);id=result['id']
assert upload(value)[0]==409
saved=call('/api/shared?id='+id)[1];assert marker in saved['content'] and saved['pdf']['bytes']==len(raw)
assert call('/api/shared?id='+id+'&pdf=1')[1]==raw
q=urllib.parse.quote(marker+' Muelller ODER '+marker+' Datenschutz');assert call('/api/library?list=1&q='+q)[1]['totalResults']==1
edit={**saved,'action':'edit','revision':saved['edit_revision'],'content':text+'\nBearbeitete Zeitung'};assert action(edit)[0]==200
saved=call('/api/shared?id='+id)[1];assert action({**saved,'action':'edit','revision':saved['edit_revision'],'content':'a'*(5*1024*1024+1)})[0]==400
assert upload({**value,'content':'a'*(5*1024*1024+1),'allowDuplicate':True})[0]==400
assert upload({**value,'allowDuplicate':True},file=document(26*1024*1024))[0]==413
print('PASS: direct 16 MiB PDF upload, >1 MB searchable text, exact original retrieval, editing, duplicate gate, CSRF, invalid/missing file and 25 MiB/5 MiB limits.')
print('Browser fixture:',fixture)
