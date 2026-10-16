# Terminänderung: abgelehnter Anmeldeschluss und blockierter Folgeauftrag

Im gemeldeten Dialog wurde der Anmeldeschluss vom 15.10.2026 auf den
16.10.2026 um 23:59 Uhr verschoben. Die frühere Reparaturdokumentation
(`2026-10-15-easyverein-registration-period.md`) belegt für Reise 2 einen
ganztägigen Terminbeginn am 16.10.2026 um 00:00 Uhr sowie easyVereins
Ablehnung sich überschneidender Anmelde- und Terminzeiträume. Der Änderungsweg
prüfte bisher nur Beginn/Ende und ließ diese Kombination erneut zu.
Die aktuelle Produktionsantwort dieses Änderungsauftrags wurde für diesen
Fix nicht ausgelesen; ein Timeout oder eine andere Ablehnung lässt sich aus
dem Screenshot allein nicht ausschließen.

## Änderung

- Browserprüfung, serverseitige Vorschau/Beauftragung und Worker prüfen den
  zusammengeführten Stand: Ein gesetzter Anmeldeschluss muss strikt vor
  Terminbeginn liegen. Das gilt auch beim Verschieben des Beginns. Eine
  gemeinsame Korrektur beider Felder bleibt möglich. Bestehende externe
  Werte bleiben lesbar, damit Fehler korrigiert werden können.
- Eindeutige Provider-Ablehnungen werden nicht mehr vom generischen
  Throwable-Catch als unbekannter Ausgang gespeichert. Der Auftrag endet
  mit `blocked` und einem festen verständlichen Fehlercode für Token,
  Schreibrechte, fehlenden Termin, Zeitraum oder sonstige Ablehnung.
  Er hält die Sperre für neue, korrigierte Aufträge nicht aufrecht.
- Echte Timeouts, Leaseabläufe und nicht bestätigte Rückmeldungen bleiben
  `unknown`. Der Worker liest weiterhin ausschließlich nach und wiederholt
  den PATCH nicht automatisch.
- Für bestehende unklare Aufträge gibt es „Aktuellen Stand prüfen und Auftrag
  abschließen“. Der Dialog lädt die aktuellen easyVerein-Felder. Ein expliziter
  Abschluss mit Grund akzeptiert diesen Stand und beendet den alten Auftrag
  lokal als `conflict` mit `term_update_resolved`. Der ursprüngliche Ausgang
  wird dadurch nicht als sicher fehlgeschlagen umgedeutet. Anschließend kann
  ein neuer, frisch geprüfter Änderungsauftrag erstellt werden.
- Der Abschluss liest vor dem Schreiben erneut; der Hash bindet Reise,
  externe Termin-ID und alle angezeigten Werte. Ein zwischenzeitlich geänderter
  Stand, fehlende Bestätigung, fremder Auftrag oder laufender Worker verhindert
  den Abschluss. Rechte, Anmeldeverfahren, Archiv/Abschluss und Terminbindung
  werden serverseitig geprüft. Abschluss und Audit sind atomar.
- Solange ein Auftrag offen ist, ist eine weitere Beauftragung im Dialog
  deaktiviert. Der Status lässt sich aktualisieren. Die Änderungsvorschau zeigt
  Datumswerte in lokaler Zeit statt als rohe UTC-Zeichenketten.

## Nach Bereitstellung

Beim alten Auftrag den aktuellen Stand prüfen und bewusst abschließen.
Danach gewünschte Änderungen erneut prüfen. Für den beschriebenen
Termin am 16.10.2026 ist ein Anmeldeschluss am Abend desselben Tages ungültig;
bei unverändertem Beginn muss er vor dem 16.10.2026, 00:00 Uhr liegen.
Es wird kein Produktionsauftrag automatisch gelöscht oder erneut gesendet.

## Validierung

PHPStan Level 10 ohne Baseline, PHPUnit-Suite und isolierte Frontendtests.
Regressionen prüfen Grenzzeitpunkt/Zeitzone, Beginnverschiebung, Worker-Vorprüfung,
HTTP-Ablehnungen, Timeout ohne zweiten PATCH, Altauftragsabschluss nach frischer
Prüfung, veralteten Stand, laufende Worker, falsche Aufträge, Archiv und
geänderte Terminbindung. Keine Browser-/Lighthouse-Prüfungen gestartet.
