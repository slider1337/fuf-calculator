# FUF Gruppenreise Kalkulator

Webanwendung zur Kalkulation von Einzelpreisen fuer Gruppenreisen (Kompaktmodus) mit DDD-Struktur, REST-API und OpenAPI.

## Features

- PHP 8.4, objektorientiert mit Domain/Application/Infrastructure
- REST API fuer Settings, Reiseanlage, Reiseabruf, Berechnung
- OpenAPI-Spezifikation in `docs/openapi.yaml`
- Lokale interaktive Swagger-UI unter `/docs` (ohne CDN)
- Frontend mit Bootstrap (Composer-Paket `twbs/bootstrap`) und Vanilla JS (`fetch`)
- SQLite als persistente Datenbank
- Rundung auf 2 Nachkommastellen je Rechenschritt
- Zwei Verteilungsmethoden: `PER_PERSON`, `PER_CATEGORY_UNITS` (erweiterbar)
- PHPUnit-Tests (Unit, Application, API-Integration)
- Coverage-Ausgabe via PHPUnit/Xdebug

## Projektstruktur

- `public/index.php` - Front Controller
- `config/container.php` - DI und Infrastruktur-Wiring
- `config/routes.php` - API-Routen
- `src/Domain` - Domain-Modelle und Berechnungslogik
- `src/Application` - Services, Ports, Validation
- `src/Infrastructure` - SQLite und HTTP Controller
- `migrations/` - Phinx-Datenbankmigrationen (aktueller Schema-Vertrag)
- `templates/index.html.php` - Bootstrap UI
- `public/assets/js/app.js` - AJAX-Frontend
- `docs/openapi.yaml` - OpenAPI-Spec
- `phpunit.xml` - PHPUnit-Konfiguration inkl. Coverage
- `tests/Unit` - Unit-Tests
- `tests/Application` - Application-Layer-Tests
- `tests/Integration` - API-Integrationstests
- `tests/Integration/OpenApiContractTest.php` - API-Vertragschecks gegen OpenAPI

## Schnellstart

Voraussetzung: PHP 8.4 mit den in `composer.json` angegebenen Erweiterungen und Composer.

```bash
composer install
php -S 127.0.0.1:8080 -t public public/router.php
```

Dann im Browser:

- App: `http://127.0.0.1:8080/`
- Neue Reise: `http://127.0.0.1:8080/trips/new`
- Reise-Detail: `http://127.0.0.1:8080/trips/1`
- API Doku (lokal): `http://127.0.0.1:8080/docs`
- OpenAPI YAML: `http://127.0.0.1:8080/openapi.yaml`

## Zusätzliche easyVerein-Anmeldung

Einrichtung und einmalige Kontoverknüpfung: [docs/easyverein-login.md](docs/easyverein-login.md).
Die bisherige E-Mail-/Passwort-Anmeldung bleibt erhalten.

## Veranstaltungsverwaltung: Design-Konzept

[Veranstaltungen, Reisen, versionierte Formulare und Orga-Auswertungen](docs/concepts/09-veranstaltungsverwaltung-formulare.md) beschreibt das geplante gemeinsame Backend für Calculator und Website-Anmeldungen, inklusive Ansichten, Beispieldaten und Design-Prompt. Calculator-Schritte 01–10 sind technisch implementiert: Reiserechte/Abschluss, Veranstaltungen/Teams, versionierte Formulare, Buchungen/Korrekturen/Teilstorno, Auswertungen/Export, kontrollierte Planungsübernahme, Terminwizard, echte easyVerein-Adapter, Gastfreigaben und getrennte Mitglieder-API. Termine können feldweise mit Vorschau und dauerhaftem Auftrag geändert werden. Nach Timeout wird ausschließlich abgeglichen, kein weiterer PATCH ausgelöst. Der Tab „Termin“ steht bei easyVerein direkt nach „Planung“ und enthält Terminvorbereitung, Mitgliederfreigabe und Teilnahmeabgleich. Der Anmeldeschluss des Termins gilt automatisch für Buchungen, Änderungen und Stornos; die Freigabe hat keine eigene Frist. „Team“ verwaltet separat das Reiseteam. Die [Termineinrichtung](docs/fixes/2026-10-15-termin-einfacher-ablauf.md) hat drei Schritte mit einer abschließenden Aktion zum Anlegen oder Verknüpfen; Entwurf und Prüfung laufen dabei automatisch, die Mitgliederfreigabe folgt erst nach bestätigter Verbindung. Bei unklaren oder fehlenden Anlagen setzt [„Termin prüfen und anlegen“](docs/fixes/2026-10-15-terminanlage-fortsetzen.md) den bestehenden Auftrag nach Prüfung vorhandener Ergebnisse ausdrücklich fort.

Alle tatsächlichen APIs stehen in OpenAPI 1.10.0; der [Mitgliedervertrag 1.6.0](docs/integration/member-api.md) bleibt kompatibel. Server-Worker/periodischer Abgleich und Sicherung/Migration sind vorbereitet. Externe Schreibaktionen bleiben standardmäßig aus. Manuelle Design-/Providerabnahme, Merge/Bereitstellung und anschließend Website-Schritt 11 sind offen. [Abschlussprüfung](docs/implementation/veranstaltungsverwaltung/PRUEFUNG-ABSCHLUSS.md), [Betriebsanweisung](docs/implementation/veranstaltungsverwaltung/BETRIEB.md).

Der [Umsetzungsplan mit elf Aufgaben](docs/implementation/veranstaltungsverwaltung/README.md) führt vom bestehenden Calculator bis zur Website-Anbindung. **Zuerst alle Calculator-Grundlagen und Funktionen, Website erst in Schritt 11.** Die vollständige [Design-Übergabe mit Desktop-/Mobilreferenzen](docs/design-handoff/veranstaltungsverwaltung/START-HIER.md) liegt ebenfalls im Repository.

Vor dem Upgrade SQLite sichern und die explizite Alt-Rechteübernahme durchführen; siehe [Migrations- und Betriebsanweisung](docs/implementation/veranstaltungsverwaltung/PRUEFUNG-01.md).

## Anmeldeverfahren je Reise

Neue Reisen verwenden standardmäßig den bisherigen Clubdesk-/CSV-Ablauf. Unter Eckdaten kann alternativ easyVerein mit Zusatzformular gewählt werden; gespeicherte Reisen wechseln ausschließlich im Tab „Anmeldungen“ über „Anmeldeverfahren ändern“. Auch Clubdesk-/CSV-Reisen haben einen eigenen Anmeldungs-Tab mit CSV-Import und manueller Erfassung. CSV-Anmeldungen bleiben beim Wechsel erhalten; kein automatischer Providerimport. Rückwechsel sind vor neuen Buchungen/Gastfreigaben/aktiven oder unklaren Übertragungen möglich. [Verhalten, Migration und Prüfstand](docs/fixes/2026-10-14-reise-anmeldeverfahren.md).

## Tests

```bash
composer test
composer test:coverage
composer analyse
composer audit --locked
```

## Docker Compose

Projektstart mit lokaler URL `http://localhost:8080`:

```bash
docker compose up --build
```

Stoppen:

```bash
docker compose down
```

## GitHub Actions (CI)

Die CI führt Syntax-Checks, den Composer-Sicherheitsaudit, PHPStan auf Level 10 und PHPUnit mit Coverage auf PHP 8.4 aus. Auch der Deployment-Build wartet auf diese Prüfungen. Die [PHPStan-Einführung](docs/fixes/2026-10-15-phpstan-level-10.md) dokumentiert den bestehenden Typisierungsrückstand in einer Baseline.




Reisen können archiviert und wiederhergestellt werden; ihre Daten bleiben erhalten. Mitgliederänderungen und Stornos gelten bis Anmeldeschluss. Die lesende easyVerein-Mitgliederintegration wird serverseitig über `EASYVEREIN_API_TOKEN` konfiguriert; externe Schreibaktionen sind standardmäßig deaktiviert. [Prüfstand 14.10.](docs/implementation/veranstaltungsverwaltung/PRUEFUNG-14-10.md).

Echte Termin-/Teilnahmeschreibadapter und Orga-Gastfreigaben sind vorhanden. `EASYVEREIN_WRITE_MODE=production` aktiviert den späteren echten Betrieb nach Abnahme; standardmäßig aus. Testmails des Calculators gehen ausschließlich an me@benna.me, normale Empfänger erst bei `MAIL_DELIVERY_MODE=production`. [Aktuelle Prüfung/Betrieb](docs/implementation/veranstaltungsverwaltung/PRUEFUNG-14-10-SCHREIBWEGE.md).

Im Reise-/Terminwizard werden bestehende easyVerein-Termine nach Name und Datum gesucht und Kalender nach Name ausgewählt. Beim Verknüpfen wird der Kalender des ausgewählten Termins übernommen; IDs werden intern gespeichert. [Bedienung und Prüfstand](docs/fixes/2026-10-14-easyverein-terminkalendersuche.md).

## Sicherheit

Browser-Schreibanfragen benötigen einen CSRF-Token. Login und Passwortreset sind persistent begrenzt; Passwortänderungen widerrufen weitere Resetlinks und bestehende Sitzungen. Änderungen und Betriebshinweise: [Sicherheitsverbesserungen vom 15.10.2026](docs/fixes/2026-10-15-security-hardening.md).
