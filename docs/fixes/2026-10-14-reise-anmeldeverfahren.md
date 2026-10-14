# Anmeldeverfahren je Reise: Clubdesk/CSV oder easyVerein

Nutzerauftrag 14.10.2026: bisherigen Clubdesk-/CSV-Ablauf je Reise behalten können; easyVerein mit Formular als separat wählbare Option. Ausgangsbasis master nach PR #16, Tree c1169e987514deae80dc6a5206b37e4c4f3ae0fa.

## Auswahl und Darstellung

- Neue normale Reisen starten mit **Clubdesk / CSV · bisheriger Ablauf**. Beim Anlegen unter Eckdaten alternativ **easyVerein · Anmeldung mit Zusatzformular** wählen. Der ausdrücklich für easyVerein gedachte Reise-/Terminwizard erstellt easyVerein-Reisen.
- Bei bestehenden Reisen steht **Anmeldeverfahren ändern** direkt unter dem Kopf. Ein eigener geschützter Endpunkt verlangt konkrete trip.manage-Rechte, aktuellen Revisionsstand, Bestätigung und Grund. Planung speichern kann das Verfahren nicht nebenbei ändern.
- Clubdesk/CSV: Anmeldungen wieder in der Planung (bei Personen ohne Planungsrecht weiterhin im zugänglichen Anmeldungs-Tab), bisheriger CSV-Import und manuelle Erfassung mit bestehender interner Abrechnung; keine Kontaktpflicht oder Zusatzfragen des neuen Buchungswegs. Kein easyVerein-Termin oder Mitglieder-API erforderlich. Die neuen Tabs Anmeldungen/Auswertung und Termin-/Übernahmefunktionen werden ausgeblendet, Teamrechte und Abschluss/Archiv bleiben verfügbar.
- easyVerein: zentrale Anmeldungen, versionierte Zusatzformulare, Auswertung, kontrollierte Planungsübernahme, Termin-/Teilnahmeabgleich und Mitgliederfreigabe. Der Formularstand bleibt optional. CSV-Schreiben und alte manuelle Erfassung sind serverseitig gesperrt; neue lokale Erfassung nutzt den versionierten Buchungsservice.
- Auswahl gilt ausschließlich für die konkrete Reise. Duplizieren übernimmt das Verfahren, kopiert aber weiterhin keine Anmeldungen oder externen Referenzen.

## Bestand und Wechsel

Migration **20261014210000** ergänzt registration_mode (clubdesk_csv/easyverein) und registration_mode_revision. Alte CSV-/manuelle Reisen bleiben Clubdesk/CSV. Bereits ausdrücklich vorbereitete neue Abläufe (gebundener Formularstand, Terminverknüpfung/-entwurf/-auftrag, neue verwaltete Buchung oder aktivierte Mitgliederfreigabe) bleiben easyVerein. Keine Zuordnung anhand Namen oder Inhalte; keine Änderung von Personen-IDs, Antworten, Preisen, Rechnungspositionen oder Finanzständen. Wiederholte Migration ist stabil; ungültige Verfahren werden auch in SQLite abgewiesen.

Wechsel alter CSV-Reise auf easyVerein erhält alle Altanmeldungen unverändert als historischen Bestand. Keine automatische Übertragung/Neuanmeldung dieser Personen. Weitere CSV-Imports sind dann gesperrt. Rückwechsel ist möglich, solange keine neuen verwalteten Buchungen, aktiven Gastfreigaben oder aktiven/unklaren Übertragungsaufträge vorliegen. In diesem Fall erklärt die Oberfläche die Sperre; keine automatische Löschung oder Rückumwandlung. Bei zulässigem Rückwechsel wird die Mitgliederfreigabe deaktiviert; gespeicherte lokale Entwürfe/Referenzen werden erhalten, aber nicht verarbeitet. Abgerechnete/archivierte Reisen erfordern vorherige reguläre Wiederöffnung/Wiederherstellung.

## Serverseitige Grenzen

Mode-Checks gelten für CSV-Import, alte manuelle Erfassung, neue Buchung, Mitgliederweg, Terminanlage/-änderung, Teilnahme-/Gastaktionen, Planungsübernahme und Hintergrundworker. Worker prüfen das Verfahren erneut am Verarbeitungspunkt; regelmäßiger Providerabgleich umfasst nur easyVerein-Reisen. Kein Providerzugriff beim normalen Öffnen einer CSV-Reise. Wechsel und Buchungsanlage laufen unter SQLite-Writerreservierung; eine laufende/unklare Übertragung kann nicht durch Wechsel in den anderen Modus umgangen werden. Mode-Änderung macht keine zusätzliche Provider-/Mailaktion.

OpenAPI **1.8.0** ergänzt registrationMode in Anlage/Detail/Liste und GET/PUT `/api/trips/{id}/registration-mode`. Mitgliederprotokoll 1.6.0 bleibt unverändert; CSV-Reisen sind dort nicht verfügbar. Vor Upgrade die vorhandene konsistente Sicherung/Migrationsreihenfolge verwenden. Keine Änderung an Clubdesk oder Website und keine echten Testteilnahmen/-mails.

## Prüfung

374 PHPUnit-Tests / 5.936 Assertions ohne Fehler/Fehlschläge auf PHP 8.4.14; 12 bestehende OIDC-Deprecations, keine neuen Warnungen, kein Coverage-Treiber. Neun neue Fälle / 132 Assertions: Default/alte manuelle Erfassung und wiederholter CSV-Import ohne Zusatzformular/Provider; explizite neue Buchung; unabhängige Reisen; Wechsel erhält IDs/Finanzwerte; Revision/Planungsbypass; neue Buchungen und laufende/unklare Aufträge sperren Rückwechsel; Gastfreigaben; Abschlusssperre/Archiv/Rechte; Duplikat ohne Buchungen; additive Bestandsmigration/idempotenter Lauf; Mitglieder-API und Worker im CSV-Modus gesperrt. Betroffene Planungsübernahme nach abschließender Mode-Sperre erneut geprüft: 19 Tests / 253 Assertions.

Frontend-Ereignisprüfung in Node: alte/neue Tabs und Formularbereiche, Anmeldungen als bestehender DOM-Knoten in Planung/Anmeldungen (kein doppelter Formularbaum), Kontaktpflicht nur neu; alte manuelle Erfassung ruft ausschließlich `/registrations` mit dem bestehenden Personenpayload auf. Alle JavaScript-Syntax, 202 PHP-Dateien, strenges YAML/271 Schema-Referenzen, Dokumentationslinks und git diff --check geprüft. Keine Browser-/Lighthouse-Automation; mobile/desktop Darstellung nach Deployment manuell prüfen. Kein Merge/Deployment ausgeführt.
