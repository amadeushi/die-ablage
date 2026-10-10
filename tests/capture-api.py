"""Capture replay/ownership test against a disposable local library on port 5921."""
import urllib.request,urllib.error,http.cookiejar,json,uuid
BASE='http://127.0.0.1:5921'
class Client:
 def __init__(self,email):
  self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=self.request('/api/auth')[1]['csrf']
  assert self.request('/api/auth',{'action':'login','email':email,'password':'local-clipping-test-password'})[0]==200
  self.csrf=self.request('/api/auth')[1]['csrf']
 def request(self,path,data=None):
  headers={'Origin':BASE,'X-CSRF-Token':getattr(self,'csrf',''),'Content-Type':'application/json'}
  req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers=headers)
  try:r=self.opener.open(req)
  except urllib.error.HTTPError as e:r=e
  return r.status,json.loads(r.read())
owner=Client('owner@example.test');member=Client('member@example.test');capture=str(uuid.uuid4())
clip={'action':'clip','captureId':capture,'url':'https://example.test/'+capture,'title':'Disposable capture lifecycle test','type':'article','content':'Test body with source links.','contentFormat':'markdown','note':'','tags':['test']}
first=owner.request('/api/library',clip);assert first==(200,{'id':capture}),first
again=owner.request('/api/library',clip);assert again==first,again
other=member.request('/api/library',clip);assert other[0]==409,other
bad=owner.request('/api/library',{**clip,'captureId':'invalid'});assert bad[0]==400,bad
print('PASS: stable capture ID, replay without duplicate, ownership guard and invalid ID rejection.')
