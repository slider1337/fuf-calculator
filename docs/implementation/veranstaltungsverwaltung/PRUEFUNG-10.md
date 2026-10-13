# Schritt 10 – sichere lokale Mitglieder-API, Website-Gate bleibt geschlossen

Stand: 13.10.2026. Technische Implementierung auf PR #13; keine produktive Abnahme, kein Merge/Deployment.

## Implementiert

Separater `/integration/v1`-Vertrag ohne Calculator-Konto/Session; Service-HMAC plus kurzlebige Mitgliedsdelegation mit Issuer/Audience, Key-ID/Rotation, Method/Pfad/Bodybindung, atomarem Replay-Schutz und Service-Scopes. Eigene Buchungen paginiert auf 25 begrenzt, eigene/zugelassene Personen serverseitig geprüft; keine globale Antwortliste. Requests maximal 256 KiB, begrenztes Kontextfenster. JSON-Antworten inklusive Fehlern haben no-store.

Gemeinsamer BookingService verwendet eine eigene eng begrenzte BookingPolicy im Mitgliederablauf. Ein Mitglied erhält keine Orga-Rechte, Benutzer oder Session; Revisionen bleiben mit Actor-ID 0 als getrenntem Mitgliedsprinzipal plus member_audit nachweisbar. Names/Geburtsdaten werden niemals vom Browser übernommen. Profilfrische/Relationen im Directory-Port; keine Zuordnung über Subject/E-Mail. Dessen produktiver Adapter bleibt gesperrt.

Testclient/Routervertrag: Kontext/Termin-ID-Zuordnung → servervalidierte Vorschau → verbindlich lokal buchen/202 → echte Fake-Bestätigung je Person → eigene Antwortkorrektur → Teilstorno/202 mit externer Bestätigung → Formularwechsel mit ausdrücklich bestätigter Pflichtfrage/Ergänzung. Ältere Revisionen unverändert; gemeinsame Mengen nach Storno ungeprüft. Preisstand und Version erfordern erneute Bestätigung bei Konflikt. Nach Commit Wiederholung mit identischem operationKey/Inhalt liefert dieselbe Buchung, Provideränderungen lösen keine Duplikate aus.

Explizite Fristen/Freigaben je Reise, unabhängige Korrektur-/Stornofrist und Zugriffshorizont zur Aufbewahrung. Kein produktives Freischalten durch Konfiguration möglich, solange die Directory-/Participationadapter gesperrt sind. Nach Aufbewahrungshorizont 410 im Mitgliederweg; keine behauptete automatische Löschung.

Vollständige Konfiguration, Signing-Protokoll, Endpunkte, Beispielschema, Fehler-/Versionsregeln, Fristen und Übergang: [Mitgliedervertrag](../../integration/member-api.md). Offline Testclient: [member-test-client.php](../../../scripts/member-test-client.php), erzeugt Text und sendet nichts. OpenAPI 1.5.0 dokumentiert alle tatsächlichen Routen; doppelte bestehende YAML-Ankernamen wurden eindeutig benannt, damit auch strenge Parser den Vertrag lesen.

## Technische Prüfung

Gesamtlauf: **327 PHPUnit-Tests, 4.548 Assertions, keine Fehler/Fehlschläge**, PHP 8.4.14; 12 bestehende OIDC-Deprecations, kein Coverage-Treiber. 18 neue fachliche/API-Integrationstests. PHP-Syntax für 168 Dateien, JavaScript-Syntax aller Anwendungsskripte, strenges YAML mit 243 gültigen Schema-Referenzen, Dokumentationslinks und git diff --check erfolgreich. Neue Domain-/API-Integrationstests prüfen auch falschen Issuer/Audience, falsche Method/Pfad/Bodybindung, Ablauf/Replay, Signaturmanipulation, Scopeentzug, fremde Person/Buchung, gefälschtes Directory-Profil, Preis-/Formular-/Idempotenzkonflikt, echte Pflichtfehler, getrennte Sessiongrenze, Aufbewahrungs-/Fristsperre und gesperrte produktive Defaults. Echte lokale JSON-Antworten gegen die OpenAPI-Schemas geprüft, Fakes ausdrücklich keine Providerabnahme. Bestehende OIDC-Deprecations werden getrennt ausgewiesen.

## Nicht erfüllt / Schritt 11 blockiert

Die Abnahmematrix bleibt ehrlich: lokale Fachabläufe sind geprüft, aber echte Integration/Benachrichtigungsverhalten, Gastfreigaben, umfassende externe Konfliktauflösung und Lösch-/Anonymisierungsverfahren fehlen. Manuelle Calculator-Designabnahme sowie konsistente Bereitstellung/Migration noch offen. Die bestehenden Website-Login-/Personen-/Preisverträge wurden read-only geprüft; tatsächliche produktive Delegation muss nach Adapterimplementierung nachgewiesen werden.

Daher ist AUFGABE-10 nicht vollständig abgenommen und AUFGABE-11 bleibt am ausdrücklich dokumentierten Gate. Kein Umbau im fuf-website-Repository, kein neuer parallel aktiver Anmeldeweg, keine Mails, keine realen Testtermine/Teilnahmen, keine Browser-/Lighthouse-Automation. Für Websiteübergabe liegt der versionsgebundene Vertrag bereits bereit; eine betriebsfähige Integration wird damit noch nicht behauptet.
