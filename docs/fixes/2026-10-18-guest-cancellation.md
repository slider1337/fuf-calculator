# Familienabmeldung mit Gästen

## Ursache

Der Mitgliederweg sortierte Gäste vor ihren Kontaktpersonen, ließ aber guestId und guestName im Abmeldeauftrag weg. Die Batchauswahl nahm dadurch Gäste mit auf. Da Gäste dieselbe Mitgliedsreferenz wie ihre Kontaktperson haben, lehnte die Batchvorprüfung die gesamte Familie als bestehende Teilnahme ab, bevor ein Schreibaufruf erfolgte.

## Korrektur

- Mitglieder- und Orgaaufträge behalten die stabile Gastreferenz.
- Die Batchauswahl prüft zusätzlich die gespeicherte Person, damit auch ältere Gastaufträge ohne Metadaten einzeln bleiben.
- Noch aktive Gäste lassen die Kontaktabmeldung warten und automatisch erneut versuchen; sie führen nicht mehr zu einer endgültigen Ablehnung der Familie.
- Bestehende Rechte-, Frist- und Referenzprüfungen bleiben bestehen. Unklare externe Ergebnisse werden weiterhin nur abgeglichen.

## Prüfung

Integrationstests melden vier Mitglieder und zwei Gäste zusammen ab und prüfen Gastaufträge, Reihenfolge und getrennte Batchverarbeitung. Ein weiterer Test deckt alte Aufträge ohne Gastmetadaten ab; ein Test prüft das Warten auf Gäste. Der echte Provideradapter wird zusätzlich mit Gast- und Kontaktabmeldung über exakte Teilnahmereferenzen geprüft.
