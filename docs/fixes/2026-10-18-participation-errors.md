# Gast-Übertragung und verständliche Fehleranzeige

## Behobene Codefehler

- Der einzelne Teilnahmeadapter liest vor dem eigentlichen POST erneut Mitglied und Termin. Ein dort ausgelöstes Anfragelimit oder ein temporärer Lesefehler wurde vom Worker als unklarer Schreibausgang eingeordnet. Ohne POST konnte ein Gast dadurch dauerhaft nur nach einem nie erstellten Ergebnis suchen. Diese nachweislich vor dem Schreiben auftretenden Fehler sind jetzt wiederholbar. Fehler nach begonnenem Schreiben bleiben ausschließlich abgleichbar.
- Ein nicht passender Gastnachweis wurde unmittelbar durch `external_result_unproven` überschrieben. Der konkrete Gastkonflikt bleibt jetzt erhalten.
- Die Auftragsübersicht nennt die gespeicherte Person und kennzeichnet Gäste. Namen werden nur mit `registration.view` ausgegeben. Buchungsdetails sind direkt erreichbar.
- Sichere Feld-/Grundcodes von Provider-Ablehnungen sowie die erste Fehlerursache werden dauerhaft gespeichert. Spätere ergebnislose Abgleiche löschen diese Ursache nicht. Keine Providertexte oder Zugangsdaten werden gespeichert.
- Nach wiederholtem erfolglosem Abgleich wird die nötige Prüfung durch die Orga ausdrücklich genannt. Ein Abgleich wird nicht als erneute Anmeldung bezeichnet.

## Bestätigte Produktionsdiagnose

Die vom Nutzer ausdrücklich freigegebene lesende SSH-Diagnose bestätigte am 18.10.2026:

- Der betroffene Buchungsindex endet mit `provider_catalog_loading` beim Zugriff auf den Cache eines verwalteten Familienmitglieds.
- Der Cache des Hauptmitglieds ist vorhanden und aktuell. Für die drei freigegebenen Familienmitglieder existiert noch nicht einmal ein angeforderter Cacheeintrag.
- Die direkt gelesenen Mitgliedsdaten entsprechen dem erwarteten Schema. Derselbe Buchungsindex funktioniert mit frisch gelesenen Familiendaten (`booking_index_fresh_profile: ok`).
- Gäste sind am betroffenen Termin erlaubt, das anmeldende Mitglied ist bestätigt. Weder der Auftragsmarker noch der zugehörige Gast sind im vollständigen aktuellen easyVerein-Stand vorhanden.
- Der Gastauftrag bleibt nach 26 Abgleichen `unknown / external_result_unproven`. Sein ursprünglicher Fehler wurde im bisherigen Schema nicht aufbewahrt und kann nicht rückwirkend rekonstruiert werden. Der gefundene Vorprüfungsfehler erklärt einen möglichen Entstehungsweg, beweist aber nicht den historischen Verlauf dieses Auftrags.

Der Hintergrundkatalog stellt jetzt bei erfolgreicher Mitgliedssynchronisation alle ausdrücklich verwaltbaren Familienreferenzen gemeinsam in die Warteschlange. Auch beim Lesen eines bestehenden Hauptmitglied-Caches werden alle benötigten Referenzen gemeinsam vorgemerkt. Ein einziger Hintergrundlauf kann die vollständige Familie laden; Seitenaufrufe greifen weiterhin ausschließlich auf lokale Daten zu. Nicht freigegebene oder einem anderen Hauptmitglied zugehörige Referenzen werden nicht geladen. Fehlende Profile werden weiterhin nicht als bestätigte Identität ausgegeben.

Bestehende unklare Teilnahmeaufträge werden nicht automatisch erneut geschrieben. Die Diagnose verändert weder Buchungen noch Teilnahmen und löst keine Nachrichten aus. Es werden ausschließlich technische Fehlercodes, Stackstellen und Auftragsstatus protokolliert. Der nur für diese Diagnose verwendete Workflow gehört nicht zum Produkt-PR.

Diagnoseläufe:
- https://github.com/slider1337/fuf-calculator/actions/runs/39152423668
- https://github.com/slider1337/fuf-calculator/actions/runs/39152606236
