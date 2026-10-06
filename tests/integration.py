"""Destruktiver Integrationstest nur gegen eine frische lokale Test-Datenbank.
Voraussetzung: lokale Konfiguration und laufender PHP-Server 127.0.0.1:5180.
Nie gegen die produktive Bibliothek ausführen.
"""
import urllib.request,urllib.error,http.cookiejar,json,re
BASE='http://127.0.0.1:5180'
class Client:
 def __init__(self): self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def req(self,path,data=None,origin=BASE,csrf=None,form=False):
  headers={}
  if data is not None:
   headers={'Origin':origin,'X-CSRF-Token':self.csrf if csrf is None else csrf,'Content-Type':'application/x-www-form-urlencoded' if form else 'application/json'}
   data=urllib.parse.urlencode(data).encode() if form else json.dumps(data,ensure_ascii=False).encode()
  r=urllib.request.Request(BASE+path,data=data,headers=headers)
  try: resp=self.opener.open(r)
  except urllib.error.HTTPError as e: resp=e
  body=resp.read().decode();return resp.status,json.loads(body) if 'application/json' in resp.headers.get('Content-Type','') else body,resp.headers
 def init(self): status,data,_=self.req('/api/auth');assert status==200,(status,data);self.csrf=data['csrf'];return data
 def post(self,data,expected=200):
  status,value,_=self.req('/api/library',data);assert status==expected,(status,value,data);return value
anon=Client();assert anon.req('/api/library')[0]==503
owner=Client();page=owner.req('/install.php')[1];csrf=re.search(r'name="csrf" value="([^"]+)"',page).group(1)
status,page,_=owner.req('/install.php',{'csrf':csrf,'setup_key':'local-test-installer-key-32-characters-minimum','email':'owner@example.test','name':'Inhaber','password':'local-integration-password'},form=True)
assert status==200 and 'wurde eingerichtet' in page,(status,page)
assert owner.req('/install.php')[0]==404
assert anon.req('/api/library')[0]==401
owner.init();assert owner.req('/api/auth',{'action':'login','email':'owner@example.test','password':'wrong'})[0]==401
assert owner.req('/api/auth',{'action':'login','email':'owner@example.test','password':'local-integration-password'})[0]==200
owner.init()
assert owner.req('/api/library',{'action':'collection','name':'blocked'},origin='https://evil.test')[0]==403
assert owner.req('/api/library',{'action':'collection','name':'blocked'},csrf='invalid')[0]==403
col=owner.post({'action':'collection','name':'Recherche ä 🇩🇪'})['id']
clip=owner.post({'action':'clip','url':'https://example.com/recherche','title':'Recherche für Die ABLAGE','type':'page','content':'Gespeicherter Inhalt','archive':'<h1>Quelle</h1><script>alert(1)</script>','note':'Notiz','collectionId':col})['id']
assert anon.req('/api/shared?id='+clip+'&archive=1')[0]==401
status,html,headers=owner.req('/api/shared?id='+clip+'&archive=1');assert status==200 and "sandbox; default-src 'none'" in headers['Content-Security-Policy']
owner.post({'action':'clip','url':'javascript:alert(1)','title':'Bad','type':'link'},400)
owner.post({'action':'note','id':clip,'note':'Neue Notiz 😀'})
token=owner.post({'action':'share','id':clip,'kind':'clip'})['token']
status,shared,_=anon.req('/api/share?token='+token);assert status==200 and shared['clips'][0]['note']=='Neue Notiz 😀'
assert anon.req('/api/shared?token='+token+'&id='+clip+'&archive=1')[0]==200
owner.post({'action':'revoke','id':clip,'kind':'clip'});assert anon.req('/api/share?token='+token)[0]==404
ctoken=owner.post({'action':'share','id':col,'kind':'collection'})['token'];assert len(anon.req('/api/share?token='+ctoken)[1]['clips'])==1
invite=owner.post({'action':'member','email':'member@example.test'})['token']
member=Client();member.init();assert member.req('/api/auth',{'action':'accept','token':invite,'name':'Teammitglied','password':'member-integration-password'})[0]==200
member.init();assert member.req('/api/auth',{'action':'accept','token':invite,'name':'Wieder','password':'member-integration-password'})[0]==404
member.post({'action':'member','email':'other@example.test'},403)
member.post({'action':'removeMember','email':'owner@example.test'},403)
for i in range(8):owner.post({'action':'member','email':f'team{i}@example.test'})
owner.post({'action':'member','email':'eleven@example.test'},400)
# Wiederholte Einladung für einen reservierten Platz darf keinen zusätzlichen Platz belegen.
owner.post({'action':'member','email':'team0@example.test'})
owner.post({'action':'removeMember','email':'member@example.test'});assert member.req('/api/library')[0]==401
invite=owner.post({'action':'member','email':'member@example.test'})['token']
new_member=Client();new_member.init();assert new_member.req('/api/auth',{'action':'accept','token':invite,'name':'Neuer Zugriff','password':'new-member-password'})[0]==200
assert member.req('/api/library')[0]==401 # Alte Sitzung darf beim erneuten Einladen nicht wiederaufleben.
owner.post({'action':'removeMember','email':'owner@example.test'});assert owner.req('/api/library')[0]==200
owner.post({'action':'delete','id':clip});assert anon.req('/api/share?token='+ctoken)[1]['clips']==[]
assert anon.req('/api/shared?token='+ctoken+'&id='+clip+'&archive=1')[0]==404
# Archive-Pfad und Konfiguration niemals direkt über das Web erreichbar.
assert '/private/config.php' not in anon.req('/private/config.php')[1]
print('PASS: MariaDB installation, authentication, CSRF/origin, invitation replay, role checks, 10-person limit, removed sessions, private archives, public collection/clip shares and revocation.')
