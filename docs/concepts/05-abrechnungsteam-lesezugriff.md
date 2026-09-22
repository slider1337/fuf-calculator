# Konzept 5: Abrechnungsteam je Reise mit Lesezugriff

**Status:** Entwurf, 22.09.2026. Keine Implementierung.

## Ziel

Pro Reise können eine oder mehrere Personen das Abrechnungsteam bilden. Sie sehen die Abrechnungszahlen, Ist-Ausgaben und die dazugehörigen Belege, können aber weder Reise noch Belege, Buchungen, Anmeldungen oder Berechtigungen ändern. Eine Person kann parallel für eine andere Reise Orga sein. Ein bloßes Login reicht nicht.

## Befund im Projekt

[Settlement-Service](../../src/Application/ActualExpenseService.php) liefert Umsatz, Plankosten, Ist-Ausgaben und Überschuss; [Reiseliste](../../src/Application/TripListService.php) enthält ebenfalls Überschuss. [Routen](../../config/routes.php) und [Controller](../../src/Infrastructure/Http/Controller/ActualExpenseController.php) prüfen noch keine Abrechnungsteam-Rolle. Es gibt keine rollenabhängige Abrechnungsansicht oder geschützte Belegdownloads.

## Rechte und Sicht

`settlement.view`, `expense.view`, `receipt.download` und optional `settlement.export` sind für die zugewiesene Reise erlaubt. `expense.create/update/delete`, `receipt.review`, `trip.manage`, `registration.manage` und `access.manage` sind gesperrt. Exporte müssen denselben Filter wie die Ansicht haben. Bei personenbezogenen Anmeldungen nur die Felder zeigen, die für die Abrechnung nötig sind; volle Teilnehmerdetails separat berechtigen.

Die Rolle ist **je Reise** zu vergeben; auf Gruppenebene kann später auch ein Standard-Abrechnungsteam als Vorlage hinterlegt werden, wenn das wirklich wiederkehrt. Zunächst je Reise wählen, denn Jahresabrechnungen und Zuständigkeiten können wechseln. Bei Kombination mit Orga-Rechten wird die effektive Summe der explizit vergebenen Fähigkeiten angezeigt.

### Kleine Abrechnungs-Skizze

| Winterfreizeit 2027 · Abrechnung · Nur Lesen |
| --- |
| Einnahmen 8.450 € · Plankosten 7.300 € |
| Zusätzliche Ist-Kosten 420 € · Überschuss 730 € |
| [Belege ansehen] [Abrechnung exportieren] |
| Team: Miriam, Chris [Admin: bearbeiten] |

## Wichtige Buchungsabgrenzung

Die heutigen Ist-Ausgaben werden zusätzlich zu sämtlichen Plankosten addiert. Sobald Zimmerrechnungen und andere schon geplante Kosten als Beleg erscheinen, müssen Plankosten und Ist-Werte in der Anzeige abgeglichen werden, sonst entsteht Doppelzählung. In der Abrechnungsansicht sollen **Plan**, **Ist**, **Abweichung**, **zusätzliche Kosten** und **ungeklärte Belege** getrennt erscheinen. „Nur Lesen“ schließt ausdrücklich eine Freigabe oder Verbuchung aus; dafür braucht es eine weitere Rolle oder einen globalen Admin.

## Abnahmekriterien

- Ein Abrechnungsteam-Mitglied kann genau die freigegebene Reise und ihre Abrechnung öffnen, Belege lesen und erlaubte Exporte laden.
- API-Schreibaufrufe, auch mit erratener Fremd-Reise-ID oder Ist-Ausgaben-ID, werden serverseitig abgewiesen.
- Nach Entzug sind auch Dateidownloads gesperrt; ein gespeicherter Direktlink ist kein Bypass.
- Historische Abrechnungsstände/Audit bleiben nachvollziehbar.

## Noch zu entscheiden

Sollen Abrechnungsmitglieder auch Namen einzelner Teilnehmender oder nur aggregierte Beträge sehen? Empfehlung: zuerst aggregiert; Einzelposten nur, wo die Abstimmung es erfordert.
