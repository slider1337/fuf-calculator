# Sicherheit und Abhängigkeiten

## Änderungen

- CSRF-Prüfung für alle schreibenden Browseranfragen, einschließlich Login, Logout und CSV-Upload. HTML-Formulare übertragen `_csrf`, JavaScript `X-CSRF-Token`. OIDC-Callbacks bleiben GET-Anfragen mit bestehender State-/Nonce-/PKCE-Prüfung. Die Mitglieder-API verwendet weiterhin die separate signierte Delegation einschließlich Replay-Schutz.
- Persistente Versuchsgrenzen über SQLite, unabhängig von Browser-Sitzungen. Login, Passwortvergabe und Kontoverknüpfung: 10 Versuche je Konto/Token und 60 je Transport-IP in 15 Minuten. Passwortreset: 3 je Konto und 20 je IP pro Stunde. Resetanforderungen liefern auch bei Begrenzung dieselbe neutrale Antwort. Erfolgreiche Anmeldungen setzen diese Sicherheitsgrenzen nicht zurück. Abgelaufene Einträge werden entfernt; Identifikatoren sind gehasht. Forwarding-Header werden nicht als vertrauenswürdige IP-Quelle verwendet.
- Passwortvergabe und Widerruf sämtlicher weiterer Einladung-/Resetlinks einer Adresse erfolgen in derselben Transaktion. Ein Fehler beim Speichern lässt den Link weiterhin verwendbar. Der SQLite-Schreibzugriff wird vor der Tokenprüfung reserviert.
- Sitzungen sind an einen Fingerabdruck des aktuellen Passwort-Hashes gebunden. Passwortänderungen machen bestehende Sitzungen beim nächsten Zugriff ungültig, auch bei easyVerein-Anmeldung. Sitzungscookies bleiben HttpOnly/SameSite=Lax, Strict-Session-Modus ist aktiv.
- Browserantworten werden nicht gecacht; Referrer-Policy `same-origin` und `nosniff` werden gesetzt.
- Slim 4.15.3, Phinx 0.16.12, PHPUnit 11.5.57 sowie weitere kompatible Paketupdates. Symfony Mailer bleibt in 7.4 LTS, transitive Symfony-Komponenten sind auf unterstütztem 8.1-Stand. Die nicht mehr gewartete 8.0-Reihe ist explizit ausgeschlossen.
- `composer audit --locked` ist Bestandteil der CI. Der Audit meldet nach dem Update weder bekannte Sicherheitslücken noch aufgegebene Pakete. Neuere Hauptversionen von PHPUnit und Swagger-PHP bleiben einem gesonderten Upgrade vorbehalten.
- Swagger UI 5.33.3 ist auf eine feste Version und SHA-256-Prüfsummen festgelegt. Vorhandene korrekte Assets werden wiederverwendet, Download-/Integritätsfehler brechen den Build ab.

## Bereitstellung

Die normale Migration legt `auth_rate_limits` samt Ablaufindex an. Nach dem Upgrade müssen sich bestehende Nutzer einmal neu anmelden, weil ältere Sitzungen noch keinen Credential-Fingerabdruck enthalten. Alte geöffnete Seiten können nach einem Sitzungswechsel eine CSRF-Fehlermeldung anzeigen; Neuladen holt den aktuellen Token.

Der bereits in PR #28 gemergte Fix für Providerzugriffe außerhalb von SQLite-Transaktionen ist enthalten und wird nicht nochmals implementiert. Architektur-/Frontend-Refactoring sind nicht Bestandteil dieses Sicherheits-PRs. PHPStan Level 10 folgt als separater PR.

## Validierung

- PHP 8.4.14: PHPUnit vollständig, 415 Tests / 6391 Assertions erfolgreich. 12 bestehende Deprecation-Meldungen verbleiben.
- Neue Integrationstests: fehlende/falsche CSRF-Tokens, HTML-Tokenübertragung, Konto-/IP-Begrenzung einschließlich Sitzungswechsel und Ablauf, neutrale Resetantworten, Tokenwiderruf, Sitzungswiderruf und Rollback bei fehlgeschlagenem Passwortschreiben.
- PHP-Syntaxprüfung und JavaScript-Syntaxprüfung erfolgreich.
- Composer-Audit ohne Befunde; direkte Abhängigkeiten auf verfügbare Updates geprüft.
- Keine echten Mails oder easyVerein-Schreibaktionen. Lokale Testlaufzeit ohne Coverage-Erweiterung; CI erzeugt Coverage mit Xdebug.
