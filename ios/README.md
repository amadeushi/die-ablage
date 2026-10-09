# Die ABLAGE für iPhone und iPad — Prototyp 0.1

Dieses Projekt enthält eine SwiftUI-App mit eingebetteter bestehender Ablage und eine Safari-Web-Erweiterung. Die Chrome-Erweiterung und das produktive Backend werden nicht verändert.

## Enthalten

- App-Zugang zur bestehenden mobilen Bibliothek, Suche, Leseansicht, Bearbeitung und Freigaben über das vorhandene Teamkonto.
- Safari: Absätze, Überschriften, Bilder und Tabellen durch Antippen auswählen; Auswahl entfernen durch erneutes Antippen; Vorschau vor Übergabe.
- Alternativ Artikel, vorhandene Textauswahl, ganze Seite oder Link erfassen.
- Auf loginpflichtigen Quellen zuerst in Safari anmelden. Die Erweiterung liest den danach zugänglichen Seiteninhalt; sie überträgt keine Anmeldedaten.
- Übergabe über eine gemeinsame App Group. Inhalte werden lokal geschützt gespeichert und nach bestätigter Übernahme in den Entwurf gelöscht. Nicht übernommene Entwürfe verfallen nach zehn Minuten; die Bereinigung erfolgt beim nächsten Zugriff, nicht per Hintergrunddienst. Höchstens 20 wartende Auswahlen.
- Speicherung erst nach der Prüfung im bestehenden Ablage-Dialog. Safari und App haben separate Sitzungen.

## In Xcode installieren

1. Vollständiges aktuelles Xcode installieren, einmal öffnen und die Apple-Lizenz sowie erforderliche iOS-Komponenten einrichten.
2. `Die ABLAGE.xcodeproj` öffnen. Unterstützt iOS/iPadOS 17 und neuer.
3. Für beide Targets unter **Signing & Capabilities** dasselbe Apple-Developer-Team wählen. Beide Bundle-IDs müssen dem Team gehören: `de.partei.hildesheim.ablage` und `de.partei.hildesheim.ablage.safari`. Falls bereits vergeben, beide ändern.
4. Für beide Targets die Capability **App Groups** aktivieren und `group.de.partei.hildesheim.ablage` registrieren/auswählen. Bei anderem Namen den Build-Wert `APP_GROUP_IDENTIFIER` in beiden Targets ändern. Die eingebettete Erweiterung benötigt ein zum App-Target passendes Provisioning-Profil.
5. Scheme **Die ABLAGE**, angeschlossenes iPhone als Ziel, dann Run. Für diese gemeinsame App Group ein Developer-Team mit passender Berechtigung verwenden; der kostenlose persönliche Zugang reicht dafür möglicherweise nicht.
6. Auf dem iPhone in den Safari-Einstellungen unter **Erweiterungen** Die ABLAGE aktivieren. Den Zugriff auf die jeweils zu clippende Website erlauben. Die genaue Position der Safari-Einstellungen hängt von der iOS-Version ab.
7. In Safari die gewünschte Seite öffnen, gegebenenfalls anmelden, dann über das Seitenmenü die Erweiterung öffnen. **Bereiche auswählen → Clipping starten**. Nach der Auswahl **Auswahl prüfen → In Die ABLAGE prüfen → In der App öffnen** antippen.
8. In der App anmelden, den übernommenen Entwurf prüfen und speichern.

## Unbedingt auf einem echten iPhone prüfen

- Erstes Öffnen nach App-Installation und Aktivierung der Erweiterung; Safari-Berechtigung nur für die aktuelle Quelle.
- Loginpflichtige Quelle: Login vor Clipping, keine Passwörter im Entwurf.
- Einen Absatz, mehrere nicht zusammenhängende Absätze, Überschrift, Tabelle, zugängliches Bild auswählen; erneut antippen entfernt Auswahl; Scrollen bleibt möglich.
- Vorschau, App-Wechsel, erstmalige App-Anmeldung, genau ein Entwurf, Speichern und erneutes Öffnen.
- Abbrechen, leere Auswahl, verweigerte Berechtigung, Offline-Zustand, abgelaufene Übergabe und mehrfaches Clipping.
- Suche, Markdown-Bearbeitung, Textmarker, Freigabe und Linkkopieren in WKWebView.
- PDF-Dateiauswahl, PDF-Anzeige, Downloads und native Teilen-Menüs separat prüfen. Dafür enthält dieser erste Stand noch keine eigene native Datei-/Downloadverwaltung.

## Stand der Validierung

Am 9. Oktober 2026 wurden mit **Xcode 27.0 (27A266a)** beide Targets erfolgreich gebaut:

- Debug für iOS Simulator (`iphonesimulator`, ohne Signierung).
- Debug für echte iPhones (`iphoneos`, ohne Signierung).
- Die Safari-Übergabetests mit simulierten Browser-APIs bestehen ebenfalls.

Ein Swift-Fehler bei der Fehleranzeige wurde behoben; das gemeinsame App-Scheme ist im Projekt enthalten. Ein unsignierter Geräte-Build ist noch nicht auf einem iPhone installierbar. Zum Prüfzeitpunkt lädt Xcode die Simulator-Laufzeit noch herunter, und in Xcode ist noch kein Apple-Konto für Gerätesignierung angemeldet. Deshalb sind App-Start, Safari-Bereichsauswahl, App-Wechsel und Anmeldung noch nicht praktisch getestet. Dieser Stand ist kein veröffentlichungsfertiges App-Store-Paket.

Vor TestFlight/App Store außerdem App-Icons, Datenschutzangaben, Review-Zugang und Distribution-Signing ergänzen. Das Backend auf ALL-INKL bleibt bestehen. Für diesen Prototyp braucht es keinen zusätzlichen Server und keine App-Store-Veröffentlichung.

Safari-Web-Extension-Dokumentation: https://developer.apple.com/safari/extensions/

## Skriptprüfung

`node tests/bridge.test.cjs`
