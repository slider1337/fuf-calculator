# Schritt 05 – Buchungen, Personen, Antworten und Revisionen

Status: **technisch umgesetzt und geprüft; manuelle Designabnahme offen** · Prüfprotokoll: [PRUEFUNG-05](PRUEFUNG-05.md) · Voraussetzung: Schritt 04 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Bestehende registrations und registration_participants weiterentwickeln: stabile Personen-IDs, Buchungsreferenz, Kontaktidentität, Quellen, Formularstand und getrennte Fach-/Sync-Zustände. Optionale Zimmerkategorie für Tagesveranstaltung durch Domain, Service, Persistenz und API führen. Zimmerwunsch separat von Zuteilung speichern. Manuelle Orga-Erfassung mit Zusatzformular als produktiven Calculator-Ablauf anbieten; externe Bestätigung noch nicht simulieren.

Gemeinsamen Buchungsservice für Validierung/Transaktion/Idempotenz/Korrektur/Storno bauen. Antwortrevisionen mit Version/Person/Feld/Wert/Akteur/Zeit, Grund bei Orga-Eingriff; nur aktuelle gültige Revision auswerten. Mengen nach Teilstorno/Teilfehler als ungeprüft markieren. Buchungsliste, Detailpanel, Revisionen und Ergänzungsbedarf zeigen. CSV/manual-Altbestand weiterführen, Herkunft und fehlendes Formular sichtbar. Bestehende Sammellösch-/CSV-Ersetzung auf referenzierte Antwortdaten abstimmen, keine destruktive Neu-ID-Vergabe.

## Betroffene Bereiche

Registration/Participant, RegistrationService/-Repository/Controller, Billing-Port und Serializer erweitern; Antwort-Domain/Services/Persistenz; UI Anmeldungen/Detail und OpenAPI. Ausstehende Operationen abstrahieren, externen Adapter erst Schritt 09 anschließen.

## Abnahme

Vier Personen = eine Buchung + vier stabile Personen. Identischer Idempotenzschlüssel/Inhalt ergibt gleiche Referenz, anderer Inhalt Konflikt. Korrektur erhält Original, zweimalige Wiederholung erzeugt keine doppelte Revision. Teilstorno erhält Historie, markiert unklare Buchungsmengen; alles storniert verlässt reguläre Grundgesamtheit. CSV-Wiederimport verändert neue Buchungen nicht. Formular fehlt/versioniert/veraltet serverseitig korrekt. Keine Version ohne sichtbaren Konflikt umdeuten.

## Grenzen und Übergabe

Referenzen D09, M08/M09, D08/Z01. Der Calculator ist ohne Website mit lokaler Orga-Erfassung und Testdaten bedienbar. Lokaler Eingang ist nicht extern bestätigte Teilnahme. Realen Sync-Zustand erst mit Schritt 09 ändern.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
