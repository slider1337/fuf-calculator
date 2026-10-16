# PHPStan-Rückstand vollständig behoben

Der auf `master` vorhandene Rückstand von 1.300 Befunden ist vollständig behoben.
`phpstan-baseline.neon` und ihre Einbindung sind entfernt. Level 10, PHP-Zielversion
8.4 und sämtliche bisherigen Prüfpfade bleiben bestehen. Es wurden keine
Fehlerunterdrückungen, neuen Baselines oder ausgeschlossenen Pfade ergänzt.

## Änderungen

- Präzise Array-Verträge für Reise-Metadaten, Formularfelder, Buchungen,
  Auswertungen, Mitgliederprofile und Provider-Teilnahmen; untrusted JSON bleibt
  bis zur tatsächlichen Validierung `mixed`.
- Geprüfte Datenbankzeilen und gezielte Hydrierung von Metadaten, Aufträgen,
  Abrechnungs- und Planungssnapshots. Beschädigte Finanzwerte werden nicht zu null
  oder null Euro umgedeutet.
- Prüfung verschachtelter Eingaben sowie gespeicherter Formular- und
  Teilnahmeinformationen vor ihrer Nutzung. Bestehende Dezimalberechnungen und
  historische Antworten bleiben erhalten.
- Geprüfte PSR-11-Serviceauflösung und Konfigurationsfabriken; typisierte HTTP-,
  Upload-, Session- und CLI-Grenzen. Die echten Provider verwenden weiterhin die
  bestehenden Freigabe-, Lease- und Idempotenzprüfungen.
- Der Testadapter liefert den vollständigen Metadatenvertrag. Zwei zusätzliche
  Integrationstests prüfen zurückgewiesene verschachtelte Reiseeingaben ohne
  Teilpersistierung und die Ablehnung beschädigter gespeicherter Finanzbeträge.

Der Stand enthält auch den inzwischen gemergten Website-Mitgliedervertrag aus
PR #37, einschließlich Terminerkennung und eigener Buchungsübersicht.

## Verifikation

- PHPStan 2.3.1: **0 Fehler auf Level 10, ohne Baseline**.
- PHPUnit 13.4.3: **441 Tests, 8.734 Assertions**, keine Warnungen oder Fehler.
- PHP-Syntax: **241 Dateien** erfolgreich geprüft.
- Frontend: Anwendungsstart und Script-Reihenfolge sowie **26 Terminszenarien**
  erfolgreich geprüft.
- `git diff --check`: erfolgreich.

Lokale PHP-Version: 8.4.14. PHPUnit lokal mit `--no-coverage`, da diese statische
Laufzeit keinen Coverage-Treiber besitzt. Die unveränderte CI führt
`composer analyse` und PHPUnit mit Xdebug-Coverage aus. Die optionale
PHPStan-Turbo-Erweiterung ist lokal nicht ladbar; die PHP-Analyse ist erfolgreich.
Es wurden keine echten Provider-Schreibaktionen oder Mails ausgelöst.
