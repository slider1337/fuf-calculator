# Konzept 3: Belege hochladen und automatisch der Reise zuordnen

**Status:** Entwurf, 22.09.2026. Keine Implementierung.

## Befund im Projekt

Ist-Ausgaben bestehen aus `trip_id`, Bezeichnung und Betrag. Es gibt keine Belegdatei, kein Einreicher-/Prüfstatus, keinen Belegtyp und keine Zuordnung zu einer geplanten Position. Die [Ist-Ausgaben-API](../../src/Infrastructure/Http/Controller/ActualExpenseController.php) speichert Beträge manuell; [Settlement-Service](../../src/Application/ActualExpenseService.php) summiert sämtliche Ist-Ausgaben **zusätzlich** zu den geplanten Kosten. Bereits vorhandene [CSV-Uploads](../../src/Infrastructure/Http/Controller/RegistrationController.php) betreffen Anmeldungen, nicht Belege.

## Nutzerablauf

1. Ein berechtigter Benutzer wählt eine **für ihn freigegebene Reise** und lädt Foto/PDF hoch, am Telefon direkt per Kamera.
2. Pflichtfelder: Beschreibung, Betrag, Währung (anfangs EUR), Belegdatum, Kostenart, optional Lieferant und „bezahlt von“. OCR kann Werte vorschlagen, darf Unsicherheiten anzeigen.
3. Uploadprüfung: Dateityp und tatsächlichen Inhalt prüfen, Größenlimit, Malware-Scan, privater Speicher außerhalb des Webroots, zufälliger Dateiname, nur autorisierter Download; Duplikate über Hash plus Rechnungsnummer/Betrag/Datum erkennen.
4. Klare Buchungsart: **zusätzliche Ausgabe** oder **Ist-Wert einer bereits geplanten Kostenposition**. Ein Zimmerbeleg über geplante Zimmerkosten darf nicht zusätzlich zum vollen Planwert summiert werden.
5. Sobald Pflichtangaben valide und keine Konflikte vorhanden sind, automatisch als Ist-Position dieser Reise buchen; andernfalls in „Klärung“. Die **fachliche Belegfreigabe** darf nur durch Orga-Mitglieder dieser Reise erfolgen; automatische interne Buchung und Freigabestatus sind getrennte Vorgänge.
6. Einreicher sieht eigene Belege und darf sie vor dem Abschluss der Reise auch nach Buchung korrigieren oder löschen. Die Buchung wird dabei atomar neu berechnet oder storniert; Änderung/Löschung und frühere Werte bleiben im Audit nachvollziehbar.
7. Orga sieht Abrechnung und alle Belege der eigenen Reise und darf sie freigeben. Abrechnungsteam sieht Belege/Buchungen nur lesend.
8. Nach dem neuen Reisestatus **abgerechnet** sind Upload, Korrektur, Löschung und Freigabe gesperrt; auch manuelle Ist-Ausgaben und sonstige abrechnungswirksame Änderungen werden gesperrt.

## Bedeutung von „automatisch verbucht“

Festgelegt ist **ausschließlich die interne Reiseabrechnung** im FUF-Kalkulator: gültige Belege erscheinen automatisch als Ist-Kosten. Es findet keine Übergabe an ClubDesk, easyVerein oder andere Buchhaltungen statt. Ein unvollständiger oder widersprüchlicher Beleg bleibt in „Klärung“ und beeinflusst die Summe nicht. Die Orga kann vor dem Abschluss fachlich freigeben oder eine Korrektur anstoßen.

| Status | Sicht in der Abrechnung | Nächster Schritt |
| --- | --- | --- |
| Hochgeladen | Noch nicht in Summe | Prüfen/Information ergänzen |
| Gebucht | In Ist-Summe, genau einmal | Orga kann fachlich freigeben |
| Klärung/Duplikat | Noch nicht in Summe | Orga/Prüfer entscheidet |
| Gelöscht/Storniert | Nicht in aktiver Summe; Audit bleibt | Vor Abschluss ggf. neu einreichen |

### Kleine Upload-Skizze

| Winterfreizeit 2027 · Beleg einreichen |
| --- |
| [Foto aufnehmen oder PDF wählen] |
| Betrag: 128,40 € · Datum: 12.02.2027 |
| Kategorie: Verpflegung · Art: Zusätzliche Ausgabe |
| Bezahlt von: Tom · [Beleg einreichen] |
| Ergebnis: Gebucht / Klärung mit konkretem Grund |
| Eigener Beleg: [Korrigieren] [Löschen] · nur vor „Abgerechnet“ |

## Daten und Rechte

Belegdatensatz mit Reise-ID, Einreicher-ID, Datei-Metadaten/Hash, Datum, Betrag in Cent, Kategorie, Status, Buchungsart und optional `planned_cost_item_id`. Buchungsdatensatz referenziert den Beleg eindeutig; Korrektur oder Löschung erzeugt ein Ereignis/Audit und passt genau eine aktive Buchung an. Die bestehende `actual_expenses`-Tabelle muss im Konzept mit Altbeständen/fehlenden Dateien umgehen: manuelle Alt-Ausgaben bleiben sichtbar. Jeder Abruf prüft Reisefreigabe und Dateizugriff, jeder Schreibweg Einreicher/Eigentum, Reisestatus und Berechtigung.

**Neuer Reisestatus `abgerechnet` (umzusetzen):** Bestehende Reisen starten als offen; Orga schließt nach Prüfung mit Zeitpunkt, Benutzer-ID und eingefrorenem Abrechnungsstand ab. Die Statusänderung und alle Buchungsschreibwege prüfen denselben Status innerhalb einer Transaktion, damit parallele Uploads/Korrekturen den Abschluss nicht unterlaufen. Nach Abschluss bleiben Belege, Historie und Abrechnung lesbar. Falls Wiedereröffnung nötig ist, nur durch Orga mit Begründung und protokollierter neuer Abrechnungsversion; vorerst nicht als stiller Standardweg. Geplante Kosten, Anmeldungen und manuelle Ist-Ausgaben dürfen nach dem Abschluss die Abrechnung ebenfalls nicht mehr verändern.

## Noch zu entscheiden

Welche geplanten Kostenarten sollen durch echte Belege ersetzt werden? Sind Teilbelege und mehrere Zahler möglich? Festgelegt ist die automatische interne Buchung bei eindeutiger Zuordnung; die Orga-Freigabe bleibt ein eigener, sichtbarer Schritt. Unklare Belege gehen in „Klärung“.
