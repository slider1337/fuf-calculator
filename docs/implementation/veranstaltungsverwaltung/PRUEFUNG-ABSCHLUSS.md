# Abschlussprüfung der Calculator-Implementierung

14.10.2026 · Fortsetzung PR #14, unveränderte Basis PR #13. Rechnerfunktionen der Schritte 01–10 technisch implementiert; dieses Protokoll behauptet weder eine manuelle Designabnahme noch eine echte Provider-/Produktivabnahme.

## Ergänzte Abschlussarbeiten

- Feldweise Terminbearbeitung im Tab „Team & Termin“: aktueller externer Stand, gespeicherte lokale Reiseeckdaten bewusst einsetzen, ausgewählte Felder, serverseitige Vorschau vorher/nachher, Grund und dauerhafter Auftrag. Titel, Beginn/Ende einschließlich Uhrzeit, Ort, Beschreibung, positive Kapazität, Anmeldeschluss und Mitgliedersichtbarkeit. Kein pauschales Kopieren der Reise und kein Überschreiben von Kalender, Notizen, Preisgruppen oder Benachrichtigungseinstellungen.
- Externe Änderungen dieser Felder erscheinen einzeln im Abgleich. Ein externes Feld kann bewusst als neuer Stand bestätigt werden. Lokale Planung und ursprüngliche gebuchte Preise bleiben unverändert.
- Neue getrennte Terminänderungsaufträge mit Operation-Key/Request-Hash, nur einem aktiven Auftrag je Reise, Lease und unverändertem Review-Stand. Worker prüft Berechtigung, Abschluss, Archiv, Bindung, Zeitraum und gewählte externe Werte vor PATCH erneut. Frisch abweichender Stand ist ein Konflikt ohne PATCH. Nach Timeout, unbewiesenem Readback oder abgelaufener Lease ausschließlich GET; keine automatische erneute Datumsänderung/Benachrichtigung.
- Mitgliederfreigabe und gemeinsamer Anmeldeschluss nun im Calculator einstellbar; vorhandener servergeschützter Endpunkt. Frühere Providerdeadline begrenzt die lokale Frist weiterhin.
- Produktionskonfiguration wird vollständig an App und separaten Worker weitergegeben. Eigener begrenzter Worker verarbeitet Termin-/Terminänderungs-/Teilnahmeaufträge; optionaler periodischer Abgleich unter explizit gewähltem Calculator-Konto. Keine impliziten Adminrechte und kein GitHub-Synchronisationsjob.
- Deployment stoppt Writer, erstellt konsistente SQLite-Sicherung mit Integritätsprüfung, migriert und startet App/Worker. Standardflags deaktivieren externe Schreibaktionen/normalen Application-Mailversand. Konfiguration ohne Secret-Ausgabe oder Shell-Auswertung. Kein Deployment tatsächlich ausgelöst.
- Additive Migration **20261014160000** ergänzt Terminänderungsaufträge und periodische Abgleichzeitpunkte, ohne Bestands-/Personen-/Finanzdaten zu ändern. OpenAPI **1.7.0** enthält alle neuen Orga-Routen; der Mitgliedervertrag **1.6.0** bleibt kompatibel.

## Nachweise

**365 PHPUnit-Tests / 5.800 Assertions**, keine Fehler/Fehlschläge auf PHP 8.4.14. Zwölf bestehende OIDC-Deprecations; kein Coverage-Treiber. Ausschließlich fiktive Providerclients; keine echten Termine, Teilnahmen oder Provider-Mails erzeugt.

Neue API-/Workerfälle: Vorschau schreibt nicht; nur ausgewählte Felder; Idempotenz vor/nach Bestätigung; veraltete Vorschau; Fremdänderung zwischen Vorschau und Worker; Timeout mit bestätigtem externem Stand; unbewiesener Ausgang; Leaseablauf; zweiter Auftrag gesperrt; Archiv/Abschluss; Rechteentzug nach GET; fremde Reise; Schreibmodus aus; unzulässige Felder, Kapazität 0, ungültige Daten/Zeiträume. Tatsächliche erfolgreiche Routerantworten gegen OpenAPI geprüft. Adaptertest belegt exaktes PATCH-Mapping, Erhalt der vorhandenen Notiz und Abweisung fehlender Providerfelder.

Periodischer Abgleich: ohne konfigurierte Identität aus, keine Websession nötig, konkrete Reiserechte, Intervallreservierung, Widerruf, Archive ausgeschlossen, nach Abschluss nur Konflikt statt Finanz-/Teilnahmestatusänderung. Einzelfeld-Konfliktbestätigung entfernt ausschließlich die gewählte Abweichung und führt keinen Provider-PATCH aus.

Betriebsprüfungen: Backup aus Datenbank mit committed WAL übernimmt erhaltene ID/Geldwert, integrity_check=ok und eingeschränkte Dateirechte; frische Datenbank migriert und Worker-Runde `--once` mit Schreibmodus aus beendet sich korrekt. PHP-Syntax sämtlicher Anwendung/Migration/Testdateien, JavaScript-Syntax, strenges YAML/Schema-Referenzen, lokale Dokumentationslinks und git diff --check geprüft. Kein Browser-/Lighthouse-Lauf. Docker-Imagebuild/Compose auf dem Zielserver nicht ausgeführt; YAML/Umgebungsverdrahtung statisch geprüft.

## Verbleibende tatsächliche Abnahmegrenzen

1. Manuelle Calculator-Designabnahme gemäß [ABNAHME](ABNAHME.md), Desktop/Mobil/Bedienung. Diese wird ohne Nutzerfreigabe für Browserprüfungen nicht als bestanden markiert.
2. Echte Providerabnahme im später freigegebenen Produktivbetrieb. Während dieser Arbeiten darf Provider-Schreiben nicht versehentlich andere Mitglieder benachrichtigen; deshalb Fakes. Ein implementierter Adapter und Fake-Receipt beweisen keinen realen Providerlauf.
3. PRs integrieren und kompatibel bereitstellen; echte Serverkonfiguration/Migration/Workerbetrieb überprüfen. [Betriebsanweisung](BETRIEB.md) ist vollständig vorbereitet. Der Auftrag hat keine gesonderte Merge-/Deploymentfreigabe erteilt.
4. Danach Website-Schritt 11 gemäß bestehendem Gate: Mitgliederschnittstelle/Terminzuordnung aktivieren, bestehenden Login erhalten und alten/neuen Writer pro Reise eindeutig trennen. Website wurde in dieser Fortsetzung nicht geändert.

Die verbleibenden Punkte sind keine behaupteten erledigten Schritte. Insbesondere **der Gesamtauftrag einschließlich Website ist noch nicht abgeschlossen**. Die neuen Calculator-Funktionen, Betriebsvorbereitung und prüfbaren Übergabedaten sind auf GitHub bereitgestellt.

## Bekannte Providergrenzen

Providerkapazität 0 ist weiterhin unbestätigt statt als unbegrenzt erfunden. Terminänderungen verwenden einen frischen Feldvergleich, aber ohne dokumentierten Provider-If-Match ist eine gleichzeitige externe Änderung genau zwischen GET und PATCH nicht atomar vermeidbar; anschließender Readback bestätigt den gewählten Stand. Nicht ausgewählte Felder werden nie mitgesendet. Unklare PATCH-Aufträge werden nicht automatisch wiederholt. Preisänderungen bleiben bewusste externe Beobachtungs-/Zuordnungsklärung; keine rückwirkende Änderung gebuchter Finanzpreise.
