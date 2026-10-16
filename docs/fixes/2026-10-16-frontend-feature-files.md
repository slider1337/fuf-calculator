# Frontend nach Fachfunktionen aufteilen

`app.js` enthält statt 3388 Zeilen nur noch 51 Zeilen für Initialisierung und
Anwendungsstart. Die bestehenden Vanilla-JS-Funktionen und Ereignisaktionen sind
in fachlich zusammengehörige Dateien verschoben; API-Routen und Oberflächen bleiben
bestehen. Es wird kein Framework oder zusätzlicher Bundle-/Installationsschritt eingeführt.

| Dateien unter `public/assets/js/` | Verantwortung |
| --- | --- |
| `core.js` | Gemeinsamer Zustand, Formatierung, authentifizierter API-Client |
| `calculator.js`, `calculator-preview.js` | Berechnungsergebnis, Preise, Live-Vorschau |
| `trip-editor.js`, `room-plan.js`, `trips.js` | Reiseformular, Zimmerplanung, Reiseliste |
| `navigation.js` | Routing, Tabs, mobile Navigation, Änderungsanzeige |
| `registrations.js` | Anmeldungsliste, CSV-Import, manuelle Erfassung |
| `settlement.js` | Abrechnung, Ausgaben, Erstattung |
| `administration.js` | Kontodaten, Benutzer, Standardeinstellungen |
| `workflows.js` | Terminwizard, Wiederaufnahme und Planungsübernahme |
| `participation.js` | Teilnahmeabgleich und Übertragungsaufträge |
| `term-updates.js` | Terminänderungen und Mitgliederfreigabe |
| `management.js`, `forms.js`, `bookings.js` | Bereits getrennte Veranstaltungs-, Formular- und Buchungsoberflächen |
| `app.js` | Einmalige Initialisierung nach Laden aller Fachdateien |

Das PHP-Template gibt die Ladereihenfolge ausdrücklich vor und versieht weiterhin
jede Datei mit ihrem bestehenden Versionsparameter. Gemeinsame Funktionen bleiben
im bisherigen Skriptkontext verfügbar. Damit entstehen keine zusätzlichen dynamischen
Imports oder asynchronen Ladeabhängigkeiten. Die zentrale Initialisierung ist
idempotent und unterstützt sowohl ein noch ladendes als auch ein bereits fertiges DOM.

## Prüfung

- Syntaxprüfung sämtlicher JavaScript-Dateien und des PHP-Templates.
- Bestehende 26 Termin-/Teilnahme-Szenarien mit Fake-API erfolgreich.
- Neuer VM-Test lädt die tatsächlichen Template-Skripte einzeln in ihrer Reihenfolge:
  keine doppelte Einbindung, Start erst nach Fachdefinitionen, beide DOM-Startfälle,
  keine doppelte Initialisierung, Navigation, Zahleneingabe, HTML-Escaping und
  Verdrahtung von Reise-, CSV-, manueller Anmeldung und Ausgabenaktionen.
- `node --test tests/Frontend/*.cjs` wird in der CI ausgeführt. Die Tests benötigen
  weder Browser noch Netzwerk. Keine Browser- oder Lighthouse-Prüfung gestartet.
- Vollständige PHPUnit-Suite: 437 Tests / 6955 Assertions erfolgreich, ohne
  Deprecations oder PHPUnit-Hinweise.
- PHPStan Level 10 erfolgreich, Baseline unverändert bei 1300 Altbefunden.

Baut auf `refactor/typed-booking-data` auf. Die Folge-PRs müssen in ihrer Reihenfolge
übernommen werden. Dies ist eine strukturelle Aufteilung; weitergehende Einführung
von ECMAScript-Modulen oder ein Austausch der Oberfläche ist nicht erforderlich.
