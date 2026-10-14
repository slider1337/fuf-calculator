# Mitgliedervertrag 1.5.1 – Calculator vor Website

Stand: 13.10.2026. Die Schnittstelle ist implementiert und mit Fakes geprüft. **Kein produktiver Mitgliederweg freigegeben.** `DisabledParticipationAdapter` bleibt fest gesperrt. Der echte lesende `EasyVereinMemberDirectory` ist implementiert und benötigt `EASYVEREIN_API_TOKEN`; ohne Token gibt es 503. Konfiguration, Session oder ein signierter Request umgehen diese Sperre nicht. Terminadapter aus Schritt 08 bleibt ebenfalls gesperrt. Keine echten easyVerein-Teilnahmen/Termine oder Nachrichten für Tests erzeugt.

## Identitätsnachweis und Vertrauen

Read-only geprüft: Website `slider1337/fuf-website`, `main`, Plugin `fuf-easyverein-login` 0.7.86. Bestehender Login liefert `fuf_easyverein_identity()['member_id']`; `includes/anmeldung-kontext.php` verwendet diese ID und liest `member/{id}` mit `related_members`, `related_member`, `editable_by_related_members` und `contact_details`. Die bestehende Registrierung prüft den Elternbezug und explizite Bearbeitungsfreigabe. E-Mail, Name und OIDC-Subject sind **keine Mitgliederreferenz** des Calculator-Vertrags.

Der konfigurierte Website-Server ist Vertrauensanker für den Mitglieds-Login. Er darf ausschließlich die Mitglieds-ID aus der geprüften aktuellen Login-Session signieren, nie eine Browser-ID. Calculator prüft über `MemberDirectoryPort` frisch die eigene Mitglieds-ID und bearbeitbare Familienbeziehungen; der Worker prüft den Personenbezug vor externem Schreiben erneut. Der lesende Directory-Adapter ist implementiert und mit fiktiven HTTP-Antworten geprüft; ein realer Ende-zu-Ende-Nachweis ist noch offen. Ohne sie gibt es 503, keine Ersatzidentität.

`/integration/v1/*` verwendet keinerlei Calculator-Session. Ein eingeloggter Admin ohne gültige Delegation erhält 401. Ein Website-Service ohne Mitgliedskontext kann nicht buchen. Mitgliedskontexte erhalten keine Orga-Rechte und keine Antwortlisten anderer Mitglieder.

## Konfiguration und Rotation

Nur serverseitige Umgebungsvariablen:

- `FUF_INTEGRATION_AUDIENCE`: exakte Zielkennung dieses Calculator, getrennt je Umgebung.
- `FUF_INTEGRATION_SERVICES`: JSON-Objekt je Service-ID mit `issuer`, `keys` (Key-ID → Geheimnis mit mindestens 32 Zeichen) und `scopes`: `member.read`, `member.write`.

Beispielstruktur (kein nutzbares Geheimnis):

```json
{"website":{"issuer":"https://website.example.invalid","keys":{"2026-10":"REPLACE_WITH_RANDOM_SERVER_SECRET_AT_LEAST_32_CHARACTERS"},"scopes":["member.read","member.write"]}}
```

Rotation: neuen Key auf beiden Servern hinterlegen, Signierer auf neue Key-ID wechseln, nach maximaler Delegationslaufzeit plus Zeitpuffer alten Key entfernen. Scopes und Keys werden bei jeder Anfrage aus Konfiguration gelesen; Entzug wirkt sofort. Secrets gehören weder in OpenAPI-Beispiele noch in WordPress-JavaScript, Browser-Storage, Git oder Logs. HTTPS ist bei Bereitstellung Pflicht; Reverse-Proxy muss Authorization erhalten und Request-/Antwortinhalte dieser Routen aus Logs ausschließen.

## Request-Signatur

Header: `Authorization: FuF <base64url-claims>.<hex-HMAC-SHA256>`.
HMAC über **genau den base64url-Teil**, mit Schlüssel aus `serviceId`/`kid`. Claims:

```json
{"serviceId":"website","kid":"2026-10","iss":"https://website.example.invalid","aud":"calculator-example","iat":1791920000,"exp":1791920060,"nonce":"UNIQUE_RANDOM_NONCE_PER_REQUEST","memberId":"11","method":"POST","target":"/integration/v1/trips/1/bookings","bodyHash":"SHA256_OF_EXACT_UTF8_REQUEST_BODY"}
```

Maximal 120 Sekunden Laufzeit, iat maximal 5 Sekunden in Zukunft; Member-ID ist positive Dezimalreferenz als String. Method, exakter Pfad **inklusive Querystring** und Hash des unveränderten Body sind gebunden. Nonce mindestens 16 Zeichen, atomar einmalig je Service; wiederholte Delegation 401. Auch bei fachlichem Fehler ist der Kontext verbraucht: Retry mit neuem Kontext und **demselben fachlichen operationKey**. Keine Browser-Tokens. Body maximal 256 KiB. Höchstens 120 noch gültige Kontexte je Service; Überlast 429. Das ist ein konservatives Fenster bis zu 120 Sekunden, keine garantierte 120/min-Quote.

Referenz-Signierer/Testclient: [scripts/member-test-client.php](../../scripts/member-test-client.php). Er erzeugt einen Request als Text, sendet nichts und verändert keine Daten. Im Integrationstest läuft dieser Vertrag durch den tatsächlichen Slim-Router.

## Endpunkte und Ablauf

OpenAPI ist maßgeblich: [openapi.yaml](../openapi.yaml), Version 1.5.1.

| Operation | Route | Ergebnis |
| --- | --- | --- |
| Terminzuordnung | GET `/integration/v1/events/{eventId}/trip` | Ausschließlich bestätigte stabile easyVerein-ID, keine Titel-/URL-Heuristik |
| Formular/Personen/Preise | GET `/integration/v1/trips/{id}/context` | Gebundene Version, geprüfte Familie, aktuelle Preisgruppen/Revision und Fristen |
| Vorschau | POST `/integration/v1/trips/{id}/preview` | Pflicht-/Sichtbarkeits-/Altersprüfung; keine Speicherung oder Provideraktion |
| Verbindlich anmelden | POST `/integration/v1/trips/{id}/bookings` | 202, lokale Buchung + Personenaufträge in einer Transaktion; noch kein Erfolgsversprechen |
| Eigene Buchungen | GET `/integration/v1/trips/{id}/bookings?after=…` | Maximal 25, nextCursor; keine globale Liste |
| Ergebnis | GET `/integration/v1/trips/{id}/bookings/{bookingId}` | Nur eigene Buchung, fachlicher und Sync-Status je Person |
| Korrektur/Ergänzung | PUT `…/bookings/{bookingId}/answers` | Revision, Grund und ausdrückliche Bestätigung; neue Pflichtfragen erforderlich |
| Teil-/Gesamtstorno | POST `…/bookings/{bookingId}/cancel` | 202; externe Bestätigung abwarten, kein vorgetäuschtes Storno |

Personen senden nur `memberReference`, `priceGroupId`, `expectedPrice`. Name/Geburtsdatum stammen aus dem frischen Directory-Profil. Antwortschlüssel in der Vorschau/Neuanmeldung: `member_<ID>`, anschließend stabile lokale Person-ID. Genau eine echte Preisgruppe je Person; ohne Gruppen beide Preiswerte null und keine Auswahl. Keine automatische Rechnung, keine behauptete Kostenlosigkeit. Kontakt/Eigentümer ist stets das anmeldende Vereinsmitglied.

Buchungsrequest: `operationKey`, `versionId`, `priceRevision`, `confirmed: true`, `persons`, `answers`. Preis als exakter normalisierter Dezimalstring; Formular und Preise neu prüfen bei 409. Nach Commit wird derselbe Key/Inhalt auch bei späterer Provideränderung wieder auf dieselbe eigene Buchung aufgelöst. Anderer Inhalt mit demselben Key: 409. Bereits offene/bestätigte Person derselben Reise wird nicht doppelt lokal angelegt. Kurzlebige Kontexte und fachliche Idempotenz erfüllen unterschiedliche Zwecke.

Bei Antworten nach Versionswechsel nur kompatible Feld-IDs weiterverwenden; neue Pflichtfragen und Bestätigung erneut einholen. Bereits gespeicherte Buchungen behalten ursprüngliche Version/Revisionen bis zur bewussten Ergänzung. Erfolgsanzeige ausschließlich bei realen bestätigten Personen, bei Teilfehlern oder pending/unknown Eingangsreferenz zeigen. API-Ausfall darf keinen alten externen Schreibweg auslösen.

## Freigaben, Fristen, Aufbewahrung

`/api/trips/{id}/member-settings` verlangt Terminfähigkeit; Änderung zusätzlich `trip.manage` und offene, nicht archivierte Reise. Der Nutzer hat festgelegt: **Änderungen und Stornos bis Anmeldeschluss**. Konfiguration benötigt nur `enabled` und `bookingUntil` (Unix-Zeitpunkt). Die beiden technischen Korrektur-/Stornospalten werden auf denselben Zeitpunkt gesetzt. Ein früherer aktueller easyVerein-Anmeldeschluss gilt ebenfalls; kein Verlängern durch veraltete lokale Konfiguration.

Es gibt keinen konfigurierbaren Aufbewahrungs-Ablauf und keine automatische Löschung. Die Reise lässt sich mit `PUT /api/trips/{id}/archive` archivieren und zurückholen (`archived`, bisheriges `archivedAt`, Grund). Das Archiv blendet sie aus der aktiven Liste aus; `GET /api/trips?archived=true` zeigt das Archiv. Direkter berechtigter Orga-Lesezugriff, eigene Mitgliederbuchungen, Antworten, Historie und Finanzstände bleiben erhalten. Änderungen/Neuanmeldungen und externe Aufträge sind gesperrt. Wiederherstellung hebt einen bestehenden Abrechnungsabschluss nicht auf. Archivierung ersetzt keine zusätzliche Löschaufgabe; eine solche ist nach Nutzerentscheidung nicht Bestandteil dieses Auftrags.

Gast-/Begleiterfreigaben sind bewusst noch nicht im Directory-Vertrag enthalten. Für deren spätere Freigabe sind Orga-Entscheidung, eindeutige Eltern-/Kontaktreferenz, Zuschlag und bestätigter Providervertrag erforderlich; eine Browser-Person ist kein Gastnachweis. Fakes mit Familienmitgliedern ersetzen diese Prüfung nicht.

## Website-Gate und Übergang

Schritt 11 bleibt nach [ABNAHME](../implementation/veranstaltungsverwaltung/ABNAHME.md) gesperrt: echte Termin-/Teilnahme-/Directory-Adapter, Benachrichtigungsverhalten, Gastvertrag, Konfliktklärung für Personen/Preise, manuelle Designabnahme und kompatible Calculator-Bereitstellung offen. Im Website-Repo gibt es keine Änderung dieses Auftrags. Bestehender Mitgliederlogin/Registrierungsweg bleibt in Betrieb.

Nach erfülltem Gate: erst Calculator migrationsfähig bereitstellen, dann pro bestätigter Reise genau einen Website-Schreibweg aktivieren. Formulardefinition zentral abrufen, keine zweite Verwaltung. Keine automatische Migration alter WP-Buchungen oder Namensmatches. Bei Rücknahme der UI bleiben Calculator-Buchungen erhalten. Die Referenzen W01/W02/W03 und die Website-Dateien sind als read-only Übergabe geprüft; keine automatische Browser-/Lighthouse-Abnahme.

Öffentlicher API-Abruf und Grenzen: [Prüfung vom 14.10.](easyverein-v3-review.md).
