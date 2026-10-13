# Prüfung Schritt 04 – Versionierung

Stand: 13.10.2026. Schritt 04 technisch umgesetzt auf Schritt 03; manuelle Designabnahme offen.

## Umsetzung

- Veröffentlichung prüft Schema, Revision und `form.publish`; speichert fortlaufende Versionsnummer, Herausgeber, Zeitpunkt und erforderliche Änderungsbeschreibung. Releases sind via PUT unveränderlich. Definitionstrigger schützen außerdem vor direkten SQLite-Änderungen/Einfügungen/Löschungen und einer Rückverwandlung in Entwürfe.
- Neues Bearbeiten erfolgt als explizite Kopie in den einzigen Entwurf. Archivieren erfordert Revision, Veröffentlichungsrecht und Grund; eine archivierte Version kann nicht mehr neu gebunden werden. Historische Definition, Nummer, Wortlaut und Veröffentlichung bleiben erhalten, auch an bereits gebundenen Reisen.
- Versionenansicht zeigt Historie, Akteur/Zeitpunkt, Änderungen, Definition und Kopier-/Archivaktionen. Vergleich zeigt hinzugefügte/geänderte/entfernte/unveränderte Felder, Reihenfolge, vollständige Konfiguration und Auswirkung auf Feld-/Optionsserien. Mobil nur Vergleichstabelle horizontal scrollbar.
- Jede Reise hat unabhängige `form_version_id` und `form_revision`. Neue Veröffentlichung stellt keine Reise um. Nur veröffentlichte Version derselben Veranstaltung bindbar. Bestehende Reisefreigabe `trip.manage`, offene Reise, aktuelle Bindungsrevision sowie Zielversionsrevision sind erforderlich. `trip.view` liest nur gebundene historische Definition; alleinige Reiseansicht erhält keinen Zugriff auf andere Entwürfe.
- Wechselvorschau zeigt echte Anzahl bestehender Registrierungen nur mit `registration.view`, sonst `null`. Noch keine erfundene Aufteilung nach Formularstand: `existingVersionBreakdownAvailable=false`, bis Schritt 05 Buchungsversionen modelliert. Bestehende Registrierungen bleiben beim Wechsel unverändert.
- Wechsel schreibt atomar Historie mit Akteur, Zeitpunkt, Grund, Revision und optionalem Ergänzungsauftrag. Ergänzung verlangt zusätzlich `registration.manage` und wird je vorhandener Registrierung lokal als `pending` vorgemerkt. Keine E-Mail/externen Aufträge. Anzeige/Erfüllung in „Mein Bereich“ folgt mit Schritten 05/11.
- Altanmeldungen können bis Schritt 05 weiterhin über bestehende Wege gelöscht/CSV-ersetzt werden: Ergänzungsauftrag bleibt mit ursprünglicher `registration_reference` erhalten, verliert aktive Registrierung und wird `obsolete`. Dadurch weder Fremdreferenz auf ersetzte Buchung noch Blockade der bisherigen Funktion.
- Gebundene Reise kann nicht still einer anderen Veranstaltung zugeordnet werden. Abgerechnete Reise bleibt historisch lesbar, Versionswechsel ist gesperrt.

## Prüfung

Gesamtsuite und Syntax-/API-Prüfungen wie in [PRUEFUNG-03](PRUEFUNG-03.md). Neue Tests belegen unabhängige Bindungen, Veröffentlichung ohne Reisewechsel, direkte Release-Sperre, Archivlesbarkeit, Fremdversionen, alte Revisionen, getrennte Rechte, Widerruf, Abschlusssperre, Datenbankschutz, dauerhafte Ergänzungsaufträge sowie unveränderte Alt-Registrierungswerte. API-Vertrag mit allen neuen realen Antwortstrukturen geprüft. Neue Migration läuft auch auf Altbestand mehrfach ohne Werte-/ID-Verlust.

## Manuelle Abnahme und Einführung

- [ ] D05: Historie, Kopie, Veröffentlichungsbeschreibung, Lesemodus und Archivaktion.
- [ ] D06/M06: Versionswahl, vollständiger Vergleich, echte Bestandszahl, Ergänzungsauftrag und Wechselhistorie.
- [ ] Zwei Reisen auf unterschiedlichen Versionen, danach neue Veröffentlichung ohne automatische Umstellung.
- [ ] Fremde/archivierte Versionen und abgerechnete Reise nicht wechselbar; archiviertes Formular weiterhin lesbar.
- [ ] Vergleich auf Mobil nur in eigener Scrollfläche; Fokus/Modal-Rückkehr und Fehlermeldungen.

SQLite sichern, Migration ausführen, Rechte für `form.manage`/`form.publish` und konkrete Reise prüfen. Keine Daten von Mockups in Produktivtabellen übernommen. Keine Website-Änderungen, Mails oder externen Schreibaktionen. Kein Merge/Deployment durch diesen Auftrag. Nächster fachlicher Schritt ist 05 (Buchungen/Antwortrevisionen); vorhandene Ergänzungsaufträge und aktive Versionsrevision sind dafür vorbereitet.
