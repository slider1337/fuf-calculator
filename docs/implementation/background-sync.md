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

Die vorhandene Teilnahme-Reconciliation ist zusätzliche Grundlast: je aktivem berechtigtem Termin und Standardintervall 300 Sekunden ungefähr `(3 + M)/5` Requests/Minute, wobei 3 für Termin, Preisgruppenliste und Teilnahmeliste steht; M sind Mitglieds-/Kontaktzuordnungsabrufe und zusätzliche Seiten. Bei mehr als fünf Teilnahmen wird die vorhandene gesammelte Mitgliederliste genutzt. Jeder gleichzeitig aktive Profilauftrag benötigt zusätzlich ungefähr einen Request je fünf Minuten; verwaltete Profile benötigen eigene Aufträge. Konfigurierte Intervalle und Batchgrenzen beeinflussen die tatsächliche Last. Diese Zusatzlast hängt nicht allein von erkannten Terminänderungen ab.

**ClubDesk:** keine Requests durch die hier beschriebenen easyVerein-Aufgaben. Bestehende ClubDesk-/ICS-/Seitenimporte bleiben unverändert und müssen separat gezählt werden; ihre Requests sind in keiner Zahl oben enthalten.

## Prüfungen

Automatisierte Modell-/Integrationstests prüfen lokale Seitenzugriffe, Minutentakt, stündliche Metadaten, unabhängige Linkänderungen, Löschung, Pagination und Fehlererhalt, Wiederaufnahme, parallele Starts sowie Retry-After. Calculator zusätzlich PHPStan Level 10, PHPUnit und Frontend-Modelltests. WordPress zusätzlich vorhandene Freigabe-/Kalender-/Inhaltsprüfungen über den bestehenden CI-Workflow. Keine echten Testmails oder Provider-Schreibtests. Browser- und Lighthouse-Prüfungen: optional, ausschließlich durch den Nutzer manuell.
