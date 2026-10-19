# Buchungsqualität – Fehler vom 19.10.2026

## Aufgaben und Abnahmekriterien

- [x] Familie und Gäste: realer HTTP-/Provider-Vertrag statt eines Fakes, das die Implementierung nur spiegelt. Gesamtablauf mit vier Mitgliedern und zwei Gästen; verzögerte Antwort, verlorene Antwort, fehlender Elternteil, Storno und sichere Wiederaufnahme ohne Doppelbuchung abdecken. Fehler vor dem Schreiben bleiben wiederholbar, ungeklärte Schreibausgänge werden ausschließlich abgeglichen.
- [x] Sichtbare Terminbeschreibung mit Standard-HTML-Editor. WordPress übernimmt die normale Inhaltsdarstellung; easyVerein erhält einen getrennten Hinweis zur Anmeldung über die Website. HTML wird vor Veröffentlichung bereinigt.
- [x] Externe Abweichungen als lesbarer Vergleich mit Namen und Preisen. Bestätigung zeigt konkret die zu übernehmenden Werte und erklärt die Wirkung. Reihenfolge und rein technische Änderungen erzeugen keinen falschen Konflikt.
- [x] Preisgruppen nachträglich bearbeiten: Name/Preis speichern, positive Rückmeldung und konkrete Fehler. Bereits akzeptierte Buchungspreise bleiben erhalten.
- [x] Veranstaltung bei neuer Reise von Anfang an auswählbar; Auswahl bleibt beim Speichern erhalten.
- [x] Qualitätsmaßnahmen: reproduzierbare Fehlerfälle vor Fix, automatisierte HTTP-Vertragstests, integrierte API-/Worker-Tests und Render-/JavaScript-Modelltests für die betroffenen Nutzerabläufe als Pflichtchecks. PHPStan Level 10 ohne Baseline; keine Behauptung über erfolgreiche Live-Prüfung ohne Nachweis.

Browser-/Lighthouse-Abnahmen bleiben gemäß AGENTS.md optional und werden ausschließlich manuell vom Nutzer gestartet. Tests senden keine Mitgliedermails.

## Nachweise und Grenzen

- Lesende Produktionsdiagnose: Reise 5 / Buchung 29 hatte vier ungeklärte Mitgliederaufträge und zwei wegen fehlendem Elternteil abgelehnte Gäste; der vollständige Providerabruf zeigte keine Teilnahmen. Die konkrete ursprüngliche Ausnahme wurde bisher nicht gespeichert und lässt sich aus dem alten Auftrag deshalb nicht rückwirkend rekonstruieren.
- Neue Anmeldungen nutzen einzeln bestätigte Übertragungen. Sammel-Anmeldungen sind im realen Adapter ausgesetzt; Sammel-Stornos bleiben verfügbar. Das kostet vorübergehend mehr Anfragen während einer Anmeldung, erzeugt aber keine zusätzlichen periodischen Abgleiche. Rate-Limit-Pausen und die sichere Abklärung verlorener Antworten bleiben erhalten.
- Ein integrierter Regressionstest verbindet echte API, Datenbank, Worker und echten easyVerein-Adapter mit einem HTTP-förmigen Anbieter-Doppel: vier Mitglieder, zwei Gäste, verzögerte Sichtbarkeit und verlorene Eltern-Antwort. Er verlangt sechs erfolgreiche Personen und genau sechs Schreibaktionen ohne Sammelanmeldung oder Doppelbuchung.
- Preisbearbeitung: Erfolg, veralteter Stand, falscher Termin, ungültiger Preis, geschlossene Reise, verlorene Antwort ohne zweiten PATCH, Erhalt bereits gebuchter Preise, dauerhafte manuelle Vorgaben und sofort aktualisierte lokale Website-Preisgruppen.
- HTML: TinyMCE wird über Composer-Lock lokal ausgeliefert. WordPress übernimmt sein bestehendes `the_content`/KSES-Verfahren. Ein Regressionstest im echten WordPress-Core prüft Formatierung, Script-Filter und die Trennung vom easyVerein-Anmeldehinweis; dafür ist die isolierte Core/MySQL-CI zuständig.
- Anzeige: konkrete Preisgruppenwerte, semantischer Vergleich ohne Reihenfolge-/Formatfehler, Ereignisauswahl mit Formular-/Team-Nachladen und Standardeditor-Synchronisierung werden automatisch geprüft.
- PHPStan Level 10 ohne Baseline und sieben JavaScript-Suiten erfolgreich. Website: Calculator-Bridge, Modell, Formularablauf, Termin-Sync und Plugin-Pakettests lokal erfolgreich.
- Ein echter Testtermin mit ausschließlich verifiziertem Testkonto wurde vorbereitet, jedoch von der automatischen Sicherheitsprüfung beim Bereitstellen des Test-Workflows abgelehnt. Er wurde weder angelegt noch gelöscht. Die bisherigen ungeklärten Produktionsaufträge wurden nicht blind erneut gesendet. Dieser Live-Nachweis bleibt offen.
