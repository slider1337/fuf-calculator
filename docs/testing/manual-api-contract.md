# Manuelle API-Vertragsprüfung

Dieser Test führt echte HTTP-Aufrufe aus. Er läuft **nur auf ausdrückliche Aufforderung des Nutzers**, nicht bei Push, PR, Deployment, Cron oder `composer test`. Agenten dürfen ihn auf diese Aufforderung hin ohne erneute Freigabefrage starten. Am Ende jeder Implementierungsphase schlagen sie einen Lauf vor und benennen den tatsächlich geprüften Umfang.

## Geprüfter Umfang

| System | Prüfung |
| --- | --- |
| Calculator | Unsignierte Anfrage wird mit 401 abgewiesen; frische FuF-HMAC-Delegation liefert die Vertragsversion und `legacy` für den neuen, nicht zugeordneten Testtermin; Wiederverwendung derselben Delegation wird mit 401 abgewiesen; Terminauflösung ohne Reise liefert 404. |
| easyVerein | Kalender und Testmitglied lesend prüfen; privaten, im Mitgliederbereich ausgeblendeten Testtermin erstellen; HTML-Beschreibung ändern und zurücklesen. |
| easyVerein | Preisgruppe erstellen, Namen ändern und zurücklesen; Teilnahme eines Testmitglieds und eines synthetischen Gasts mit Preisgruppe erstellen; Status, Preisgruppenzuordnung und Gast-Elternbezug zurücklesen. |
| easyVerein | Gast und danach Mitglied mit `state: 0` abmelden; Status jeweils zurücklesen. |
| Bereinigung | Eigene Teilnahmen (Gäste zuerst), Preisgruppen und Termin löschen; nach jeder Löschung 404 prüfen. |

Der Test belegt ausgewählte echte API-Verträge. Er belegt **nicht** den gesamten Website-Anmeldeweg, Calculator-Buchungsschreibwege, Worker, lokale Buchungspreise, Formularvalidierung oder die Bedienoberfläche. Insbesondere ersetzt ein erfolgreicher direkter Provideraufruf keinen Regressionstest der Calculator-Gastübertragung. Im Calculator werden keine Reisen/Buchungen angelegt oder geändert. Kurzlebige Replay-Schutzeinträge werden regulär vom Calculator verwaltet.

## Voraussetzungen

- Python 3.10 oder neuer, nur Standardbibliothek; HTTPS-Zugriff auf beide Systeme.
- Eigener Testkalender und ausdrücklich dafür vorgesehenes Testmitglied, dessen verknüpfter Kontakt ausschließlich `me@benna.me` als Testadresse nutzt. Der Runner prüft `contact-details.email` vor dem ersten Schreibaufruf und bricht bei Abweichung ab. Kalender-/Providerbenachrichtigungen müssen vor dem Lauf so eingerichtet sein, dass Testmails ausschließlich an diese Adresse gehen. Der Runner kann zusätzliche Providerempfänger nicht erkennen und verändert keine globalen Mailregeln.
- Tokenrechte zum Lesen, Anlegen, Ändern und Löschen der genannten Providerressourcen; passender vorhandener Calculator-Integrationsschlüssel mit `member.read`. Keine Rechteerweiterung durch den Test.
- Ein beständiger, nicht mit Git synchronisierter Pfad für das Laufjournal. Dieses enthält ausschließlich Laufkennung, Calculator-Zieladresse, Termin-ID und POST-Status; keine Tokens, Namen oder Antwortinhalte. Nicht veröffentlichen.

Folgende Umgebungsvariablen über die vorhandene sichere Secret-Konfiguration bereitstellen. Keine Secrets in Befehlsargumente, Shellverlauf, Git oder Protokolle kopieren:

| Variable | Inhalt |
| --- | --- |
| `EASYVEREIN_API_TOKEN` | Serverseitiger easyVerein-API-Token |
| `FUF_TEST_CALCULATOR_URL` | HTTPS-Origin des Zielcalculators, ohne Unterpfad |
| `FUF_TEST_CALENDAR_ID` | Eigener easyVerein-Testkalender |
| `FUF_TEST_MEMBER_ID` | Ausschließlich das vorgesehene Testmitglied; keine Kontakt-ID |
| `FUF_TEST_SERVICE_ID` | Konfigurierter Integrationsservice, z. B. `website` |
| `FUF_TEST_KEY_ID` | Vorhandene Key-ID dieses Services |
| `FUF_TEST_ISSUER` | Exakter konfigurierter Issuer |
| `FUF_INTEGRATION_AUDIENCE` | Exakte Calculator-Audience |
| `FUF_TEST_SIGNING_KEY` | Zugehöriges HMAC-Geheimnis mit mindestens 32 Zeichen |

## Ausführen

### GitHub Actions mit vorhandenen Secrets

Actions → **API-Vertragsprüfung (nur manuell)** → **Run workflow**, Modus `run`.
Der Test legt den Termin selbst an; es gibt keine Eingabe einer Termin-ID.
Benötigt werden lediglich der übergeordnete Testkalender und das Testmitglied mit
Kontaktadresse `me@benna.me`, jeweils per Eingabefeld oder Repository-Variable
`FUF_TEST_CALENDAR_ID` / `FUF_TEST_MEMBER_ID`. Die oben genannten Mailgrenzen gelten weiterhin.

Der Workflow verwendet die vorhandenen Deployment-Secrets `DEPLOY_SSH_KEY`,
`EASYVEREIN_API_TOKEN`, `FUF_INTEGRATION_SERVICES` und die Variablen `DEPLOY_HOST`,
`DEPLOY_USER`, `APP_URL`, `FUF_INTEGRATION_AUDIENCE`. Der Integrationsschlüssel wird
im Testprozess aus dem konfigurierten Service übernommen (standardmäßig `website`,
Key `current`, alternativ der einzige vorhandene Key). Abweichende Kennungen über
`FUF_TEST_SERVICE_ID` / `FUF_TEST_KEY_ID` konfigurieren. Keine Secrets kopieren oder ausgeben.

Die Skripte laufen isoliert per SSH auf der vorhandenen VM. Sie deployen keine
Anwendung und ändern keine Betriebskonfiguration. Das Journal bleibt bei Fehlern
unter `/opt/fuf-calculator/api-contract/journals/<Run-ID>-<Versuch>.json` erhalten,
auch wenn der Actions-Runner ausfällt. Zur Wiederaufnahme denselben Workflow mit
Modus `cleanup` und der im Protokoll genannten früheren Laufkennung starten.
Ein normaler neuer Lauf verwendet eine neue Kennung und bereinigt kein altes Journal.
Der Workflow hat ausschließlich `workflow_dispatch`, keine automatischen Auslöser.

### Direkter Aufruf

Nach ausdrücklicher Nutzeraufforderung aus dem Repository:

```bash
python3 scripts/manual/api_contract_test.py --run-live --journal /persistent/private/fuf-api-run.json
```

Der Journalpfad darf für einen neuen Lauf noch nicht existieren. Der Test verwendet eine zufällige Kennung `fuf-api-test-<UUID>`. Ausgaben enthalten nur technische Prüfergebnisse, keine API-Antwortkörper oder personenbezogenen Daten. Es gibt keine automatischen POST-Retries, keinen Browserzugriff und keine Änderungen am Clubdesk.

Exitcode 0 bedeutet, dass alle ausgewählten Prüfungen und die Bereinigung erfolgreich waren. Bei einer fehlgeschlagenen Prüfung wird auch dann Exitcode 1 geliefert, wenn die Bereinigung gelingt. Fehlende Bereinigung führt ebenfalls zu Exitcode 1 und das Journal bleibt erhalten.

## Wiederaufnahme nach Fehler oder Abbruch

Der Runner bereinigt in `finally`, auch nach SIGINT/SIGTERM. SIGKILL, Stromausfall und nicht erreichbare APIs können die sofortige Bereinigung verhindern. Ein zweiter Aufruf mit demselben Journal setzt die Bereinigung fort, ohne neue Testdaten anzulegen:

```bash
python3 scripts/manual/api_contract_test.py --run-live --cleanup-only --journal /persistent/private/fuf-api-run.json
```

Dafür genügen `EASYVEREIN_API_TOKEN` und dieselbe `FUF_TEST_CALCULATOR_URL`. Bereits entfernte Ressourcen gelten als bereinigt. Vor dem Termin-POST wird die Absicht dauerhaft gespeichert. Geht dessen Antwort verloren, sucht die Bereinigung paginiert nach der exakten eigenen Markierung. Teilnahmen und Preisgruppen werden über Termin-ID plus exakte Markierung wiedergefunden, auch wenn ihre POST-Antwort verloren ging. Fremde Markierungen und widersprüchliche Referenzen verhindern Löschungen. Bei einem nicht eindeutig auflösbaren POST bleibt das Journal bestehen; keinen neuen POST als vermeintlichen Retry starten. Zurückgebliebene Daten und ungeklärte Ergebnisse im Abschluss melden.

## Offline-Prüfung des Runners

```bash
python3 -m unittest discover -s tests/Manual -v
```

Diese Tests verwenden ausschließlich lokale Fakes. Sie prüfen u. a. erfolgreichen Ablauf, Teilfehler, verlorene POST-Antworten, Wiederaufnahme, Löschreihenfolge, Schutz fremder Daten, Signaturformat, Pagination, Mailgrenze und den erforderlichen manuellen Startschalter. Sie führen den echten API-Test nicht aus.
