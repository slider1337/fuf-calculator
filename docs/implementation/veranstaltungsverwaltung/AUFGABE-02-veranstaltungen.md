# Schritt 02 – Veranstaltungen, Reisen und Teams

Status: **umgesetzt, technisch geprüft; manuelle Designabnahme offen** · [Prüfprotokoll](PRUEFUNG-02.md) · Voraussetzung: Schritt 01 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Veranstaltungen anlegen/bearbeiten/archivieren, Suche sowie Karten-/Tabellenansicht umsetzen. Veranstaltungsdetail mit Reisen, Formular, Standardteam, Einstellungen. Bestehende Reisen gezielt zuordnen; neue Durchführung erhält Veranstaltung. Reiseübergreifende Schnellliste mit erlaubten Kennzahlen behalten. Archivierung erhält historische Reisen und Versionen, unterbindet neue Anlage bis bewusster Reaktivierung.

Standardteam verwalten und beim Reiseanlegen als unabhängigen Vorschlag kopieren; spätere Vorlageänderung verändert keine Reisefreigaben. Reiseteam mit konkreten Fähigkeiten bearbeiten und widerrufen. Keine automatische Orga-Verknüpfung aus easyVerein-Login. Reisekopf/Breadcrumb und sechs Tabs ergänzen; noch nicht fertige Tabs zeigen erklärten Entwicklungszustand ohne fingierte Ergebnisse. Duplizieren kopiert erlaubte Stammdaten/Planungsdaten und Teamvorschlag, nie Teilnehmer, Antworten, externe IDs oder Abschluss.

## Betroffene Bereiche

Event-Domain/Services/Ports/SQLite-Adapter neu, Trip- und Listenbereiche erweitern; `templates/index.html.php`, `app.js`, `fuf.css`, Routen/DI/OpenAPI. Neue SPA-Pfade etwa /events und /events/{id}; alte /trips/{id} und Planung/Abrechnung-Links weiter nutzbar.

## Abnahme

Winterfreizeit mit zwei unabhängigen Reisen; Zuordnung einer Alt-Reise ohne Datenverlust. Vorlagenänderung lässt Reiseteam unverändert. Reiseliste, Anzahl/nächste Reise und Finanzkennzahlen leaken keine fremden Reisen. Abrechnungsteam sieht keine Antworten. Direktlink/Neuladen/Zurück bleiben korrekt. Leere/archivierte Veranstaltung und Tagesveranstaltung ohne Nächte funktionieren.

## Grenzen und Übergabe

Referenzen D01/D02/D08/D13/D15, M01/M02/M13/M14, K01/Z01. Mobil horizontale Reise-Tabs und Bottom-Bar, nicht gequetschte Navigation. Planung und Abrechnung behalten vorhandenes Layout. Termin-/Buchungszahlen erst ausliefern, wenn deren Grundlagen vorhanden sind.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
