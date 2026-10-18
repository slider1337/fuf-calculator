# Gemeinsamer Teilnahmeabgleich (18.10.2026)

Die öffentliche easyVerein-v3.0-OpenAPI-Dokumentation wurde direkt als HTML geladen und das eingebettete `var spec` ausgewertet. Der gezielte Vertragsauszug samt Quell-Hash steht in `participation-feed-contract.json`. Nachgewiesen **im veröffentlichten Vertrag**, nicht durch einen schreibenden Live-Test:

- `participation` unterstützt `updated_at`, `updated_at__gte`, `participation_event__in` und `id__in`.
- `participation-price-group` unterstützt ebenfalls `updated_at__gte` und besitzt eine Teilnahme-Referenz. Preiszuordnungen dürfen deshalb nicht ausschließlich über die Änderung ihrer Eltern erkannt werden.
- DELETE ist für beide Ressourcen dokumentiert. Zusätzlich gibt es Wastebasket-Routen. Ein zuverlässig gefilterter, vollständiger Löschstrom einschließlich endgültig gelöschter Einträge ist damit nicht bewiesen. `delete_after_date` ist kein Löschprotokoll.
- Kein Rückschluss von `event.updated_at` auf Teilnahmen oder Preiszuordnungen. Das genaue Zeitstempelverhalten bei realen Mutationen wurde in diesem Auftrag nicht experimentell geprüft; automatisierte Tests nutzen ausschließlich Fakes. Es gab keine Testteilnahmen oder Mails.

## Verarbeitung

Der vorhandene fünfminütliche `ScheduledReconciliation` verwendet einen gemeinsamen dauerhaften Feed für die nicht archivierten verknüpften Calculator-Reisen. Die Reisesicht erhält jeweils ihre vollständige lokale Teilmenge. Ein eigener Scheduler kommt nicht hinzu. Der minütliche Terminkatalog und die stündlichen Preisgruppen-/Mitgliedsreferenzen bleiben erhalten.

Erststart, geänderte Reisenauswahl und spätestens nach einer Stunde: vollständiger Teilnahmeabruf (enthält Preiszuordnungen), anstelle beider Deltaabrufe. Bei nur einer Reise bleibt ein gemeinsamer Vollabruf alle fünf Minuten günstiger als zwei Deltaabrufe und wird bevorzugt. Bei mehreren Reisen dazwischen: ein gemeinsamer Teilnahme-Deltaabruf und ein gemeinsamer Preiszuordnungs-Deltaabruf. Nur betroffene bekannte Teilnahmen, die nicht schon im ersten Delta enthalten sind, werden per `id__in` gesammelt nachgeladen. Listenfilter umfassen höchstens 100 IDs je Batch; Pagination bleibt zusätzlich möglich. Der Zuordnungsdelta ist global, lokal werden ausschließlich bereits bekannte Teilnahmereferenzen berücksichtigt.

Cursor = Startzeitpunkt des vollständig erfolgreichen Abrufs, mit 60 Sekunden Überlappung beim nächsten Lauf. Erst nach allen Seiten beider Feeds und aller Nachladungen werden Daten, Cursor und Vollabgleichzeit gemeinsam gespeichert. IDs werden idempotent ersetzt. Fehlende IDs im Delta sind kein Löschbeweis. Nur ein vollständig erfolgreicher Vollabgleich entfernt verschwundene Teilnahmen/Zuordnungen aus dem Feed und der externen Beobachtung. Historische lokale Buchungen und Finanzdaten werden durch eine fehlende Providerzeile nicht automatisch storniert oder gelöscht; eine explizite Stornierung (`state=0`) läuft durch die bisherigen fachlichen Regeln.

Eine atomare DB-Lease schützt alle Seiten gemeinsam und wird vor jeder Seite erneuert. Ein verlorener Besitzer darf weder weitere Seiten laden noch speichern. Fehler behalten den letzten vollständigen Bestand einschließlich Cursor. Wartezeiten: mindestens 60, dann 120 Sekunden, ab dem dritten Fehler eine Stunde; `Retry-After` darf dies verlängern. Das bestehende gemeinsame Providerbudget bleibt wirksam. Ohne Erstbestand wird der bestehende Ladefehler verwendet.

Produktive An-/Abmeldungen verwenden weiterhin frische unabhängige Providerprüfungen. Vor und nach einem Teilnahme-Schreibversuch wird der gemeinsame Feed invalidiert, auch bei unklarem Ergebnis. Damit werden ein konkurrierender Feedabruf und dessen alter Fallback ungültig; die nächste Hintergrundrunde liest erneut vollständig. Seitenaufrufe verwenden diese Hintergrundklasse nicht.

## Berechnete Requestzahlen (keine Messwerte)

Annahmen: fünf Minuten Teilnahmeintervall, zwölf Runden/Stunde, eine stündliche Vollrunde ersetzt zwei Deltas, jede Antwort passt auf eine Seite, keine Fehler/Schreiboperationen/Scopeänderungen. `N >= 2` = verknüpfte Reisen, `K = ceil(N/100)` = Reisebatches, `M` = Seiten des stündlichen globalen Mitgliederindex.

| Ressource | Berechnete Requests/Minute |
| --- | ---: |
| Teilnahmefeed inkl. Zuordnungsdelta | `(11 × (K + 1) + K) / 60` |
| Preisgruppenreferenzen | `N / 60` |
| Mitgliederreferenzen | `M / 60` |
| Minütlicher Terminkatalog | `1` |
| Kalender | `1 / 60` |

Beispiel **10 Reisen, K=1, M=1**: Teilnahmefeed `23/60 ≈ 0,383` statt früher `10/5 = 2` Requests/Minute (rund 81 % weniger in diesem Teil). Einschließlich der oben genannten übrigen Calculator-Grundlast **ca. 1,583 Requests/Minute**. Bei nur einer Reise: ein vollständiger Teilnahmeabruf je fünf Minuten (0,2/Minute, ohne Pagination). Bei keinen abzugleichenden Reisen fällt kein Teilnahmefeed an. WordPress und ClubDesk sind in diesen Calculator-Zahlen nicht enthalten; dieser Auftrag ändert keine ihrer Aufrufe.

Zusätzlich bei Änderungen: weitere Delta-Ergebnisse kosten zunächst nur Nutzlast; betroffene Preiszuordnungen erfordern maximal einen Nachlade-Listenabruf je 100 Eltern, soweit diese nicht bereits im Teilnahme-Delta stehen. Jede weitere Ergebnisseite kostet einen weiteren Request. Erststart, Scopeänderung und Cacheinvalidierung nach Schreibversuchen lösen einen zusätzlichen vollständigen gemeinsamen Abruf aus. Schreiboperationen selbst, ihre frischen Prüfungen, aktive Mitgliederprofile und gezielte unbekannte Referenzen sind bedarfsabhängige Zusatzlast. Retry-Requests kommen nur nach Backoff hinzu. ClubDesk: keine zusätzlichen Aufrufe durch diese Änderung.
