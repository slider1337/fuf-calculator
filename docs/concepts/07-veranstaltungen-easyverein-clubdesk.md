# Konzept 7: Veranstaltungen aus easyVerein anzeigen und ClubDesk-Inhalte übernehmen

**Status:** Entwurf, 25.09.2026. Keine Implementierung.

## Ziel und Quellen

Die öffentliche FuF-WordPress-Seite zeigt Veranstaltungen aus easyVerein. easyVerein ist die führende Quelle für Termin, Ort, Anmeldezeitraum, Teilnahmeoptionen und Preisgruppen. Bestehende ClubDesk-Veranstaltungsseiten enthalten zusätzliche Texte, Bilder, Downloads und Hinweise; diese Inhalte werden einmalig oder übergangsweise ähnlich dem Berichte-Import übernommen und mit dem passenden easyVerein-Termin verknüpft. Die alte ClubDesk-Anmeldeseite ist keine Ziel-URL.

## Darstellung

- Öffentliche Liste mit kommenden Veranstaltungen, Datum, Ort, kurzem Teaser, Status und Link zur Detailseite; vergangene Veranstaltungen gesondert oder archiviert.
- Detailseite vereint strukturierte easyVerein-Daten mit den übernommenen Zusatzinformationen. Der Anmeldeknopf führt später zum eigenen Anmeldeplugin (Konzept 8).
- Bis die eigene Anmeldung bereitsteht, nur anzeigen oder bewusst auf das bestehende easyVerein-Anmeldeverfahren verlinken; keine unbrauchbare Anmeldeschaltfläche.
- Quelle und Synchronisationszeit intern sichtbar machen. Bei Terminverschiebung, Absage oder Anmeldeschluss gelten die Daten aus easyVerein.

## Import und Zuordnung

1. easyVerein-Termine über die im Vereinsaccount verfügbare API abrufen. Die konkreten Ressourcen, Rechte, Felder und Grenzen vor Umsetzung mit Testdaten prüfen.
2. Je Termin die stabile easyVerein-ID speichern. Importe sind idempotent; Änderungen aktualisieren den bestehenden Eintrag, ohne lokale redaktionelle Ergänzungen zu überschreiben.
3. ClubDesk-Seiten kontrolliert importieren: Titel, Text, Bilder/Dateien, Quell-URL und letzte Änderung erfassen; Links und eingebettete Inhalte prüfen und für WordPress bereinigen.
4. Zuordnung easyVerein ↔ ClubDesk zunächst über eine prüfbare Liste mit manueller Bestätigung, nicht allein über ähnliche Namen oder Daten. Nicht zugeordnete Seiten als Klärfälle anzeigen.
5. Für jeden Abschnitt die Datenhoheit festhalten: strukturierte Termindaten easyVerein, importierte Zusatzinfos ClubDesk, spätere lokale Ergänzungen WordPress. Gelöschte/abgesagte Termine nicht stillschweigend als buchbar stehen lassen.
6. Nach der Migration ClubDesk-Import abschalten und die Zusatzinfos in WordPress weiterpflegen. Alte Links bei der Domänenumstellung soweit möglich auf die neue Detailseite weiterleiten.

Das bestehende `fuf-berichte`-Plugin und dessen Import liefern ein technisches Muster, aber Veranstaltungen erhalten ein eigenes Datenmodell und eigene WordPress-Komponente.

## Abnahme

Ein Termin erscheint genau einmal; Terminänderungen und Absagen werden übernommen; Zusatzinfos bleiben erhalten; keine ClubDesk-Anmeldelinks sind als neue Anmeldung aktiv; ungeklärte Zuordnungen sind sichtbar.

## Offene technische Prüfung

easyVerein-API-Zugriff auf Termine, Preisgruppen, Kapazitäten, Anmeldungen und Schreiboperationen im konkreten Tarif; ClubDesk-Zugriff und Rechte an eingebundenen Medien; Datenschutz für alte Veranstaltungsseiten.

## Quellen

- [easyVerein API](https://hilfe.easyverein.com/en/articles/360898)
- [Termindetails und Preisgruppen](https://hilfe.easyverein.com/en/articles/337858)
