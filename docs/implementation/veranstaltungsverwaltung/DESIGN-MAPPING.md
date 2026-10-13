# Design-Zuordnung und Oberflächenregeln

Die gesamte Originalübergabe liegt unter [veranstaltungsverwaltung](../../design-handoff/veranstaltungsverwaltung/START-HIER.md). Referenzen sind über [mockups/index.html](../../design-handoff/veranstaltungsverwaltung/mockups/index.html) erreichbar. D/M/W/Z/K sind Dateikürzel, keine produktiven Routen.

| Referenz | Funktion | Umsetzung |
| --- | --- | --- |
| Main / index | Einstieg in 35 Referenzansichten | Dokumentation; kein zusätzlicher Produkt-Menüpunkt |
| D01 / M01 | Veranstaltungsliste, Karten/Tabelle, Archiv | 02 |
| D02 / M02 | Veranstaltungsdetail und Reisen | 02, Version/Terminstatus später 04/08/09 |
| D03 / M03 / M04 | Feldliste und Feldbearbeitung | 03 |
| D04 / M05 | Formularvorschau mit Beispielpersonen | 03 |
| D05 | Versionshistorie, Vergleich, Veröffentlichung | 04; Mobilvergleich eigene Scrollfläche |
| D06 / M06 | Reiseversionswechsel und Ergänzung | 04, betroffene Buchungen 05, Mitgliederanzeige 11 |
| D07 / M07 | Fünfschritt-Wizard Reise/Termin | 08 auf lokaler Reise-/Team-/Formulargrundlage |
| D08 / M14 | Reiseübersicht, Status, Klärfälle, Formular | 02 als Grundansicht; 04/05/07/09 liefern echte Daten |
| D09 / M08 / M09 | Buchungsliste/Detail, Personen, Revisionen | 05, externe Teilfehler 09 |
| D10 / M10 | Auswertungen und Filter | 06 |
| D11 / M11 | Antwort-Drilldown mit gleichen Filtern | 06 |
| D12 / M12 | Team/Termin und feldweiser externer Konflikt | 02 Team; 08/09 Termin/Abgleich |
| D13 / M13 | Standardteam / Reiseteam | 02 |
| D14 / Einstieg M14 | Planungsübernahme Geplant→Angemeldet | 07 |
| D15 | Veranstaltungsübergreifende Reisen | 02; responsive Karten nach M02/M14, keine separate mobile D15-Referenz |
| W01 / W02 | Mitgliederformular Desktop/Mobil | ausschließlich 11 |
| W03 | Website-Fehler, neue Version, Ergänzung, Ergebnis | ausschließlich 11, Vertrag in 10 |
| Z01 | Calculator-Leer-/Fehler-/Sperrzustände | jeweiliger Schritt; abschließende Abnahme 10 |
| K01 | Komponenten, Feldtypen, Status und Diff | Basis ab 02, je Fachschritt erweitern |

## Umsetzung in der bestehenden Oberfläche

Calculator-Tokens aus DESIGN.md/fuf.css weiterverwenden. Neues Rot nur für Fehler/Klärung (#FBE9E4 / #9B2C2C), nie für Finanzwerte. Zwei getrennte Statuschips für Buchung und Sync. Kennzahl nennt Einheit/Grundgesamtheit und Abdeckung, keine Erklärung ausschließlich als Tooltip. Entfernte Felder im Versionsdiff durchgestrichen und beschriftet. Externe Aktionsbox nur im letzten Prüfschritt und mit wirklicher Operation.

Hauptnavigation Veranstaltungen · Reisen · Mein Konto; Benutzer/Globale Einstellungen nur berechtigt. Veranstaltungs-Tabs Reisen · Formular · Standardteam · Einstellungen. Reise-Tabs Übersicht · Planung · Anmeldungen · Auswertung · Abrechnung · Team & Termin. Bestehende Reise-Links/Planung/Abrechnung und dirty-Dialog erhalten. Ungespeicherte Änderungen nicht beim Tabwechsel verwerfen. Altes NAVIGATION.md beschreibt den früheren Zwei-Tab-Zustand; die sechs Tabs aus der neuen Übergabe gelten für die Erweiterung, bestehende Sticky-/Kontextregeln bleiben.

Planung/Abrechnung werden nicht neu gestaltet. Bestehende Anmeldungsdarstellung innerhalb der Planung in Schritt 05 auf gemeinsamen Buchungsbestand umstellen; keine zwei konkurrierenden Listen oder doppelte Erfassungswege. Neuer Tab wird zentraler Einstieg, Planungslinks verweisen darauf; vorhandene Preis-/Ist-Ergebnisse bleiben verfügbar.

Mobile Reise-Tabs horizontal scrollbar (46 px), Hauptaktionen unten mit safe-area-Abstand; Inhalt nicht überdecken. Feldreihenfolge per beschrifteten 48-px-Buttons. Editor sequenziell, Buchungen als Karten, Detaildialog als Vollbild-Sheet mit Fokus/Schließen/Rückkehr. Vergleichstabellen allein scrollbar, nicht gesamte Seite. Tastatur, Labels, Fehlersammelhinweis/Fokus und ausreichende Touchflächen gehören zur Umsetzung.

## Design-Lücken und fachliche Abweichungen

Veranstaltungs-Einstellungen, Mein Konto und Abrechnung sind in der Übergabe teils nur Platzhalterlinks auf Z01. Keine neue Abrechnungsseite daraus erfinden. Einstellungen brauchen reale Name/Beschreibung/Archiv-Aktionen im bestehenden Komponentenstil. Mein Konto bleibt vorhandene Kontoverwaltung.

D10/M10/D11 zeigen teilweise ungeprüfte Mengen gleichzeitig in Zahlen, die wie reguläre Summen aussehen. Maßgeblich ist der getrennte Auswertungsvertrag in GRUNDLAGEN.md: geprüfte Summe und ungeprüft separat. D10s Formularvarianten sind alternative Demo-Datensätze, kein Umschalter, der im Produkt Fragen/Antworten semantisch ersetzt. W01/W02 enthalten Skikursfragen neben v3-Angaben, obwohl die Übergabe sie als v4-Feld benennt: reale Darstellung folgt stets der gebundenen Version. Keine Demo-Inkonsistenz in Testfixtures übernehmen.

Fonts in den Referenzen kommen teils aus Google Fonts; produktive Schriften gemäß vorhandener lokaler Einbindung verwenden. Canvas und Demo-Runtime bleiben Quellenmaterial. Fiktive 2027/2028-Daten/Preise werden nicht in Produktivtabellen gesät.
