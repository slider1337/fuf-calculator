# Automatische easyVerein-Token-Erneuerung

## Speicherort und Einrichtung

Der Calculator speichert den aktuellen API-Token in `easyverein-token.json` neben
der über `DB_PATH` gewählten Datenbank. Im Produktions-Compose ist das:

**`/app/database/easyverein-token.json`**

Die Datei liegt im bereits vorhandenen externen SQLite-Docker-Volume, gemeinsam
für `app` und `worker`. Sie überlebt Container-Neustarts und Image-Updates. Auf
dem Host ist sie kein Bestandteil von `/opt/fuf-calculator/.env`, sondern eine
Datei im Datenvolume. Den vorhandenen Volume-Namen nennt
`FUF_SQLITE_VOLUME` in `/opt/fuf-calculator/proxy.env`. Keine Volume-Löschung oder
neuen Mounts sind erforderlich.

Ohne vorhandene Tokendatei wird `EASYVEREIN_API_TOKEN` aus der Prozessumgebung
beim ersten API-Zugriff übernommen. Vor dem ersten Rollout muss dieser Wert
gültig sein. Eine `.env`-Änderung allein verändert keine laufenden Container;
deren Umgebung muss über den bestehenden Compose-Aufruf neu übernommen werden.
Der Deployment-Workflow schreibt `.env` aus GitHub-Secrets: vor dem ersten
Rollout auch dort den aktuellen Starttoken hinterlegen, sonst kann die manuelle
Änderung überschrieben werden.

Die Tokendatei und ihre Sperrdatei haben Modus `0600`. `app` und `worker` müssen
mit derselben UID auf dasselbe Volume zugreifen können. Die Datei enthält ein
Secret: nicht veröffentlichen, nicht als Actions-Artefakt hochladen. Git und
Docker-Buildkontext schließen die Dateien am Standardort aus. Separat erstellte
Backups des gesamten Volumes müssen wie Zugangsdaten geschützt werden; ein
reines SQLite-Backup enthält diese Datei nicht.

## Automatischer Ablauf

- Lese- und Schreibclient verwenden denselben Transport und lesen den aktuellen
  Token bei jedem Request unter einer gemeinsamen Dateisperre. Auch bereits
  gestartete Worker übernehmen dadurch eine Erneuerung sofort.
- Ein wahrer `token_refresh_needed`-Header (auch `tokenRefreshNeeded`) löst genau
  einen GET auf `/api/v3.0/refresh-token` aus. Ohne Hinweis entstehen keine
  zusätzlichen API-Requests. Der Refresh zählt zum vorhandenen Requestbudget.
- Request und anschließender Refresh halten dieselbe Sperre. Andere Prozesse
  können während des Wechsels keinen alten Token neu verwenden. Nach maximal
  zwei Sekunden Wartezeit melden sie `provider_token_busy`, statt die Sperre zu
  umgehen. Ein Prozessabbruch gibt die Betriebssystem-Sperre frei.
- Nur HTTP 200 mit einem gültigen Tokenfeld (`Bearer`, `token`, `key`) wird
  übernommen. Speicherung erfolgt über eine private temporäre Datei und
  atomaren Austausch. Ein ungültiger Speicher blockiert API-Aufrufe; kein
  stiller Rückfall auf einen möglicherweise überholten Umgebungstoken.
- Ein fehlgeschlagener Refresh ändert nicht das bereits erhaltene Ergebnis der
  ursprünglichen Buchung. Es gibt keine automatische Wiederholung von POST oder
  PATCH. Der Fehler wird ohne Antworttext/Token protokolliert und als
  `last_error` gespeichert; weitere Refreshversuche frühestens nach fünf Minuten
  und nur bei erneutem Providerhinweis.
- Ein HTTP 401 allein startet keinen Refresh und keinen erneuten Schreibaufruf.
  Nach Ablauf, verloren gegangener Refreshantwort oder einem Abbruch zwischen
  Providerwechsel und lokaler Speicherung kann ein manueller neuer Token nötig
  sein. Die API bietet für diesen verteilten Wechsel keine atomare Transaktion.

## Status prüfen und Token manuell ersetzen

Im Serververzeichnis `/opt/fuf-calculator`, mit der bestehenden Compose-Konfiguration:

```bash
docker compose --env-file .env --env-file proxy.env -f docker-compose.prod.yml exec -T app php scripts/easyverein-token.php status
```

Die Ausgabe enthält Speicherpfad, Zeitpunkt der letzten erfolgreichen Erneuerung
als Unix-Zeitstempel, letzten Fehlercode und frühesten nächsten Refreshversuch.
Sie enthält weder Token noch Token-Auszug. Auch dieser Befehl führt keinen
easyVerein-Request aus. Ein Statusabruf kann die Datei aus dem Starttoken erstmalig
initialisieren.

Für einen späteren **bewussten manuellen Austausch** zuerst den neuen Wert in
`.env` eintragen, anschließend aus der aktuellen Compose-Umgebung importieren:

```bash
docker compose --env-file .env --env-file proxy.env -f docker-compose.prod.yml run --rm --no-deps app php scripts/easyverein-token.php import-env
```

Dieser einmalige Container übernimmt die aktuelle `.env`; ein `exec` in einen
alten Container würde dagegen dessen alte Umgebung verwenden. Der Import hält
dieselbe Sperre wie die Anwendung. Laufende Web-/Worker-Prozesse übernehmen die
neue Datei beim nächsten Aufruf; ihre alten Umgebungstokens überschreiben sie
nicht. Der manuelle Import prüft die lokale Speicherung, nicht die Gültigkeit
des Tokens beim Provider. Den neuen Wert auch in den Deployment-Secrets pflegen,
damit nach einem Verlust des Volumes die richtige Ausgangskonfiguration vorliegt.

WordPress behält seine vorhandene Tokenverwaltung. Einen Token nicht unabhängig
in mehrere Systeme kopieren, die ihn jeweils erneuern: beim Providerwechsel wird
die andere Kopie ungültig. Ob separat erzeugte Tokens unabhängig bleiben, muss
für die verwendeten easyVerein-Zugänge bestätigt werden. Der Importer bleibt
wie gewünscht manuell.

## Prüfungen

`tests/Integration/EasyVereinTokenRefreshTest.php` prüft den cURL-Headerpfad,
dauerhafte Übernahme durch Web/Worker/Neustart, parallele Prozesse, fehlerhafte
Refreshantworten, verlorene Antworten, unveränderte Buchungsergebnisse, expliziten
manuellen Austausch, private Dateirechte und beschädigten Speicher. Der isolierte
cURL-Regressionstest scheitert am alten Code mit `token_refresh_missing`.
Alle Providerantworten sind Fakes; keine echten Tokens, Buchungen oder Mails.

Nach dem Deployment bietet sich zusätzlich die bestehende manuelle
[API-Vertragsprüfung](../testing/manual-api-contract.md) an. Sie startet nur auf
ausdrücklichen Auftrag und belegt keinen echten 15-Tage-Tokenwechsel.
