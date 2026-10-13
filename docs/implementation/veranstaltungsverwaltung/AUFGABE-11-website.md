# Schritt 11 – Website an den fertiggestellten Calculator anbinden

Status: **offen** · Voraussetzung: Schritt 10 vollständig abgenommen und kompatibler Calculator bereitgestellt.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Erst jetzt im Repository slider1337/fuf-website vorhandene AGENTS.md, Login-/Termin-/Anmeldeverträge und aktuellen Stand prüfen. Bestehende Mitgliederanmeldung erhalten. Reise nur über stabile easyVerein-Termin-ID zuordnen. WordPress-Server authentifiziert sich zum Calculator und übermittelt geprüfte Identität; Browser hat keine Servicegeheimnisse. Formulardefinition samt unveränderlicher Version zentral abrufen, keine zweite Formularverwaltung.

Ablauf Personen→Zusatzangaben→Zusammenfassung→Verbindlich anmelden→Ergebnis. Gemeinsame Felder einmal, persönliche je Person; Ohne Preisgruppen keine Auswahl. Pflicht/Sichtbarkeit/Bestätigungsumfang nach Backendvertrag. Mein Bereich zeigt eigene Buchungen, Fristen, Korrektur/Storno/Ergänzung. Versionswechsel bewahrt kompatible Eingaben und fragt neue Pflichtangaben nach. Teilfehler/speichern-ausstehend verständlich anzeigen, Doppelsenden mit stabiler Idempotenz verhindern. Tatsächlicher Backendstatus bestimmt Erfolg.

## Betroffene Bereiche

Bestehende FuF-WordPress-Plugins für Login/Termin/Anmeldung zunächst identifizieren und gezielt erweitern; Calculator-Vertrag aus 10 verwenden. Website-Tokens Holz & Lagerfeuer: Zilla Slab/Figtree, Creme/Holz/Orange/Grün. Konfiguration/API-Aufrufe serverseitig, sensible Daten nicht öffentlich cachen.

## Abnahme

Familienbuchung mit vier Personen ist sofort im Calculator lesbar/auswertbar; keine Orga-Rechte für Mitglieder. Pflichtfehler/Versionskonflikt/Teilfehler sichtbar; ungefragte alte Antworten bleiben historisch. Mein Bereich nur eigene zulässige Buchungen. API-Ausfall bewahrt Eingaben und Referenz, kein automatischer alter Schreibweg. Ohne verknüpften/bestätigten Termin Anmeldung gesperrt. Keine globalen Foto-/Allergiefelder, reale Preisgruppen statt Mockuppreise.

## Grenzen und Übergabe

Referenzen W01/W02/W03. Schrittweise Umstellung je Reise; keine Doppelregistrierung durch gleichzeitig aktive alte/neue Abläufe. Kalkulator-Buchungen bei UI-Rücknahme erhalten. Browser-/Lighthouse-Prüfungen nicht automatisch starten; manuelle Designabnahme als Checkliste mitliefern. Keine Testmails.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
