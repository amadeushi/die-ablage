# Die ABLAGE auf ALL-INKL Business

Die eigenständige Ausgabe verwendet PHP (ab 8.2), MariaDB/MySQL mit InnoDB und PDO-MySQL sowie privaten Dateispeicher. Das Frontend ist fertig gebaut. Auf dem Webspace werden weder Node.js noch npm, Docker, Cloudflare oder ein ChatGPT-Konto benötigt. Design und Zeile „Wissen ist Macht. Ablage auch.“ bleiben erhalten.

## Einrichtung im KAS

1. Eine eigene Domain oder Subdomain für die Bibliothek anlegen, z. B. `ablage.eure-domain.de`. HTTPS/SSL aktivieren und HTTP auf HTTPS umleiten. PHP 8.2 oder neuer wählen; eine aktuell unterstützte Version ist vorzuziehen. `pdo_mysql`, `mbstring` und Sessions müssen verfügbar sein.
2. Eine MariaDB-/MySQL-Datenbank samt Datenbankbenutzer anlegen. Name, Benutzer und Passwort notieren. Nur eine neue, leere Datenbank verwenden.
3. Das Paket entpacken. Die Ordner `public` und `private` gemeinsam nach `/ablage/` hochladen. Im KAS das Zielverzeichnis der Domain auf **`/ablage/public/`** setzen. Den Ordner `private` niemals als Domainziel verwenden. Versteckte Dateien wie `.htaccess` mit hochladen. `extension/`, `frontend/`, `tests/` und die Entwicklungsdateien müssen nicht auf den Webspace.
4. `private/config.example.php` als `private/config.php` kopieren. Die Datenbank-Zugangsdaten eintragen. Beispiel-DSN: `mysql:host=localhost;dbname=DATENBANKNAME;charset=utf8mb4`. `origin` auf eure genaue HTTPS-Adresse ohne abschließenden Schrägstrich setzen, z. B. `https://ablage.eure-domain.de`. Ein eigenes, zufälliges Installationskennwort mit mindestens 32 Zeichen unter `setup_key` eintragen. Keine Beispielwerte verwenden.
5. `private/` für den PHP-Prozess beschreibbar machen. Dort entstehen `archives/`, `sessions/`, `limits/` und `installed.lock`. Bei ALL-INKL über KAS/WebFTP den richtigen Dateibesitzer setzen. Übliche Rechte: Ordner 750, Dateien 640; PHP muss lesen und in den privaten Ordner schreiben können. Keine pauschalen 777-Rechte setzen.
6. `https://ablage.eure-domain.de/install.php` aufrufen. Installationskennwort, Namen, E-Mail-Adresse und ein eigenes Passwort (mindestens 12 Zeichen) eingeben. Die Installation legt Tabellen und das Inhaberkonto an und sperrt sich anschließend. Danach `public/install.php` vom Webspace entfernen.
7. In der Bibliothek anmelden. Unter „Team verwalten“ Personen per E-Mail einladen. Den erzeugten Link selbst weitergeben; er gilt 48 Stunden und wird nach der Passwortvergabe ungültig. Insgesamt höchstens 10 Personen einschließlich Inhaber und offenen Einladungen. Für eine abgelaufene Einladung dieselbe Adresse erneut einladen. Bei zehn belegten Plätzen den abgelaufenen Zugriff zuerst entfernen und neu einladen.
8. Unter „Browser-Erweiterung“ die neue ZIP herunterladen. Chrome/Edge → Erweiterungsverwaltung → Entwicklermodus → „Entpackte Erweiterung laden“. Die HTTPS-Adresse eurer Bibliothek eintragen und die Berechtigung für diese Adresse beim ersten Clip bestätigen. Alte Erweiterung ersetzen, damit die feste Vorschau-Adresse nicht mehr verwendet wird.

Die App muss auf einer eigenen Domain/Subdomain im Wurzelverzeichnis laufen. Ein Unterordner wie `/tools/ablage/` wird nicht unterstützt. Keine öffentliche Registrierung und kein automatischer Mailversand; SMTP ist für diesen Stand nicht erforderlich.

## Clippen und Teilen

Bei einer Quelle mit Anmeldung zuerst auf der Originalwebsite einloggen, dann die Erweiterung öffnen und sichtbaren Inhalt bestätigen. Die Erweiterung erfasst den geöffneten Tab in dessen Sitzung. Sie öffnet eine Vorschau in Die ABLAGE. Prüfen, optional Notiz und Sammlung ergänzen, dann speichern. Falls Die ABLAGE eine Anmeldung verlangt, anmelden und anschließend den Clip prüfen; die Übergabe bleibt maximal zehn Minuten lokal bereit.

Manuell: „Neuer Clip“ → Adresse eintragen → Quelle öffnen und ggf. anmelden → „Der gewünschte Inhalt ist sichtbar“ → „Weiter zur Vorschau“ → Titel und kopierten Text einfügen → speichern.

„Teilen“ erstellt einen geheimen Leselink. Jeder mit diesem Link kann die betreffenden Inhalte ohne Konto lesen. Sammlungslinks zeigen auch später hinzugefügte Clips. „Alle Leselinks deaktivieren“ beendet zukünftigen Zugriff über diese Links. Bereits angefertigte Kopien lassen sich nicht zurückrufen. Entfernte Teammitglieder verlieren ihre Anmeldung sofort; Leselinks werden separat widerrufen.

Seitenkopien speichern Text und Struktur; geschützte Bilder, Medien und dynamische Inhalte können fehlen. Die Erweiterung umgeht keine Anmeldungen oder Zugangssperren.

## Betrieb, Sicherung und Wiederherstellung

- Datenbank **und** `private/archives/` gemeinsam regelmäßig sichern. `private/config.php` sicher aufbewahren. Sitzungen und Anmelde-Limit-Dateien müssen nicht in Sicherungen aufgenommen werden.
- PHP-Grenzen: `post_max_size` mindestens `16M`, `memory_limit` mindestens `128M`. Die App akzeptiert höchstens 12 MB pro Anfrage und 5 MB pro HTML-Seitenkopie. TLS und automatische Sicherheitsupdates des Hosting-PHP aktiv halten.
- Passwort vergessen: per SSH im Projektordner `php private/reset-password.php person@example.de` ausführen und das neue Passwort an der Eingabeaufforderung eingeben. Keine Passwörter als Kommandoargument übergeben. Alternativ kann die betreuende Person mit SSH-Zugang diesen Schritt durchführen. Bisherige Sitzungen werden ungültig.
- „Bibliothek vorübergehend nicht erreichbar“: PHP-Fehlerlog, Datenbankname/Benutzer, `pdo_mysql`, Ordnerrechte und `origin` prüfen. Keine Datenbankpasswörter im Browser veröffentlichen.
- Bei Updates `public/` sowie den Backend-Quellcode aktualisieren; **`private/config.php`, `installed.lock`, archives/, sessions/ und limits/** erhalten. Installation nicht erneut ausführen. Vorher sichern.

Die ursprüngliche lokale Bibliothek bleibt bestehen. Ein separater Export `die-ablage-bibliothek.json` enthält die bisherigen 3 Clips und 2 Sammlungen samt Seitenkopien und vorhandenen Freigaben. Er ist bewusst nicht im allgemeinen Upload-Paket enthalten.

Optional direkt nach Installation und vor dem ersten neuen Clip: Export per SFTP nach `private/` hochladen, per SSH `php private/import-library.php private/die-ablage-bibliothek.json` ausführen und die Exportdatei anschließend vom Webspace entfernen. Der Import funktioniert nur in eine leere Bibliothek und übernimmt keine Konten oder Anmeldesitzungen. Teammitglieder separat einladen. Vorhandene Lesetokens bleiben gültig; Links müssen auf die neue Domain zeigen. Wenn alte Freigaben nicht übernommen werden sollen, diese nach Import unter „Teilen“ deaktivieren. Den Export privat aufbewahren und niemals in `public/` ablegen.

## Entwicklung und geprüfter Stand

Frontend lokal bauen: im entpackten Projektordner `npm install` und `npm run build` (Node.js ab 22.13). Alternativ im ursprünglichen Projekt mit installierten npm-Abhängigkeiten `node node_modules/vite/bin/vite.js build --config all-inkl/vite.config.ts`. Das Paket enthält bereits die gebauten Dateien. Node.js wird ausschließlich für spätere Frontend-Änderungen auf dem Entwicklungsrechner benötigt.

Lokaler PHP-Server: `php -S 127.0.0.1:5180 -t all-inkl/public all-inkl/router.php`. Nur für die Entwicklung; auf ALL-INKL übernimmt Apache mit `.htaccess` die Routen.

Geprüft mit PHP 8.5 und MariaDB 12.3: Installation, Anmeldung, CSRF- und Herkunftsprüfung, Einladung und Wiederverwendungsschutz, Rollen, Zehn-Personen-Limit, Sperrung alter Sitzungen nach Entfernung und erneuter Einladung, private Seitenkopien, öffentliche Clip-/Sammlungslinks und Widerruf. PHP-Syntax und Produktionsbuild geprüft. Der lokale Test nutzt eine getrennte Testdatenbank, keine echten Nutzerdaten.

Noch ausstehend: Prüfung auf dem konkreten ALL-INKL-Account (PHP-/Apache-Konfiguration und Rechte) sowie vollständiger Chrome-/Edge-Test mit installierter Erweiterung. `tests/integration.py` ist ausschließlich für eine frische lokale Testdatenbank bestimmt; nicht gegen eine produktive Bibliothek ausführen.

## Bereichsauswahl in Chrome (Version 1.2.0)

Quelle öffnen und bei Bedarf anmelden. Erweiterung öffnen, „Bereiche auf der Seite auswählen“ wählen, sichtbaren Inhalt bestätigen und „Bereiche auswählen“ anklicken. Absätze, Bilder und Tabellen auf der Seite anklicken; erneuter Klick entfernt einen Bereich. Die Auswahl bleibt beim Scrollen sichtbar. „Vorheriger Bereich“, „Nächster Bereich“ und „Bereich übernehmen“ erlauben Tastaturbedienung. „Auswahl prüfen“ zeigt die erfassten Bereiche; „In Die ABLAGE prüfen“ öffnet die bestehende Bibliotheksvorschau. Erst „Clip speichern“ speichert den Clip. Escape oder „Abbrechen“ beendet die Auswahl.

Mehrere Bereiche werden in Seitenreihenfolge gespeichert, überlappende Bereiche nicht doppelt. Eingabefelder, Skripte und versteckte Inhalte werden ausgelassen. Bilder werden nach Möglichkeit lokal eingebettet; technisch nicht kopierbare Bilder erscheinen als Hinweis. Eingebettete Frames, Shadow-DOM-Inhalte, Videos und pixelgenaue Bildschirm-Ausschnitte sind nicht Teil dieser ersten Version. Maximale Auswahl: 50 Bereiche, 1 MB Text und 5 MB Seitenkopie.

Update: ZIP neu herunterladen und in den bisherigen Erweiterungsordner entpacken. In Chrome unter chrome://extensions bei Die ABLAGE auf „Neu laden“ klicken; die Quellseite ebenfalls neu laden. Alternativ einen neuen Ordner entpacken und die bisherige Erweiterung ersetzen.

Prüfung: `node tests/extension-background.cjs` testet den Übergabepfad mit isolierten Chrome-API-Mocks. Browserprüfung auf synthetischer Quelle: Text/Bild/Tabelle, Seitenreihenfolge, Überlappung, Tastatur, Escape/Fokusrückgabe, Vorschau, ausgeschlossene Inhalte. Die Installation und der vollständige Übergabepfad mit einer echten Chrome-Erweiterung bleiben separat zu prüfen.

## Gespeicherte Clips bearbeiten

Clip öffnen und „Bearbeiten“ neben „Teilen“ wählen. Titel, Quellenadresse, Lesetext, Notiz und Sammlung ändern; „Änderungen speichern“ führt zurück zum Clip. Die Originalkopie mit Bildern bleibt unverändert. Nur Ersteller und Inhaber können bearbeiten, auch über die API und den Notiz-Endpunkt. Bestehende Leselinks bleiben gültig und zeigen die Änderungen; Sammlungswechsel ändern den Zugriff über Sammlungslinks. Zwischenzeitliche Änderungen werden mit HTTP 409 zurückgewiesen. Ungespeicherte Änderungen beim Schließen werden im Dialog abgefragt; beim Verlassen der Website erscheint die Browserwarnung. Bei Updates gehört private/clip-edit.php in den privaten App-Ordner. Keine Datenbankmigration nötig.

## Digitale PDFs

„Neuer Clip“ → „PDF-Dokument“ → Datei auswählen → Angaben prüfen → „Clip speichern“. Die Verarbeitung findet zunächst im Browser statt. Erst beim Speichern wird das Original hochgeladen. Volltext aller Seiten und vorhandene Dokumentmetadaten werden durchsuchbar gespeichert. Die PDF lässt sich seitenweise ansehen, herunterladen, einer Sammlung zuordnen und über bestehende Leselinks teilen. Clip-Titel, Lesetext und Notiz sind bearbeitbar; Originaldatei und ursprüngliche Dokumentmetadaten bleiben erhalten.

Grenzen: 4 MB, 500 Seiten, 1 MB extrahierter Text. Scans ohne Textschicht und passwortgeschützte PDFs werden noch nicht unterstützt. PDFs werden zunächst per Upload hinzugefügt; der Chrome-Clipper erfasst PDF-Dateien noch nicht direkt. Originaldateien liegen außerhalb des Webverzeichnisses und sind nur nach Anmeldung oder mit gültiger, passender Freigabe abrufbar. PDF.js wird nur bei Bedarf geladen; die Apache-Lizenz liegt unter public/pdfjs-LICENSE.txt.

## Tags und Recherche-Suche

Beim Speichern oder Bearbeiten eines Clips lassen sich bis zu zwölf Tags mit Kommas getrennt eingeben. Vorhandene Tags werden vorgeschlagen. Sammlungen bleiben unabhängig davon bestehen. Die Bibliothek bietet aufklappbare Filter für Tag, Quelldomain, Ersteller und gespeicherten Zeitraum; sie sind mit Suchbegriff und Clip-Art kombinierbar. Suchergebnisse zeigen die passende Textstelle mit hervorgehobenem Begriff, bei PDF-Lesetext zusätzlich die ursprüngliche Seitenangabe.

Bestehende Installationen: vor Austausch des App-Codes einmal `php private/migrate-tags.php` ausführen. Bei privatem Verzeichnis außerhalb des Webverzeichnisses kann dessen absoluter Pfad als erstes Argument angegeben werden. Die Migration ergänzt ausschließlich `clip_tags`; vorhandene Clips und Freigaben bleiben erhalten. Neuinstallationen erhalten die Tabelle über schema.sql. Tags eines freigegebenen Clips sind Teil seiner Metadaten; die Vorschlagsliste und Bibliothekssuche sind nur nach Anmeldung zugänglich. Markierungen und Kommentare sind weiterhin ein späterer Ausbau.
