# Schritt 10 – Mitglieder-API und Calculator-Abnahme vor Websitebau

Status: **lokale technische Grundlage implementiert; produktive Abnahme offen** · Voraussetzung: Schritte 01–09 abgenommen; reale Integration nur soweit ihr Vertrag geklärt ist.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Separate serverseitige Website-Authentifizierung und geprüfte Mitgliederdelegation implementieren. Bestehenden Website-Loginvertrag read-only prüfen, sichere Zuordnung zur easyVerein-Mitglieds-ID belegen; Subject/E-Mail allein nicht als Mitgliedsrecht verwenden. Kurzlebiger Kontext mit Ziel/Aussteller/Ablauf/Replay-Schutz, begrenzte Service-Scopes und Rotation. Keine unbegrenzte Service-Antwortliste. Integration läuft unabhängig von Calculator-Verwaltungssession.

Vollständigen Vertrag mit Testclient umsetzen: verfügbare Reise/Formularversion, berechtigte Personen/Preisgruppen, Vorschau/Validierung, verbindliche Buchung, eigene Buchung/Korrektur/Storno/Ergänzung und Ergebnis je Person. Neue Pflichtfragen nach Versionskonflikt ausdrücklich erneut bestätigen. Gemeinsamen Anmeldeschluss, Reise-Archivierung, Preisabweichung, Fehler-/Auftragsantworten, Limits und Featurefreigabe dokumentieren. Alle Prüffälle aus ABNAHME.md durchführen; sichere Übergabe an Website vorbereiten.

## Betroffene Bereiche

Getrennte Integrationsmiddleware/-Identität/-Controller, gemeinsame Services aus 03–09, OpenAPI/Contract-Tests, Betriebs-/Konfigurationsdokumentation. Schema/DTOs und Testfixtures dürfen ins Website-Handover; noch keine produktive Websiteänderung.

## Abnahme

Testclient ohne Calculator-Account durchläuft Buchung→Korrektur→Teilstorno→Ergänzung mit Ergebnis in Calculator. Gefälschte/fremde Person, abgelaufener/falscher Aussteller/Ziel/Replay und fremde Buchung abgewiesen. Service ohne Mitgliedskontext kann nicht für beliebige Mitglieder buchen. 409-Version/Preis/Idempotenz und 202-Auftrag nachweisbar; 422-Feldfehler haben stabile Referenzen. Alle fachlichen Abnahmefälle und manuellen Design-Checklisten dokumentiert.

## Grenzen und Übergabe

Freigabekriterium: Calculator-Funktionen und versionierter Vertrag stehen unabhängig von WordPress bereit; bekannte Sperren konkret dokumentiert. Nicht erfüllte Pflichtkriterien blockieren Schritt 11. Bestehende Website kann zu Testzwecken gelesen, nicht geändert werden. Offene echte API-/Versandfragen nicht als bestanden markieren.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.

Aktueller Prüfstand: [PRUEFUNG-10.md](PRUEFUNG-10.md). Offene Provider-/Freigabekriterien bleiben Voraussetzung; Fakes sind kein produktiver Nachweis.
