# Prüfung Schritt 06 – Auswertung, Drilldown und Export

Stand: 13.10.2026. Schritte 05 und 06 gemeinsam umgesetzt. Technische Umsetzung geprüft; manuelle Designabnahme offen. Datenmigration/Betrieb siehe [Schritt 05](PRUEFUNG-05.md).

## Umsetzung

Ein gemeinsames lesendes Snapshotmodell liefert Reise-/Veranstaltungskennzahlen, paginierte Antwortliste, CSV und Druckansicht. Standard ist bestätigt und aktiv; alternative Statussichten sind ausdrücklich als nicht reguläre Summe markiert. Reiseauswahl in der Veranstaltung enthält nur freigegebene Reisen. Explizit angeforderte fremde Reisen werden abgewiesen; Gesamtwerte zählen Reise-Teilnahmen, keine eindeutigen Mitglieder. Veranstaltungspflegerecht allein berechtigt nicht zum Lesen von Antworten.

Buchungsfelder zählen einmal je Buchung mit mindestens einer passenden Person; Personenfelder je passender stabiler Person. Keine JOIN-Vervielfachung. Anwendbarkeit wird anhand der gespeicherten Formularversion und aktuellen Personenkonstellation geprüft. Fehlend/Nullwert/false/nicht anwendbar/nicht abgefragt/ungeprüft sind getrennt. Nenner enthält beantwortet, fehlend und ungeprüft. Altbestand erhält keine erfundenen Nullen. Geprüfte Zahlensumme und ungeprüfte Summe bleiben getrennt. Dezimalsummen sind exakt, auch jenseits eines einzelnen Feldlimits, ohne Float oder Zusatzextension.

Checkbox und Ja/Nein zeigen Ja/Nein/fehlt; Auswahl zeigt stabile Optionen, Mehrfachauswahl ihren abweichenden Nennerbezug. Text/Datum werden gelistet, nicht summiert. Standardkennzahlen respektieren die Auswertungsaktivierung des Feldes. Explizit gefilterte historische Feldserien bleiben lesbar; Typ/Einheit/Geltung werden auf Kompatibilität geprüft.

Detail, CSV und Druck verwenden dieselben Reise-/Versions-/Status-/Feld-/Antwortzustands-/Suchfilter. CSV enthält Stand, Herkunft, Status, Sync, Version, Buchungs-/Personenreferenzen, Einheit und aktive Filter. Fehlende Werte bleiben leer, 0 und false erhalten. Freitext-Tabellenformeln werden neutralisiert; validierte numerische Dezimalwerte bleiben exakt. Antwortrevisionen sind ein eigener Export mit Originalwert, Akteur, Grund und Zeit. Exporte einschließlich Druck prüfen `answers.export` je ausgewählter Reise erneut. Keine Daten: erklärter Leerzustand und deaktivierte UI-Exportaktion. XLSX wurde nicht zusätzlich eingeführt, da CSV den beauftragten Vertrag erfüllt.

## Nachweise

297 PHPUnit-Tests / 1.554 Assertions auf PHP 8.4.14 ohne Fehler oder Fehlschläge. Zwölf bestehende OIDC-Deprecations; Lauf ohne Coverage-Treiber. Fachfixture mit 12 Buchungen/42 Personen: geprüfte Zahlensumme 17 aus 11/12 Antworten, Checkbox 9/10 anwendbare Buchungen mit Kindern (zwei nicht anwendbar), Personenserie 17 Ja/23 Nein/2 fehlt aus 42. Drilldown/CSV reproduzieren die geprüfte Summe; Null-/false-Werte, Text ohne Summe, historische nicht abgefragte Felder, Teilstorno und ungeprüfte Werte separat. Pagination, Filterfehler, Formelinjektion, Exportsperre, Veranstaltungsgesamtwerte/Reisefreigaben und Widerruf geprüft. Neue JSON-Antworten stimmen mit OpenAPI 1.3.0 überein.

PHP-/JS-Syntax, eindeutige HTML-IDs, Dokumentationslinks und `git diff --check` geprüft. Keine Browser-/Lighthouse-Automation, keine externen Schreibaktionen oder Mails.

## Manuell offen und nächste Schritte

D10/D11/M10/M11/Z01: Kacheln/Filter/Listen auf Desktop und Mobil, Tabellen nur innerhalb eigener Scrollfläche, Rückkehr von Drilldown, CSV-Download und Druck über den Browserdruckdialog. Visuelle Designabnahme bleibt offen. Schritt 07 (kontrollierte Planungsübernahme) und Schritte 08–11 gehören nicht zu diesem Auftrag; Website und produktiver Mitglieder-Anmeldeweg wurden nicht geändert.
