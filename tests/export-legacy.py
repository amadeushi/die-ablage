"""Lokalen D1-/R2-Bestand exportieren. Nur lokal; nie als Web-Endpunkt bereitstellen."""
from pathlib import Path
import sqlite3,json,sys
root=Path(sys.argv[1] if len(sys.argv)>1 else '.');destination=Path(sys.argv[2] if len(sys.argv)>2 else 'outputs/die-ablage-bibliothek.json')
source=None
for path in (root/'.wrangler/state/v3/d1').rglob('*.sqlite'):
 db=sqlite3.connect(path);db.row_factory=sqlite3.Row
 if db.execute("select name from sqlite_master where name='clips'").fetchone():source=db;break
if source is None:raise SystemExit('Keine lokale D1-Bibliothek gefunden.')
objects={}
for path in (root/'.wrangler/state/v3/r2').rglob('*.sqlite'):
 db=sqlite3.connect(path)
 if db.execute("select name from sqlite_master where name='_mf_objects'").fetchone():
  for key,blob in db.execute('select key,blob_id from _mf_objects'):objects[key]=blob
clips=[dict(c) for c in source.execute('select * from clips')]
for clip in clips:
 key=clip.pop('archive_key',None);clip['archive']=''
 if key:
  blob=objects.get(key)
  matches=list((root/'.wrangler/state/v3/r2').rglob(str(blob))) if blob else []
  if len(matches)!=1:raise SystemExit('Eine gespeicherte Seitenkopie konnte nicht eindeutig gefunden werden; Export abgebrochen.')
  clip['archive']=matches[0].read_text()
result={'format':'die-ablage-export-v1','clips':clips,'collections':[dict(c) for c in source.execute('select * from collections')],'shares':[dict(c) for c in source.execute('select * from shares')]}
destination.parent.mkdir(parents=True,exist_ok=True);destination.write_text(json.dumps(result,ensure_ascii=False,indent=2));destination.chmod(0o600)
print(f'Export: {len(clips)} Clips, {len(result["collections"])} Sammlungen, {len(result["shares"])} Freigaben. Privat aufbewahren; nicht nach public/ hochladen.')
