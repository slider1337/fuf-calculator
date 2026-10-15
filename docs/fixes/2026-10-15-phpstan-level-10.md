# PHPStan Level 10 als Build-Prüfung

## Ausführung und CI

`composer analyse` startet PHPStan 2.3.1 auf Level 10 mit Zielversion PHP 8.4. Geprüft werden `src`, `config`, PHP-Einstiegspunkte in `public`, `scripts`, `migrations` und `phinx.php`. PHP-Templates und Test-Fixtures sind nicht Bestandteil der statischen Analyse; PHPUnit und die bestehenden PHP-Syntaxchecks prüfen sie weiterhin im bisherigen Umfang.

Die bestehende CI führt die Analyse verpflichtend vor PHPUnit aus. Sie verwendet das GitHub-Ausgabeformat und bricht bei einem Befund ab. Der produktive Docker-Build wartet bereits über den wiederverwendeten CI-Workflow auf diesen Job. Es gibt kein `continue-on-error`, keinen abgesenkten Level und keine ausgeschlossenen Application-/Infrastructure-Dateien.

## Ausgangsstand und Baseline

Der erste Lauf meldete 2010 Befunde. Nach gezielten Vertrags- und Typkorrekturen verbleiben **1978 bestehende Befunde**, überwiegend untypisierte Arrays und ungeprüfte Daten aus Datenbank-/JSON-Grenzen. Sie sind in `phpstan-baseline.neon` mit konkreter Meldung, Fehlerkennung, Dateipfad und Anzahl festgehalten. Ein grüner Check bedeutet damit ausdrücklich nicht, dass der komplette Altbestand bereits vollständig Level-10-konform ist.

Zusätzliche Meldungen bzw. höhere Anzahlen lassen den Build scheitern. `reportUnmatchedIgnoredErrors: true` verlangt außerdem, erledigte Ausnahmen zu entfernen. CI erzeugt die Baseline niemals neu. Neue Fehler dürfen nicht durch zusätzliche Baseline-Einträge oder pauschale Dateiausschlüsse versteckt werden. Die Baseline sollte mit späteren fachlich begrenzten Refactorings schrumpfen.

Keine Baseline-Einträge sind für den neuen CSRF-/Rate-Limit-Code, AuthService, Session oder SqliteAuthTokenRepository erforderlich.

## Direkt behobene Befunde

- Der `RegistrationRepositoryInterface`-Vertrag enthält jetzt auch `findById`, `deleteById` und `deleteCsvByTripId`, die der Anmeldeservice bereits verwendet. Der fehlerschlagende Testadapter implementiert diese Methoden ebenfalls explizit.
- Das Kalkulationsergebnis ist einschließlich Preiskategorien und Aufschlüsselungen als Array-Shape beschrieben. Die Abrechnung verwendet diesen garantierten Vertrag direkt.
- Veranstaltungsausgabe und Dezimalsummen besitzen passende Ein-/Ausgabetypen.
- Sitzungswerte werden auf ihre tatsächlichen Laufzeittypen geprüft; Flash-Nachrichten verwenden keine ungeprüften Arrayzugriffe mehr.
- Die Token-Hydrierung prüft Datenbankzeilen, IDs und Textspalten, statt unbekannte Werte blind zu casten.

## Validierung

- Vollständige PHPUnit-Suite: **415 Tests, 6391 Assertions**, erfolgreich; 12 bestehende Deprecation-Meldungen.
- `composer analyse`: erfolgreich unter PHP 8.4.14, Level 10 mit dokumentierter Baseline.
- Temporär eingefügter neuer Typfehler (`mixed` als `int` zurückgeben): Analyse scheitert mit `return.type` und Exit-Code 1. Nach Entfernen wieder erfolgreich. Die Prüfdatei ist nicht Bestandteil des PRs.
- PHP-Syntaxprüfung und Composer-Audit erfolgreich; keine bekannten Sicherheitslücken oder aufgegebenen Pakete.
- Die lokale statische PHP-Laufzeit kann die optionale PHPStan-Turbo-Erweiterung nicht laden; die Analyse läuft korrekt mit der PHP-Implementierung. PHPUnit-Coverage bleibt Aufgabe der CI mit Xdebug.

Dieser PR baut auf dem separaten Sicherheits-PR #29 auf. Zuerst #29 mergen, anschließend diesen PR gegen `master` übernehmen.
