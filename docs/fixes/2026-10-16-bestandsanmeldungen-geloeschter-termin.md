# Bestehende Anmeldungen und gelöschte easyVerein-Termine

## Bestehende Teilnahmen vor einem Formular

Der easyVerein-Import legte einzelne lokale Buchungen ohne Formularversion und ohne
`owner_member_reference` an. Der Mitgliederzugriff filterte ausschließlich nach diesem
lokalen Eigentümerfeld. Daher waren die Teilnahmen für Organisatoren sichtbar, aber
für das Mitglied unsichtbar. Die erneute Anmeldung wurde zurecht als Dublette abgelehnt;
die vorhandene Teilnahme konnte zugleich nicht über die Mitgliederoberfläche abgemeldet werden.

- Kontext- und Buchungsabrufe übernehmen vorhandene Provider-Teilnahmen für das verifizierte
  Mitglied und seine aktuell verwaltbaren Familienkonten. Die erste Seite der globalen
  Buchungsliste gleicht freigegebene offene Reisen ab, auch ohne vorherigen Besuch der Terminseite.
- Importierte Buchungen sind über die stabilen Mitgliedsreferenzen des verifizierten Profils
  zugänglich. Namen und bloße Kontakttexte gewähren keine Rechte. Eine normale lokale
  Familienbuchung behält ihre bisherige Eigentümerbindung. Importierte Buchungen werden nicht
  dem zuerst lesenden Familienkonto dauerhaft zugewiesen.
- Bestehende Teilnahmen bleiben ohne Formularantworten sichtbar und stornierbar. Ein später
  gebundenes Formular wird als fehlende Ergänzung kenntlich gemacht. Antworten werden erst
  bei ausdrücklichem Ergänzen validiert und gespeichert; es erfolgt keine zweite Anmeldung.
- Der Teilnahme-Worker prüft bei importierten Buchungen ebenfalls das verifizierte Profil vor
  dem Schreiben. Der Import überschreibt während eines laufenden/ungeklärten Personenauftrags
  nicht dessen Status. Storno und erneute Anmeldung sind mit dem späteren Formular möglich.

## Gelöschte Termine

Ein externer HTTP 404 auf die konkrete Termin-ID wird von Lesefehlern unterschieden.
Der Terminstatus bleibt lesbar und zeigt „Löschung bestätigen“. Solange dies nötig ist,
erscheint kein irreführender Einrichten-Button, der an der alten Verknüpfung scheitert.

Die Bestätigung prüft die aktuelle Termin-ID erneut am Provider. Nur eine definitive
Nichtvorhanden-Antwort erlaubt das Lösen. Authentifizierungsfehler, Ausfälle, geänderte
Verknüpfungen, archivierte/abgerechnete Reisen und aktive Worker-Leases verhindern es.
Es wird kein externer Termin gelöscht und noch kein neuer Termin angelegt.

Die lokale Transaktion historisiert alten Link und Anlageauftrag in `deleted_term_history`,
beendet die nun nicht mehr existierenden Teilnahmen, stoppt alte Änderungs-/Personenaufträge,
entfernt veraltete Abgleichdaten und sperrt die Mitgliederfreigabe bis zur neuen Einrichtung.
Buchungen, Personen, Preise und Formularantworten bleiben historisch erhalten. Ein vorhandener
Entwurf wird auf Neuanlage, aktuelle Reiseeckdaten, gebundene Formularversion und aktuelles Team
vorbereitet. Der alte Anlageauftrag blockiert die neue Einrichtung nicht mehr; alte Aufträge
werden nicht auf den Ersatztermin übertragen. Der Ablauf benötigt keine Begründungseingabe.

## Prüfungen

Regressionen decken Anmeldung vor Formularbindung, Ergänzen ohne zweite Teilnahme,
Storno ohne neue Pflichtantworten und anschließende Neuanmeldung, verifizierten Familienzugriff,
Fremdzugriff, Provider-404 gegenüber Ausfall, explizite Löschbestätigung, alte Job-Sperren,
Erhaltung historischer Personen, neue Terminanlage, veraltete IDs, laufende Worker sowie
Archiv-/Zuordnungsrennen ab. Browser-/Lighthouse-Prüfungen bleiben manuell.
