# Veranstaltungsverwaltung näher an der Designvorlage

14.10.2026. Nutzervergleich zwischen mobilen Calculator-Screenshots (Terminkalender/Preisgruppen) und Referenzen (Feldeditor, Vorschau, Auswertung). Basis: aktueller master nach PR #15, Tree bd821dffc8418521af67d4a5d04cb8ecb726cb53. Referenzen D03/D04/D07/D10, M04/M05/M07/M10 sowie VERANSTALTUNGEN.md gelesen.

## Änderungen

- Rechte-Checkbox-CSS traf normale `.f`-Labels: mittig ausgerichtete Felder mit schmalen, unterschiedlichen Breiten. Regel auf tatsächliche Rechte-Labels begrenzt; Formularfelder linksbündig und gleich breit.
- Wizard: kompakte fünf Schrittkreise mit aktivem Stand/abgeschlossenen Navigationsschritten, mobil kurze Labels, auf Desktop vollständige Namen. Anzeige bleibt beim Scrollen sichtbar. Jede Seite als gegliederte Karte mit Überschrift. Zurück/Weiter und Schrittstand in der festen Dialogfußleiste, Entwurf als sekundäre Aktion; Schließen links. Navigieren setzt Scrollstand zurück und fokussiert Überschrift.
- Preisgruppen als Karten mit Bezeichnung, Preis/Euro-Suffix, verständlichen Kategorien und eigener Entfernen-Aktion. Interne Werte ADULT_DOUBLE/ADULT_MULTI/CHILD/GUEST bleiben als option.value erhalten. Termin-ID nur beim Verknüpfen sichtbar. Kapazität unverändert: 0 ist weiterhin kein behaupteter Unlimited-Wert.
- Feldeditor: geteilte Auswahl pro Buchung/pro Person, Pflichtregel als Auswahl, Auswertungsoption als Karte, technische IDs/Bedeutungswechsel unter Fachliche Einstellungen. Mobil kontextbezogene Fußleiste: Vorschau/Übernehmen oder Zurück zur Feldliste. Übernehmen speichert via bestehender servervalidierter Route; Fehler erhalten Eingaben.
- Vorschau: Buchungs-/Personenkarten, Ja/Nein und kurze Einzelauswahl als native Radioknöpfe mit sichtbaren Auswahlflächen, Einheiten am Zahleneingabefeld. Nein bleibt boolean false, nicht fehlend. Optional kann die Auswahl gelöscht werden. Pflichtfehler sind mit den zugehörigen Eingaben verknüpft; Tastaturfokus bleibt sichtbar. Keine erfundenen Beispielantworten.
- Auswertung: weiße Karten mit Titel/Typ/Geltung, großem Wert und lesbarer Einheit/Bezugsgröße, Abdeckung, getrennten fehlenden/ungeprüften/alten Angaben, Balken und Textlink zum Drilldown. Ja/Nein als getrennte Werte. Filter als lesbare Chips statt JSON; Filterformular und Antworttabelle aufklappbar. Drilldown öffnet die Antworttabelle mit unveränderten Filtern. Exportrechte/-routen erhalten.
- Assets erhalten Inhalts-Hashes in ihren URLs, damit neue CSS-/JS-Dateien nach Deployment zuverlässig geladen werden.

Keine Änderung an API, Berechnung, Datenbank, Synchronisation, Preisen oder produktiver Website. Kein Merge/Deployment und keine externen Schreibaktionen.

## Prüfung

Frontend-Ereignisse in Node-VM: Pflichtwahl bleibt boolean true/false, Scopewechsel erhält die vorhandene Sichtbarkeitsregel, Nein bleibt false, Beschriftungen sind escaped. Generiertes HTML für Wizard, Feldeditor und Vorschau auf ausgeglichene Tags, fünf Geschwisterpanels und stabile IDs geprüft. CSS mit Lightning CSS geparst, alle Anwendungsskripte mit node --check; PHP-Template-Syntax und git diff --check erfolgreich.

Betroffene Formular-/Auswertungs-/Wizard-API-Fälle: 30 Tests / 637 Assertions bestanden. Abschließender bestehender Gesamtlauf: 365 Tests / 5.805 Assertions, keine Fehler/Fehlschläge; 12 bestehende OIDC-Deprecations, kein Coverage-Treiber. Keine neuen Tests für reines Layout. Keine Browser-/Lighthouse-Automation. Manuelle Prüfung der tatsächlichen Darstellung bleibt erforderlich: Mobil 360/390/430 px, Desktop, längere Kategorien, Schrittnavigation, Feld übernehmen, Ja/Nein/optionale Auswahl löschen, Filter/Drilldown/Export. Keine pixelgenaue visuelle Abnahme behauptet.
