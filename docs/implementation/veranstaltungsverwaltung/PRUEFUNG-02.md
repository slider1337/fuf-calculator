# Prüfprotokoll 02 – Veranstaltungen, Reisen und Teams

Stand: 13.10.2026; derselbe Branch/Ausgangsstand wie [Prüfung 01](PRUEFUNG-01.md). Schritt 01 technisch geprüft; dessen manuelle Designabnahme ist offen.

## Ergebnis

Veranstaltungen mit Name/Beschreibung anlegen, suchen, als Karten/Tabelle anzeigen, bearbeiten, archivieren und bewusst reaktivieren. Revisionskonflikt verhindert Überschreiben eines veralteten Standes. Archivierung erhält Reisen und sperrt neue Durchführung/Zuordnung bis zur Reaktivierung.

Veranstaltung zeigt ausschließlich freigegebene Reisen; Anzahl/nächster Termin und Reisefinanzkennzahlen werden entsprechend begrenzt. Veranstaltungspflege allein gewährt keine Reise- oder Antwortrechte. Standardteam und Veranstaltungsverwaltung sind getrennte Freigaben. Beim Reiseanlegen wird das Standardteam unabhängig kopiert, spätere Vorlageänderungen greifen nicht in vorhandene Reiseteams ein. Ersteller erhält explizite Rechte. Keine Rechteübernahme aus easyVerein-SSO.

Neue Reisen können über die Veranstaltung mit vorbelegter Zuordnung angelegt werden. Bestehende offene Reisen werden gezielt und begründet zugeordnet. Duplizieren übernimmt Planung, neue Eckdaten, Ersteller und aktuelle Standardteamvorlage; keine Anmeldungen, Personen, Ist-Ausgaben, Abschluss oder externen Identitäten. Tagesreisen mit Beginn=Ende und null Nächten werden unterstützt; die bestehenden Anmeldungsfelder werden erst in Schritt 05 für Unterkunftsfreiheit erweitert.

Navigation: Veranstaltungen/Reisen/Mein Konto sowie berechtigte Benutzer-/Einstellungsaktionen. Veranstaltung hat Reisen/Formular/Standardteam/Einstellungen, Reise hat Übersicht/Planung/Anmeldungen/Auswertung/Abrechnung/Team & Termin. Alte Reise-URLs sowie Planung-/Abrechnungshashes bleiben vorhanden. Formulare, Auswertung und externe Terminverwaltung nennen den Entwicklungszustand; keine erfundenen Buchungs-/Sync-Ergebnisse.

## Technische Nachweise

- Zwei unabhängige Reisen, Standardteamänderung ohne rückwirkenden Zugriff, gefilterte Veranstaltungszahlen, getrennte Veranstaltungs-/Reiserechte, Archiv/Reaktivierung/Revisionskonflikt, Zuordnung ohne Datenverlust, Tagesreise und saubere Duplikation: `EventAccessApiTest` besteht.
- API-Schreibaktionen prüfen echte Fähigkeiten; `EventOpenApiContractTest` prüft Routenabdeckung und reale Antworttypen/Pflichtfelder/Arrays/Referenzen/Komposition für Veranstaltungsliste/-detail, Teams, Personenauswahl, Zuordnung, Abschluss/Wiederöffnung, Duplikation und Abrechnung. Dies ist ein gezielter Strukturtest, kein vollständiger OpenAPI-Standardvalidator.
- HTTP-Direktlinks `/events`, `/events/{id}`, `/trips/{id}` liefern die vorhandene SPA inklusive Verwaltungsmodul. Tab-/URL-Logik im Quellcode geprüft; JavaScript-Syntax erfolgreich. Einmalige Neureise-Vorbelegung wird zurückgesetzt; zusätzliche Reise-Tabs aktualisieren auch Kontext/Bottom-Bar; Veranstaltungs-Hashes werden behandelt. Ungespeicherte Planungswarnung berücksichtigt hinzugefügte/entfernte Zimmerzeilen.
- Gesamtlauf: 274 PHPUnit-Tests auf PHP 8.4.14; keine Fehler/fehlgeschlagenen Tests. Syntax und Diff-Prüfung siehe Protokoll 01.

## Vorbereitete manuelle Designabnahme

Keine Browser-/Lighthouse-Automation ausgeführt. Die folgende Prüfung ist vor visueller Freigabe/Einführung noch offen:

- [ ] D01/M01: Karten/Tabelle, Suche, Archiv, leere Veranstaltung, lange Titel und mobile Hauptaktion.
- [ ] D02/M02: zwei Reisen, historische abgeschlossene Reise, neue Durchführung/gezielte Altzuordnung, sichtbarer Archivzustand.
- [ ] D08/M14/D15: Übersicht und Schnellliste, sechs Tabs, alte `#planung`/`#abrechnung`-Links, Neuladen und Zurück/Vorwärts einschließlich ungespeicherter Eingaben.
- [ ] D13/M13: Standardteam und Reiseteam, Bearbeiten/Widerrufen, Orga-/Abrechnungs-/Terminvorlagen, unabhängige Teamkopie.
- [ ] K01/Z01: leere/gesperrte Ansichten, Fehler und fehlende Rechte, abgeschlossene Reise, Entwicklungszustände ohne Beispieldaten.
- [ ] Mobil bei 390 px: horizontale Tabs, Bottom-Bar mit Safe-Area, keine Seitenüberbreite/überdeckten Felder; Dialog als Vollbild-Sheet, Fokus/Schließen/Rückkehr und Tastatur.
- [ ] Planung/Abrechnung mit alten Referenzen 02/03 vergleichen; bestehende Berechnung/Anmeldungsdarstellung bleibt im bisherigen Layout.

Technische Arbeiten an Schritt 01/02 sind abgeschlossen. Manuelle Designfreigabe und produktive Altzuordnung sind noch offen. Aufgaben 03–11 bleiben außerhalb dieses Auftrags; Website-Freigabegate bleibt geschlossen. Keine externen API-Schreibvorgänge/Mails oder Änderung des produktiven Loginwegs.
