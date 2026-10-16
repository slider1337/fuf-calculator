# Typisierte Termin- und Anmeldeverwaltung

## Umfang

`TermService`, `TermWorker`, `TermUpdateService`, `TermUpdateWorker` und
`RegistrationModeService` verwenden fachliche Repository-Interfaces statt SQL oder
konkreter SQLite-Abhängigkeiten. Die SQLite-Adapter kapseln Abfragen, Auftragsstatus
und Lease-Prüfungen. Der bisherige `SqliteWorkflowRepository` delegiert Terminlesezugriffe
an den neuen Adapter, damit bestehende Aufrufer dieselbe Dateninterpretation verwenden.

Unveränderliche Datenobjekte und Enums beschreiben Terminentwürfe, Änderungsaufträge
und Anmeldemodi. Persistierte JSON-Daten und Datenbankwerte werden beim Einlesen
validiert; beschädigte Werte werden nicht durch leere Standardwerte verdeckt.
Transaktionsverträge erhalten den Rückgabetyp ihres Callbacks.

## Verhaltenskorrekturen

- Eine während des Provider-Vorabchecks abgelaufene Lease verhindert den externen PATCH.
- Ein während des externen Lesens gewechselter Terminlink wird vor dem Einreihen
  einer Änderung erneut geprüft.
- Ungültige gespeicherte Änderungsdaten werden vor einem Provider-Schreibzugriff abgewiesen.
- Fehlende Datensätze werden an betroffenen Aufrufstellen explizit behandelt.
- Beobachtete Kapazität null Plätze bleibt lesbar; Kapazitätsänderungen müssen positiv sein.

Datenbankschema und öffentliche API-Routen bleiben unverändert.

## Prüfung

Geprüft einschließlich der automatischen Teilnahmeübernahme aus PR #31.

- PHPUnit: 434 Tests, 6950 Assertions erfolgreich; 12 bereits vorhandene Deprecations.
- `composer analyse -- --no-progress`: PHPStan Level 10 erfolgreich mit Baseline.
- Ohne Baseline: 1963 auf 1651 Befunde reduziert (312 weniger, rund 16 %).
- Die fünf oben genannten Application-Klassen und neuen Klassen benötigen keine
  Baseline-Ausnahmen. Beim Vergleich der Baselines wurden keine neuen Ausnahmen
  oder erhöhten Befundzahlen aufgenommen.
- Regressionstests decken Repository-Grenzen, Datenvalidierung, abgelaufene Leases
  und wechselnde Terminlinks ab. Externe Schreibzugriffe laufen in Tests über Fakes.
- PHP-Syntax und `git diff --check` erfolgreich.

## Verbleibende Arbeit

1651 Altbefunde bleiben in der Baseline. Andere Abläufe, insbesondere Mitgliedsbuchungen
und Teilnahmeverwaltung, verwenden weiterhin Teile des generischen Workflow-Repositorys.
Diese können schrittweise auf fachliche Ports umgestellt werden. Die Baseline darf dabei
nur schrumpfen; neue Befunde müssen behoben werden.
