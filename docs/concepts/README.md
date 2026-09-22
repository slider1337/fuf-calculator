# FUF-Kalkulator: Zugang, Reisegruppen und Belege

**Konzeptstand:** 22.09.2026 · ausschließlich Dokumentation, keine Implementierung.

Die fünf unabhängigen Konzepte bauen aufeinander auf:

1. [SSO und Benutzerprovisionierung](01-sso-benutzerprovisionierung.md) – Anbieterbewertung, Kontoverknüpfung und Einladung.
2. [Berechtigungen und Gruppen](02-berechtigungen-gruppen.md) – pro Reise erteilte Rechte und serverseitige Prüfung.
3. [Belege hochladen und verbuchen](03-belege-upload-verbuchung.md) – Upload, automatische interne Zuordnung und Klärfälle.
4. [Reisegruppen mit Standard-Orga-Team](04-reisegruppen-orga-defaults.md) – jährlich wiederkehrende Events und lokale Anpassung.
5. [Abrechnungsteam mit Lesezugriff](05-abrechnungsteam-lesezugriff.md) – gesonderte Finanzsicht je Reise.

## Empfohlene Reihenfolge

1. Berechtigungsmodell und Zugriffsprüfungen für **alle** heutigen Endpunkte festlegen. Heute prüft die Middleware im Kern nur eine vorhandene Sitzung; die globale Rolle `user` begrenzt Reisen nicht.
2. Reisegruppen und Teamvorlagen anlegen; bestehende Reisen zuordnen, ohne historische Rechte ungewollt zu ändern.
3. SSO optional je Anbieter ergänzen. easyVerein und Google sind dokumentierte Login-Wege; Berechtigungen bleiben lokal.
4. Belege und Unterscheidung zwischen geplanter Kostenposition und zusätzlicher Ausgabe modellieren, damit die Abrechnung nicht doppelt summiert.
5. Lesende Abrechnungsteam-Ansicht und Download-/Exportrechte auf derselben Reiseberechtigung aufbauen.

## Gemeinsame Produktentscheidungen

- Was bedeutet „verbuchen“: nur in der FUF-Reiseabrechnung oder auch Übergabe an eine externe Vereinsbuchhaltung? Diese Konzepte behandeln zunächst die **interne** Abrechnung.
- Dürfen Orga-Mitglieder automatisch Abrechnungsdaten sehen/Belege freigeben? Empfohlen ist ein ausdrückliches zusätzliches Recht.
- Dürfen hochladende Personen ihre eigenen Belege nach der Buchung korrigieren? Empfohlen ist ein nachvollziehbarer Korrekturprozess.
- Welche Anbieter sind im Verein tatsächlich im Einsatz, welche Tarife/API-Rechte sind freigeschaltet?

## Repository-Basis

Untersucht: `master` vom 22.09.2026, PHP-8.4-/Slim-/SQLite-Anwendung; insbesondere `config/routes.php`, `src/Application/AuthService.php`, `src/Application/ActualExpenseService.php`, `src/Infrastructure/Http/Auth/AuthMiddleware.php`, `src/Domain/Trip/Trip.php` und die Benutzer-/Reise-Migrationen. Die Konzepte enthalten kleine Markdown-Mockups und Quellenlinks. Es wurde kein Anwendungscode verändert.
