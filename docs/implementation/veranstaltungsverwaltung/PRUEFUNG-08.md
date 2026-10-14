# Prüfung Schritt 08 – lokaler Reise-/Terminwizard und Terminaufträge

Stand: 13.10.2026. Technisch lokal und mit Fakes umgesetzt; **echter easyVerein-Terminzugriff gesperrt**, manuelle Designabnahme offen. Ausgangspunkt und Migration siehe [Schritt 07](PRUEFUNG-07.md). Schritte 09–11 bleiben außerhalb des Auftrags.

## Lokaler Ablauf und Terminauftrag

Fünf Schritte Eckdaten → Kalender/Anmeldung → Preisgruppen → Formular/Team → Prüfen im vorhandenen scrollbaren Dialog. „Neue Reise“ einer Veranstaltung öffnet den Wizard. Entwurf legt atomar eine lokale Reise, ausgewählte veröffentlichte Formularversion, unabhängige Auswahl des aktuellen Standardteams und Terminvorbereitung an. Idempotenter Wizardauftrag verhindert doppelte Reisen nach Wiederholung. Der Ersteller behält seine Rechte; fremde Team-IDs werden abgewiesen. Kosten/Teilnehmer starten bei null und werden bewusst in der bestehenden Planung gepflegt.

„Team & Termin“ zeigt echte lokale Entwurfs-/Auftrags-/Referenzzustände, keine erfundenen Erfolge. Bestehender Reiseentwurf lässt sich fortsetzen. Bei bestehenden Reisen bleiben Teamfreigaben/Formularwechsel in den vorhandenen auditierten Dialogen; der Prüfschritt erkennt noch nicht angewendete Vorschläge. Eckdaten/Preise/Formular/Team werden serverseitig geprüft. Entwurf speichern erzeugt weder API-POST noch Terminauftrag. Fremde Entwurffelder (etwa ein behauptetes `notify=false`) werden abgewiesen.

Ein bewusst angenommener Terminauftrag ist dauerhaft, gegen gleichen Schlüssel mit abweichendem Inhalt geschützt und auf einen Erstellauftrag je Reise begrenzt. Jobzustände pending/blocked/running/reconciling/unknown/succeeded, eigene Referenz, Versuche und bereinigte Fehlercodes. Datenbank erzwingt eine Reise pro Termin-ID und eine Termin-ID pro Reise. Verknüpfen prüft die externe Referenz per Adapter, nie per Titelmatch; ein unklarer Auftrag kann mit ausdrücklich gewählter und nachgewiesener ID aufgelöst werden. Laufende Erstellung sperrt Verknüpfen.

CLI `php scripts/term-worker.php 10` verarbeitet begrenzte Batches über Server-Scheduler, keine GitHub-Sync-Actions. Atomare Lease vor externem Aufruf; Netzwerk außerhalb der SQLite-Transaktion. Rechte des Auftraggebers und Abschluss vor Ausführung und Ergebnisspeicherung erneut geprüft. Ein Job bekommt höchstens einen Create-Aufruf: Timeout/abgelaufene Lease/Absturz wechselt auf unknown und danach ausschließlich Reconcile. Fehlender Nachweis bleibt unknown; keine automatische Neuanlage. Abgleich mit 60 Sekunden Rückstand; Lease 300 Sekunden. Fakes belegen Wiederholung und konkurrierende Worker. Produktiv deaktivierter Adapter führt überhaupt keine externen Aufrufe aus.

Bestätigte Termin-ID wird separat angezeigt. Sie schaltet den produktiven Mitgliederweg noch nicht frei: Teilnahme-/Kapazitäts-/Preis- und Mitgliedervertrag aus 09/10 fehlen. Lokale Orga-Erfassung bleibt zur Vorbereitung möglich und bezeichnet ihre Buchungen weiterhin als ausstehend.

## Primärquellen und konkrete Integrationssperre

Am 13.10.2026 geprüft:

- [Offizielle API-Übersicht](https://easyverein.com/api/): v2.0 als stable, v3.0 als latest; Warnung vor dynamischen latest/stable-Aliasänderungen.
- [Offizielle API-Hilfe, bearbeitet 18.08.2026](https://hilfe.easyverein.com/en/articles/360898): Tokenrechte hängen an der Administratorgruppe; versionsbezogene Detaildokumentation benötigt Anmeldung. Keine vorhandenen Token/Accounts wurden verändert.
- Die öffentlich zugänglichen Quellen belegen **keinen hinreichenden feldweisen Event-POST-/Preisgruppenvertrag und keine verlässlich abschaltbare Terminbenachrichtigung**. Der Abruf der Detaildokumentation war ohne unabhängig bereitgestellten Inhalt nicht möglich. Deshalb keine geratenen Provider-Feldnamen, keine Vermischung des OIDC-Logins mit einer Schreibberechtigung und kein livefähiger HTTP-Adapter.

`ExternalEventPort` ist ausdrücklich ein interner Adaptervertrag: `get(stabile ID)`, `create(lokaler Entwurf, Operationsreferenz)`, `reconcile(Operationsreferenz)`, `blockedReason()`. Nur verifizierte Resultate enthalten `externalId`; Timeout ist kein nachgewiesener Fehler vor Anlage. Die lokalen Entwurffelder in OpenAPI sind **keine behaupteten easyVerein-API-Felder**. `DisabledExternalEventAdapter` ist fest in DI verdrahtet, ohne Netzwerkclient oder Credentials. Es gibt keinen Umgebungs-Schalter, der diese Prüfung umgeht. UI erklärt die Sperre und deaktiviert die externe Aktion; direkt angenommene API-Aufträge bleiben blocked. Auch ID-Lookup/Verknüpfen ist im Produktivadapter gesperrt, bis der konkrete Lesepfad belegt ist.

Vor Freigabe: versionierte Primärspezifikation für Termin/Kalender/Preisgruppe mit Berechtigungen und Benachrichtigungskontrolle bereitstellen; vollständiges Feldmapping und belastbarer Reconcile-Nachweis ergänzen; ohne abschaltbare Benachrichtigungen bleibt das Versandverbot bindend. Das ist die in AUFGABE-08 ausdrücklich vorgesehene Sperre, keine ausstehende Freigabefrage. Keine echten Testtermine/-teilnahmen oder Nachrichten erzeugt.

## Nachweise und manuell offen

Abschließender Lauf: **309 PHPUnit-Tests / 1.746 Assertions** auf PHP 8.4.14, keine Fehler/Fehlschläge; zwölf unveränderte bestehende OIDC-Deprecations, Lauf ohne Coverage-Treiber. PHP-Syntax für 151 Dateien, JavaScript-Syntax, eindeutige statische HTML-IDs, lokale Dokumentationslinks und `git diff --check` erfolgreich. Neue HTTP-/Domainfälle: Planungskonflikte und Abschlusssperre, Mengen 0/fehlend/ungeprüft, atomarer/idempotenter Wizard mit Rollback, Entwurfrevision und veralteter Prüfschritt, unbekannte Felder/ungültige Preise, gesperrter echter Adapter, Timeout nach bereits erfolgreicher Fake-Anlage, Abgleich ohne zweiten POST, doppelte externe ID, abgelaufene/noch laufende Lease, konkurrierende Worker und Rechtewiderruf. Reale Antworten gegen OpenAPI 1.4.0 geprüft. Bestehende Migration-/Abrechnungs-/Formular-/Buchungs-/Rechtetests weiter enthalten.

Manuell offen: D07/D12/M07/M12/Z01, fünf Schritte und Rücknavigation, Preiseingaben, Team-/Formularvorschläge, Draft-Fortsetzung, mobile Bottom-/Modalaktionen, Fokus/Fehleranzeige. Externe Feldkonfliktauflösung/Teilnahmeabgleich gehören 09 an. Kein Merge/Deployment.
