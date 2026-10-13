# Prüfprotokoll 01 – Migration, Reiserechte und Abrechnungsabschluss

Stand: 13.10.2026. Umsetzung auf `feat/veranstaltungen-schritt-01-02`, Ausgangspunkt `23ffe9385d519f186b5a549a2dff697485593bc0` aus Konzept-PR #7. Aktueller `master` ist unverändert `3eb48fd0e37b0d2cb5b662aba761f05fd2360367`. Offene/kürzlich gemergte PRs geprüft; keine AGENTS.md gefunden.

## Ergebnis

Additive Phinx-Migration `20261013120000_add_event_access_and_settlement.php` ergänzt Veranstaltungen, Veranstaltungsrechte, unabhängiges Standardteam, Reiserechte, Abschluss/Snapshots, Audit und Altzuordnungsvorschau. Bestehende Tabellen bleiben erhalten. Reisen sind zunächst offen und ohne Veranstaltung. Die gemeinsame `AccessPolicy` prüft Benutzer und Freigaben bei jeder Anfrage gegen die Datenbank, auch in Application-Services. Globaler Admin ist kein fachlicher Lesebypass.

Reise-, Registrierungs-, CSV-, Berechnungs- und Ist-Ausgabenendpunkte sowie Reiselisten prüfen konkrete Fähigkeiten. Fremde Ausgaben-/Registrierungs-IDs im eigenen Reise-Pfad werden abgewiesen. Globale Benutzerverwaltung, Einladungen und das Schreiben von Einstellungen sind nur für aktuelle Datenbank-Admins erlaubt. Abrechnungsteam erhält aggregierte Finanzsicht ohne Teilnehmernamen/Zusatzantworten; Terminpflege erhält reduzierte Reisestammdaten.

Abschluss mit Grund speichert Finanz-/Berechnungssnapshots und sperrt Finanzschreibwege. Eine separate, begründete Wiederöffnung erfordert `settlement.reopen`; Vergabe, Widerruf, Abschluss und Wiederöffnung werden mit Akteur, Zeitpunkt, Ziel und Korrelations-ID protokolliert. Schreibaktionen reservieren den SQLite-Schreiber vor Prüfung von Freigabe/Abschluss. Audit enthält keine Passwörter oder Login-Token.

## Alt-Rechteübernahme und Betrieb

**Keine automatische Rechtevergabe.** Die Migration friert die Kombinationen der zum Migrationszeitpunkt vorhandenen Benutzer und Reisen als Vorschau ein. Neue Benutzer/Reisen werden nicht nachträglich aufgenommen. Ohne bestätigte Zuordnung bleiben Alt-Reisen unsichtbar, auch für Admins; die Benutzerverwaltung und der Einführungsdialog bleiben erreichbar.

1. Anwendung während Upgrade/Zuordnung für Fachschreibzugriffe sperren. SQLite mit konsistentem Backup sichern (SQLite-Backup oder gestoppte Anwendung einschließlich vorhandener WAL-Dateien). Wiederherstellung der Sicherung vorbereiten.
2. Neues Release und `composer migrate` bereitstellen. Die normale Containerinitialisierung nutzt dieselben Phinx-Migrationen. Eine Schemaänderung allein vergibt keine Rechte.
3. Als bestehender Admin unter **Benutzer → Alt-Reiserechte übernehmen** die Vorschau prüfen. Nur tatsächlich gewünschte Benutzer/Reise-Paare markieren, Grund angeben und speichern. Der UI-Weg vergibt volle Orga-Reiserechte; anschließend im Reiseteam einschränken. Es wird kein Orga-Eigentümer erraten.
4. Alternativ: `GET /api/admin/legacy-access` und `POST /api/admin/legacy-access` mit `pairs: [{tripId, userId, capabilities?}]` und `reason`. Ohne `capabilities` gilt das volle Orga-Paket; für begrenzte Rechte explizite Fähigkeiten mitsenden. Neue Nutzer können ausschließlich über die normale Reiseteamverwaltung freigegeben werden.
5. Zuordnungen prüfen, Stichprobe von Planung/Anmeldungen/Finanzständen öffnen, danach Fachzugriff wieder freigeben. Wiederholte Übernahme erzeugt keine doppelten Freigaben, wird aber erneut auditiert. Fehler in einer Auswahl rollen die gesamte Auswahl zurück.
6. Code-Rollback nicht blind mit neuen Daten kombinieren. Bei Rücknahme gesicherten konsistenten Stand wiederherstellen oder Schema-/Rechtekompatibilität gesondert prüfen.

Der konkrete produktive Benutzer/Reise-Zuordnungsplan wird beim administrativen Einführungsprozess bestätigt. Es wurden keine produktiven Rechte verändert oder Migrationen auf dem Server ausgeführt.

## Technische Nachweise

- `LegacyAccessMigrationTest`: Alt-Datenbank mit CSV/manuellen Anmeldungen, Personen, Buchungs-/Abrechnungspositionen und Ausgaben vor Migration erstellt; IDs, Quellen und bestehende Werte exakt erhalten. Zwei Migrationsläufe ohne Duplikate. Explizite Vorschau, Rechteübernahme, Wiederholung, Ausschluss neuer Benutzer und atomarer Fehlerfall bestehen.
- `EventAccessApiTest`: getrennte A/B-Reisen, fremde Datensatz-ID, Session-Widerruf, Admin ohne implizite Rechte, Finanz-/Terminrollen, sämtliche Finanzschreibsperren, Snapshots, begründete Wiederöffnung und direkter Application-Schreibversuch bestehen.
- Alle Bestandstests für Preisberechnung und easyVerein-Kontoverknüpfung bestehen. API-Vertrag auf Version 1.1.0 ergänzt; alle real registrierten API-Operationen sind dokumentiert. Neue Antwortstrukturen einschließlich geschlossener Reise/Snapshots werden rekursiv gegen die verwendeten OpenAPI-Schemas geprüft.
- Gesamtergebnis gemeinsam mit Schritt 02: 274 PHPUnit-Tests mit 993 Assertions auf PHP 8.4.14. PHP-/JavaScript-Syntax und `git diff --check` erfolgreich. Lokaler Lauf ohne Coverage-Treiber mit `--no-coverage`; keine Coverage-Prozentzahl behauptet. 12 bestehende PHP-Deprecations betreffen ausschließlich `jumbojett/openid-connect-php`; keine Fehler oder fehlgeschlagenen Tests.

## Verbleibende Abnahme

Technische Umsetzung abgeschlossen. Abschluss-/Sperrzustand im Calculator manuell nach Z01 prüfen; kein Browser-/Lighthouse-Lauf gestartet. Keine Website-Änderungen, echten Mails, Termine, Teilnahmen oder produktive Bereitstellung. Spätere Antwortrevisionen/Formulare bleiben Aufgaben 03–05. Schritt 02 ist im selben Branch implementiert; vor Einführung beide Protokolle beachten.
