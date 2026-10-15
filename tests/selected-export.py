"""Local export regression, extends the independently scoped article fixture."""
exec(open(__file__.replace('selected-export.py','scoped-export.py')).read())
status,data=call('/api/library',{'action':'export','ids':[article]});assert status==200
z=zipfile.ZipFile(io.BytesIO(data));manifest=json.loads(z.read('sammlung.json'));assert [c['id'] for c in manifest['clips']]==[article];assert manifest['scope']=='selected' and manifest['includeNotes'] is False
assert 'pdf/'+article+'.pdf' in z.namelist();assert 'pdf/'+source+'.pdf' not in z.namelist();assert 'dossiers.json' not in z.namelist();assert z.read('pdf/'+article+'.pdf')==attachment
for f in ['sammlung.json','clips/'+article+'.md']:
 text=z.read(f).decode();assert 'PRIVATE ARTICLE NOTE' not in text and 'PRIVATE SOURCE NOTE' not in text and 'PRIVATE OUTSIDE THE ARTICLE' not in text
status,data=call('/api/library',{'action':'export','ids':[article,source,article]});assert status==200;both=zipfile.ZipFile(io.BytesIO(data));assert len(json.loads(both.read('sammlung.json'))['clips'])==2;assert 'pdf/'+source+'.pdf' in both.namelist()
assert call('/api/library',{'action':'export','ids':[]})[0]==400
assert call('/api/library',{'action':'export','ids':'all'})[0]==400
assert call('/api/library',{'action':'export','ids':[article,'missing']})[0]==409
assert call('/api/library',{'action':'export','ids':['x']*101})[0]==400
print('PASS: selected clips only, own PDF attachment, no linked parent/dossiers/notes, explicit PDF selection, deduplication and invalid selections.')
