# Konzept 2: Berechtigungen und Gruppen

**Status:** Entwurf, 22.09.2026. Keine Implementierung.

## Befund im Projekt

`AuthMiddleware` prüft im Regelfall nur, ob eine Session existiert. Die Endpunkte für Reisen, Registrierungen und Ist-Ausgaben enthalten derzeit keine reisenspezifische Berechtigungsprüfung; allein das Löschen von Benutzern prüft explizit `admin`. Siehe [Middleware](../../src/Infrastructure/Http/Auth/AuthMiddleware.php), [Routen](../../config/routes.php), [TripController](../../src/Infrastructure/Http/Controller/TripController.php), [UserController](../../src/Infrastructure/Http/Controller/UserController.php). Die globale Rolle `user` bedeutet aktuell noch keine begrenzte Reisesicht.

## Modell

- **Globaler Admin:** Benutzer und Systemeinstellungen, Einladungen sowie technische Verwaltung; Eingriffe protokollieren. Für die fachliche Belegfreigabe wird ein expliziter Orga-Eintrag auf der betreffenden Reise benötigt.
- **Reisegruppe:** eine wiederkehrende Reihe wie „Winterfreizeit“; enthält Standard-Orga-Team, keine pauschale Einsicht in alle späteren Belege.
- **Reise/Event:** eigene Freigaben je Benutzer und Aufgabe. Eine Person kann für verschiedene Reisen verschiedene Rollen besitzen.
- **Fähigkeiten statt grober Einzelrolle:** `trip.view`, `trip.manage`, `registration.manage`, `receipt.create`, `receipt.edit_own`, `receipt.delete_own`, `receipt.view_own`, `receipt.approve`, `settlement.view`, `access.manage`. Orga erhält `settlement.view` und `receipt.approve` automatisch für die eigene Reise. Ein Benutzer kann mehrere Rollen je Reise haben. Alle Belegänderungen und abrechnungswirksamen Schreibaktionen sind beim Reisestatus `abgerechnet` gesperrt.
- **Deny by default:** Keine Freigabe = Reise erscheint nicht und API liefert 403/404 entsprechend gewünschter Informationspolitik. Jede Aktion wird serverseitig gegen **die konkrete Reise-ID** geprüft, auch Download/Export und einzelne Datensatz-IDs.

## Startmatrix

| Rolle für genau eine Reise | Reise sehen | Reise/Anmeldungen ändern | Eigene Belege hochladen | Alle Belege prüfen | Abrechnung lesen | Team verwalten |
| --- | :---: | :---: | :---: | :---: | :---: | :---: |
| Orga | Ja | Ja* | Ja | Ja, einschließlich Freigabe* | Ja | Ja |
| Beleg-Einreicher | Eingeschränkte Ansicht | Nein | Ja, eigene Bearbeitung/Löschung* | Nein | Nein | Nein |
| Abrechnungsteam | Ja, reduzierte Stammdaten | Nein | Nein | Ja, nur lesend | Ja | Nein |
| Globaler Admin ohne Orga-Zuordnung | Administrative Sicht | Technische Verwaltung* | Nein | Keine fachliche Freigabe | Administrative Sicht | Ja |

\* Bei `abgerechnet` keine Änderungen an Belegen oder anderen abrechnungswirksamen Daten. Nur ein ausdrücklich zugewiesenes Orga-Mitglied darf vor Abschluss Belege freigeben; ein globaler Admin kann sich bei Bedarf nachvollziehbar als Orga zuordnen.

## Freigaben

`users` bleiben kontenweit; `trip_memberships(trip_id,user_id,role,source,granted_by,...)` oder normalisierte `trip_grants` enthalten die Reiserechte. Auf Gruppenebene existiert ein separater Satz `group_default_organizers`. Die Mitgliedschaft einer Vereinssoftware kann Vorschläge liefern, gewährt aber nicht automatisch FUF-Rechte. Für Uploadende gibt es einen gezielten Zugang, selbst wenn sie nicht im Orga-Team sind. Einladungen, Widerruf und Rollentausch werden protokolliert. Entzug soll ab der nächsten Anfrage wirken, nicht erst nach erneutem Login; Rollen daher nicht dauerhaft aus dem Session-Snapshot ableiten.

### Kleine Freigabe-Skizze

| Winterfreizeit 2027 · Personen & Zugriffe | Rolle | Status |
| --- | --- | --- |
| Lea Muster | Orga | Aktiv · [Ändern] |
| Tom Beispiel | Belege hochladen | Eingeladen · [Widerrufen] |
| Finanzteam | Abrechnung lesen | Aktiv · [Ändern] |
| [Person per E-Mail hinzufügen] | [Rolle auswählen] | [Freigeben] |

## Grenzen und Abnahmekriterien

- Kein Zugriff auf fremde Reisen durch Ändern von URL/`tripId`/`expenseId`; insbesondere Update/Delete einer Ausgabe muss deren Eigentümerreise prüfen (heutige Ausgabe-Update-API nimmt nur die `expenseId` in der Service-Schicht).
- Reiseliste, Suche, Abrechnungskennzahlen, CSV-Import, Export und Downloads werden konsistent gefiltert. Gerade die heutige Reiseliste enthält bereits Überschusswerte; diese für Uploadende nicht ausliefern.
- Bearbeiten/Löschen nur eigener Belege und nur vor dem Abschluss. Nach `abgerechnet` sperrt die API auch direkte Schreibaufrufe; Orga-Abrechnung bleibt lesbar.
- Nach Entzug auch laufende Sessions und Downloadlinks unbrauchbar; Audit enthält Wer/Wann/Was ohne Beleginhalt.

## Festlegung

Orga-Mitglieder sehen Abrechnungsdaten und dürfen Belege ihrer Reise freigeben. Das Abrechnungsteam hat ausschließlich Lesezugriff. Fachliche Freigaben durch globale Admins erfordern zusätzlich die Orga-Zuordnung auf dieser Reise.
