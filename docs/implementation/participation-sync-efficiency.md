# Zentrale Anmeldungen und weniger Provideranfragen

Der Calculator führt Buchungen, Formularantworten, Personenaufträge und Übertragungszustände. Die Website nutzt für Calculator-Reisen bereits die signierte Calculator-Integration. Clubdesk/Altfälle behalten ihre bestehende Route; easyVerein-Login und allgemeiner WordPress-Kalenderimport sind eigenständige Aufgaben.

## Gemeinsamer Lesestand

Website-Kontext, Import älterer Teilnahmen und Calculator-Terminanzeige verwenden denselben gespeicherten Providerstand bis zu fünf Minuten. Wiederholtes Öffnen einer Ansicht löst keinen vollständigen Abgleich aus. Der Teilnahmebereich liest standardmäßig die lokalen Aufträge; ein manueller Abgleich liest bewusst frisch. Auch der Terminstatus kann mit `?refresh=1` ausdrücklich frisch geprüft werden. Nach erfolgreicher Übertragung wird nur der Cache als ungültig markiert; Zeitstempel und bisherige Konflikte bleiben erhalten. Der nächste Lesezugriff lädt einmal neu.

Der konfigurierte Hintergrundabgleich bleibt vom Browser unabhängig (standardmäßig alle fünf Minuten je berechtigter Reise). Ein gültiger FUF_RECONCILE_ACTOR_ID mit Terminrechten ist weiterhin Voraussetzung. Bei fehlendem/abgelaufenem Cache wird einmalig beim Lesen aktualisiert; die Website ist damit noch nicht vollständig vom Provider entkoppelt. Die Prüfung der aktuellen Familienberechtigungen bleibt frisch. Diese Änderung verspricht keine globale Rate-Limit-Koordination mit WordPress oder fremden API-Nutzern.

## Sammelübertragung

Der Worker reserviert bis zu 25 fällige unabhängige Personen derselben Buchung und Aktion gemeinsam. Er prüft die Familienberechtigungen einmal je anmeldendem Mitglied. Der Adapter liest die betroffenen Kontaktreferenzen und einen gemeinsamen aktuellen Terminstand, sendet einen Bulk-Aufruf und prüft einen gemeinsamen Ergebnisstand. Jeder Auftrag behält seinen Marker `calculator-person-ID` und wird ausschließlich bei passender Identität, Preisgruppe, Preis und Status bestätigt. Direkt vor dem Schreiben werden lokale Rechte und sämtliche Reservierungen erneut geprüft.

Verwendete offizielle v3.0-Endpunkte:

- POST `participation/bulk-create/` ohne Preisgruppen
- POST `participation/bulk-create-participations-with-price-groups/` mit Preisgruppen
- PATCH `participation/bulk-update/` zur Abmeldung

Der Request enthält jeweils `entries`. Gäste bleiben Einzelaufträge, da ihre Teilnahme die externe Referenz der bestätigten Elternteilnahme benötigt. Bei uneindeutiger Bulk-Antwort oder möglichem Teilerfolg werden alle unbewiesenen Personen einzeln abgeglichen, niemals automatisch neu angelegt. Die Reihenfolge der Bulk-Antwort ist kein Identitätsnachweis.

## Wiederholung und Grenzen

Ein explizites HTTP 429 oder ein ausgeschöpftes lokales Budget pausiert die Familie und den weiteren Teilnahme-/Abgleichdurchlauf. Retry-After (Sekunden oder HTTP-Datum) wird berücksichtigt. Temporäre Lesefehler vor einem bewiesenermaßen noch nicht begonnenen Schreiben bleiben automatisch zur Wiederholung vorgemerkt. Steigende Abstände mit kleiner zufälliger Streuung vermeiden schnelle Wiederholungsschleifen. Unklare Schreibausgänge bleiben ausschließlich im Leseabgleich. Rechte-, Preis- und Validierungsfehler bleiben sichtbar und erfordern Korrektur.

Im Calculator werden Fehlergrund, Versuchszahl und nächster Versuch angezeigt. Website-Buchungen bleiben nach dem lokalen Speichern als ausstehend sichtbar.

Ein Rechenbeispiel für vier unabhängige Mitglieder: vorher wurden drei vollständige Terminstände und die Familie je Person gelesen; nun gibt es einen gemeinsamen Vorher-/Nachherstand und einen Schreibaufruf. Die genaue Ersparnis hängt von Familiengröße, Pagination und bestehenden Kontakten ab. Eine gemessene Produktionsquote oder ein bestätigter HTTP-429-Auslöser liegt aus dieser Änderung allein nicht vor.

Quelle: https://easyverein.com/api/v3.0/documentation (öffentliche OpenAPI-Dokumentation, geprüft am 17.10.2026). Die Dokumentation nennt 100 Anfragen pro Minute und empfiehlt Bulk-Endpunkte. Das interne Calculator-Budget beträgt standardmäßig 60/min und gilt pro Token innerhalb derselben Calculator-Datenbank.
