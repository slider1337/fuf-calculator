# Prüfung Schritt 03 – Formular-Editor

Stand: 13.10.2026. Aufbau auf `master` `15b076f22fcdab1a9f201603876eb9bc666d4712` (gemergte PRs #7/#8). Keine AGENTS.md im Repository vorhanden. Schritt 03 technisch umgesetzt; manuelle Designabnahme offen.

## Umsetzung

- Genau ein offener Entwurf je Veranstaltung, mit optimistischer Revision und serverseitigem Revisionskonflikt statt Überschreiben. Datenbankindex schützt auch konkurrierende Entwurfsanlagen.
- Editor in bestehendem PHP-/Vanilla-JS-Stack: Feldliste, Konfiguration, gemeinsame Vorschau; mobil Feldliste → Bearbeiten → Vorschau und Hauptaktionen unten. Auf/Ab-Buttons sind echte beschriftete 48-px-Aktionen. Entwurf bleibt bei Veranstaltungstabwechsel im Arbeitsspeicher; Verlassen/Reload fordert bei Änderungen eine bewusste Verwerfentscheidung, einschließlich Browser-Zurück. Browserabnahme bleibt manuell.
- Zahl (Ganzzahl/Dezimal), Checkbox, Ja/Nein, Einzel-/Mehrfachauswahl, kurzer/langer Text, Datum, Hinweis, Abschnitt. Label, Hilfetext, Pflicht, Geltung, Sichtbarkeit, Auswertung, Grenzen und feste Kalkulationsziele sind konfigurierbar.
- Server vergibt `f_…`-/`o_…`-IDs. Sie sind sichtbar und nicht frei editierbar. Textkorrekturen behalten IDs; Typ/Einheit/Geltung/Ganzzahlart sowie ausdrücklich geänderte Bedeutung erzeugen neue Feldserien. Neue Optionsbedeutung erzeugt neue Options-ID. Doppelte oder fremde IDs werden zurückgewiesen.
- Bedingungen nur Kinder in Buchung, Kind/Erwachsener oder vorherige Auswahl. Vorwärts-/zyklische Referenzen, falsche Geltung und ungültige Optionswerte scheitern. Kinderstatus aus Geburtsdatum am Reisebeginn und dessen vorhandener Altersgrenze; eigener Stichtag nur für fiktive Vorschau ohne Reise.
- Gemeinsamer `FormSchema`-/`AnswerValidator` prüft alle Eingaben serverseitig. `0`/`false` bleiben Werte; Pflichtcheckbox verlangt `true`, Ja und Nein erfüllen Pflichtentscheidung. Verborgene Felder sind nicht anwendbar. Hinweis/Abschnitt speichern keine Antwort.
- Zahlen als exakte Festpunktstrings mit bis zu sechs Nachkommastellen und zwölf Vorkommastellen; keine JSON-Floats. Editor akzeptiert auch Komma und übergibt kanonisch Punkt. Vorschau speichert keine Buchung oder Antwort.
- Kalkulationsziele sind eine feste Allowlist (`vegetarianMeals`, `roomPreference`, `arrivalDate`) mit Typ-/Geltungsprüfung und eindeutiger Zuordnung; noch keine Preis-/Planungsübernahme. Diese folgt ausschließlich in Schritt 07.

## Migration und Betrieb

Neue Phinx-Migration `20261013180000_add_versioned_forms.php`: `form_versions`, `form_fields`, nullable Reisebindung mit separater Revision, Wechselhistorie und Ergänzungsaufträge. Keine automatische Formularanlage, Alt-Reisebindung oder Antworterfindung. Altbestand bleibt erhalten. Neue Domain-/Application-Services und SQLite-Port sind in bestehender DI verdrahtet.

Vor Einführung konsistente SQLite-Sicherung durchführen und `php scripts/migrate.php` ausführen. Die bestehende Altbestandsprüfung migriert zweimal und erhält IDs, Herkunft und Finanzwerte. Bei Rücknahme nach neuen Formulardaten keine Tabellen löschen; kompatiblen Code verwenden oder konsistenten Sicherungsstand wiederherstellen. Die Definitionstrigger machen einen pauschalen Phinx-Down nicht zum Rücknahmeverfahren.

## Technische Prüfung

Gemeinsamer Nachweis für Schritte 03/04: vollständige PHPUnit-Suite auf PHP 8.4.14: **286 Tests, 1.264 Assertions, keine Fehler oder fehlgeschlagenen Tests**, ohne Coverage (kein Treiber vorhanden). PHP-Syntax für 133 Dateien bestanden. Neue Fälle in `FormApiTest`, `FormOpenApiContractTest` und `FormDecimalTest`; vorhandene Rechte-/Migrations-/Berechnungstests ebenfalls ausgeführt. PHP-Syntax, JavaScript-Syntax und `git diff --check` geprüft. OpenAPI 1.2.0 dokumentiert ausschließlich reale Routen; alle registrierten API-Operationen und sämtliche neuen Antwortstrukturen, einschließlich echter leerer JSON-Objektmaps, werden geprüft. Zwölf vorhandene Deprecations aus `jumbojett/openid-connect-php` bleiben unverändert.

## Manuelle Designabnahme

Referenzen gelesen: D03/D04, M03/M04/M05, K01/Z01, Design-/Mobilregeln und Übergabe. Keine Browser-/Lighthouse-Läufe gestartet.

- [ ] Desktop: drei Spalten, Feldsortierung, vollständige Optionen/Regeln und servergeprüfte Inline-/Gesamtvorschau.
- [ ] Mobil: sequenzieller Editor, feste Hauptaktionen mit Safe-Area und ausreichend Platz unter den Eingaben; keine Seitenüberbreite.
- [ ] Tastatur/Fokus, sichtbare ID, Pflicht-/Schemafehler, Optionen, Dirty-/Verlassen-Dialog und konfliktbedingter Reload.
- [ ] Familie/nur Erwachsene, Reise-Altersstichtag, ausgeblendete Felder und `0`/Nein.

Website, bestehender Anmeldeweg, echte easyVerein-Termine/Teilnahmen und Mails wurden nicht verändert. Schritt 04 gehört ebenfalls zum aktuellen Auftrag; Schritte 05–11 bleiben offen.
