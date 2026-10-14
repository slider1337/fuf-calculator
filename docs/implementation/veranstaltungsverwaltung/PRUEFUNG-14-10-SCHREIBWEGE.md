# Folgeprüfung 14.10. – Mailregel, Schreibadapter, Gäste und Konfliktklärung

## Mailregel

Nutzerfreigabe vom 14.10., 13:32: Tatsächliche Mails während der Arbeiten/Tests ausschließlich an **me@benna.me**. Nach Fertigstellung sind normale produktive Benachrichtigungen an Mitglieder gewünscht. Diese Regel steht dauerhaft in AGENTS.md; das pauschale frühere Versandverbot gilt nicht mehr.

Application-Mailer: standardmäßig TestRecipientMailer, der exakt einen Empfänger verwendet. `MAIL_DELIVERY_MODE=production` aktiviert später normale Empfänger. Null-DSN sendet ohnehin nichts. Externe Provider-Mails lassen sich nicht zuverlässig durch unseren Mailer umleiten; reale Provider-Schreibtests werden deshalb nicht ausgeführt. Alle Adaptertests sind fiktiv und schicken keine Mails.

## Implementiert

- Echte easyVerein-POST/PATCH-Adapter für Terminanlage mit Kalender, lokalen Eckdaten, Preisgruppen und anschließendem Öffnen; Teilnahme mit genau einer Preisgruppe bzw. ohne Auswahl bei fehlenden Gruppen; Storno über den bestehenden Status. Keine zusätzlichen Mail-/Einladungs-/Rechnungsaktionen und keine globalen Provider-Einstellungen ändern.
- `EASYVEREIN_WRITE_MODE=production` ist die explizite spätere Produktivfreigabe. Standard `off` bedeutet keine externen Schreibaufrufe. Credentials bleiben ausschließlich serverseitig; ohne Token keine Freigabe. Eine Session oder ein Payload kann diese Betriebsart nicht ändern.
- Marker in interner Terminnote, Preisgruppenbeschreibung und Teilnahmebeschreibung korrelieren dauerhafte Aufträge. Ergebnisse werden anschließend lesend mit Termin-/Personen-/Status-/Preisreferenzen bewiesen. Fehlendes Receipt ist kein falscher Erfolg. Nach Timeout nur Abgleich; unvollständige Preisgruppen bleiben sichtbar in Klärung, kein zweiter Event-/Gruppen-POST. Zugriff auf andere Ressourcen/Notification-Endpunkte ist nicht im HTTP-Write-Client möglich.
- Vollständiger paginierter Abgleich mit festen Ressourcen, geprüftem Provider-Origin, erhaltenen Originalfiltern und Zyklus-/Größenlimits. Kontakt→Mitglied nur über explizite Providerreferenzen; Nichtmitglieder/unbekannte Kontakte bleiben Klärfälle. Zustände „teilgenommen/nicht teilgenommen“ bleiben historische bestätigte Buchung, werden nicht als Storno erfunden.
- Gemeinsames lokales Request-Budget für read/write/Directory: standardmäßig 60/min je gehashtem Token, konfigurierbar 1–90. Budget wird vor Netzwerkcall unter kurzer DB-Reservierung geprüft; keine Netzwerkcalls in einer fachlichen DB-Transaktion. Provider-429 bleibt zusätzlich maßgeblich; andere Systeme müssen ihr Budget berücksichtigen.
- Gäste: Orga muss Name/Geburt, anmeldendes Mitglied und **konkreten aktuellen Gastpreis** begründet freigeben. Provider muss Begleitpersonen erlauben. Freigabe ist idempotent, auf Eigentümer/Reise begrenzt und nur einmal in eine Buchung übernehmbar; Browser kann weder Namen noch Personenbezug erfinden. Kontakt/Elternteilnahme bleibt das anmeldende Vereinsmitglied. Gast kann allein ergänzt werden, wenn dessen eigene Teilnahme bereits bestätigt ist. Vor dem externen Schreiben wird Elternteilnahme/Gastfreigabe erneut geprüft. Aktive Begleitpersonen sind vor ihrem Elternteil zu stornieren; Gruppenstorno ordnet Gäste zuerst.
- Orga-UI für Gastfreigabe/Widerruf und konkrete externe Zuordnung. Direkte Teilnahme wird bewusst an vorhandene lokale Person gebunden, niemals nach Namen/Familienvermutung. Unbekannte/mehrdeutige Identität, Gast-/Mitgliedsmismatch oder laufende Worker blockieren die Zuordnung.
- Externe Personen-/Preisabweichung bewusst bestätigen mit Revision/Grund; ursprünglicher gebuchter Preis bleibt erhalten, externer beobachteter Preis separat. Akzeptanzhistorie append-only, aktuelle Beobachtung mit Quellhash. Externes Storno/Statusänderung nutzt dieselben Revisionen/ungeprüften Mengen. Abschlusssperre/Archiv gelten auch für diese Aktionen. Mehrfach-/ungültige Preiszuordnung bleibt Klärfall statt falschem Umbepreisen.
- OpenAPI 1.6.0, Mitgliederkontext mit freigegebenen Gästen, eigener Gast-Choice, tatsächliche neue Orga-Endpunkte und Status-/Fehlerregeln.

## Konfiguration und Einführung

Nach Abnahme/Bereitstellung auf dem Calculator-Server:

| Variable | Bedeutung |
| --- | --- |
| EASYVEREIN_API_TOKEN | Bestehender korrekt berechtigter Vereins-API-Token, nie im Browser/Git |
| EASYVEREIN_WRITE_MODE | `off` während Entwicklung/Tests; `production` erst bei produktiver Freigabe |
| EASYVEREIN_REQUESTS_PER_MINUTE | Standard 60; Teilbudget mit Website/anderen Clients abstimmen |
| MAILER_DSN / MAIL_FROM | Vorhandene Application-Mailkonfiguration |
| MAIL_DELIVERY_MODE | Standard Testempfänger me@benna.me; `production` normale Empfänger erst im fertigen Betrieb |
| FUF_INTEGRATION_AUDIENCE / FUF_INTEGRATION_SERVICES | Getrennte serverseitige Website-Delegation aus Mitgliedervertrag |

Vor Upgrade konsistente SQLite-Sicherung; neue Migration **20261014140000** nach 20261014110000. Sie ergänzt Gastfreigaben, externe Preis-Metadaten, unveränderliche Beobachtungshistorie und Request-Budget; bestehende Buchungen/Finanzwerte unverändert. Standardbetrieb bleibt gesperrt. Bereits alte `blocked`-Aufträge werden beim Umstellen nicht blind gestartet: aktuelle Daten/Preise prüfen und ausdrücklich neu beauftragen; unbekannte Vorgänge weiterhin ausschließlich abgleichen/zuordnen.

Server-Cron: `php scripts/term-worker.php 10`, `php scripts/participation-worker.php 10`, kein GitHub-Sync. Begrenzt auf höchstens einmal/min beginnen; Request-Budget kann einen Batch früher stoppen. Providerseitige 0-Kapazität ist im öffentlichen Feldschema nicht ausdrücklich als unbegrenzt definiert: diese bleibt **unbestätigt/null**, Buchung bis zur Klärung blockiert; keine erfundene Million freier Plätze. Positive Kapazität wird aus vollständig bestätigten Teilnahmen berechnet und erneut vor dem Schreiben geprüft.

## Technische Prüfung

Tests verwenden ausschließlich fiktive HTTP-Clients und Mailer. Echte Routerabläufe: Familienbuchung/Teilstorno/Ergänzung; Gast erst nach Orga-Freigabe, nur eigener Gast buchbar; direkte externe Teilnahme bewusst zuordnen; Preisabweichung getrennt bestätigen ohne Rückänderung des ursprünglichen Preises. Adaptertests belegen verlorene Event-/Preisgruppen-/Teilnahmeantwort ohne weiteren POST, vorhandene Parent-Referenz für Gast, Storno, Produktionssperre, gefährliche Pagination abweisen und Testempfänger. Shared-Budget und Netzwerk-in-Transaktion ebenfalls geprüft.

Manuelle Design-/Providerabnahme und kompatible Bereitstellung bleiben offen. Keine echten Termine/Teilnahmen/Provider-Mails erzeugt, kein Merge/Deployment und keine Browser-/Lighthouse-Automation. Website-Schritt 11 bleibt bis zu dieser Abnahme/Bereitstellung am vereinbarten Gate; die pauschale Mailsperre ist kein Implementierungsblocker mehr. Feldweise externe Übertragung von lokalen Terminänderungen ist noch nicht implementiert; die vorhandene Konfliktaktion bestätigt die externe Basis lokal und ist kein behaupteter Provider-PATCH.

Abschließender Gesamtlauf: **352 Tests / 5.094 Assertions**, keine Fehler/Fehlschläge auf PHP 8.4.14. Zusätzlich betroffene Adaptertests nach der Maskierung fremder Provider-Freitexte bestanden. 12 bestehende OIDC-Deprecations; kein Coverage-Treiber. PHP-Syntax für 187 Dateien, alle Anwendungsskripte, strenges OpenAPI 1.6.0/Referenzen, lokale Dokumentationslinks, 360 eindeutige statische HTML-IDs und git diff --check geprüft.


Aktuelle Fortsetzung: [Abschlussprüfung](PRUEFUNG-ABSCHLUSS.md) mit feldweiser Terminbearbeitung, Mitgliederfreigabe-Oberfläche und vorbereiteten Serverworkern/Sicherung. Historische fehlende Implementierungen dieses Protokolls wurden inzwischen ergänzt; manuelle/echte Abnahme und tatsächliche Bereitstellung bleiben offen.
