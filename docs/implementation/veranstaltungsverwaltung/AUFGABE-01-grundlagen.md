# Schritt 01 – Rechte, Migration, Audit und Abschluss

Status: **umgesetzt, technisch geprüft; manuelle Designabnahme offen** · [Prüfprotokoll](PRUEFUNG-01.md) · Voraussetzung: Keine; erster Umsetzungsschritt.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Bestehende Architektur prüfen und additive Phinx-Migrationen für Veranstaltungen, konkrete Veranstaltungs-/Reisefreigaben, Reiseabschluss und Audit anlegen. Registrierungstabellen werden erst in Schritt 05 fachlich erweitert. Eine gemeinsame Berechtigungs-Policy in der Application-Schicht verwenden; sämtliche heutigen Reise-, Registrierungs-, Berechnungs- und Ausgabenendpunkte einschließlich Listen danach absichern. Fremde Ressourcen-ID im eigenen Reise-Pfad ebenfalls abweisen. Globale Einstellungen/Benutzerrechte serverseitig explizit absichern.

Abschlussstatus samt begründeter, berechtigter Abschlussaktion und Lesbarkeit einführen. Abgerechnete Reisen erlauben keine abrechnungswirksamen Änderungen und keine Neuberechnung gespeicherter Finanzstände. Audit für Vergabe/Widerruf/Abschluss; Wiederöffnung nur eigener protokollierter Ausnahmeweg. Rechteübernahme alter Reisen mit Vorschau, Zuordnung und Protokoll; keine erratenen Orga-Eigentümer. Neue Benutzer erhalten keine Altfreigaben automatisch.

## Betroffene Bereiche

`migrations/`, `Trip`, `User`, `TripService`, `TripListService`, `RegistrationService`, `ActualExpenseService`, deren Ports/SQLite-Repositories, `AuthMiddleware`, Controller, DI, Routen und OpenAPI. Die Domain-/Application-Policy ist auch für spätere Worker verbindlich.

## Abnahme

Datenbankkopie mit bestehenden Reisen, CSV/manuellen Buchungen und Finanzpositionen migrieren: IDs/Summen bleiben erhalten; zweimaliger Migrationslauf ohne Duplikate. Benutzer A kann Reise B weder listen, lesen, ändern noch über expenseId erreichen. Widerruf wirkt in laufender Session. Abschluss blockiert direkte Schreib-/Recalculate-API; Lesen weiter möglich. Aktuelle Berechnungstests bleiben erfolgreich. Audit enthält keine Passwörter/Token.

## Grenzen und Übergabe

Kein Formular, kein externer Termin, kein Websitecode. Abnahmeprotokoll nennt ausdrücklich die gewählte Alt-Rechteübernahme. Bezug Z01: fehlende Rechte und abgerechnet; sichtbare Adminaktion für Abschluss in vorhandener Oberfläche ergänzen.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
