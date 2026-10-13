# Schritt 04 – Veröffentlichung, Historie und Versionswechsel

Status: **offen** · Voraussetzung: Schritt 03 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Veröffentlichen mit Schema-Prüfung, Versionsnummer, Herausgeber, Zeitpunkt und Änderungsbeschreibung. Veröffentlichte Definition unveränderlich, neues Bearbeiten erzeugt Kopie als Entwurf. Archivierung verhindert neue Auswahl, alte Antworten bleiben lesbar.

Reise bindet veröffentlichte Version derselben Veranstaltung; kein automatischer Wechsel. Vergleich zeigt neue/geänderte/entfernte Felder und Auswirkungen auf Auswertungsserien. Bewusster Reisewechsel schreibt trip_form_history; bestehende Buchungen behalten ihren Stand. Ergänzungsbedarf als Datenauftrag vormerken, Benachrichtigung später in Mein Bereich. Aktive Versionsrevision für konkurrierende Änderungen/Absenden bereitstellen.

## Betroffene Bereiche

FormService/Version-Domain/Persistenz, TripService und trip_form_history, OpenAPI, Versionen-/Vergleichs-/Wechselansichten. Wechselvorschau zunächst mit den vorhandenen Daten, später realen Buchungszahlen aus Schritt 05; Demozahlen nicht als Ergebnis ausgeben.

## Abnahme

Zwei Reisen verwenden v2/v3 unabhängig. Veröffentlichung v4 ändert keine Reise. Direkter PUT einer veröffentlichten Definition scheitert; Archivformular bleibt historisch lesbar. Reise kann keine Version einer fremden Veranstaltung wählen. Textkorrektur bleibt Serie, Typ/Einheit/Geltung erzeugt getrennte Serie. Konkurrierende Veröffentlichung/Wechsel liefert Konflikt.

## Grenzen und Übergabe

Referenzen D05/D06, M06/Z01. Versionenvergleich mobil gezielt scrollbar. Kein Mailversand bei Ergänzungsauftrag. Späteres Wiederabsenden alter Version in Schritt 05/10 integrieren.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
