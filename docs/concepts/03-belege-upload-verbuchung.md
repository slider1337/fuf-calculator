# Konzept 3: Belege hochladen und automatisch der Reise zuordnen

**Status:** Entwurf, 22.09.2026. Keine Implementierung.

## Befund im Projekt

Ist-Ausgaben bestehen aus `trip_id`, Bezeichnung und Betrag. Es gibt keine Belegdatei, kein Einreicher-/Prüfstatus, keinen Belegtyp und keine Zuordnung zu einer geplanten Position. Die [Ist-Ausgaben-API](../../src/Infrastructure/Http/Controller/ActualExpenseController.php) speichert Beträge manuell; [Settlement-Service](../../src/Application/ActualExpenseService.php) summiert sämtliche Ist-Ausgaben **zusätzlich** zu den geplanten Kosten. Bereits vorhandene [CSV-Uploads](../../src/Infrastructure/Http/Controller/RegistrationController.php) betreffen Anmeldungen, nicht Belege.

## Nutzerablauf

1. Ein berechtigter Benutzer wählt eine **für ihn freigegebene Reise** und lädt Foto/PDF hoch, am Telefon direkt per Kamera.
2. Pflichtfelder: Beschreibung, Betrag, Währung (anfangs EUR), Belegdatum, Kostenart, optional Lieferant und „bezahlt von“. OCR kann Werte vorschlagen, darf Unsicherheiten anzeigen.
3. Uploadprüfung: Dateityp und tatsächlichen Inhalt prüfen, Größenlimit, Malware-Scan, privater Speicher außerhalb des Webroots, zufälliger Dateiname, nur autorisierter Download; Duplikate über Hash plus Rechnungsnummer/Betrag/Datum erkennen.
4. Klare Buchungsart: **zusätzliche Ausgabe** oder **Ist-Wert einer bereits geplanten Kostenposition**. Ein Zimmerbeleg über geplante Zimmerkosten darf nicht zusätzlich zum vollen Planwert summiert werden.
5. Sobald Pflichtangaben valide und keine Konflikte vorhanden sind, automatisch als Ist-Position dieser Reise buchen; andernfalls in „Klärung“. Korrektur/Storno als nachvollziehbare Änderung, kein lautloses Überschreiben.
6. Einreicher sieht eigene Belege und Status. Orga/Prüfer sieht je nach Recht alle, kann Zuordnung korrigieren. Abrechnungsteam sieht Beleg und Buchung **lesend**.

## Bedeutung von „automatisch verbucht“

Der erste Umfang ist die **interne Reiseabrechnung** im FUF-Kalkulator: gültige Belege erscheinen automatisch als Ist-Kosten. Das ist noch keine Zahlung, keine Prüfung steuerlicher Anforderungen und keine Buchung in ClubDesk/easyVerein/DATEV. Für eine externe Finanzbuchhaltung wären Kontenplan, Steuercodes, Schnittstelle und Freigabeprozess gesondert zu definieren. Bei fehlender Bestätigung verhindert der Status „Klärung“ fehlerhafte automatische Summen.

| Status | Sicht in der Abrechnung | Nächster Schritt |
| --- | --- | --- |
| Hochgeladen | Noch nicht in Summe | Prüfen/Information ergänzen |
| Gebucht | In Ist-Summe, genau einmal | Optional prüfen |
| Klärung/Duplikat | Noch nicht in Summe | Orga/Prüfer entscheidet |
| Storniert | Nicht in aktiver Summe; Audit bleibt | Ggf. neu einreichen |

### Kleine Upload-Skizze

| Winterfreizeit 2027 · Beleg einreichen |
| --- |
| [Foto aufnehmen oder PDF wählen] |
| Betrag: 128,40 € · Datum: 12.02.2027 |
| Kategorie: Verpflegung · Art: Zusätzliche Ausgabe |
| Bezahlt von: Tom · [Beleg einreichen] |
| Ergebnis: Gebucht / Klärung mit konkretem Grund |

## Daten und Rechte

Belegdatensatz mit Reise-ID, Einreicher-ID, Datei-Metadaten/Hash, Datum, Betrag in Cent, Kategorie, Status, Buchungsart und optional `planned_cost_item_id`. Buchungsdatensatz referenziert den Beleg eindeutig; Änderung oder Storno erzeugt Ereignis/Audit. Die bestehende `actual_expenses`-Tabelle muss im Konzept mit Altbeständen/fehlenden Dateien umgehen: manuelle Alt-Ausgaben bleiben sichtbar. Jeder Abruf prüft Reisefreigabe und Dateizugriff, jeder Schreibweg den Bearbeitungsumfang.

## Noch zu entscheiden

Welche geplanten Kostenarten sollen durch echte Belege ersetzt werden? Sind Teilbelege und mehrere Zahler möglich? Soll eine Person erst nach Freigabe buchen dürfen oder bei vollständigem, unverdächtigem Beleg sofort? Empfehlung: sofort bei eindeutiger Zuordnung, sonst Klärung; Finanzprüfung bleibt optional dokumentiert.
