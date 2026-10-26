# Prüfung der Proxy-Trennung — 26.10.2026

- Drei neue Python-Vertragstests in tests/Deployment erfolgreich.
- Produktions-Compose mit Docker Compose 2.40.3 `config --quiet` und Dummywerten erfolgreich.
- Internes Calculator-Caddyfile mit FrankenPHP 1.13.2 standalone / Caddy 2.11.7 erfolgreich validiert.
- Proxy-Paket: drei Architekturtests und ein echter lokaler Caddy-TLS/Routingtest erfolgreich (Login-/Asset-/API-Pfade, Query, Host/Authorization/Cookie, Secure-Cookie, HTTPS-Header und HTTP-Redirect).
- Bash-Syntaxprüfung und git diff --check erfolgreich.
- Composer-Lock-Abhängigkeiten lokal installiert (keine Updates; OIDC-Kompatibilitätspatch aus dem Repository angewendet).
- PHPUnit komplett mit `--no-coverage`: **537 Tests, 14.512 Assertions, keine Fehler/Failures, sieben Warnungen**. Laufzeit PHP 8.5.12 aus FrankenPHP standalone; kein Beleg für die produktive PHP-8.4.27-Laufzeit. Coverage-Treiber fehlt.

PHPStan Level 10 wurde mit unveränderter Repository-Konfiguration gestartet.
Die standalone Laufzeit benötigt eine externe temporäre php.ini, um PHPStan-
Selbstneustarts mit dem Nicht-PHP-Binary zu vermeiden. Keine Unterdrückung,
Baseline oder Änderung am analysierten Code wurde hinzugefügt.
Ergebnis: **PHPStan Level 10 ohne Fehler** (PHP-Zielversion 80400 laut
phpstan.neon). Ausgeführt über `frankenphp php-cli vendor/bin/phpstan analyse
--configuration=phpstan.neon --no-progress --debug --xdebug` mit temporärer
php.ini (`memory_limit=1G`, `register_argc_argv=On`, `phpstan.restarted=1`).
Diese CLI-Einstellungen vermeiden den Selbstneustart mit dem Caddy-Binary und
stellen normale CLI-Argumentvariablen bereit. Die Standardprüfung im tatsächlichen
PHP-8.4-Container bleibt vor produktiver Freigabe empfohlen.

Nicht ausgeführt: Docker-Containerstart / gepinntes Produktionsimage, Live-HTTPS,
Login/Logout/Rate-Limits über den echten Proxy, tatsächliches Zertifikats-/Volume-
Inventar, Heat-Tunnel und alle übrigen produktiven Domains. Keine Live-Provider-
Schreibaufrufe, keine Heizungsänderung, keine authentik-Installation oder
produktive Umschaltung. Die manuellen echten API-Vertragstests wurden nicht gestartet.

Keine zusätzlichen CI-Workflows, Builds oder Actions-Artefakte. Der Infrastruktur-
Commit ist mit [skip ci] markiert, damit bestehende Push-/PR-CI für diesen Entwurf
keine doppelten Runner-Minuten verbraucht. Vor endgültiger Freigabe kann die
bestehende CI gezielt einmal auf dem geprüften Stand ausgeführt werden.
