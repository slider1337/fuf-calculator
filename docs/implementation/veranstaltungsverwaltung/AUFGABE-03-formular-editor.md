# Schritt 03 – Formular-Editor und gemeinsame Validierung

Status: **offen** · Voraussetzung: Schritt 02 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Formularfamilie und speicherbaren Entwurf je Veranstaltung bauen. Zahl, Checkbox, Ja/Nein, Einzel-/Mehrfachauswahl, kurzer/langer Text, Datum, Hinweis/Abschnitt implementieren. Label/Hilfetext/Pflicht/Geltung/Sichtbarkeit/Auswertung und erlaubtes Kalkulationsziel konfigurieren. Stabile Feld-/Options-IDs sichtbar und nicht frei editierbar; Änderungen an Typ/Einheit/Geltung erzwingen neue fachliche ID. Semantische Änderung ausdrücklich erfassen.

Gemeinsame serverseitige Schema-/Antwortvalidierung sowie identische Vorschau mit fiktiven Personen; Kind/Erwachsener nach konsistentem Reise-Altersstichtag. Versteckt ist nicht fehlend. Ja und Nein erfüllen erforderliche Entscheidung; Pflichtcheckbox braucht true. Reihenfolge mit echten Auf/Ab-Buttons, mobil Feldliste→Bearbeiten→Vorschau. Entwurf speichern und dirty Zustand/Verlassen-Dialog; Revision verhindert konkurrierendes Überschreiben.

## Betroffene Bereiche

Form-Domain, Schema-/Antwortvalidator, FormService und Persistenz neu; Frontend im vorhandenen Stack, wiederverwendbare Eingabekomponenten statt kopierter Demo-Runtime. OpenAPI für Entwurf/Schema/Preview mit Beispielen; kein veröffentlichtes Formular vortäuschen.

## Abnahme

Alle Typen mit gültigen/ungültigen Werten serverseitig prüfen; 0 und false bleiben Werte. Auswahlgrenzen/Datumsbereich/zyklische Sichtbarkeit/ungültige Options-ID abweisen. Pro-Buchung einmal, Pro-Person je zutreffender Person. Nach Reload identische Reihenfolge, zweiter Bearbeiter erhält Konflikt statt Datenverlust. Vorschau schreibt keine Buchung.

## Grenzen und Übergabe

Referenzen D03/D04, M03/M04/M05 und K01/Z01. Keine globale Foto-/Allergieabfrage. Mapping darf keine frei ausführbaren Formeln enthalten. Veröffentlichung und Versionswechsel erst Schritt 04.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
