# Schritt 06 – Auswertungen, Antwort-Drilldown und Export

Status: **technisch umgesetzt und geprüft; manuelle Designabnahme offen** · Prüfprotokoll: [PRUEFUNG-06](PRUEFUNG-06.md) · Voraussetzung: Schritt 05 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Ein gemeinsames lesendes Modell für Reise-/Versions-/Status-/Feldfilter erstellen; regulär nur bestätigt und aktiv. Standardfilter und alternative Grundgesamtheit sichtbar beschriften. Buchungsfelder einmal je gültiger Buchung, Personenfelder je bestätigter aktiver Person zählen. Antwortzustände fehlt/0/false/nicht anwendbar/nicht abgefragt/ungeprüft getrennt. Geprüfte Summe und ungeprüfte Mengen getrennt ausweisen.

Kennzahlen für Zahlen/Checkbox/Ja-Nein/Auswahl, Listen für Text/Datum; identische Filter für Drilldown, CSV und Druckansicht. Veranstaltungssicht über berechtigte ausgewählte Reisen mit separaten Reisezeilen und klar benanntem Gesamtwert. Kompatibilität der Feldserien prüfen, entfernte alte Felder weiterhin filterbar. Export trägt Einheit, Stand, Herkunft, Status, Version, Referenzen und Filter; Revisionen eigener Export. XLSX nur bei tatsächlichem Bedarf zusätzlich zur funktionierenden CSV.

## Betroffene Bereiche

AnalyticsService/Read-Port/SQLite-Abfragen/Controller, UI Auswertungen/Drilldown und OpenAPI. Personen/Antwortrevisionen nicht durch JOIN-Vervielfachung doppelt summieren. Pagination und Filter in Detailansicht; serverseitiger Exportrechtecheck.

## Abnahme

12 Buchungen/42 Personen: einmalige Mengenserie 17 mit 11/12 beantwortet; Checkbox 9/10 anwendbare Buchungen mit Kindern, nicht 9/42. Personenserie alternativ 17/42, 23 Nein, 2 fehlt. Älteres Feld nicht abgefragt, Text keine Summe, Teilstorno/Teilfehler/ungeprüft getrennt. Detail und Export reproduzieren Kennzahlen einschließlich Nullwerten. Keine fremde Reise in Gesamtstatistik. CSV neutralisiert Tabellenformeln.

## Grenzen und Übergabe

Referenzen D10/D11, M10/M11, Z01. Beispielwerte nur mit dazu passenden Fixtures prüfen; widersprüchliche ungeprüfte Mockup-Werte nicht als reguläre Summe übernehmen. Ohne Daten erklärter Leerzustand und gesperrter UI-Export.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
