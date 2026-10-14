# Grundlagen und technische Umsetzung

## 1. Quellen und geprüfter Stand

Geprüft: `master` Commit `3eb48fd0e37b0d2cb5b662aba761f05fd2360367`; offener Konzept-PR #7, Branch `docs/veranstaltungsverwaltung-formulare`, Ausgangsstand `5f8146367b13f105171ed05242b422ba58f6a204`; jüngste gemergte PRs #4–#6 (easyVerein-Login/Kontoverwaltung). Dieser Plan führt PR #7 weiter. Designquelle ist das übergebene ZIP `FuF-Calculator-Veranstaltungsverwaltung-Handover.zip`, vollständig unter `docs/design-handoff/veranstaltungsverwaltung/` aufgenommen.

| Vorhandener Bereich | Befund und Weiterentwicklung |
| --- | --- |
| PHP 8.4, Slim, DDD, SQLite | Beibehalten: Domainregeln, Application-Services/Ports, Infrastructure-Adapter |
| `migrations/`, `scripts/migrate.php`, `SqliteSchema` | Phinx-Migrationen sind maßgeblich; README-Verweis auf `database/schema.sql` ist veraltet |
| `config/routes.php`, `config/container.php` | Routen und DI erweitern, keine parallele Anwendung aufbauen |
| `AuthMiddleware`, `Session`, `User` | Middleware prüft primär Session; globale Rollen admin/user ersetzen keine Rechte je Reise |
| `Trip`, `TripService`, `TripListService`, `SqliteTripRepository` | Veranstaltungen/Reisefreigaben/Version/Status hinzufügen; bestehende Berechnung erhalten |
| `Registration`, `Participant`, `RegistrationService`, `SqliteRegistrationRepository` | Bereits Buchungsgruppe mit Personen; nur csv/manual, Pflicht-Zimmerkategorie. Personenobjekt liefert noch keine stabile DB-ID nach außen |
| `registration_participants` | Vorhandene Tabelle weiterentwickeln, **keine zweite parallele participants-Tabelle** |
| `trip_bookings` / `RoomBooking` | Kalkulationszeilen, nicht Mitgliederbuchungen. Nicht mit registrations gleichsetzen |
| `RegistrationBillingService`, `ActualExpenseService`, `PriceCalculatorService` | Interne Preis-/Ist-Berechnung erhalten; easyVerein-Rechnung davon getrennt |
| `templates/index.html.php`, `public/assets/js/app.js`, `public/assets/css/fuf.css` | Bestehende PHP-/Vanilla-JS-Oberfläche ergänzen, keine neue Framework-Pflicht |
| `showTab`, `openTrip`, `showListSection`, `routeForTrip` | Navigation erweitern, alte Reise-URLs und Planungs-/Abrechnungslinks erhalten |
| `docs/openapi.yaml`, `OpenApiContractTest`, bestehende Tests | Vertrag mit jedem Schritt erweitern und gegen reale Antworten prüfen |
| easyVerein-OIDC | Login und explizite Kontoverknüpfung vorhanden; kein Nachweis eines Termin-/Teilnahme-API-Adapters oder geprüfter Mitglieds-ID-Delegation |
| Abrechnungsabschluss | In Konzepten beschrieben, im geprüften Trip-Modell noch kein Abschlussstatus; daher Grundlage in Schritt 01 |

## 2. Verbindliche Fachgrenzen

Veranstaltung ist der dauerhafte Rahmen, Reise die Durchführung mit eigener Kalkulation und eigenem Team. Es entsteht keine zusätzliche Reisegruppenebene. Tagesveranstaltungen sind Reisen ohne Unterkunft/Nächte. Reise darf Entwurf ohne Termin sein; Anmeldung öffnen erfordert genau eine bestätigte easyVerein-Terminreferenz. Zusatzformular kann fehlen (etwa Tagesveranstaltung); dann keine erfundenen Zusatzfragen.

Calculator hält Formulare, Antworten, Revisionen, Rechte und Buchungsgruppen. easyVerein ist maßgeblich für Mitgliedsbeziehungen, aktuelle Teilnahme, Kapazität, Preisgruppen und Vereinsrechnung. WordPress bleibt der Mitglieder-Einstieg und hält später keine unabhängige Formulardefinition. Kein normales Mitglied benötigt ein Calculator-Verwaltungskonto. SSO gewährt keine Orga-Rechte.

Keine globalen Foto-/Allergiefelder, keine echten Beispieldaten, keine Mails. Ergänzungsanforderungen erscheinen später in „Mein Bereich“. Mockup-Preise, Namen und Statuszahlen sind Gestaltungsmaterial, keine Vorgaben für echte Veranstaltungen. Technische Herkunftserklärungen aus den Mockups (z.B. „Fragen kommen aus Calculator“) sind kein notwendiger Text für die Mitgliederoberfläche.

## 3. Datenmodell und Migration

Namen sind Umsetzungsvorschläge; endgültige Migration/Modelle werden im jeweiligen Schritt dokumentiert.

| Speicherbereich | Inhalt / Invariante | Schritt |
| --- | --- | --- |
| `events` | Name, Beschreibung, Archivierung; keine Löschung abhängiger Historie | 01/02 |
| `event_access`, `event_default_team`, `trip_access` | Konkrete Fähigkeiten, Vergabe/Widerruf; Standardteam nur Kopiervorlage | 01/02 |
| `trips` | nullable event_id für Bestand, Reiselebenszyklus, Registrierungsfreigabe, Abschlusszeit/Akteur; Versionsbindung später | 01/04 |
| `audit_events` | Akteur, Aktion, Ziel, Zeitpunkt, Grund, Korrelations-ID; keine Token oder vollständigen Antworten | 01 |
| `form_versions`, `form_fields` | Veranstaltung, Entwurf/Release/Archiv, optimistische Revision; immutable Release mit stabilen Feld-/Options-IDs | 03/04 |
| `trip_form_history` | Aktivierte Version und Zeitpunkt, Wechselakteur, ergänzungsbedürftige Buchungen | 04 |
| `registrations` | Bestehende Buchung: Kontaktreferenz, Herkunft, Formularstand, fachlicher Status, öffentliche Referenz, Idempotenz | 05 |
| `registration_participants` | Stabile bestehende ID, externe Mitglieds-/Gast-/Teilnahmereferenz, Preisgruppe, fachlicher und Sync-Zustand | 05/09 |
| `answer_revisions` | Buchung, optional Teilnehmer-ID, Version, Feld-ID, typisierter Wert, Akteur/Grund, Sequenz und Zeit | 05 |
| aktuelle Antwortreferenzen | Genau eine gültige Revision pro Buchung/Person/Feld; historischer Stand weiterhin lesbar | 05 |
| `calculation_snapshots` | Eingabestand, Mapping, Ergebnis, Quelle/Zeit, Benutzer; veralteter Stand erkennbar | 07 |
| `external_event_links` | Eindeutige Reise↔Termin-Bindung, letzter Vergleichsstand, feldweise Abweichungen | 08 |
| `sync_jobs` | Dauerhafte Operation, Idempotenz, Zielperson, Zustand, Versuche, Ergebnisreferenz | 08/09 |
| Integrationsidentitäten | Website-Service mit begrenzten Scopes und geprüfter Mitgliedsdelegation; geheimnisfreie Auditdaten | 10 |

Bestand additiv migrieren. Bestehende Reise- und Personen-IDs, csv/manual-Herkunft, Preispositionen und Abrechnungssummen erhalten. Keine automatische Zuordnung nach Namen und keine rückwirkenden Antworten. Alte Reisen zunächst ohne Veranstaltung zulassen und später gezielt zuordnen. Alte Buchungen heißen „Altanmeldung ohne Zusatzformular“; bestätigte lokale Altbestände ohne easyVerein-Referenz nicht fälschlich als extern synchronisiert ausgeben. Deren Statusübernahme im Migrationsprotokoll ausdrücklich festlegen.

Vor Rechteumstellung eine genehmigte Zuordnung der bestehenden Benutzer zu bestehenden Reisen verwenden. Weil heute kein Orga-Eigentümer modelliert ist, keine Besitzer raten. Als kompatible Ausgangsbasis kann der bisher zugriffsberechtigte Benutzerbestand explizit je Alt-Reise übernommen werden; diese Auswahl in Vorschau/Protokoll ausweisen und vom administrativen Einführungsprozess bestätigen lassen. Neue Benutzer erben diese Übergangsrechte nicht. Neue Reisen sind ab Anlage gezielt freigegeben. Kein stiller globaler Zugriffsbypass.

Neue stabile Personenreferenzen verlangen ein Update-Verfahren, das Personen nicht bei jeder Buchungsänderung löscht und neu anlegt. Bestehende CSV-Ersetzung darf keine neuen Buchungen/Antworten und keine externen Referenzen vernichten. „Löschen“ für neue bestätigte Buchungen wird fachliches Storno; endgültige Bereinigung ist gesondert. Migrationen auf Datenbankkopie prüfen, SQLite vor Upgrade sichern; Zurückrollen des Codes nach neuen Daten nur mit kompatiblem Schema oder gesicherter Wiederherstellung.

## 4. Rechte und Abschlusssperre

Fähigkeiten als explizite Berechtigungen prüfen, nicht nur Tabs ausblenden. Vorschlag: `event.view/manage`, `form.manage/publish`, `access.manage`, `trip.view/manage`, `registration.view/manage`, `answers.view/correct/export`, `analytics.view`, `calculation.manage`, `settlement.view/close`, `term.manage`. Veranstaltungspflege allein gibt keine Antwortrechte aller Reisen. Reiseliste und Veranstaltungskennzahlen enthalten nur erlaubte Reisen; reine Terminpflege und Abrechnungsteam erhalten keine Zusatzantworten.

Bei jedem Lesen/Ändern/Export und jeder Statistik Zielreise prüfen; Teilnehmer-/Buchungs-/Ausgaben-ID zusätzlich an Zielreise binden. Bestehende Update/Delete-Pfade sind eingeschlossen. Widerruf ab nächster Anfrage wirksam. Administrative Rechtevergabe nachvollziehbar; ein Admin ohne entsprechende fachliche Zuweisung bekommt nicht automatisch alle Antworten.

„Abgerechnet“ friert Finanzwerte ein: Planung, Ist-Ausgaben, Abrechnungsneuberechnung, relevante Teilnehmer-/Zimmeränderung und Übernahme sperren. Rein nichtfinanzielle Antwortkorrekturen sind nur mit Antwortrecht, Grund und Revision erlaubt. Versehentliche Finanzwirkung verhindern. Externe Änderungen nach Abschluss als Konflikt anzeigen; keine stille Neuberechnung. Wiederöffnung nur als gesonderte berechtigte, begründete und auditierte Aktion, nicht im normalen Speichern.

## 5. Formulare und Antworten

Pro Veranstaltung eine Formularfamilie. Entwürfe mit Revisionsnummer gegen konkurrierendes Überschreiben schützen. Veröffentlichung nach serverseitiger Prüfung: stabile IDs, gültige Optionen/Regeln, keine zyklischen/ungültigen Sichtbarkeitsbezüge und nur erlaubte Mappingziele. Release unveränderlich auch über direkte API; Archivierung ändert keine historische Definition.

Textkorrektur gleicher Bedeutung kann ID behalten. Typ/Einheit/Bedeutung/Geltung ändern erzeugt neue ID. Bedeutung kann der Server nicht automatisch erraten: Editor verlangt bewusste Angabe der semantischen Änderung; Typ/Einheit/Geltung erzwingen sie automatisch. Bedingungen zunächst nur Kind/Erwachsener, Kinder in Buchung und vorherige Auswahl; keine ausführbaren Formeln.

Antwort gilt pro Buchung oder stabiler Person. Revisionen append-only mit aktueller Referenz; Version und Bestätigungswortlaut nachweisbar. Zahlen als definierte Dezimalwerte speichern/aggregieren (kein Float-Zufall bei Summen), Ausgabe lokalisiert. `false`, 0, leer, verborgen, nicht abgefragt und ungeprüft bleiben unterscheidbar. Hinweis/Abschnitt speichern keine Antwort. Server prüft Pflicht, Grenzen, Optionen, Personenbezug und Sichtbarkeit. Änderungen an Personen lösen erneute Geltungs-/Pflichtprüfung aus.

Neue Buchung mit veralteter Version liefert Versionskonflikt statt stiller Annahme: alter Stand wird zum Erhalt kompatibler Eingaben ausgewertet, neue aktive Version ausdrücklich neu bestätigt. Bestehende Buchungen bleiben auf gespeichertem Stand; Ergänzung kann eine zusätzliche versionierte Antwortrevision erzeugen, ohne alten Wortlaut zu überschreiben.

## 6. Buchungs- und Synchronisationsablauf

Fachstatus: in Bearbeitung, bestätigt, teilweise bestätigt, storniert, Klärung nötig. Sync separat: ausstehend, synchronisiert, Fehler; unklarer externer Ausgang zusätzlich als Klärfall mit gesperrter Neuanlage. UI zeigt je Person Ergebnis. Eine lokal gespeicherte Buchung ist noch keine bestätigte externe Teilnahme.

Lokal eine Transaktion für Buchung/Personen/Antworten/Audit/ausstehenden Auftrag. Externe Aufrufe erst danach, niemals in offener SQLite-Schreibtransaktion. Eindeutige Operation und Request-Hash: Wiederholung mit identischem Inhalt gibt dieselbe Buchung zurück; gleicher Schlüssel mit anderem Inhalt ist Konflikt. Worker mit atomarer Übernahme/Lease und kontrollierter Wiederholung; nach Absturz nur sichere offene Operationen fortsetzen.

Timeout bei Terminanlage/Teilnahme: vor weiterem Schreiben Ergebnis abgleichen. Nicht voraussetzen, dass easyVerein einen Idempotenzschlüssel unterstützt. Ohne beweisbare Referenz oder eindeutigen Nachweis keine blinde Neuanlage. Wiederholung nur für gescheiterte Person; bereits bestätigte Personen unverändert. Kapazitäts-/Preisprüfung erneut am verbindlichen Schreibpunkt; externe Ablehnung erzeugt Teilfehler, keine simulierte Bestätigung.

Teilstorno entfernt persönliche Antworten aus regulärer Auswertung. Buchungsfelder bleiben bei mindestens einer bestätigten aktiven Person relevant; Mengen/Bestätigungen mit geändertem Personenbezug als ungeprüft ausweisen, bis bewusst korrigiert/bestätigt. Nicht proportional schätzen. Direkte externe Teilnahme mit fehlender Kontaktzuordnung zunächst separater Klärfall, keine erratene Familienbuchung.

## 7. Auswertungsvertrag

Ein gemeinsames Filter-/Lesemodell für Kennzahlen, Detail und Export: Reise(n), Status, Version, Feldserie, Zeitpunkt. Standard nur bestätigt und aktiv. Andere Statussicht als nicht reguläre Summe sichtbar beschriften. Veranstaltungsgesamtwert zählt Teilnahmen/Buchungen über Reisen, keine einzigartigen Mitglieder.

N = anwendbare Buchungen/Personen im Filter einschließlich fehlender Antwort. Nicht anwendbar und in gespeicherter älterer Version nicht abgefragt separat ausweisen. Altbestand ohne Definition als Zusatzangaben fehlen/Altanmeldung führen, nicht als 0. Ungeprüfte Mengen nicht als geprüfte reguläre Summe ausgeben: geprüfte Summe und ungeprüfter Betrag/Anzahl getrennt; Beispielzahlen der Mockups dürfen dabei korrigiert werden. Antwortabdeckung nennt gültig beantwortet, fehlt und ungeprüft ausdrücklich.

Checkbox: angehakt / nicht angehakt / fehlt, Ratio X von N. Ja/Nein: beide gültig. Zahlen: Summe mit Einheit. Auswahl: Anzahl je stabiler Option, Mehrfachauswahl kann N übersteigen. Text/Datum: Liste. Revisionen nicht mehrfach zählen. Versionsübergreifend nur kompatible Feldserie. Drilldown und Export müssen dieselben Zähler reproduzieren. CSV-Formelinjektion bei Texten neutralisieren; leere Werte bleiben leer. Druck/CSV und ggf. XLSX haben dieselben Rechte.

## 8. Integration – erst Calculator fertigstellen

Geplante Routen in OpenAPI beim jeweiligen Schritt konkretisieren. Kein vorzeitiges Freischalten öffentlich anonymer Verwaltungsendpunkte. Backendbereiche: Veranstaltungen/Teams, Formularentwurf/Release/Versionen, Buchungen/Antwortrevisionen, Aggregate/Export, Planungsübernahme, Termin-/Teilnahmeaufträge sowie später getrennte Mitgliederoperationen.

Website-Integration separat zur Calculator-Session authentifizieren. Vorgabe: serverseitiger Service-Nachweis plus kurzlebiger, signierter/prüfbarer Mitgliedskontext mit Zielsystem, Aussteller, Ablauf und Replay-Schutz. Mitgliedsreferenz stammt aus nachgewiesener easyVerein-Identität; Familienbeziehungen serverseitig neu prüfen. Konkrete Art der Delegation in Schritt 10 mit dem bestehenden Website-Login belegen, keine frei behauptete Browser-ID und keine automatische Zuordnung per E-Mail. Keine Browsergeheimnisse.

Mitgliedervertrag muss vor Websitebau vollständig funktionieren: Formulardefinition/Version, zulässige Personen/Preisgruppen, Validierung, Vorschau, verbindliche Gruppenbuchung, eigene Buchung lesen/ändern/stornieren/ergänzen, Ergebnis je Person. Fehlerfälle 401/403/404, Feldfehler 422, Versions-/Preis-/Idempotenzkonflikt 409 und Auftrag angenommen 202 dokumentieren. Vorschau ist kein externer Schreibauftrag. Verbindliches Absenden bestätigt geltenden Formular-/Preisstand.

## 9. Entscheidungen und Einführung

| Entscheidung | Spätester Schritt | Regel bis zur Klärung |
| --- | --- | --- |
| Alt-Reiserechte übernehmen | 01 | Vorschau und explizite Zuweisung; keine erratenen Eigentümer |
| Korrektur-/Stornofristen und Aufbewahrung | 05/10 | Nutzerentscheidung 14.10.: Änderungen/Storno bis Anmeldeschluss; Reisen archivieren/wiederherstellen. Kein zusätzlicher automatischer Lösch-/Ablaufauftrag. |
| API-Schreibfähigkeit und Benachrichtigungen | 08/09 | Nur Adapter/Fakes; reale Operation mit unvermeidbarem Mailversand gesperrt |
| Abgleichintervall/Worker-Aufruf | 09 | Konfigurierbar, begrenzte Batches; Server-Cron statt GitHub-Sync. Intervall nach dokumentiertem API-Budget wählen |
| Externe Preisänderung | 09/10 | Konflikt und erneute bewusste Preisbestätigung; kein rückwirkendes automatisches Umbepreisen |
| Website-Mitgliedsdelegation | 10 | Integration gesperrt, bis geprüfter Identitätsnachweis und negative Zugriffstests vorliegen |
| Website-Terminzuordnung/Übergang | 10/11 | Stabile easyVerein-ID; kein Match nach Titel/URL allein; keine automatische Migration alter WP-Anmeldungen |

Schritte separat einführen, neu gebaute Funktionen bis zur Vollständigkeit entsprechend sperren. Ein Testclient/Fakes und fiktive Seeds machen Buchungen/Auswertung schon vor WordPress prüfbar; Demonstrationsdaten ausschließlich in Tests/Entwicklungsumgebung. Kein Success-Screen mit erfundenem externem Ergebnis.

Vor Schritt 11 [Abnahme](ABNAHME.md) dokumentieren, OpenAPI versionieren und Calculator kompatibel bereitstellen. Danach Website pro eindeutig zugeordneter Reise umstellen. Bestehende Website-Buchungen nicht gleichzeitig von altem und neuem Schreibweg verändern lassen. Rücknahme neuer UI erhält eingegangene Calculator-Buchungen; kein automatischer Wechsel auf alten Schreibweg bei API-Ausfall.
