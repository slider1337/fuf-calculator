# Buchungsarchitektur und Build-Pflege

## Fachliche Persistenzgrenzen

Mitgliedsbuchungen und deren Policy verwenden `MemberBookingRepositoryInterface`.
Teilnahme-Service, Worker, Importer und geplanter Abgleich verwenden
`ParticipationRepositoryInterface`. SQL, PDO-Ergebnisverarbeitung und das Einlesen
lokaler Personen liegen in den SQLite-Adaptern. Die Ports enthalten benannte
Operationen statt einer beliebigen SQL-Schnittstelle.

Autorisierung, Providerzugriffe, Idempotenz und Transaktionsgrenzen bleiben bei den
Anwendungsabläufen. Der Importer ordnet weiterhin nur über stabile Referenzen zu;
Gastanmeldung und automatischer Teilnehmerimport aus PR #31 bleiben erhalten.
Einfügen und Ermitteln einer Gast-ID erfolgen innerhalb einer Adapteroperation.
Die Eingabegrenzen akzeptieren vorerst teilweise `mixed` und weisen nichtskalare
SQL-Parameter zur Laufzeit ab. Detailliertere Fachtypen folgen im Typisierungs-PR.

Die PHPStan-Baseline sinkt von 1651 auf 1634 Befunde, ohne neue oder erhöhte Ausnahmen.
Die neuen Adapter und Ports benötigen keine Baseline-Ausnahmen.

## Container und Abhängigkeiten

- Composer 2.10.3, PHP CLI 8.4.26 (Bookworm), FrankenPHP 1.13.0 mit PHP 8.4
  (Bookworm), jeweils mit dem geprüften Multiarch-Manifest-Digest festgelegt.
- Entwicklungs-Xdebug auf 3.5.3 festgelegt; Produktion prüft beim Build explizit,
  dass Xdebug nicht geladen wird. Beide Images installieren mbstring.
- CI baut bei geänderten Container-/Composer-Eingaben im PR beide Images und prüft
  die Erweiterungen. Ein unveränderter Frontend-PR benötigt keinen Containerbuild.
- Dependabot prüft Composer- und Docker-Aktualisierungen wöchentlich. Digest-Pins
  werden damit überprüfbar aktualisiert, nicht unbemerkt durch neue Basisimages.
- PHPUnit 13.4.3 und Swagger-PHP 6.15.0 inklusive kompatibler transitiver Pakete.
  Symfony Mailer bleibt bewusst auf der unterstützten 7.4-LTS-Reihe.

Die 12 PHP-8.4-Deprecations entstehen im unverändert aktuellen OIDC-Paket 1.0.2.
`patch-openid-nullability.php` ergänzt ausschließlich die 12 expliziten nullable
Parametertypen. Der Composer-Installationsschritt prüft den vollständigen Quellhash
vor und nach der Änderung, ist wiederholbar und bricht bei einer anderen
Upstream-Datei zur erneuten Prüfung ab. Keine Deprecations werden ausgeblendet.
Bei einem upstream korrigierten Release ist dieser Kompatibilitätsschritt zu entfernen.

Quellen der Versionsprüfung: [offizielle PHP-Images](https://github.com/docker-library/official-images/blob/master/library/php),
[Composer-Images](https://github.com/docker-library/docs/tree/master/composer),
[FrankenPHP-Releases](https://github.com/php/frankenphp/releases/tag/v1.13.0),
[Xdebug](https://xdebug.org/updates). Die Digests wurden gegen die Registry geprüft.

## Validierung

PHPStan Level 10 mit reduzierter Baseline erfolgreich. PHPUnit 13: 434 Tests /
6950 Assertions erfolgreich, keine PHP-Deprecations. Zwei Test-Mocks ohne
Erwartungen wurden anschließend durch Stubs ersetzt; die beiden betroffenen
Tests sind ohne PHPUnit-Hinweise erfolgreich. Die Abläufe verwenden Fakes.
Composer-Validierung und Audit werden zusätzlich ausgeführt. Lokale Containerbuilds
sind mangels Docker-Daemon nicht möglich; dafür enthält die PR-CI den Buildcheck.
