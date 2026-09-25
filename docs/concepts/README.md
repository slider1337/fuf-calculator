# FUF-Kalkulator: Zugang, Reisegruppen und Belege

**Konzeptstand:** 25.09.2026 · ausschließlich Dokumentation, keine Implementierung.

Die Konzepte für Kalkulator und Website bauen aufeinander auf:

1. [SSO und Benutzerprovisionierung](01-sso-benutzerprovisionierung.md) – Anbieterbewertung, Kontoverknüpfung und Einladung.
2. [Berechtigungen und Gruppen](02-berechtigungen-gruppen.md) – pro Reise erteilte Rechte und serverseitige Prüfung.
3. [Belege hochladen und verbuchen](03-belege-upload-verbuchung.md) – Upload, automatische interne Zuordnung und Klärfälle.
4. [Reisegruppen mit Standard-Orga-Team](04-reisegruppen-orga-defaults.md) – jährlich wiederkehrende Events und lokale Anpassung.
5. [Abrechnungsteam mit Lesezugriff](05-abrechnungsteam-lesezugriff.md) – gesonderte Finanzsicht je Reise.
6. [MFA und WebAuthn/Passkeys](06-mfa-webauthn.md) – zusätzlicher Schutz lokaler Konten und Kontowiederherstellung.
7. [Veranstaltungen aus easyVerein und ClubDesk-Inhalte](07-veranstaltungen-easyverein-clubdesk.md) – öffentliche WordPress-Anzeige und kontrollierter Import der Zusatzinfos.
8. [Familienanmeldung in WordPress](08-familienanmeldung-wordpress.md) – eigenes Plugin mit Gästen und Preisgruppe je Person.

## Empfohlene Reihenfolge

**Für die neue Veranstaltungsfunktion:** (1) easyVerein-OIDC für WordPress gemäß Konzept 1 ergänzen und die nötigen API-Rechte prüfen; (2) öffentliche Anzeige der easyVerein-Termine gemäß Konzept 7 aufbauen; (3) ClubDesk-Zusatzinfos zuordnen und importieren; (4) eigenes Anmeldeplugin gemäß Konzept 8 entwickeln. Anzeige und ClubDesk-Import können schon vor Fertigstellung des Logins stattfinden; die eigene Anmeldung wird erst mit funktionsfähigem Login und bestätigtem easyVerein-Schreibweg freigeschaltet. Der SSO-Schritt hat Vorrang vor dem Anmeldeplugin.

**Für den bestehenden Kalkulator:**

1. Berechtigungsmodell und Zugriffsprüfungen für **alle** heutigen Endpunkte festlegen. Heute prüft die Middleware im Kern nur eine vorhandene Sitzung; die globale Rolle `user` begrenzt Reisen nicht.
2. Reisegruppen und Teamvorlagen anlegen; bestehende Reisen zuordnen, ohne historische Rechte ungewollt zu ändern.
3. SSO optional je Anbieter ergänzen. easyVerein und Google sind dokumentierte Login-Wege; Berechtigungen bleiben lokal.
4. Belege und Unterscheidung zwischen geplanter Kostenposition und zusätzlicher Ausgabe modellieren, damit die Abrechnung nicht doppelt summiert. Den neuen Reisestatus **abgerechnet** und die Sperre für Änderungen an Belegen/Ausgaben in diesem Schritt mit umsetzen.
5. Lesende Abrechnungsteam-Ansicht und Download-/Exportrechte auf derselben Reiseberechtigung aufbauen.
6. Passkeys/WebAuthn und einen Wiederherstellungsweg für lokale Konten ergänzen; bei externem SSO die Provider-Anmeldung getrennt bewerten.

## Festgelegte Produktentscheidungen

- **Verbuchung:** ausschließlich intern in der FUF-Reiseabrechnung, keine externe Vereinsbuchhaltung.
- **Orga:** sieht Abrechnungsdaten automatisch und darf Belege freigeben. Nur für die konkrete Reise zugewiesene Orga-Mitglieder dürfen freigeben. Das gesonderte Abrechnungsteam bleibt rein lesend.
- **Einreichende:** dürfen eigene Belege korrigieren und löschen, auch nach automatischer Buchung, solange die Reise noch nicht **abgerechnet** ist. Danach sind diese Änderungen gesperrt. Änderungen bleiben im Audit nachvollziehbar.
- **Reisestatus:** `abgerechnet` ist ein neues, noch zu implementierendes Status-Feature; es sperrt auch andere abrechnungswirksame Änderungen, damit der Abschluss belastbar ist.
- **Anbieter:** derzeit ClubDesk, Wechsel zu easyVerein geplant; Google als verbreitete zusätzliche Anmeldung. Proton bleibt allenfalls Mailadresse für Einladungen. Die konkreten easyVerein-Tarif-/API-Rechte sind noch zu prüfen.
- **Sicherheit:** separates Konzept für MFA und WebAuthn/Passkeys.
- **Veranstaltungen:** easyVerein liefert strukturierte Termine und Preisgruppen; ClubDesk liefert zusätzliche Veranstaltungsinfos für die Migration. Die öffentliche Anzeige ist ohne Login nutzbar.
- **Anmeldung:** eigenes WordPress-Plugin nach easyVerein-SSO; eine Kontaktperson meldet Familie und Gäste gemeinsam an und wählt die Preisgruppe je Person. Die konkrete easyVerein-API-Schreibfähigkeit ist noch zu bestätigen.

## Repository-Basis

Untersucht: `master` vom 22.09.2026, PHP-8.4-/Slim-/SQLite-Anwendung; insbesondere `config/routes.php`, `src/Application/AuthService.php`, `src/Application/ActualExpenseService.php`, `src/Infrastructure/Http/Auth/AuthMiddleware.php`, `src/Domain/Trip/Trip.php` und die Benutzer-/Reise-Migrationen. Die Konzepte enthalten kleine Markdown-Mockups und Quellenlinks. Es wurde kein Anwendungscode verändert.
