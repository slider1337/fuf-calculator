# Schritt 09 – Teilnahmen, Abgleich und externe Konflikte

Status: **lokale technische Grundlage implementiert; produktive Abnahme offen** · Voraussetzung: Schritt 08 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Gemeinsamen Buchungsservice an geprüfte easyVerein-Teilnahmeoperationen anbinden. Familien-/Gastzulassung, Fristen, verfügbare Preisgruppen/Kapazität serverseitig prüfen. Je Person genau eine Preisgruppe, falls vorhanden; sonst Auswahl entfällt. Gäste nur nach Orga-Entscheidung. Vereinsmitglied bleibt Kontakt/Rechnungsempfänger.

Outbox/Worker verarbeitet Personen einzeln; Wiederholung nur fehlgeschlagene Person, kein erneutes Schreiben bestätigter Teilnahme. Externe Referenzen nach Timeout sicher abgleichen. Direkte externe Teilnahmen importieren/als Klärfall sichtbar machen; Kontakt/Familiengruppe nicht anhand Namens raten. Abgleich aktualisiert Status und markiert abweichende Terminfelder/Preise feldweise; kein automatisches Überschreiben. Herkunft/letzter Stand/Fehler erklären. Teilstorno und externe Änderungen durch dieselben Antwort-/Planungsregeln führen.

## Betroffene Bereiche

Teilnahme-Port/Adapter/Abgleichservice, Worker, Buchungs-/Sync-Lesemodell, Konfliktauflösung/Preis-Mapping, Anmeldungen/Team-&-Termin-UI, OpenAPI. Konfigurierbare Batches/Intervall, API-Limits/Backoff dokumentieren; Cron auf Calculator-Server.

## Abnahme

Drei von vier Personen bestätigt, eine abgelehnt: zwei Chips, Ergebnis je Person, Wiederholung nur der fehlenden. Nicht doppelt buchen bei Timeout, Restart oder zwei Workern. Externes Storno wird erkannt, Mengen ungeprüft/Planung veraltet. Direkte Teilnahme ohne Antworten sichtbar, keine erfundene Antwort. Externe Kapazitäts-/Preisänderung erzeugt Konflikt und erneute Prüfung. Abschluss schützt Finanzstand. Logs maskieren personenbezogene Antwortwerte/Token.

## Grenzen und Übergabe

Referenzen D08/D09/D12, M08/M09/M12/M14 und Z01. Vor echter Buchungsfreigabe Benachrichtigungsverhalten aller Teilnahme-/Storno-/Änderungswege dokumentieren. Bestehende Website wird weiterhin nicht geändert.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.

Aktueller Prüfstand: [PRUEFUNG-09.md](PRUEFUNG-09.md). Offene Provider-/Freigabekriterien bleiben Voraussetzung; Fakes sind kein produktiver Nachweis.
