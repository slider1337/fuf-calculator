# Veranstaltungsverwaltung – Umsetzung des Designs

Stand: 13.10.2026 · **Schritte 01–04 umgesetzt und technisch geprüft; manuelle Designabnahme offen. Schritte 05–11 weiterhin offen.**

Verbindliche Reihenfolge: **zuerst alle Grundlagen und Funktionen im Calculator, erst zum Schluss die Website anpassen.** Calculator, easyVerein und WordPress behalten ihre Aufgaben aus [Konzept 09](../../concepts/09-veranstaltungsverwaltung-formulare.md). Diese Reihenfolge ersetzt für diese Erweiterung die frühere Reihenfolge in Konzept 09, Abschnitt 13. Vor Schritt 11 gibt es keine Änderung im Website-Repository und keinen Wechsel des produktiven Anmeldewegs.

## Einstieg und Quellen

- [Grundlagen und technische Umsetzung](GRUNDLAGEN.md): Bestand, Datenmodell, Rechte, Zustände, Integrationsvertrag und Einführung.
- [Design-Zuordnung](DESIGN-MAPPING.md): jede Referenzansicht und ihre Umsetzung.
- [Abnahme und Freigabe zur Website](ABNAHME.md): fachliche Prüffälle und Übergabekriterien.
- [Originalübergabe](../../design-handoff/veranstaltungsverwaltung/START-HIER.md), [Designregeln](../../design-handoff/veranstaltungsverwaltung/VERANSTALTUNGEN.md) und [klickbare Übersicht](../../design-handoff/veranstaltungsverwaltung/mockups/index.html).
- Unveränderte Canvas-Quellen und fiktive Beispieldaten liegen zusammen mit den HTMLs unter `docs/design-handoff/veranstaltungsverwaltung/`. Die eingebettete Demo-Runtime wird nicht in die Anwendung übernommen.
- Bestehende [Design-Tokens](../../design-handoff/DESIGN.md), [Mobilregeln](../../design-handoff/RESPONSIVE.md) und [Navigation](../../design-handoff/NAVIGATION.md) bleiben Grundlage; die neue Übergabe ergänzt sie.

## Schritte

Jeder Schritt wird separat umgesetzt und geprüft. Aktueller Stand: Schritte 01–04 technisch umgesetzt; Prüfprotokolle [01](PRUEFUNG-01.md), [02](PRUEFUNG-02.md), [03](PRUEFUNG-03.md) und [04](PRUEFUNG-04.md). Die manuelle Designabnahme ist noch offen. Schritte 05–11 sind offen. Reihenfolge ist eine Abhängigkeit, kein Auftrag zur sofortigen Gesamtimplementierung.

| Schritt | Ergebnis | Aufgabe |
| --- | --- | --- |
| 01 | Migration, konkrete Reiserechte, Audit und Abrechnungssperre | [Grundlagen](AUFGABE-01-grundlagen.md) |
| 02 | Veranstaltungen, Reisezuordnung, Teams und neue Navigation | [Veranstaltungen](AUFGABE-02-veranstaltungen.md) |
| 03 | Zentraler Formular-Editor mit Typen, Regeln und Vorschau | [Formular-Editor](AUFGABE-03-formular-editor.md) |
| 04 | Veröffentlichung, Historie, Vergleich und bewusster Versionswechsel | [Versionierung](AUFGABE-04-versionierung.md) |
| 05 | Lokale Buchungen, einzelne Personen, Antworten und Korrekturen | [Buchungen](AUFGABE-05-buchungen-antworten.md) |
| 06 | Auswertungen, Drilldown und Export mit identischen Filtern | [Auswertungen](AUFGABE-06-auswertung.md) |
| 07 | Kontrollierte Übernahme in die bestehende Kalkulation | [Planungsübernahme](AUFGABE-07-planungsuebernahme.md) |
| 08 | Reise-/Terminwizard, eindeutige Verknüpfung und Terminaufträge | [Termine](AUFGABE-08-termine.md) |
| 09 | easyVerein-Teilnahmen, Abgleich, Teilfehler und Konflikte | [Synchronisation](AUFGABE-09-synchronisation.md) |
| 10 | Sichere Mitglieder-API im Calculator und vollständige Abnahme | [Integrationsvertrag](AUFGABE-10-integrationsvertrag.md) |
| 11 | Website-Formular und „Mein Bereich“ auf Calculator anbinden | [Website zuletzt](AUFGABE-11-website.md) |

**Calculator fertig** bedeutet nach Schritt 10: Orga kann Veranstaltungen und Reisen verwalten, Formulare veröffentlichen, Buchungen bearbeiten und auswerten, Planungswerte kontrolliert übernehmen sowie Termine/Teilnahmen abgleichen. Die künftige Website-Schnittstelle ist mit einem Testclient geprüft. Dafür muss WordPress noch nicht umgebaut sein.

## Arbeitsregel für die Umsetzung

Vor jedem Schritt aktuellen `master`, offene/kürzlich gemergte PRs und vorhandene Arbeit prüfen. AGENTS.md suchen und gegebenenfalls lesen. Hier wurde am 12.10.2026 keine gefunden. Keine pauschale Übernahme der damaligen Bestandsbefunde ohne erneute Prüfung. Nur die beauftragten Schritte implementieren. OpenAPI-Verträge und Implementierung gemeinsam ändern; geplante Endpunkte nicht als bereits verfügbar dokumentieren. Prüfprotokoll je Schritt mit Ergebnis, verbleibenden Sperren und Migration ergänzen. Keine echten E-Mails, Termine oder Teilnahmen für Tests erzeugen. Keine Browser-/Lighthouse-Läufe starten; manuelle Designabnahme vorbereiten.

## Prompt für Schritt 1

> Arbeite im Repository slider1337/fuf-calculator ausschließlich an docs/implementation/veranstaltungsverwaltung/AUFGABE-01-grundlagen.md. Lies zuerst vorhandene AGENTS.md, README.md, GRUNDLAGEN.md, ABNAHME.md, Konzept 09 sowie die dort verlinkten Rechte-/Abschlusskonzepte. Prüfe aktuellen master und offene/kürzlich gemergte PRs; führe vorhandene Arbeit weiter. Implementiere migrationsfähige Veranstaltungs-/Reisegrundlagen, serverseitige Reiserechte für bestehende Endpunkte, Audit und die Sperre abrechnungswirksamer Änderungen bei abgerechneten Reisen. Erhalte Altbestand und bestehende Berechnungen. Ergänze OpenAPI und sinnvolle Domain-/Integrationsprüfungen, dokumentiere Migrations- und Rechteübernahme. Keine Website-Änderungen, keine externen Schreibaktionen oder Mails und keine Browser-/Lighthouse-Prüfungen. Schritte 2–11 gehören nicht zum Auftrag.
