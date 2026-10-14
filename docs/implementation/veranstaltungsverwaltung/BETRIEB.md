# Calculator: Einführung und Betrieb

Stand 14.10.2026. Technische Vorbereitung, kein Nachweis eines ausgeführten Deployments. Bestehende PR-Reihenfolge: #12, #13, #14. PR #14 erweitert #13. Keine automatische Website-Umstellung.

## Konfiguration ohne Geheimnisse

Bestehende GitHub-Secrets `MAILER_DSN`, `EASYVEREIN_CLIENT_SECRET`, `DEPLOY_SSH_KEY` bleiben bestehen. Zusätzlich für den Calculator:

| Name | GitHub-Typ | Standard / Bedeutung |
| --- | --- | --- |
| `EASYVEREIN_API_TOKEN` | Secret | Vorhandener berechtigter Vereins-API-Token; ausschließlich serverseitig |
| `FUF_INTEGRATION_SERVICES` | Secret | Service-JSON mit Issuer, Key-IDs/Geheimnissen, Scopes gemäß Mitgliedervertrag |
| `FUF_INTEGRATION_AUDIENCE` | Variable | Exakte Zielkennung; ohne gültige Integration keine Mitgliederfreigabe |
| `EASYVEREIN_WRITE_MODE` | Variable | `off`; später `production` nach Abnahme/Bereitstellung |
| `MAIL_DELIVERY_MODE` | Variable | `test`; später `production` für normale Application-Empfänger |
| `EASYVEREIN_REQUESTS_PER_MINUTE` | Variable | 60, 1–90; gemeinsames lokales Budget aller Calculator-Providerclients |
| `FUF_WORKER_BATCH` | Variable | 10, 1–100, je Worker / Runde |
| `FUF_WORKER_INTERVAL` | Variable | 60 Sekunden, mindestens 60 |
| `FUF_RECONCILE_ACTOR_ID` | Variable | 0: periodischer Abgleich deaktiviert; ausdrücklich gewähltes Calculator-Konto mit konkretem term.manage je erlaubter Reise |
| `FUF_RECONCILE_INTERVAL` | Variable | 300 Sekunden je Reise, mindestens 60; auf Gesamtzahl der Reisen/API-Budget abstimmen |

Die Deployment-Umgebung wird ohne Shell-Auswertung und ohne Ausgabe der Werte geschrieben. Compose reicht dieselbe Konfiguration an App/Worker weiter. Nie Geheimnisse ins Repository oder in Browsercode kopieren. Ohne bewusste Konfiguration sind Provider-Schreiben und regulärer Application-Mailversand nicht aktiviert.

## Sicherung und Migration

1. App und Worker stoppen, damit neue fachliche Schreibvorgänge nicht zwischen Sicherung und Migration entstehen.
2. `php scripts/backup.php`: SQLite `VACUUM INTO` erstellt einen konsistenten Snapshot einschließlich committed WAL-Inhalt; `integrity_check` prüft das Ergebnis. Sicherung liegt geschützt neben der Datenbank, mit Zeitstempel/eindeutiger Endung. Keine automatische Löschung.
3. `php scripts/migrate.php` aus dem neuen Image. Neue additive Migration 20261014160000 ergänzt Terminänderungsaufträge und Abgleichzeitpunkte; vorherige Migrationen bleiben erforderlich.
4. Erst nach erfolgreicher Migration App/Worker starten. Bei fehlgeschlagener Sicherung/Migration beendet `set -e` das Deployment; Anwendung bleibt gestoppt, Betreiber prüft Fehler und stellt die gesicherte kompatible Kombination her. Neue Buchungsdaten nicht durch Zurückkopieren eines alten Backups vernichten.

Der vorhandene Deployment-Workflow führt diese Reihenfolge beim später autorisierten Merge/Deployment aus. `worker` ist ein eigener Compose-Service ohne veröffentlichte Ports. `migrate` muss erfolgreich beendet sein. Sicherungen sind lokal im Datenvolume; externe Sicherung/Retention nach Vereinsbetrieb ergänzen, keine neue automatische Löschregel.

## Verarbeitung und Abgleich

`php scripts/workers.php` verarbeitet begrenzte Termin-, Terminänderungs- und Teilnahmebatches, danach den optionalen periodischen Abgleich. `--once` führt genau eine Runde aus. Produktions-Compose startet den Prozess mit Neustartregel; keine zusätzliche Cron-Schleife daneben betreiben. Bestehende CLI-Einzelaufrufe bleiben möglich:

- `php scripts/term-worker.php 10`: Terminanlage und Terminänderungen.
- `php scripts/participation-worker.php 10`: Teilnahmeaufträge.

Der periodische Abgleich verwendet ausschließlich die Rechte des ausdrücklich konfigurierten Calculator-Kontos, auch ohne Websession. Keine administrative Umgehung. Widerruf wirkt beim nächsten Poll; archivierte Reisen sind ausgeschlossen. Abgerechnete Reisen werden lesend geprüft, externe Finanz-/Statusänderungen als Konflikte angezeigt. Zeitreservierung vor GET verhindert parallele Poll-Duplikate; Fehlversuche warten bis zum nächsten Intervall. Kein Provideraufruf in einer SQLite-Fachtransaktion.

Gemeinsames Budget und Provider-429 gelten zusätzlich. Logs enthalten nur Zeit, Anzahl und konstante Fehlercodes. Unbekannte Termin-/Teilnahmeergebnisse werden lesend abgeglichen; kein blinder zweiter POST/PATCH. Bei unklarem Terminänderungsauftrag bleibt die Reise für weitere Terminänderungsaufträge gesperrt, bis der gewählte externe Stand bewiesen ist. Ein sicherer Konflikt vor PATCH erlaubt eine frische Vorschau und einen neuen bewussten Auftrag.

## Fachliche Aktivierung

Im Calculator unter „Team & Termin“: bestätigte Termin-ID, Formularstand/Preis-/Kapazitätszuordnung prüfen, Mitgliederanmeldung einstellen und gemeinsamen Anmeldeschluss speichern. Der frühere aktuelle Provider-Anmeldeschluss begrenzt diesen Zeitpunkt. Für eine neue Buchung müssen Mitgliederbeziehungen/aktuelle Preise/Kapazität frisch geprüft sein. Providerkapazität 0 bleibt mangels belegter Unlimited-Semantik unbestätigt; positive echte Kapazität setzen oder gesondert klären.

Datumsänderungen können normale Providerbenachrichtigungen und Teilnehmerstatusänderungen auslösen; die Vorschau weist darauf hin. Während Entwicklung keine echten Provider-Testschreibvorgänge: deren Empfänger lassen sich nicht auf me@benna.me begrenzen. Application-Testmails gehen ausschließlich an me@benna.me. Normale produktive Benachrichtigungen sind ausdrücklich für später vorgesehen.

Website-Schritt 11 beginnt nach dokumentierter Calculator-Design-/Providerabnahme und kompatibler Bereitstellung. Die bisherige Website nutzt bis dahin ihren bestehenden Anmeldeweg. Kein automatischer Rückfall oder doppelter Writer.


## Wahl des Anmeldeverfahrens (14.10.)

Migration 20261014210000 nach den bisherigen Migrationen. Neue normale Reisen starten Clubdesk/CSV; easyVerein ist eine bewusste Option je Reise. Bestehende ausdrücklich vorbereitete neue Abläufe bleiben erhalten. CSV-Reisen werden vom Providerworker/periodischen Abgleich nicht verarbeitet und sind im Mitgliedervertrag nicht verfügbar. Ein zulässiger Rückwechsel deaktiviert die Mitgliederfreigabe. [Vollständige Regeln und Nachweise](../../fixes/2026-10-14-reise-anmeldeverfahren.md).
