# Prüfung Schritt 05 – lokale Buchungen und Antworten

Stand: 13.10.2026. Ausgangspunkt: `master` ff156ae (PR #11 gemergt); keine offenen PRs. Schritte 05 und 06 gemeinsam beauftragt. Technische Umsetzung geprüft; manuelle Designabnahme weiterhin offen.

## Umsetzung

- Zentraler Tab Anmeldungen statt zweier Erfassungswege. Die Planung verweist darauf; bestehende Preis-/Abrechnungsergebnisse bleiben erreichbar. Lokale Orga-Erfassung verwendet den gemeinsamen Buchungsservice mit gebundenem Zusatzformular, Kontakt, einer Buchungsreferenz und stabilen vorhandenen `registration_participants.id`. Kein Website-Umbau.
- Atomare, reiserechtlich geprüfte Buchung/Personen/Antwortrevisionen/Operation/Audit. Identischer Schlüssel und Inhalt liefert dieselbe Buchung, abweichender Inhalt 409. Optimistische Buchungsrevision schützt Korrekturen. Wiederholungen einer Korrektur oder eines Stornos erzeugen keine doppelte Revision.
- Servervalidierung für alle vorhandenen Formularfeldtypen, Pflicht, Sichtbarkeit, Auswahloptionen, echte Dezimalwerte, tatsächlichen Reisestichtag und Altersgrenze. Neue Buchung mit alter Versionsbindung liefert 409. Ergänzung auf aktive Version ist ausdrücklich zu bestätigen; ursprüngliche Definition/Antworten bleiben erhalten. Versionswechsel-Vorschau zeigt echte Buchungszahlen je gespeicherter Version.
- Append-only Antwortrevisionen und Personenkorrekturhistorie, jeweils Akteur/Zeit/Grund. Personenkorrekturen erhalten IDs, prüfen Geltung/Pflicht neu und benötigen zusätzlich Registrierungsrechte sowie eine offene Reise. Rein nichtfinanzielle Antwortkorrektur bleibt mit Grund und Antwortrecht nach Abschluss möglich.
- Teil-/Gesamtstorno erhält Personen/Antworten. Betroffene Buchungsantworten werden konservativ ungeprüft; kein proportionales Schätzen. Vollstorno verlässt reguläre Auswertung. Zimmerkategorie ist nullable (Tagesveranstaltung); Zimmerzuteilung ist separat vom Wunsch. CSV/Altbestand wird klar als ohne Zusatzformular und ohne externe Bestätigung angezeigt.
- Neue lokale Buchungen bleiben fachlich `pending`, Sync `pending`; Altbestand `confirmed`/`local_only` bezeichnet übernommene lokale Anmeldungen und **keine** bestätigte externe Synchronisation. Es gibt keinen Endpunkt zum Simulieren einer easyVerein-Bestätigung. Ausstehende lokale Operationen sind unabhängig von einem künftigen externen Adapter gespeichert.
- Sammellöschen/Einzellöschen lehnen geschützte Buchungen mit 409 ab. CSV-Ersetzung überspringt Antwort-/Personenhistorie und neue Buchungen. Alte CSV-/Preisabläufe bearbeiten keine neuen Buchungsfinanzwerte; die kontrollierte Planungsübernahme gehört Schritt 07. CSV-Buchungen können weiterhin als Altbestand eingelesen werden; die alte manuelle Route lehnt gebundene Zusatzformulare ab und verweist auf den neuen Ablauf.

## Datenmigration und Betrieb

Vor Upgrade SQLite konsistent sichern (WAL/SHM berücksichtigen), dann `php scripts/migrate.php` mit neuem Code ausführen. Migration `20261013220000` ergänzt vorhandene Buchungs-/Personentabellen und legt Operationen, Antwortrevisionen und Personenkorrekturen an. Keine parallele Personentabelle, keine Neuvergabe bestehender IDs und keine rückwirkenden Antworten. Bestehende Finanzwerte werden nicht verändert. Altbestände erhalten `ALT-<id>` als Referenz; nach Migration eingelesener Altbestand verwendet dieselbe deterministische Referenz im Lesemodell.

DB-FKs und Trigger schützen die Historie vor Löschen/Überschreiben. Rollback nach Eingang neuer Daten ausschließlich mit kompatiblem Schema oder konsistenter gesicherter Wiederherstellung. Keine erfundenen Korrektur-/Aufbewahrungsfristen; diese bleiben vor Mitgliederfreigabe in Schritt 10 festzulegen.

## Nachweise

Gesamtlauf: 297 PHPUnit-Tests / 1.554 Assertions auf PHP 8.4.14 ohne Fehler oder Fehlschläge; bestehende zwölf OIDC-Deprecations und Hinweis auf fehlenden Coverage-Treiber. Neue API-/Fachtests prüfen Vierpersonenbuchung, Null/false, stabile IDs, Original/Korrektur, Idempotenz, Versionskonflikt/Ergänzung, Teil-/Gesamtstorno, ungeprüfte Mengen, Zimmerzuteilung, Personenstichtag/Pflichtprüfung, Rechte/Widerruf/kein Admin-Bypass, CSV-Schutz und Abschlusssperre. Bestehender Altbestands-Migrationstest prüft erhaltene IDs/Finanzwerte, wiederholte Migration sowie die neuen lokalen Status-/Referenzwerte ohne erfundene Antworten. Reale neue Antworten werden gegen OpenAPI 1.3.0 geprüft.

PHP-/JS-Syntax, HTML-ID-Eindeutigkeit, zentrale Platzierung der Anmeldung, Dokumentationslinks und `git diff --check` geprüft. Keine Browser-/Lighthouse-Läufe, keine echten E-Mails, Termine oder Teilnahmen.

## Manuell offen

D09/M08/M09/Z01: Layout/Touch/Fokus auf echten Geräten, Formularpflichtfehler, Vierpersonen-Erfassung, Detaildialog mit erreichbaren Aktionen, Korrektur/Ergänzung, Storno und Rückkehr zur Liste. Keine Behauptung einer bereits erfolgten visuellen Abnahme. Externe Teilnahmebestätigung/Teilfehlerübertragung bleibt ausschließlich Schritt 09.
