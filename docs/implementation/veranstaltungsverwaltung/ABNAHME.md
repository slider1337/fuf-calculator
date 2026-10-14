# Abnahme und Freigabe zur Website-Anpassung

Alle Punkte zunächst **offen**. Jeder Umsetzungsschritt ergänzt ein Prüfprotokoll; diese Liste behauptet keine bereits bestandenen Tests. Sinnvolle Domain-, Application-, Migrations- und API-Integrationstests verwenden. Änderungen an reinem Layout manuell anhand der Referenzen abnehmen. Keine Browser-/Lighthouse-Automation und keine echten Nachrichten oder externen Testbuchungen.

## Fachliche Prüfmatrix

| Fall | Nachweis | Schritt |
| --- | --- | --- |
| Altbestand | Kopie mit Alt-Reisen, csv/manual, Personen-IDs und Finanzständen migriert; identische Werte, wiederholbarer Migrationslauf | 01/05 |
| Rechte | A/B-Reisen: Listen, Details, einzelne IDs, Antwortliste, Aggregate, Export und Finanzaktionen konsistent begrenzt | 01–10 |
| Widerruf | Nächste Anfrage auch in existierender Session abgewiesen | 01/02 |
| Standardteam | Vorlageänderung beeinflusst historisches Reiseteam nicht | 02 |
| Reise ohne Unterkunft | Anlage/Planung/Buchung ohne Zimmer und Nächte gültig | 02/05/07 |
| Formulartypen | 0/false gültig, Ja/Nein-Pflicht akzeptiert beide, Pflichtcheckbox nur true, Grenzen/Optionen serverseitig | 03/05 |
| Bedingungen | Buchungs-/Personenfeld einmal bzw. je Person; verborgen ohne fehlend; Kind-/Erwachsenen-Stichtag identisch | 03/05 |
| Release | v2/v3 parallel, neue Veröffentlichung ohne Wechsel, API kann Release nicht ändern | 04 |
| Identität Feld/Option | Typ/Einheit/Geltung/Bedeutung getrennte Serie, entfernte Felder historisch lesbar | 03/04/06 |
| Veraltetes Formular | Konflikt, kompatible Angaben bleiben, neue Pflichtfrage erneut bestätigt | 05/10/11 |
| Buchungsgruppe | Vier Personen ergeben eine Buchung; stabile Personenreferenzen nach Korrektur | 05 |
| Revision | Original/Korrektur sichtbar; aktuelle gültige Antwort nur einmal aggregiert | 05/06 |
| Teilstorno | Eine von vier Personen fällt aus Personenwerten; ungeprüfte Buchungsmengen getrennt; Gesamtstorno aus regulärer Summe | 05/06/09 |
| Zahlensumme | 17 Personen aus 11/12 beantworteten Buchungen (separate geprüfte Fixture), 0 nicht leer | 06 |
| Checkbox | 9/10 anwendbare Buchungen mit Kindern, 1 fehlt; Personenvariante alternativ 17/42, 23 Nein, 2 fehlt | 06 |
| Alte Version | nicht abgefragt separat von verborgen/fehlend; keine Einheit/Geltung mischen | 06 |
| Detail/Export | Identische Filter/Zähler/Einheit/Stand, fehlende Werte leer; Revision eigener Export; Formelinjektion neutralisiert | 06 |
| Veranstaltungsstatistik | Nur erlaubte Reisen; Gesamtwert zählt Reise-Teilnahmen, keine eindeutigen Mitglieder | 06 |
| Planungsübernahme | Vorschau schreibt nicht; bewusste Übernahme protokolliert; Änderung macht Stand veraltet; konkurrierende Änderung Konflikt | 07 |
| Abschluss | Keine Finanzänderung/Übernahme/Neuberechnung via UI oder API; Antworten lesbar, nichtfinanzielle Revision getrennt | 01/05/07/09 |
| Terminauftrag | Entwurf ohne externes Schreiben, bestätigte ID erforderlich; eindeutige Reise-Termin-Zuordnung | 08 |
| Unklarer Ausgang | POST mit Fake-Timeout/Worker-Absturz: erst Abgleich, kein ungeprüfter zweiter Termin/Teilnahme | 08/09 |
| Teilfehler | Drei Personen bestätigt, vierte Fehler; Wiederholung nur vierte; Buchungs-/Sync-Chip getrennt | 09 |
| Externe Teilnahme | Quelle/fehlende Angaben/Kontaktklärkriterium sichtbar; keine geratenen Familienbeziehungen | 09 |
| Externer Konflikt | Frist/Kapazität/Preis feldweise bewusst auflösen; keine automatische Überschreibschleife | 09 |
| Integration | Ohne Calculator-Verwaltungskonto buchen; fremde Buchung/Person/abgelaufene oder gefälschte Delegation verboten | 10 |
| API-Wiederholung | Derselbe Schlüssel/Inhalt gleiche Buchung, anderer Inhalt 409, parallele Requests keine Duplikate | 05/09/10 |
| Testmailregel | Application-Testmail ausschließlich me@benna.me, Provider-Tests mit Fakes; normaler Produktivversand erst nach Freigabe. Ergänzung bleibt Portalhinweis | 08–11 |

## Manuelle Designabnahme im Calculator

- [ ] D01–D15/M01–M14 sinnvoll erreichbar, alle realen Aktionen mit Rechteprüfung.
- [ ] Bestand Planung/Abrechnung entspricht bisherigen Referenzen, neue Navigation/Übernahmedialog integriert.
- [ ] Mobil keine Seitenüberbreite, Tabs scrollen, Bottom-Bar überdeckt keine Eingaben.
- [ ] Feldsortierung ohne Drag-Pflicht, Tastatur/Fokus, beschriftete Aktionen und Pflichtfehler.
- [ ] K01-Komponenten, Statusfarbe/Bezugsgröße, Z01-Leer-/Fehler-/Sperrzustände vorhanden.
- [ ] Ungeprüfte/falsche Demo-Versionen aus Mockups fachlich korrigiert, keine Platzhalter als Live-Erfolg.

## Gate vor Schritt 11

- [ ] Schritte 01–09 dokumentiert abgenommen; keine offenen Pflichtkriterien unter „später Website“ verschoben.
- [ ] Calculator-Buchungen vollständig lokal erfassbar, auswertbar und in Planung kontrolliert übernehmbar.
- [ ] Externe Schreib-/Abgleichverträge und Benachrichtigungssperren belegt; fehlende echte Voraussetzungen als Blocker dokumentiert.
- [ ] Schritt 10: OpenAPI und echte Calculator-Antworten konsistent; Testclient besteht vollständigen Mitgliederablauf inklusive Fehlerfälle.
- [ ] Delegation entspricht nachweislich bestehendem Website-Login; Fristen/Aufbewahrung/Übergang pro Reise dokumentiert.
- [ ] Migration/Backup/Workerbetrieb und kompatible Calculator-Bereitstellung abgeschlossen.
- [ ] Website-Handover enthält Vertragsversion, Konfiguration ohne Secrets, Beispielrequests/-responses, Fehler-/Version-/Idempotenzregeln und Termin-ID-Zuordnung.

## Website-Abnahme in Schritt 11

W01/W02/W03 manuell nach Referenz prüfen, inklusive Versionskonflikt, Pflichtfehler, Teilfehler und Mein Bereich. Vierpersonenbuchung erscheint ohne weitere Handarbeit mit richtiger Version im Calculator. Öffentliche Terminseiten und bestehender Mitgliederlogin bleiben nutzbar. Bei API-Ausfall keine Doppelbuchung/kein automatischer Rückfall auf alten Schreibweg. Neue API-Schreibwege je Reise eindeutig aktiviert; bereits eingegangene Buchungen bleiben bei Rücknahme lesbar.

## Format für das Prüfprotokoll

Datum/Commit, umgesetzter Schritt, Änderungen und Migration, geprüfte Fälle mit Ergebnis, manuelle Designpunkte, externe Vertragsbelege (wo erforderlich), verbleibende Sperren/Entscheidungen, Betriebsanweisungen und nächster freigegebener Schritt. Nie Fake-Prüfung als echten easyVerein-Nachweis ausgeben.
