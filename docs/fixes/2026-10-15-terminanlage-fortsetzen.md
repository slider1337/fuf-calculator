# Fehlende oder teilweise angelegte Termine tatsächlich fortsetzen

## Bisherige Sackgasse

Ein alter Auftrag im Zustand `unknown` wurde ausschließlich lesend abgeglichen. Ohne gefundenen Termin blieb er dauerhaft dort. PR #24 ergänzte zwar die manuelle Zuordnung eines vorhandenen Termins, löste aber den vom Nutzer bestätigten Fall „Termin wurde nicht angelegt“ nicht. Die ursprüngliche Providerursache des Altauftrags ist weiterhin nicht aus der alten Sammelmeldung rekonstruierbar.

## Bedienung

Bei einem unklaren oder gesperrten Terminauftrag erscheint jetzt als Hauptaktion **„Termin prüfen und anlegen“**. Ein Klick autorisiert die Fortsetzung dieses konkreten Auftrags. Die Oberfläche erklärt, dass vorhandene Ergebnisse zuerst geprüft werden und ein fehlender Termin erst nach der Prüfung auf mögliche Doppeltermine angelegt wird. Die manuelle Zuordnung bleibt eine Nebenaktion. Status und Polling berücksichtigen die Fortsetzung; Nachlesen erfolgt bis zu zwei Minuten.

`POST /api/trips/{id}/term/resume` verlangt bestätigte Absicht, die konkrete Job-ID und den aktuell gelesenen Versuchszähler. Trip-spezifisches Terminrecht, Bearbeitbarkeit und easyVerein-Modus sind erforderlich. Der Server prüft den gespeicherten Entwurf erneut, hält den ursprünglichen Auftrag samt Referenz und Nutzlast fest und reserviert ihn als `resume_pending`. Bereits aktive Aufträge werden nicht erneut reserviert; alte Versuchszähler werden nach abgeschlossener Verarbeitung zurückgewiesen. Abschluss/Archivierung/Rechteentzug stoppen die Ausführung wie bisher. Laufende Fortsetzungen blockieren auch den Verfahrenswechsel und parallele manuelle Zuordnung.

## Tatsächliche Wiederaufnahme

Der Adapter besitzt einen gesonderten, ausdrücklich autorisierten Fortsetzungsweg:

1. Eine bereits gespeicherte externe ID wird zuerst direkt geprüft. Sonst wird nach dem unveränderten Auftragsmarker gesucht. Mehrdeutige oder abweichende Funde stoppen die Fortsetzung.
2. Falls der Marker fehlt, wird zusätzlich der vollständige Kalender-/Datumsbereich gelesen. Ungültige, fehlerhafte oder unvollständige Ergebnisse sind keine Erlaubnis zum Anlegen. Ein ähnlicher unmarkierter Termin mit gleichem Namen und Beginn im ausgewählten Kalender verlangt bewusste Zuordnung.
3. Ist nach diesen Prüfungen kein Ergebnis vorhanden, wird der fehlende Termin mit der ursprünglichen Auftragsreferenz angelegt.
4. Eine vorhandene eindeutige Teilanlage wird fortgesetzt. Bereits markierte Preisgruppen werden anhand Referenz, Name, Preis und Termin geprüft; nur tatsächlich fehlende Gruppen werden ergänzt. Fremde oder abweichende Gruppen stoppen den Vorgang. Abschließend wird die vorgesehene Zugangsart aktiviert und der Zustand zurückgelesen.

Die externe Termin-ID wird unmittelbar nach einer erfolgreichen Event-Antwort dauerhaft im vorhandenen Auftrag gespeichert – vor Preisgruppen, Aktivierung und vollständigem Readback. So kann ein späterer Fehler gezielt an derselben Anlage fortgesetzt werden. Ein verlorener Event-Response wird über den ursprünglichen Marker aufgeklärt. Datumsvergleiche verwenden Europe/Berlin; eine UTC-Antwort für Mitternacht wird damit nicht fälschlich dem Vortag zugeordnet.

Vor jedem Schreiben bei neuer Anlage und Fortsetzung prüft ein Guard erneut die konkrete Worker-Lease und die Berechtigungen und verlängert die Lease. Zwei Worker können nicht denselben Auftrag zugleich übernehmen. Nach Timeout oder Lease-Ablauf geht der Auftrag weiterhin ausschließlich in den automatischen Abgleich zurück; ein weiterer schreibender Versuch benötigt wieder einen ausdrücklichen Klick.

## Vertrag und Grenzen

OpenAPI 1.10.0 ergänzt die Resume-Route, deren bestätigte Anfrage und `resume_pending`. Der Mitgliedervertrag bleibt unverändert. Die bestehende externe-ID-Spalte wird genutzt; keine Datenbankmigration. Alte Worker verstehen `resume_pending` nicht, weshalb App und Worker gemeinsam über den bestehenden Deploymentweg aktualisiert werden müssen; dieser stoppt beide Writer vor dem Imagewechsel.

Die API bietet hier keinen verwendeten atomaren Idempotenzschlüssel. Ein vollständiges Lesen und ein späterer POST sind providerseitig keine atomare Operation. Der neue Create nach einem alten unklaren Ausgang erfolgt deshalb ausschließlich nach ausdrücklicher Nutzeraktion, Marker-/ID-Prüfung, vollständiger Kollisionsprüfung und lokaler Serialisierung. Bei veralteten Provider-Leseergebnissen oder gleichzeitigen Änderungen außerhalb des Calculators lässt sich eine absolute Dublettengarantie nicht behaupten. Automatischer Abgleich allein erzeugt weiterhin keinen neuen Termin.

## Validierung

20 Node-Szenarien erfolgreich, inklusive bestätigtem Resume-Aufruf mit frisch gelesener Job-ID/Versuch und keiner Fortsetzung einer bereits verbundenen Reise. 38 gezielte PHPUnit-Tests / 398 Assertions erfolgreich: fehlender Alttermin wird nach expliziter Freigabe erstellt; Teilanlage ergänzt ausschließlich fehlende Gruppen; verlorener Response und UTC-Datumsdarstellung führen nicht zu einem zweiten Event; Suchfehler, Konflikte und verlorene Lease verhindern Schreiben; Wiederholung/Parallelität/Rechte/Abschluss/Bestätigung/Verfahrenssperren bleiben geschützt. Vollständige PHPUnit-Suite: 392 Tests / 6.237 Assertions ohne Fehler oder Fehlschläge; 12 bestehende OIDC-Deprecations. PHP-/JS-Syntax, OpenAPI/YAML und `git diff --check` erfolgreich geprüft.

Keine echten Testtermine/Teilnahmen oder Mails, keine Browser-/Lighthouse-Automation. Der konkrete Produktivauftrag wurde weder zurückgesetzt noch ausgeführt. Nach Merge/Deployment führt der neue Knopf den beschriebenen Weg unter den vorhandenen produktiven easyVerein-Einstellungen aus.
