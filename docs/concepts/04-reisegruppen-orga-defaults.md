# Konzept 4: Reisegruppen und Standard-Orga-Team

**Status:** Entwurf, 22.09.2026. Keine Implementierung.

## Befund im Projekt

Eine Reise hat Name und Start-/Enddatum, aber keine übergeordnete Reisegruppe. Die [Trip-Domäne](../../src/Domain/Trip/Trip.php), [Trip-Liste](../../src/Application/TripListService.php) und [Reise-API](../../src/Infrastructure/Http/Controller/TripController.php) behandeln jede Reise separat. `groupExpenses` sind **Kostenpositionen innerhalb einer Reise** und sind nicht die hier gewünschten Reisegruppen.

## Fachmodell

Eine **Reisegruppe** ist eine dauerhafte Veranstaltungsreihe („Winterfreizeit“). Ein **Event/Reise** ist eine einzelne Durchführung („Winterfreizeit 2027“, 10.–14.02.2027). Eine Reise gehört zu höchstens einer Reisegruppe; auch Einzelreisen ohne Gruppe sind möglich. Die Gruppe enthält Name/Beschreibung und ein Standard-Orga-Team. Die Reise enthält ihre tatsächlichen Rollen, Termine, Preise, Anmeldungen und Abrechnung.

| Reisegruppe | Reisen | Standard-Orga |
| --- | --- | --- |
| Winterfreizeit | 2026 · 2027 · 2028 | Lea, Chris |
| Sommerfahrt | 2026 · 2027 | Miriam |

## Vererbung und Anpassung

**Vorschlag: Vorlage beim Erstellen einer Reise kopieren.** Bei „Winterfreizeit 2028“ wird das aktuelle Standard-Orga-Team in die Reise übernommen. Auf der Reise kann man Personen hinzufügen, entfernen und deren Rolle ändern. Spätere Änderungen am Gruppenstandard ändern bestehende Reisen **nicht heimlich**. Optional zeigt ein Vergleich „Standard geändert – für diese Reise übernehmen?“ mit Vorschau. Das ist für abgeschlossene Jahre und Abrechnungen nachvollziehbar.

Alternative wäre dynamische Vererbung mit Ausnahmen; sie spart einzelne Änderungen, birgt aber Überraschungen bei historischen Reisen und beim Entzug. Falls gewählt, müssen `grant`/`deny` und wirksame Rechte ausdrücklich sichtbar sein. Empfehlung: Snapshot plus bewusstes Übernehmen.

### Kleine Gruppen-Skizze

| Winterfreizeit · Übersicht |
| --- |
| Standard-Orga: Lea · Chris [Bearbeiten] |
| 2028 · Entwurf · Orga: Lea, Chris [Team anpassen] |
| 2027 · In Abrechnung · Orga: Lea, Tom [Öffnen] |
| 2026 · Abgeschlossen [Öffnen] |
| [Neue Reise aus dieser Gruppe] |

## Daten und Regeln

`trip_groups(id,name,...)`, `trips.trip_group_id NULL`, `group_default_organizers(group_id,user_id)`; Reisefreigaben als eigene Zuordnung. Eindeutigkeit `(group_id, year)` nur, falls wirklich eine Reise pro Jahr erlaubt sein soll; sonst mehrere Events im selben Jahr unterstützen. Bestehende Reisen bleiben ohne Gruppe und können einzeln zugeordnet werden. Gruppenseite zeigt nur Events, für die die Person Zugriffsrecht hat; eine Standard-Orga-Mitgliedschaft für zukünftige Reisen öffnet keine historischen Abrechnungen.

## Abnahmekriterien

- Neue Reise erhält Teamvorschlag aus Gruppe; Änderung an einer einzelnen Reise bleibt lokal.
- Wechsel der Gruppe oder Löschen der Gruppenzuordnung entzieht nicht stillschweigend bereits individuell erteilte Reiserechte; Admin bekommt vorher eine Vorschau.
- Geschlossene Reisen und ihre Ausgaben bleiben unverändert, wenn sich das Standardteam ändert.

## Noch zu entscheiden

Wer darf eine Gruppe anlegen und die Standard-Orga festlegen? Empfehlung: globale Admins; Gruppeneigentümer nur, wenn dieses Recht später ausdrücklich hinzukommt.
