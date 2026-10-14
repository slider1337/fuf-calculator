# Schritt 07 – Anmeldedaten kontrolliert in Planung übernehmen

Status: **technisch umgesetzt und geprüft; manuelle Designabnahme offen** · Voraussetzung: Schritt 06 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Feste kompatible Kalkulationsziele definieren und Formular-Mappings validieren. Reale bestätigte Personenzahlen/Alterskategorien und zugeteilte Zimmer berücksichtigen; bloßer Zimmerwunsch verändert keine Zuteilung. Vorschau Geplant→Angemeldet mit Budget-/Preisauswirkung auf Grundlage bestehender Berechnungsservices. Keine beliebige Zusatzantwort wird automatisch zum Preis.

Bewusstes Übernehmen schreibt Eingaben/Mapping/Quellstand/Ergebnis/Akteur als Kalkulationssnapshot. Zwischen Vorschau und Übernahme Datenrevision erneut prüfen; veränderte Buchungen ergeben Konflikt. Spätere Buchung/Korrektur/Storno kennzeichnet übernommenen Stand als veraltet. Aktuelle Reiseparameter bleiben kontrolliert gespeichert; keine automatische Übernahme und keine rückwirkende externe Rechnungskorrektur.

## Betroffene Bereiche

TripService, PriceCalculatorService, RegistrationBillingService und bestehende Planungsoberfläche; Vorschau-/Übernahme-Service, Snapshot-Persistenz und OpenAPI. Bestehende Rundung, Preislogik und Plan/Ist-Komponenten erhalten.

## Abnahme

Vorschau ohne Schreibwirkung; explizites Übernehmen erzeugt nachvollziehbaren Stand. Alte Preisberechnung bleibt unverändert bei gleichen Eingaben. Nach Buchungsänderung veraltet, konkurrierende Übernahme verhindert. Ungeprüfte Mengen nicht als sichere Eingabe; fehlende Zuordnung zeigt Fehler statt Schätzung. Abgerechnete Reise API-seitig gesperrt; bestätigte Teilnehmer werden nicht neu bepreist.

## Grenzen und Übergabe

Referenzen D14 und Einstieg M14; Planung/Abrechnung nach bestehenden Mockups 02/03. Mehrfachauswahl von Reisen führt nicht zur unbeabsichtigten Vermischung ihrer Kalkulationen.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
