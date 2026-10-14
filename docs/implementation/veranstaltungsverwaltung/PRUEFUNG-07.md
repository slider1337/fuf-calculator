# Prüfung Schritt 07 – kontrollierte Planungsübernahme

Stand: 13.10.2026. Auf PR #12 (`da03593`, Tree `7564007`) aufgebaut; PR #12 war beim Start offen, master `ff156ae`. Der lokale Ausgangstree entspricht dem GitHub-Tree. Beauftragter Umfang: 07/08. Keine Website-Änderung, kein Merge/Deployment. Manuelle Designabnahme bleibt offen.

## Umsetzung

Vorschau und explizite Übernahme verwenden `PriceCalculatorService`, unveränderte Rundung, Verteilung, Aufschläge, Nacht-/Preislogik und gespeicherte Verkaufspreisvorgaben. Gezählt werden ausschließlich bestätigte aktive Personen nach Geburtsdatum und Reisestichtag. Offene/stornierte Personen werden separat ausgeschlossen. Kinder verwenden die vorhandene Altersgrenze. Erwachsenen-Unterkunftskategorien benötigen eine tatsächliche Orga-Zimmerzuteilung plus ausdrückliche Zuordnung zur bestehenden Doppel-/Mehrbett-Kalkulation; Zimmerwunsch und Formularmapping `roomPreference` sind keine Zuteilung. Tagesveranstaltungen bündeln Erwachsene in der bestehenden Erwachsenen-Kategorie, mit null Nächten.

Reservierte Zimmer bleiben Unterkunftsvertragsbestand: Eine freie Zuteilungsbezeichnung identifiziert keine verlässlich gebuchte Zimmeranzahl. Keine Zimmerzahl wird aus Personenzahlen geschätzt. Zusatzmapping `vegetarianMeals` übernimmt nur geprüfte, kompatible Zahl-/Personencheckboxmengen als eigene Planungsmengen im Snapshot, ohne automatische Kosten-/Preiswirkung; 0 und false bleiben gültig. Fehlend, ungeprüft und relevante historische Nichtabfrage blockieren. `arrivalDate` und `roomPreference` bleiben Information. Keine frei ausführbaren Formeln oder beliebigen Preisziele.

Preview schreibt nichts. Apply verlangt Quellhash, expliziten Grund und Idempotenzschlüssel; dieselbe Quelle und gleiche Wiederholung liefern denselben Snapshot. Der Quellhash umfasst Planung, Formularbindung und vollständigen Buchungsstand inklusive Revisionen sowie einer eigenen Übernahmerevision. Diese verhindert konkurrierende Übernahmen auch dann, wenn die Planungswerte identisch bleiben. Konkurrierende Planung/Buchung/Übernahme liefert 409. Spätere Änderungen markieren den Stand beim Lesen als veraltet. UI aktualisiert ihn beim Öffnen der Planung. Append-only Snapshot speichert Eingabe vorher/nachher, Mapping, Zeitpunkt, Quellstand, Mengen, Budget/Preisergebnis, Akteur und Grund. Identische Wiederholung erzeugt keine zweite Historie.

Bereits vorhandene Buchungs-/Abrechnungspreise und externe Rechnungen werden nicht verändert. Aktuelle Reiseplanung wird innerhalb derselben SQLite-Schreibtransaktion gespeichert. Konkrete Rechte `calculation.manage`, `registration.view`, `answers.view`, `analytics.view` erforderlich; kein Admin-Bypass. Abschluss sperrt Preview/Apply, lesender historischer Stand bleibt erreichbar mit den entsprechenden Rechten.

## Einführung und Prüfung

Vor Upgrade SQLite konsistent sichern, inklusive WAL/SHM, dann `php scripts/migrate.php`. Migration `20261014010000` ergänzt Snapshots und Termin-/Wizardtabellen additiv. Sie ändert keinen bestehenden Reise-/Anmeldungs-/Finanzwert. Snapshot-Update/Delete sind über DB-Trigger verboten. Nach Eingang neuer Daten nur kompatiblen Code zurückrollen oder konsistentes Backup wiederherstellen.

Automatische Nachweise gemeinsam mit Schritt 08: vollständiger PHPUnit-Lauf, neue reale HTTP-Antworten gegen OpenAPI 1.4.0, Vorschau ohne Änderungen, Staleness nach Storno/Planänderung, Idempotenz/Quellkonflikt, Zimmerwunsch ohne Zuteilung, Tagesveranstaltung, Altbestand, fehlende/ungeprüfte Zuordnungsmenge und gültige Nullmenge. PHP-/JS-Syntax und `git diff --check`. Keine externen Schreibaktionen oder Mails, keine Browser-/Lighthouse-Automation. Genaue Testergebnisse in [Schritt 08](PRUEFUNG-08.md).

Manuell offen: D14/M14, Tabellen in eigener Scrollfläche, Auswahl/Fehlermeldungen/Fokus, erreichbare Modalaktionen, Budget-/Preisvergleich und Neuöffnen des veralteten Stands auf echten Desktop-/Mobilgeräten.
