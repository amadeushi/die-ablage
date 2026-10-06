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

Die ursprüngliche lokale Bibliothek bleibt bestehen. Ein lokaler Export der bisherigen Ausgabe kann bei Bedarf separat erstellt werden. Private Bibliotheksdaten gehören nicht in dieses Repository.

Optional direkt nach Installation und vor dem ersten neuen Clip: Export per SFTP nach `private/` hochladen, per SSH `php private/import-library.php private/die-ablage-bibliothek.json` ausführen und die Exportdatei anschließend vom Webspace entfernen. Der Import funktioniert nur in eine leere Bibliothek und übernimmt keine Konten oder Anmeldesitzungen. Teammitglieder separat einladen. Vorhandene Lesetokens bleiben gültig; Links müssen auf die neue Domain zeigen. Wenn alte Freigaben nicht übernommen werden sollen, diese nach Import unter „Teilen“ deaktivieren. Den Export privat aufbewahren und niemals in `public/` ablegen.

## Entwicklung und geprüfter Stand

Frontend lokal bauen: im entpackten Projektordner `npm install` und `npm run build` (Node.js ab 22.13). Alternativ im ursprünglichen Projekt mit installierten npm-Abhängigkeiten `node node_modules/vite/bin/vite.js build --config all-inkl/vite.config.ts`. Das Paket enthält bereits die gebauten Dateien. Node.js wird ausschließlich für spätere Frontend-Änderungen auf dem Entwicklungsrechner benötigt.

Lokaler PHP-Server: `php -S 127.0.0.1:5180 -t public router.php`. Nur für die Entwicklung; auf ALL-INKL übernimmt Apache mit `.htaccess` die Routen.

Geprüft mit PHP 8.5 und MariaDB 12.3: Installation, Anmeldung, CSRF- und Herkunftsprüfung, Einladung und Wiederverwendungsschutz, Rollen, Zehn-Personen-Limit, Sperrung alter Sitzungen nach Entfernung und erneuter Einladung, private Seitenkopien, öffentliche Clip-/Sammlungslinks und Widerruf. PHP-Syntax und Produktionsbuild geprüft. Der lokale Test nutzt eine getrennte Testdatenbank, keine echten Nutzerdaten.

Auf ALL-INKL mit PHP 8.3 zusätzlich geprüft: Datenbankverbindung, Installation des Inhaberkontos, HTTPS, API-Routing, privater Zugriff ohne Anmeldung (401), ungültige Leselinks (404) und Erweiterungsdownload. Ein vollständiger Chrome-/Edge-Test der Erfassung bleibt ausstehend. `tests/integration.py` ist ausschließlich für eine frische lokale Testdatenbank bestimmt; nicht gegen eine produktive Bibliothek ausführen.

## Bestehende Installation

Die laufende Installation unter `https://ablage.partei-hildesheim.de` verwendet das vorhandene Domainziel. Dort liegen die Dateien aus `public/` direkt im Domainverzeichnis; der private Ordner liegt als `.die-ablage-private/` daneben außerhalb des Webverzeichnisses. In den hochgeladenen PHP-Dateien wurden die Verweise `dirname(__DIR__).'/private/…'` entsprechend angepasst. Das Repository enthält die portable Vorlage mit `public/` und `private/`.

Bei Updates der bestehenden Installation diese Pfadanpassung erhalten. Konfiguration, `installed.lock`, Archive und Sitzungen nicht überschreiben. Der Installer wurde auf dem laufenden Webspace bereits entfernt und wird dort nicht erneut benötigt.
