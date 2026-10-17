# Reiseaktionen und abgeschlossene Ladeanzeigen

Archivieren funktionierte serverseitig, aber das anschließende Laden rief die reine Berechnung mit einer Bearbeitungssperre auf. Die Berechnung ist jetzt auch im Archiv lesend erlaubt; Planungsänderungen bleiben gesperrt. Im Archiv liest die Terminansicht den lokalen Stand ohne Providerzugriff. Teilnahmeabgleich-Schaltflächen sind bei archivierten oder abgeschlossenen Reisen gesperrt.

Archivieren und Wiederherstellen erzeugen den Audittext intern. Wiederholte Archivaufrufe sind ohne doppelte Auditereignisse erfolgreich. Nach dem Archivieren geht es direkt zur Liste.

„Reise löschen“ betrifft ausdrücklich nur den Calculator. Eine bestätigte Aktion setzt deleted_at und archiviert den Datensatz intern. Listen und Zugriffe blenden ihn aus; Buchungen, Antworten, Finanzen und externe Referenzen bleiben erhalten. Es erfolgt kein easyVerein-Schreibzugriff.

Auf der Webseite wird das anfängliche Ladegerüst bei einem fehlgeschlagenen Kontextaufruf ersetzt. Eine gesperrte Anmeldung zeigt damit keine gleichzeitige Ladestatusmeldung mehr. Laufende Eingaben bleiben bei späteren Fehlern erhalten.
