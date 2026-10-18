# Hintergrundsynchronisation (18.10.2026)

WordPress und Calculator lesen Termine unabhängig minütlich. Darstellung, Auswahl und abgelaufene Caches lesen den letzten vollständigen lokalen Stand. Ohne Erstbestand erscheint ein Ladehinweis. Bestehende Terminfreigaben, Kalenderauswahl, Inhalte und Preise bleiben erhalten. Calculator-Reisen werden weiterhin ausschließlich über den Calculator gebucht; auch der alte WordPress-Kontext ist dagegen abgesichert.

## WordPress

Der vorhandene Minuten-Cron `fuf_ev_termine_sync` führt unter derselben MySQL-Verbindungssperre drei getrennte Aufgaben aus:

- Terminliste jede Minute; nur bei geändertem Fingerprint oder fälligem lokalen Neuabgleich Beiträge aktualisieren. Kein erneuter API-Abruf beim Übernehmen derselben Liste.
- Kalender und Felddefinitionen vollständig stündlich (maximal ein Vollabrufversuch pro Stunde). Vollständige Antworten werden lokal gespeichert; Fehler erhalten den bisherigen Katalog. Unbekannte Kalender-IDs erzeugen deduplizierte einzelne Hintergrundabrufe mit stündlicher Begrenzung.
- Linkzuordnungen alle 300 Sekunden anhand `event-custom-field-assignment.updated_at__gte`, mit 60 Sekunden Überlappung. Der gespeicherte Cursor ist die Startzeit des erfolgreichen Gesamtablaufs; er wird erst nach allen Seiten und der Übernahme geschrieben. Ein stündlicher Vollabruf ersetzt den Deltaabruf und erkennt gelöschte Zuordnungen sowie entfernte Definitionen. Erster Lauf: vollständig. Der Linkbestand ist unabhängig von `event.updated_at` und wird auch bei unverändertem Termin-Fingerprint übernommen.

Fehlerhafte Antworten ersetzen keine vollständigen Linkbestände. Nach HTTP 429 werden `Retry-After` (Sekunden oder HTTP-Datum) und ein wachsender Hintergrundabstand gespeichert; nach drei Fehlversuchen beträgt die Pause mindestens eine Stunde. Kein Schlafen oder schnelles Wiederholen im HTTP-Aufruf. Admin-Schaltflächen und REST-Anstöße verweisen auf den bereits geplanten Hintergrundlauf.

Der externe WordPress-Zeitgeber muss weiterhin jede Minute `wp-cron.php` aufrufen. Die Änderung ersetzt diesen Zeitgeber nicht.

## Calculator

Der bestehende `scripts/workers.php`-Prozess pflegt den neuen persistenten `provider_catalog`. Der reguläre Takt bleibt 60 Sekunden. Kalender kommen stündlich, Termine minütlich; vollständige Pagination und Validierung gehen einer atomaren Datenbankübernahme voraus. Linkdaten und vorhandene Beobachtungen erhalten neue Termininformationen ohne automatisches Umbepreisen. Fehlende zukünftige Termine werden erst nach einem vollständigen erfolgreichen Abruf als gelöscht gekennzeichnet. Historische Termine außerhalb des Abruffensters werden dadurch nicht als gelöscht behandelt.

Katalogsuche, Terminbearbeitungsanzeige und Teilnahmeanzeige lesen lokale Daten, auch bei `refresh=1`. Fehlende Referenzen werden gezielt eingereiht. Das bestehende Teilnehmer-Reconciliation läuft separat weiter. Teilnehmerimport beim Seitenaufruf entfällt, damit veraltete Beobachtungen keine bestätigten Abmeldungen zurücksetzen.

Für reine Mitgliederanzeigen werden benötigte verifizierte Profile und Familienreferenzen ebenfalls im Hintergrund bereitgestellt, mit fünfminütlichem Takt während der letzten 30 Minuten Nutzung. Schreibaktionen und Worker prüfen Berechtigungen weiterhin frisch. Der bestehende gemeinsame Request-Budgetschutz speichert jetzt auch eine tokenbezogene `Retry-After`-Pause. Job-Leases, Tokenprüfung vor Commit und gedeckelte Wiederholungen schützen vor parallelen Starts und übernehmen nach Abbruch nur vollständige Stände. Eine neue Migration legt Katalog- und Cooldown-Tabelle an.

Felddefinitionen werden im Calculator derzeit nicht gebraucht und deshalb dort auch nicht zusätzlich abgerufen.

## Berechnete API-Last, keine Live-Messung

Bei jeweils einer Ergebnisseite, ohne Fehler, Buchungsvorgänge, Profilaufträge oder Teilnahmeabgleiche:

| Aufgabe | WordPress / Stunde | Calculator / Stunde |
|---|---:|---:|
| Termine | 60 | 60 |
| Linkzuordnungen | 11 Delta + 1 Vollabruf | 0 |
| Kalender | 1 | 1 |
| Felddefinitionen | 1 | 0 |
| Summe | 74 | 61 |
| Durchschnitt / Minute | 1,2333 | 1,0167 |

Zusammen: **135 Requests/Stunde = 2,25 Requests/Minute im Durchschnitt**. Die reine Termin-Grundlast beträgt 2/Minute. Stunden- und Fünfminutenaufgaben erzeugen entsprechende kurze Spitzen; ihre Durchschnittswerte sind keine gleichmäßige Last pro Minute. Bei Erstinitialisierung: WordPress 4 und Calculator 2 Requests für diese Aufgaben.

Mit Seitenzahlen E_W, E_C (Termine), D (Delta), F (Link-Vollbestand), K_W, K_C (Kalender), C_W (Felddefinitionen) lautet die Stundenformel:
`60*E_W + 60*E_C + 11*D + F + K_W + K_C + C_W`.

Änderungen an Terminen oder Links erzeugen bei gleicher Seitenzahl keine zusätzlichen API-Requests. Zusätzliche Requests entstehen durch weitere Ergebnisseiten, neue unbekannte Referenzen (ein Einzelabruf je Referenz/Versuch), Token-Erneuerungen, begrenzte Fehlversuche sowie echte Schreibaktionen mit ihren Sicherheitsprüfungen.

Die Teilnahme-Reconciliation ist zusätzliche Grundlast. Seit der Optimierung vom 18.10.2026 verwendet ausschließlich der passive Hintergrundabgleich den lokalen Minuten-Terminbestand. Preisgruppen werden vollständig pro Termin stündlich und der minimale Mitglieder-/Kontaktindex vollständig gemeinsam stündlich gespeichert. Der Index überlebt Worker-Neustarts und wird innerhalb einer Stunde für alle Reisen und Personen wiederverwendet. Gäste ohne Mitgliedseintrag lösen keine einzelnen erneuten Zuordnungsabrufe aus. Die Teilnahmeabfrage bleibt standardmäßig bei 300 Sekunden.

Die weitere Optimierung verwendet nun einen gemeinsamen Teilnahme-Änderungsabruf sowie einen unabhängigen Preiszuordnungs-Änderungsabruf statt einer Teilnahmeliste je Reise. Stündlich ersetzt ein vollständiger Teilnahmeabruf beide Deltas und entfernt verschwundene Providerzeilen aus dem lokalen Feed. Details zum geprüften API-Vertrag, Löschgrenzen und allen Zusatzrequests stehen in [participation-feed.md](../integration/participation-feed.md).

Bei T=10 Reisen, höchstens 100 Reisen je Batch und einer Seite je Antwort beträgt die **berechnete** zusätzliche Last nun `23/60 + T/60 + P_M/60`, mit einer Seite Mitgliederindex also **0,5667 Requests/Minute** statt zuvor 2,1833. Einschließlich minütlicher Termine und stündlicher Kalender ergibt das für den Calculator **1,5833/Minute**, zusammen mit der oben beschriebenen WordPress-Konfiguration **2,8167/Minute**. Das sind Durchschnittsberechnungen ohne Änderungen, Pagination, Fehler, aktive Profilaufträge oder Schreibaktionen; keine Messwerte. Der Mitgliederindex entfällt, wenn keine Teilnahmen zuzuordnen sind.

Eine neue Migration legt `participation_reference_cache` an. Die vollständige Referenzliste wird nach validierter Pagination unter einer atomaren Lease übernommen; Fehler und HTTP 429 erhalten die vorherigen Daten und verschieben Wiederholungen. Überlappende Aufträge und abgelaufene Leases sind abgedeckt. Die bisherige reine RAM-Zuordnung wird für passive Snapshots zurückgesetzt, damit sie die stündliche Aktualisierung nicht überlagert.

An- und Abmeldungen, Termin-Schreibaktionen und deren Worker behalten den frischen Provider-Client für Berechtigungen, Kapazität, Preise und Ergebnisnachweis. Der passive Client lässt keine Provider-Schreibaktionen zu. Es werden keine neuen Browserprüfungen, Live-Schreibtests oder Testmails gestartet.

**ClubDesk:** keine Requests durch die hier beschriebenen easyVerein-Aufgaben. Bestehende ClubDesk-/ICS-/Seitenimporte bleiben unverändert und müssen separat gezählt werden; ihre Requests sind in keiner Zahl oben enthalten.

## Prüfungen

Automatisierte Modell-/Integrationstests prüfen lokale Seitenzugriffe, Minutentakt, stündliche Metadaten, unabhängige Linkänderungen, Löschung, Pagination und Fehlererhalt, Wiederaufnahme, parallele Starts sowie Retry-After. Calculator zusätzlich PHPStan Level 10, PHPUnit und Frontend-Modelltests. WordPress zusätzlich vorhandene Freigabe-/Kalender-/Inhaltsprüfungen über den bestehenden CI-Workflow. Keine echten Testmails oder Provider-Schreibtests. Browser- und Lighthouse-Prüfungen: optional, ausschließlich durch den Nutzer manuell.
