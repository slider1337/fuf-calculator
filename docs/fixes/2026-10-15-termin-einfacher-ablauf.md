# Termin einrichten: drei Schritte und ein Abschluss

Die bisherige Oberfläche speicherte über den dominanten Dialogknopf nur einen lokalen Entwurf. Für die tatsächliche Anlage waren zusätzlich „Entwurf speichern & prüfen“ und „Terminaktion ausführen“ nötig. Trotzdem war vorher bereits ein Mitgliederfreigabe-Dialog erreichbar. Dessen pauschale Fehlermeldung vermischte fehlende Terminverknüpfung und gesperrte Anbindung. Aus dem Screenshot allein lässt sich die konkrete serverseitige Sperrursache nicht sicher unterscheiden.

## Neuer Ablauf

1. **Eckdaten:** Bei bestehenden Reisen aus der Planung übernommen und lesbar; Änderungen erfolgen dort.
2. **Anmeldung:** Kalender bzw. bestehenden Termin suchen, Sichtbarkeit, Kapazität, Frist und optional Preisgruppen. Zusatzformular/Team unter aufklappbaren Details; bei bestehenden Reisen werden diese in den jeweiligen Verwaltungsbereichen geändert.
3. **Prüfen & abschließen:** Zusammenfassung mit einem eindeutigen Hauptknopf „Termin in easyVerein anlegen“ oder „Ausgewählten Termin verknüpfen“. Das Speichern des Entwurfs und die serverseitige Prüfung erfolgen automatisch innerhalb dieser Aktion. Der Knopf ist nur auf der Zusammenfassung sichtbar. „Als Entwurf speichern“ bleibt eine separate Nebenaktion ohne externen Terminauftrag.

Neue Reisen lassen sich über denselben Ablauf anlegen und direkt abschließen. Der Dialog merkt sich die bereits erstellte lokale Reise und deren Revision für nachfolgende Versuche. Ein einmal gesendeter Terminauftrag behält seinen Schlüssel und dieselbe Nutzlast. Wiederholung nach verlorener Antwort sendet denselben idempotenten API-Auftrag; kein zweiter Create-Auftrag und kein Zurücksetzen unklarer Worker-Ausgänge.

## Status und Freigabe

Der Terminbereich zeigt den nächsten Schritt an: Entwurf fertigstellen, laufende Anlage, unklare Rückmeldung oder bestätigte Verknüpfung. Bei laufenden/unklaren Aufträgen liest die Oberfläche den Status alle fünf Sekunden bis zu zwölf Mal nach; zusätzlich gibt es „Status aktualisieren“. Diese Abfragen lösen keine Anlage aus. Bei Reisewechsel wird keine weitere Abfrage für die alte Reise gestartet.

Die Mitgliederfreigabe ist erst nach bestätigter Terminbindung erreichbar. Damit führt ein bloß gespeicherter Entwurf nicht mehr in einen nicht erfüllbaren Freigabedialog. Verbleibende Sperren verwenden die konkrete Ursache statt der bisherigen Sammelmeldung. Die Freigabe selbst bleibt bewusst eine eigene Aktion. Die bestehenden Prüfungen für Rechte, Abschluss, Revision, Formular-/Teamzuordnung, Terminprüfung und unbekannte externe Ergebnisse bleiben erhalten.

## Prüfung

- `node tests/Frontend/term-setup.cjs`: 14 Szenarien erfolgreich; Entwurf ohne externen Auftrag, Anlage/Verknüpfung, neue Reise, fachliche und technische Sperren, idempotente Wiederholung nach verlorener Antwort, keine geänderte Wiederholung, drei Geschwisterpanels, erhaltene Termin-/Kalendersuche sowie Entwurfs-/Auftrags-/Freigabeanzeige.
- 44 gezielte PHPUnit-Tests / 3.781 Assertions erfolgreich (PlanningTermApiTest, MemberParticipationApiTest und TermCatalogApiTest).
- JavaScript-Syntax, PHP-Template-Syntax und `git diff --check` erfolgreich.

Keine API-/Datenbankänderung oder Migration. Keine echten Testtermine, Teilnahmen oder Mails; keine Browser-/Lighthouse-Automation. Mobile/Desktop-Darstellung nach Bereitstellung manuell prüfen. Durch die Erstellung des Pull Requests erfolgt kein Merge oder Deployment.
