"""Disposable integration checks: source history, review, permissions, conflicts and shares."""
import urllib.request,urllib.error,http.cookiejar,json,uuid
BASE='http://127.0.0.1:5922'
class Client:
 def __init__(self,email=None):
  self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=self.call('/api/auth')[1]['csrf']
  if email:self.call('/api/auth',{'action':'login','email':email,'password':'local-clipping-test-password'});self.csrf=self.call('/api/auth')[1]['csrf']
 def call(self,path,v=None):
  req=urllib.request.Request(BASE+path,data=json.dumps(v).encode() if v is not None else None,headers={'Origin':BASE,'Content-Type':'application/json','X-CSRF-Token':getattr(self,'csrf','')})
  try:r=self.opener.open(req)
  except urllib.error.HTTPError as e:r=e
  return r.status,json.loads(r.read())
 def ok(self,path,v=None):
  status,j=self.call(path,v);assert status==200,(status,j);return j
o=Client('owner@example.test');m=Client('member@example.test');guest=Client();name='Versionstest '+uuid.uuid4().hex;url='https://example.test/'+uuid.uuid4().hex
id=o.ok('/api/library',{'action':'clip','url':url,'title':name,'type':'article','content':'Der Rat plant drei neue Bäume.','contentFormat':'text','note':'Private Einordnung','tags':['Recherche'],'archive':'<article>Erstes Original</article>'})['id']
context=o.ok('/api/library?articleVersions=1&id='+id);old=context['versions'][0]['id'];assert len(context['versions'])==1 and context['baseline']['origin']=='clip'
token=o.ok('/api/library',{'action':'share','id':id,'kind':'clip','includeNote':False,'duration':'7'})['token']
payload={'action':'articleApply','id':id,'revision':context['current']['revision'],'url':url,'title':name,'content':'Der Rat plant fünf neue Bäume und eine Bank.','contentFormat':'text','origin':'recapture','archive':'<article>Neues Original</article>'}
assert m.call('/api/library',payload)[0]==403
assert o.call('/api/library',{**payload,'url':'https://elsewhere.test/'})[0]==400
o.ok('/api/library',payload);assert o.call('/api/library',payload)[0]==409
shared=guest.ok('/api/shared?token='+token+'&id='+id);assert shared['content']==payload['content'] and not shared.get('note') and 'articleVersions' not in shared
assert guest.call('/api/library?articleVersions=1&id='+id)[0]==401
before=o.ok('/api/shared?id='+id);assert before['note']=='Private Einordnung' and before['tags']==['Recherche']
o.ok('/api/library',{'action':'edit','id':id,'revision':before['edit_revision'],'title':name,'url':url,'content':'Eigene redaktionelle Bearbeitung.','contentFormat':'text','note':before['note'],'tags':before['tags'],'collectionId':''})
c=o.ok('/api/library?articleVersions=1&id='+id);assert c['current']['content']=='Eigene redaktionelle Bearbeitung.' and c['baseline']['content']==payload['content'];assert c['versions'][0]['origin']=='manual'
original=o.ok('/api/library?articleVersion=1&id='+id+'&versionId='+old);assert original['content']=='Der Rat plant drei neue Bäume.'
o.ok('/api/library',{**payload,'revision':c['current']['revision'],'content':'Endgültige bestätigte Meldung.','archive':''})
c=o.ok('/api/library?articleVersions=1&id='+id);assert c['baseline']['content']=='Endgültige bestätigte Meldung.' and any(v['origin']=='manual' for v in c['versions'])
assert o.ok('/api/library?list=1&q=Endg%C3%BCltige')['clips'][0]['id']==id
# SSRF rejected before connecting, with no candidate or version written.
local=o.ok('/api/library',{'action':'clip','url':'http://127.0.0.1/private-'+uuid.uuid4().hex,'title':'Private target test','type':'article','content':'Protected content','note':'','tags':[]})['id']
assert o.call('/api/library',{'action':'articleCheck','id':local})[0]==400
assert len(o.ok('/api/library?articleVersions=1&id='+local)['versions'])==1
print('PASS: reviewed versions, manual/source separation, preserved notes/tags, old snapshots, latest shared text, access control, stale revision, source identity, search re-index and private-target rejection.')
