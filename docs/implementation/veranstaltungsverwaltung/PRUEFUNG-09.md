# Schritt 09 – technische Grundlage, keine produktive Abnahme

Stand: 13.10.2026. Grundlage ist PR #13, Tree `0b1711c38c69521a41f6fb77803d1bd9c80bb9e4`. Aktueller master `ff156ae` enthält PR #11; #12/#13 sind noch offen. Dieser PR baut auf #13 auf. Prüfungen ausschließlich mit fiktiven Mitgliedern und In-Memory-Providern.

## Implementiert

- Additive Migration `20261014020000`: stabile externe Personen-/Preisreferenzen, dauerhafte Teilnahmejobs, Lease/Backoff, getrennte Abgleichbeobachtungen/Klärfälle und Mitgliedergrundlagen. Bestand bleibt `local_only`; keine geratenen Zuordnungen, keine rückwirkenden Antworten, keine automatische externe Bestätigung.
- Atomare Personenaufträge mit fachlichem Schlüssel/Hash, eindeutiger aktiver Personenoperation. Individuelle Ablehnung ist wiederholbar; bestätigte Personen bleiben unangetastet. Externe Calls erfolgen außerhalb SQLite-Transaktionen.
- Nach Timeout/Leaseablauf ausschließlich Abgleich. Unklarer Ausgang bleibt auch nach Rechteentzug/Abschluss reserviert: kein neues register und kein gefährlicher Wechsel zurück zu pending. Fehlende Beweise sind keine Erlaubnis zu einem weiteren POST.
- Worker prüft Freigaben/Abschluss und aktuelle Kapazität, Frist, Preisgruppen/Preis. Mitgliederaufträge prüfen zusätzlich die aktuelle Familienfreigabe. Ergebnisse müssen Personen-/Preisreferenzen beweisen. Keine Providerfehlermeldungen oder Token in gespeicherten Errors.
- Externe Stornos/Statusänderungen über stabile externe ID und passende Mitgliedsreferenz; aktuelle Buchungsrevision und gemeinsame Antwortmengen werden bei Personenentfall angepasst. Der Planungs-Quellhash erkennt Änderungen. Bei Abschluss oder Preisabweichungen nur Konflikt, keine stille Finanzänderung. Lokales Storno einer externen Teilnahme wird serverseitig auf den externen Auftragsweg verwiesen.
- Read-only Abgleich speichert aktuelle Beobachtung, feldweise Abweichungen und direkte externe Teilnahmen als Kontakt-Klärfälle. Keine Namens-/Familienvermutung oder erfundene Antworten. Orga kann einzelne Terminfelder bewusst als geprüfte externe Basis bestätigen; diese Aktion ändert weder Reiseplanung noch Buchungspreise.
- Team-&-Termin-Oberfläche: eigene Jobzustände, pro Person wiederholen, Abgleich, feldweiser Konfliktvergleich und begründete Bestätigung; lokale Buchungsdetails können Personenaufträge vorbereiten. Unklare Ergebnisse bleiben sichtbar.

## Prüfung

`MemberParticipationApiTest` durchläuft tatsächliche Router-/Middleware-/Service-/SQLite-Pfade. Nachgewiesen: 3/4 bestätigt und nur Person 4 wiederholen; Timeout mit vorherigem externer Erfolg und abgelaufene Lease ohne zweiten POST; Preis-/Kapazitätsprüfung; direkte externe Teilnahme ohne geratene Buchung; feldweise Konflikte mit Revisionsschutz; externes Storno; Abschlussschutz; unbekannte Operation nach Freigabeentzug weiterhin gegen Neuanlage reserviert. Reale lokale Antwortformate gegen OpenAPI 1.5.0 geprüft. Kein echter Provider-Nachweis.

Gesamtlauf und Syntaxnachweise siehe [PRUEFUNG-10](PRUEFUNG-10.md). Manuelle D08/D09/D12 und M08/M09/M12/M14/Z01-Abnahme bleibt offen. Die Original-HTMLs und Designregeln wurden gelesen; keine Browser-/Lighthouse-Automation.

## Betrieb und verbleibende Pflichtkriterien

Vor Upgrade SQLite konsistent sichern; Migration nach 20261014010000 ausführen. Server-Cron, **keine GitHub-Synchronisation**:

```sh
php scripts/participation-worker.php 10
```

Batch 1–25 über Argument, Standard 10; Lease 300s, Abgleich-Backoff 60–3600s. Kein Dauerprozess, keine automatische Installation eines Cronjobs. Nach Adapterfreigabe vorerst höchstens einmal/min, maximal 10 Personen je Lauf; gemeinsames API-Budget einschließlich Website-/Termin-/Directoryaufrufen und Pagination im echten Adapter begrenzen. Offizielle easyVerein-Dokumentation nennt 100 Requests/min; das ist keine Zusage eines separaten Calculator-Budgets. Ohne Adapter ist runOne ein No-op.

**Offen:** produktiver ParticipationPort samt feldweisem Schreib-/Storno-/Benachrichtigungsvertrag; echte Lesesnapshots/Pagination/Versionsbezug; Gast-/Begleitervertrag inklusive Orga-Entscheidung; explizite Zuordnung direkter Teilnahmen und Korrektur von Personen-/Preiskonflikten; externes Übertragen lokaler Terminfeldänderungen. Der gesperrte Adapter hat weder HTTP-Client noch Credentials. „Terminstand bestätigen“ ist kein Provider-PATCH. Diese Punkte sind keine erfolgreiche Abnahme von Schritt 09.

Quellen/Prüfgrenze: [easyVerein API](https://easyverein.com/api/documentation/), [v3.0](https://easyverein.com/api/v3.0/documentation), [API-Hilfe](https://hilfe.easyverein.com/en/articles/360898). Die erste Abrufmethode lieferte nur einen unvollständigen Textauszug. Am 14.10. wurde die öffentlich eingebettete OpenAPI vollständig ausgelesen; der fehlende öffentliche Zugang ist damit widerlegt. Die Benachrichtigungsfreiheit aller Operationen und fehlende Detailschemas sind weiterhin gesondert zu prüfen. Keine echten Aufrufe zum Erproben.

Aktualisierung 14.10.: öffentliche vollständige API-Doku erfolgreich ausgelesen; lesender Mitglieder-Adapter implementiert. Gemeinsamer Anmeldeschluss und Reise-Archivierung gemäß Nutzerentscheidung, kein zusätzlicher Lösch-/Ablaufauftrag. Siehe [Folgeprüfung](PRUEFUNG-14-10.md) und [Providerprüfung](../../integration/easyverein-v3-review.md).


Aktuelle Fortsetzung: [Abschlussprüfung](PRUEFUNG-ABSCHLUSS.md) mit feldweiser Terminbearbeitung, Mitgliederfreigabe-Oberfläche und vorbereiteten Serverworkern/Sicherung. Historische fehlende Implementierungen dieses Protokolls wurden inzwischen ergänzt; manuelle/echte Abnahme und tatsächliche Bereitstellung bleiben offen.
