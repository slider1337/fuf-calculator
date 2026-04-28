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
- `database/schema.sql` - Datenbankschema
- `templates/index.html.php` - Bootstrap UI
- `public/assets/js/app.js` - AJAX-Frontend
- `docs/openapi.yaml` - OpenAPI-Spec
- `phpunit.xml` - PHPUnit-Konfiguration inkl. Coverage
- `tests/Unit` - Unit-Tests
- `tests/Application` - Application-Layer-Tests
- `tests/Integration` - API-Integrationstests
- `tests/Integration/OpenApiContractTest.php` - API-Vertragschecks gegen OpenAPI

## Schnellstart

Hinweis: In der aktuellen Umgebung war `composer` beim Check nicht installiert. Falls lokal vorhanden, so starten:

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

## Tests

```bash
composer test
composer test:coverage
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

Die CI fuehrt Syntax-Checks, PHPUnit und Coverage auf PHP 8.4 aus.



