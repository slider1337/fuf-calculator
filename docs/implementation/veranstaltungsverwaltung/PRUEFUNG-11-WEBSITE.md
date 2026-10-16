# Schritt 11 – Website-Anbindung, 16.10.2026

Der Nutzer hat die vollständige Anbindung beauftragt. Grundlage ist der aktuelle `master` **e21b9da6f8bbe34a08a2499d21411d86675efe2d**, einschließlich der Architektur-/Typisierungs-/Frontend-PRs #33–#35. Der Website-Teil liegt im Repository `slider1337/fuf-website`, Plugin 0.8.0. Keine Rücknahme der dort vorgenommenen Entkopplung: neue Indexabfrage liegt hinter `MemberBookingRepositoryInterface` im SQLite-Adapter.

## Additiver Mitgliedervertrag 1.7.0 / OpenAPI 1.14.0

- `GET /integration/v1/events/{eventId}/route`: signierte Antwort `legacy` oder `calculator`. Ein angebundener easyVerein-Termin bleibt auch bei zurückgenommener Mitgliederfreigabe im Calculator-Weg (`released: false`). Authentifizierungs-/API-Fehler erlauben keinen Fallback.
- `GET /integration/v1/bookings?after=…`: höchstens 25 eigene Buchungen, stabiler Cursor, eventId/tripTitle. Auch archivierte Reisen und deaktivierte Freigaben verlieren ihre bisherigen eigenen Buchungen nicht. Frische verifizierte Identität, keine Fremdbuchungen und keine Orga-Rechte.
- Kontext ergänzt Reise-Stichtag, Altersgrenze und Lesesperre für die Mitgliedsoberfläche. Der Server bleibt für Bedingungen/Pflichtregeln maßgeblich.
- Buchungsausgaben ergänzen die historische Formulardefinition zur unveränderten gespeicherten Version. Dadurch zeigt WordPress frühere Antworten mit ihren damaligen Feldbezeichnungen. Akteurs-/Begründungsinformationen und fremde Revisionen werden nicht ausgegeben.
- Keine Schema-/Datenmigration, keine Änderung der Berechnung oder vorhandener Provider-Aufträge.

## Website

Serverseitige HMAC-Delegation aus dem bestehenden easyVerein-Login; keine Browser-Service-Tokens. Zentrale Formularversion, Gruppen-/Personenfelder, Pflicht-/Sichtbarkeitsregeln, reale Preisgruppen, Familien- und erlaubte Gastbuchung, Vorschau, verbindliches Absenden, eigene Antworten/Korrekturen/Ergänzung und Teil-/Gesamtstorno. Bestätigung erst aus tatsächlichem Personen-/Syncstatus, Operation-Key bei Übertragungsabbruch unverändert. Alte Website-Schreibpfade prüfen denselben Terminweg. Verbindungseinrichtung unter WordPress → Einstellungen → Calculator-Anmeldung; Konfiguration und manuelle Freischaltungsprüfung im Website-Repo: `docs/website-redesign/13-calculator-anmeldung/README.md`.

## Nachweise

- Vollständige Calculator-Suite auf dem kombinierten Stand: **439 Tests / 8722 Assertions**, keine Fehler/Fehlschläge, keine PHPUnit-/Deprecation-Hinweise.
- PHPStan Level 10 erneut ausgeführt, keine zusätzliche Baseline-Ausnahme. Baseline des Hauptbranches unverändert.
- Neue echte Router-/OpenAPI-Tests: Route bei fehlender/gesperrter Freigabe, Kontextmetadaten, eigene/fremde Buchungen, historische Definition, 25er-Pagination ohne Überschneidung, ungültiger Cursor.
- Website: PHP-Grenzprüfungen für Signaturbytes/Identität/CSRF/Origin, sichere Konfiguration, private Antworten, Terminbindung und Fail-closed. JS-Modell und tatsächlicher Client mit HTTP-/DOM-Doubles prüfen Vierpersonenbuchung, `false`, keine Preisgruppen, Antwortverlust nach Commit/identische Wiederholung, echte Statusanzeige, Korrektur und Storno. Bisherige Anmeldetests und Paketgrenzen erhalten.

Keine echten Testtermine, Teilnahmen oder Mails. Kein Browser-/Lighthouse-Lauf. Keine vollständige echte WordPress-/Providerabnahme oder produktive Bereitstellung behauptet. Visuelle Abnahme optional manuell durch den Nutzer; Serverkonfiguration, kompatibler Deploy und bewusste Freigabe je Reise gehören zur Inbetriebnahme.
