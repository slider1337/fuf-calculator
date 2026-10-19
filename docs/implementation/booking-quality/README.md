# Buchungsqualität – Fehler vom 19.10.2026

## Aufgaben und Abnahmekriterien

- [ ] Familie und Gäste: realer HTTP-/Provider-Vertrag statt eines Fakes, das die Implementierung nur spiegelt. Gesamtablauf mit vier Mitgliedern und zwei Gästen; verzögerte Antwort, verlorene Antwort, fehlender Elternteil, Storno und sichere Wiederaufnahme ohne Doppelbuchung abdecken. Fehler vor dem Schreiben bleiben wiederholbar, ungeklärte Schreibausgänge werden ausschließlich abgeglichen.
- [ ] Sichtbare Terminbeschreibung mit Standard-HTML-Editor. WordPress übernimmt die normale Inhaltsdarstellung; easyVerein erhält einen getrennten Hinweis zur Anmeldung über die Website. HTML wird vor Veröffentlichung bereinigt.
- [ ] Externe Abweichungen als lesbarer Vergleich mit Namen und Preisen. Bestätigung zeigt konkret die zu übernehmenden Werte und erklärt die Wirkung. Reihenfolge und rein technische Änderungen erzeugen keinen falschen Konflikt.
- [ ] Preisgruppen nachträglich bearbeiten: Name/Preis speichern, positive Rückmeldung und konkrete Fehler. Bereits akzeptierte Buchungspreise bleiben erhalten.
- [ ] Veranstaltung bei neuer Reise von Anfang an auswählbar; Auswahl bleibt beim Speichern erhalten.
- [ ] Qualitätsmaßnahmen: reproduzierbare Fehlerfälle vor Fix, automatisierte HTTP-Vertragstests, integrierte API-/Worker-Tests und Render-/JavaScript-Modelltests für die betroffenen Nutzerabläufe als Pflichtchecks. PHPStan Level 10 ohne Baseline; keine Behauptung über erfolgreiche Live-Prüfung ohne Nachweis.

Browser-/Lighthouse-Abnahmen bleiben gemäß AGENTS.md optional und werden ausschließlich manuell vom Nutzer gestartet. Tests senden keine Mitgliedermails.
