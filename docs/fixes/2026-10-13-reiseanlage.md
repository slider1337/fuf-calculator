# Reiseanlage ohne Veranstaltung und Feldrückmeldung

13.10.2026. Ausgangsstand: gemergte Veranstaltungsverwaltung Schritte 01–04.

Die Oberfläche verlangte eine Veranstaltung, obwohl Backend und API-Vertrag unabhängige Reisen erlauben. Bei fehlender Auswahl hätte der Frontend-Payload zudem `eventId=0` gesendet. Die Auswahl ist jetzt optional und leer wird als `null` übergeben. Ohne auswählbare Veranstaltung erklärt ein Hinweis die Möglichkeiten. Eine Veranstaltung kann direkt im Reiseformular angelegt werden; danach ist sie ausgewählt, bestehende Reiseeingaben bleiben erhalten. Bestehende Zuordnungen werden weiterhin über die Veranstaltungsverwaltung geändert.

Die orange Feldumrandung kennzeichnete ungespeicherte Änderungen, Grün den Eingabefokus. Diese beiden Zustände wirkten wie eine widersprüchliche Gültigkeitsprüfung. Ungespeicherte Änderungen bleiben über den vorhandenen Textstatus erkennbar; Feldrahmen werden deshalb nicht mehr orange. Der Fokus verwendet einen neutralen Braunton. Echte Validierungsfehler behalten ihre rote Markierung.

Serverseitig muss eine angegebene Veranstaltungs-ID eine positive Ganzzahl sein; ungültige Referenztypen liefern einen Feldfehler (422). Vorhandene Event-/Reiserechte bleiben wirksam.

Validierung: 288 PHPUnit-Tests / 1.284 Assertions bestanden, darunter neue Fälle für Speichern ohne Veranstaltung mit anschließender Zuordnung sowie ungültige Veranstaltungsreferenzen. PHP-/JavaScript-Syntax und git diff --check bestanden. Zwölf bestehende OIDC-Deprecations unverändert; keine Browser-/Lighthouse-Läufe. Mobilansicht und Direktanlage im Dialog bleiben manuell abzunehmen. Kein Merge/Deployment.
