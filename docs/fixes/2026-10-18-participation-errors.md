# Gast-Übertragung und verständliche Fehleranzeige

## Behobene Codefehler

- Der einzelne Teilnahmeadapter liest vor dem eigentlichen POST erneut Mitglied und Termin. Ein dort ausgelöstes Anfragelimit oder ein temporärer Lesefehler wurde vom Worker als unklarer Schreibausgang eingeordnet. Ohne POST konnte ein Gast dadurch dauerhaft nur nach einem nie erstellten Ergebnis suchen. Diese nachweislich vor dem Schreiben auftretenden Fehler sind jetzt wiederholbar. Fehler nach begonnenem Schreiben bleiben ausschließlich abgleichbar.
- Ein nicht passender Gastnachweis wurde unmittelbar durch `external_result_unproven` überschrieben. Der konkrete Gastkonflikt bleibt jetzt erhalten.
- Die Auftragsübersicht nennt die gespeicherte Person und kennzeichnet Gäste. Namen werden nur mit `registration.view` ausgegeben. Buchungsdetails sind direkt erreichbar.
- Sichere Feld-/Grundcodes von Provider-Ablehnungen sowie die erste Fehlerursache werden dauerhaft gespeichert. Spätere ergebnislose Abgleiche löschen diese Ursache nicht. Keine Providertexte oder Zugangsdaten werden gespeichert.
- Nach wiederholtem erfolglosem Abgleich wird die nötige Prüfung durch die Orga ausdrücklich genannt. Ein Abgleich wird nicht als erneute Anmeldung bezeichnet.

## Noch nicht bestätigte Produktionsursachen

Der konkrete historische Gastauftrag hatte laut bestehendem Deploymentprotokoll bereits 24 erfolglose Abgleiche. Sein ursprünglicher Fehler wurde im bisherigen Schema nicht aufbewahrt und kann durch diese Migration nicht rückwirkend rekonstruiert werden. Bestehende unklare Aufträge werden nicht automatisch erneut geschrieben.

Der Screenshot der Website zeigt getrennte Fehler beim Buchungsindex und bei der easyVerein-Übersicht. Die Anmeldung des Calculators ist erreichbar; das beweist nicht die Funktionsfähigkeit des authentifizierten Buchungsindex. Für die konkrete Ursache wird eine begrenzte Produktionsdiagnose benötigt. Ein zusätzlicher SSH-Diagnoseworkflow wurde von der automatischen Freigabeprüfung abgelehnt und wird nicht mit diesem PR ausgeliefert oder ausgeführt.
