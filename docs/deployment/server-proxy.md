# Unabhängiger Reverse Proxy

Ziel: privates Repository `slider1337/server-proxy`. Die Migration wird NICHT durch
einen Merge oder dieses Deployment automatisch ausgeführt. Die vollständige
Anleitung liegt dort in `docs/migration.md`; bis das Repository angelegt ist,
steht das vorbereitete Paket im Abschlussbericht zur Verfügung.

Calculator bedient nur noch :80 im externen Netz `benna-web` unter dem Alias
`fuf-calculator`. Öffentliche Ports und ACME-Volumes gehören ausschließlich dem
separaten Proxy. app/worker/migrate und SQLite bleiben hier. Der ursprüngliche
Compose-Projektname und das tatsächliche SQLite-Volume werden in der lokalen,
nicht versionierten `/opt/fuf-calculator/proxy.env` festgelegt:

```dotenv
FUF_COMPOSE_PROJECT=fuf-calculator
FUF_SQLITE_VOLUME=<tatsächlicher bisheriger Volume-Name>
FUF_PROXY_CIDR=<tatsächliches benna-web Subnet>
```

Die Platzhalter niemals unverändert übernehmen. `write-env.py` verwaltet weiterhin
nur die Anwendungs-.env, proxy.env bleibt separat erhalten. Alle produktiven
Compose-Befehle benötigen beide Env-Dateien:

```bash
cd /opt/fuf-calculator
docker compose --env-file .env --env-file proxy.env -f docker-compose.prod.yml config --quiet
# Normale Releases weiterhin über den bestehenden Deploy-Workflow.
```

Vor dem Kopieren von Dateien prüft das Deployment den Marker
`.proxy-migration-approved`, proxy.env und das gemeinsame Netzwerk. Der Marker wird
erst nach freigegebener Migration und erfolgreicher Live-Abnahme gesetzt. Vorher
scheitert das Deployment ohne Überschreiben der bisherigen Caddy-/Compose-Dateien.
Für den Migrationszeitraum Deploy-Workflow pausieren, laufende Releases abwarten.
Der bestehende Build läuft nach einem Merge weiterhin nach dem bisherigen Ablauf;
daher vor Merge pausieren, wenn kein Build gewünscht ist. Keine zusätzlichen
Workflows oder Artefakte wurden eingerichtet.

Keine öffentlichen Ports, Proxy-Container, ACME-Mounts, globales Image-Prune,
`down -v` oder `--remove-orphans` wieder ins Calculator-Deployment aufnehmen.
`setup-vm.sh` setzt eine bereits freigegebene gemeinsame Infrastruktur voraus.
Der lokale Entwicklungs-Compose und Anwendungscode bleiben unverändert.

Noch offene Live-Nachweise: tatsächlich verwendete Projekte/Volumes, manuelle
Caddy-Sonderregeln, alle Domains/Upstreams (insbesondere heat), Zertifikatserhalt,
TLS/HTTP-Redirects, Login/Secure-Cookie/API-Pfade und unveränderte Proxy-ID nach
einem späteren Calculator-Release. AuthRateLimitMiddleware nutzt REMOTE_ADDR;
die geteilte Proxy-Adresse kann gemeinsame Limits verursachen. Vor Abnahme mit
zwei Clients prüfen; keine ungeprüfte Authentifizierungsänderung in diesem PR.

Docker-Daemon und produktiver PHP-8.4-Container fehlen. Compose config --quiet
ist mit Compose 2.40.3 und Dummywerten erfolgreich; beide Caddyfiles bestehen
FrankenPHP-Standalone-Validierung. Siehe `docs/deployment/proxy-validation.md`
für tatsächlich ausgeführte PHP-Prüfungen und deren Grenzen.
Die lokalen Tests ersetzen keine produktive Routing-Abnahme.

Die Python-Architekturtests benötigen PyYAML (geprüft mit 6.0.3).


## nginx-proxy-Erkennung seit 30.10.2026

server-proxy verwendet nginxproxy/nginx-proxy mit acme-companion. Nur app erhält
VIRTUAL_HOST, VIRTUAL_PORT=80 und ACME_HOST. Die gemeinsamen Einstellungen liegen
in einer separaten YAML-Mapping-Verankerung; worker und migrate erben keine
Erkennungsvariablen. FUF_VIRTUAL_HOST in proxy.env ist optional und hat den
Standard fuf-calc.benna.me. FUF_PROXY_CIDR bleibt für internes FrankenPHP erforderlich.
Der Proxy stellt Zertifikate neu aus; die SQLite-Volume-Zuordnung bleibt erhalten.
Keine Images, Workflow- oder Datenbankänderungen für diese Compose-Anbindung.
Die Details stehen im server-proxy-Repository unter docs/migration.md.
