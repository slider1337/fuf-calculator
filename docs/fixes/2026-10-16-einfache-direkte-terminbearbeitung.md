# Termin bearbeiten: ein Speichern mit direkter Rückmeldung

## Problem

Die bisherigen Änderungen ließen die fachliche Bedienung zu kompliziert:
Vorschau, Auswahl einzelner Felder, Grund, asynchroner Auftrag und anschließend
zahlreiche Bestätigungen. Der Screenshot vom 16.10. zeigt dabei bereits geänderte
Termindaten in easyVerein. Diese wurden beim Teilnahmeabgleich als Fehler gegen
alte gespeicherte Werte dargestellt. Revision-Hashes und doppelte Anmeldefristen
waren technische Details ohne hilfreiche Handlungsanweisung.

## Verhalten

- Im Dialog gibt es bearbeitbare normale Felder und „In easyVerein speichern“.
  Änderungen werden automatisch erkannt; keine Übertragen-Checkboxen oder manuelle
  Vorschau. Die bestehenden Sicherheitsprüfungen laufen serverseitig.
- Beginn/Ende werden schreibgeschützt angezeigt und serverseitig aus der gespeicherten
  Reise übernommen: Beginn 00:00 Uhr, Ende 23:59:59 Uhr in Europe/Berlin. Ohne Enddatum
  gilt ein eintägiger Termin. Fremde vom Client gesendete Beginn-/Endwerte werden
  durch die gespeicherten Reisedaten ersetzt. Änderungen an den Reisedaten zwischen
  Dialogöffnung und Speichern machen den Stand ungültig.
- Kein Grund wird vom Benutzer eingegeben oder von den Termin-/Abgleich-APIs verlangt.
  Die Nachvollziehbarkeit über Akteur, Zeitpunkt, geänderte Felder und interne
  Auditbeschreibung bleibt erhalten.
- Der neue Endpunkt `/term/updates/save` führt den zugehörigen dauerhaften Auftrag
  unmittelbar im Request aus. Erfolg bedeutet verifizierten Provider-Readback.
  Bearbeitung durch den Hintergrundworker bleibt möglich und ist über dieselbe
  Lease exklusiv. Es wird ausschließlich die angeforderte Job-ID ausgeführt.
- Eindeutige Ablehnungen liefern sofort Fehlercode und sichere Feld-/Grundcodes.
  Die Details werden in `term_update_jobs.validation_json` gespeichert und übersetzt
  angezeigt; keine ungefilterten Providertexte, Werte oder Tokens. Der Dialog bleibt
  bei Fehlern offen. Rate-Limits sind als solche erkennbar.
- Netzwerkabbrüche/unklare Ausgänge erzeugen keinen blinden zweiten PATCH. Wiederholung
  verwendet denselben operationKey und dieselbe Nutzlast; laufende Aufträge werden
  kurz abgefragt, unklare Aufträge bleiben ausdrücklich als unbestätigt sichtbar.
- Falls ein bereits bestätigter Speichervorgang beim anschließenden Aktualisieren
  der Anzeige scheitert, bleibt die Rückmeldung „gespeichert“ erhalten.
- Anmeldeschluss-Eingaben gelten in deutscher Ortszeit unabhängig von der Gerätezeitzone.
  Nicht existierende lokale Zeiten und sich überschneidende Fristen werden vor dem
  Schreiben abgelehnt. Datumsanzeigen verwenden Europe/Berlin.
- Der Teilnahmeabgleich übernimmt normale Termindaten automatisch als aktuellen Stand.
  Alte gespeicherte technische Konflikte erscheinen auch vor dem nächsten Abgleich
  nicht mehr. Preisgruppen-/Personenpreisänderungen bleiben separat überprüfbar;
  bestehende Buchungspreise werden nicht automatisch ersetzt.

## Bereitstellung

Neue Migration für gespeicherte, sichere Fehlerdetails. Der vorhandene Deploy-Workflow
sichert die Datenbank und migriert vor dem Start der neuen App-/Worker-Version.
Bestehende unbekannte Schreibausgänge werden nicht automatisch erneut übertragen.

## Prüfumfang

Direktes Speichern ohne Vorschau/Grund, Daten aus gespeicherter Reise,
Winterzeit/Sommerzeit und ungültige lokale Uhrzeit, veralteter Stand, gezielte
Job-Ausführung bei mehreren Reisen, eindeutige Ablehnung mit Felddetails,
unklare Antwort mit idempotenter Wiederholung und reinem Readback-Abgleich,
Archiv-/Berechtigungsprüfungen sowie automatischer Termindatenabgleich bei
weiterhin getrennten Preisänderungen. Frontendtests verwenden gefälschte API-Antworten;
Browser-/Lighthouse-Prüfungen werden nicht gestartet.
