# Buchungsprozess – Aufgaben vom 18.10.2026

In dieser Reihenfolge umsetzen. Anschließend Prüfungen, Merge und Deployment beider Repositories.

- [x] 1. Eine eindeutige Buchungsliste: Website / easyVerein / Manuell korrekt unterscheiden, stabile Referenzen statt Namensabgleich, Bearbeiten anbieten, CSV-Hinweise ausschließlich bei Clubdesk.
- [x] 2. Manuelle Anmeldung: Namen und Geburtstage direkt eingeben, Preise auswählen, interne Kennungen ausblenden.
- [x] 3. Zimmerwahl auf Website und im Calculator: mehrere Zimmer pro Buchung, Kategorien aus der Reise und Personen je Zimmer zuordnen (auch Gäste / Kinder separat). Serverseitig prüfen und dauerhaft speichern.
- [x] 4. Reise als feste Basis für Termin: Preise und Zimmerkategorien automatisch übernehmen, zusätzliche Gastpreise zulassen. Bereits gebuchte Preise unverändert lassen; Konflikte klar anzeigen.
- [x] 5. Anmeldungen verständlich darstellen, stornierte Buchungen / Personen sichtbar kennzeichnen, aus aktiven Anmelde-, Teilnehmer- und Zimmerzahlen ausschließen.
- [x] 6. Keine Eingabe von Änderungs- oder Stornogründen im Calculator und auf der Website. Auditinformationen intern erzeugen.

## Abnahme

PHPStan Level 10 ohne Baseline, PHPUnit und betroffene JS-/WordPress-Prüfungen. Browser-/Lighthouse-Prüfungen ausschließlich optional manuell durch den Nutzer (AGENTS.md). Keine Testmails an Mitglieder.

## Umsetzung

1. Herkunft bestehender Mitgliederbuchungen wird anhand der gespeicherten Inhaberschaft als Website ausgegeben. Direktimport bleibt easyVerein. Die beiden API-Projektionen verwenden dieselbe Buchungs-ID; die Oberfläche zeigt genau eine Karte je gespeicherter Buchung. Bearbeiten öffnet die Buchungsdetails. CSV bleibt im Clubdesk-Verfahren.
2. Namens-/Geburtstagsfelder und aktuelle lokale Preisgruppen je Person, keine leeren Person-ID-Karten. Gewählte Preisreferenz und akzeptierter Preis bleiben gespeichert und werden angezeigt.
3. Eigene Zimmerliste je Buchung mit Kategorie und stabilen Personen-IDs. Mehrere Zimmer derselben Kategorie sind möglich; Familien, Kinder und Gäste lassen sich aufteilen. Der Server weist fremde Personen, doppelte Zuordnung und unbekannte Kategorien zurück. Neue Website und Calculator nutzen die Kategorien der gespeicherten Reise. Additiver API-Vertrag erhält ältere Clients.
4. Kalkulierte Verkaufspreise werden beim Einrichten serverseitig als feste Basis übernommen. Gastgruppen ergänzen die Basis. Zimmerkategorien ergänzen die Terminbeschreibung. Änderungen an verknüpften Reisen gleicht ausschließlich der Hintergrundworker ab; zusätzliche Providergruppen und Beschreibungstext bleiben erhalten. Unveränderte Basis verursacht keine neuen Providerrequests. Ein verlorenes POST-Ergebnis wird gelesen und nicht blind erneut angelegt. Bereits akzeptierte Buchungspreise werden nicht überschrieben. Der Termin zeigt den lokalen Übertragungsstatus und konkrete Basisfehler.
5. Aktive Anmeldungen und Personen getrennt von Stornierten zählen; eigener Storniert-Filter, Kennzeichnung je Person, aktive Zimmerzählung. Storno externer Teilnahmen läuft über den vorhandenen Auftragsweg.
6. Keine Begründungseingaben mehr, auch nicht für Formularveröffentlichungen, Rechte oder Wiederöffnung. Auditinformationen entstehen intern; historische Auditdaten bleiben erhalten.

## Prüfung

- PHPStan Level 10 ohne Baseline erfolgreich.
- Fünf JavaScript-Modellsuiten des Calculators erfolgreich.
- Website: Calculator-Modell, Formularablauf einschließlich mehrerer Zimmer und PHP-API-Brücke erfolgreich.
- Neue API-Fälle prüfen Mehrzimmer, stabile Referenzen, gefälschte Preise, atomaren Rollback, Herkunft, Storno ohne Grund sowie Hintergrundabgleich inklusive verlorener Antwort.
- Vollständige PHPUnit-Suite: 520 Tests, 14.002 Assertions erfolgreich. GitHub-Checks vor Merge abschließen.
- Browser-/Lighthouse-Abnahme optional und ausschließlich manuell durch den Nutzer.

## Ergänzung: einheitlicher Erwachsenenpreis (19.10.2026)

- [x] Ein einheitlicher Erwachsenen-Verkaufspreis wird als genau eine Basispreisgruppe „Erwachsener“ übernommen. Das gilt auch bei identischen kalkulierten Preisen ohne Mittelung.
- [x] Unterschiedliche Erwachsenenpreise bleiben getrennt; Kinder, zusätzliche Gastpreise und Zimmerkategorien bleiben erhalten. Alte Erwachsenen-Basisgruppen aus einem Termin-Entwurf werden nicht als zusätzliche Gruppen übernommen.
- [x] Der Hintergrundabgleich aktualisiert bei einer bereits verwalteten Preisgruppe auch deren Namen. Bereits angenommene Buchungen werden nicht umgeschrieben.
- Bereits extern bestehende zusätzliche Preisgruppen werden durch diese Änderung nicht gelöscht.
