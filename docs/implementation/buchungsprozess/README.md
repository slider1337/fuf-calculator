# Buchungsprozess – Aufgaben vom 18.10.2026

In dieser Reihenfolge umsetzen. Anschließend Prüfungen, Merge und Deployment beider Repositories.

- [ ] 1. Eine eindeutige Buchungsliste: Website / easyVerein / Manuell korrekt unterscheiden, stabile Referenzen statt Namensabgleich, Bearbeiten anbieten, CSV-Hinweise ausschließlich bei Clubdesk.
- [ ] 2. Manuelle Anmeldung: Namen und Geburtstage direkt eingeben, Preise auswählen, interne Kennungen ausblenden.
- [ ] 3. Zimmerwahl auf Website und im Calculator: mehrere Zimmer pro Buchung, Kategorien aus der Reise und Personen je Zimmer zuordnen (auch Gäste / Kinder separat). Serverseitig prüfen und dauerhaft speichern.
- [ ] 4. Reise als feste Basis für Termin: Preise und Zimmerkategorien automatisch übernehmen, zusätzliche Gastpreise zulassen. Bereits gebuchte Preise unverändert lassen; Konflikte klar anzeigen.
- [ ] 5. Anmeldungen verständlich darstellen, stornierte Buchungen / Personen sichtbar kennzeichnen, aus aktiven Anmelde-, Teilnehmer- und Zimmerzahlen ausschließen.
- [ ] 6. Keine Eingabe von Änderungs- oder Stornogründen im Calculator und auf der Website. Auditinformationen intern erzeugen.

## Abnahme

PHPStan Level 10 ohne Baseline, PHPUnit und betroffene JS-/WordPress-Prüfungen. Browser-/Lighthouse-Prüfungen ausschließlich optional manuell durch den Nutzer (AGENTS.md). Keine Testmails an Mitglieder.
