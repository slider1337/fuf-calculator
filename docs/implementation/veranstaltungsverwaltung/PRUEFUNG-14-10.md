# Folgeprüfung 14.10.2026 – API-Abruf, gemeinsamer Anmeldeschluss, Reise-Archiv

Weiterführung von PR #14. Nutzerentscheidungen: Änderungen/Storno bis Anmeldeschluss; Archivierung genügt, keine zusätzliche automatische Löschung.

## Änderungen

- Öffentliche easyVerein-HTML-Doku direkt gelesen und eingebettetes OpenAPI vollständig ausgelesen. Kein Login/Export nötig; frühere Annahme korrigiert. Funktionaler Vertragsauszug und [Providerprüfung](../../integration/easyverein-v3-review.md) mit genauer Abgrenzung fehlender Schreib-/Benachrichtigungsdetails.
- Echte lesende Mitgliederintegration: strenger HTTPS-GET-Client und Directory-Adapter für eigene Mitglieds-ID, Kontakt, Geburtsdatum und ausdrücklich bearbeitbare Familienkonten. Freigegebene Kinder werden erneut einzeln gelesen; keine geratenen Identitäten. Fester API-Origin/Allowlist, keine Redirects/Schreibmethoden, keine Tokens/Responsewerte in Fehlern. Intern benötigte Kontaktreferenz bleibt aus der Mitglieder-Kontextantwort heraus. Serverseitige Konfiguration `EASYVEREIN_API_TOKEN`; hier keine Credentials gelesen oder übertragen.
- Nur ein `bookingUntil` für Anlage, Korrektur und Storno; aktuelle frühere Providerfrist begrenzt ebenfalls. Konfigurationsweg verlangt keine Aufbewahrungsfrist. Bestehende getrennte Spalten werden beim Upgrade auf denselben Anmeldeschluss vereinheitlicht; `retention_until` entfernt. Kein automatischer Zugriffsentzug aufgrund einer Aufbewahrungsfrist, keine automatische Löschung.
- Archivieren/wiederherstellen einer Reise mit `trip.manage`, vorherigem Archivstand, Grund und Audit. Aktive Liste und Archivfilter getrennt. Historische berechtigte Lesezugriffe, eigene Mitgliederbuchungen, Buchungen/Antworten/Personen-IDs und Finanzwerte bleiben erhalten. Archivierte Reisen sperren Änderungen und externe Workeraktionen; ein unklarer externer Auftrag bleibt gegen eine zweite Anlage reserviert. Wiederherstellung entfernt keinen Abrechnungsabschluss.
- Oberfläche: Archivfilter, Archivstatus und begründete Archiv-/Wiederherstellungsaktion in Reiseübersicht; Bearbeitungsaktionen für archivierte Reisen gesperrt. OpenAPI 1.5.1 dokumentiert Archivroute und vereinfachte Fristen.

## Migration und Betrieb

SQLite konsistent sichern; zusätzliche Migration `20261014110000` nach 20261014020000 ausführen. Additive Archivspalte; obsolete Aufbewahrungsspalte wird entfernt, getrennte Fristen auf bestehenden Anmeldeschluss gesetzt. Migration erfindet keine Deadline und löscht keine Buchungs-/Antwort-/Finanzdaten. Rollback nur durch konsistente Sicherung; Rücknahme der neuen Archivfunktion darf erhaltene Historie nicht löschen.

## Prüfung

**335 PHPUnit-Tests / 4.643 Assertions**, keine Fehler/Fehlschläge auf PHP 8.4.14. Acht zusätzliche Tests für Archiv/Rechte/Fristen und Directory-Profil/Revocation/Kontaktmehrdeutigkeit/URL-Referenzen. Bestehende 12 OIDC-Deprecations; kein Coverage-Treiber. Direkte Routertests zeigen Erhalt von Buchung/Personen/Abrechnung und Archivfilter/Wiederherstellung. Die echten HTTP-Zugriffe werden in Tests durch fiktive Antworten ersetzt. Kein echter Mitgliederabruf und keine realen Termin-/Teilnahmeaktionen oder Mails.

PHP-/JavaScript-Syntax, Dokumentationslinks, eindeutige statische HTML-IDs, strenges OpenAPI-YAML/Referenzen und git diff --check vor Upload geprüft. Keine Browser-/Lighthouse-Läufe; manuelle Layoutabnahme offen.

## Verbleibende Grenze

Termin-/Teilnahme-Schreibadapter sind weiter gesperrt; die jetzt vollständig öffentliche Doku ersetzt keinen Nachweis, dass alle automatischen E-Mails bei den konkreten Schreibwegen sicher unterbleiben. Fehlende Bodies/Antwortschemas der Preisgruppen-Teilnahmeaktion sind konkret dokumentiert. Gastfreigaben, vollständige Personen-/Preiskonfliktklärung und manuelle Integrations-/Designabnahme bleiben offen. **Löschplanung und ein Export der öffentlichen Doku sind keine offenen Voraussetzungen mehr.** Kein Merge/Deployment, kein Websiteumbau.
