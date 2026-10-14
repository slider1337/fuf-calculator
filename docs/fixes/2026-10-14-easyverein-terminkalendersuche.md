# easyVerein-Termin- und Kalendersuche

Nutzerauftrag: Bestehende Termine und Kalender im Wizard über Namen auswählen, keine IDs eintippen.

## Bedienung

- Unter „Kalender & Anmeldung“ zeigt „Bestehenden Termin verknüpfen“ eine Suche nach Terminname mit Startdatumfilter. Das Reisedatum ist vorbelegt; Datum leeren für ältere Termine. Treffer zeigen Name und Zeitraum in Europe/Berlin; abgesagte Termine sind markiert und nicht auswählbar.
- Termin auswählen übernimmt dessen Referenz und Kalender. Eine abweichende Kalenderauswahl wird beim Verknüpfen nicht angeboten.
- Für „Neuen Termin vorbereiten“ Kalender nach Name suchen und auswählen. Keine automatische Auswahl des ersten Treffers.
- Beide Suchen bieten „Weitere … laden“ nach jeweils 50 Treffern. Suche per Knopf oder Enter, keine Provideranfrage bei jedem Zeichen. Namen aus gespeicherten Entwürfen werden über die vorhandenen Referenzen nachgeladen. Die Prüfansicht zeigt Namen statt IDs.
- Suchbegriffe ändern entfernt veraltete Treffer; verspätete Antworten überschreiben keine neue Suche. Eine bereits bewusst getroffene Auswahl bleibt erhalten. Ladefehler sind sichtbar und erneut versuchbar.

## Backend und Berechtigungen

GET `/api/trip-wizard/catalog/{events|calendars}` für angemeldete Reiseersteller (wie bestehende Reiseanlage); GET `/api/trips/{id}/term/catalog/{events|calendars}` mit term.manage und easyverein-Modus für vorhandene Reisen. Rechte werden vor jedem Providerzugriff geprüft. Antworten sind no-store. Keine Erweiterung von Buchungs-/Antwortrechten.

Ein neuer TermCatalogService verwendet ausschließlich EasyVereinReadClient und dessen feste Pfadfreigabe. Er liest event/calendar mit dokumentierten search/ordering-Parametern, maximal 50 Ergebnissen pro Anfrage und begrenztem Offset. Keine Provider-Weiterleitungs-URLs werden aufgerufen oder an den Browser gegeben. Ausgegeben werden nur Referenz, Name sowie bei Terminen Datum, Kalenderreferenz und Absagestatus; keine Teilnehmer, Notizen, Zugangsdaten oder Provider-Rohantworten. selected liest eine gespeicherte Referenz und prüft Identität; ungültige Parameter werden vor dem Provider abgewiesen.

Keine Änderung der gespeicherten Entwurfsstruktur oder Migration. Die bestehende bewusste Prüfung/Verknüpfung und abgesicherte Terminanlage bleiben erhalten. Die Suche schreibt weder Termine noch Teilnahmen und verschickt keine Mails. easyVerein-Schreibfreigabe ist für die Suche nicht nötig; API-Token muss Leserechte für Termine/Kalender besitzen.

API-Parameter wurden gegen die öffentliche easyVerein-v3.0-Dokumentation am 14.10.2026 geprüft: https://easyverein.com/api/v3.0/documentation. OpenAPI 1.9.0 dokumentiert beide neuen Suchrouten und das reduzierte Ergebnisschema.

## Prüfung

30 gezielte PHPUnit-Tests / 806 Assertions erfolgreich (Katalog, Terminwizard, bestehende Verknüpfung, Terminänderungen und Adapter). Vier neue Integrationstests prüfen Suchparameter/Paginierung, Kalendernamen, gespeicherte Referenzen, minimierte Antwortdaten, Sitzungs-/Reiserechte, CSV-Sperre, ungültige Eingaben und fehlerhafte Providerantworten mit reinem Lesefake.

Node-Ereignisprüfung erfolgreich: Suche, Terminauswahl/automatischer Kalender, Seitenwechsel, verspätete Suchantworten, Fehler/erneuter Versuch, gespeicherte Auswahl, Termin-/Kalendermodus; Providernamen werden als Text eingefügt. JavaScript-/PHP-Syntax und git diff --check erfolgreich. Keine Browser-/Lighthouse-Automation und keine echten Provider-Schreibtests.

Gesamte PHPUnit-Suite: 378 Tests / 5.970 Assertions ohne Fehler/Fehlschläge; 12 bereits bestehende OIDC-Deprecations.
