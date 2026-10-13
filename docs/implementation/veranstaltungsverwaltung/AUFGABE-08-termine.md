# Schritt 08 – Reise-/Terminwizard und dauerhafte Terminaufträge

Status: **lokal und mit Fakes umgesetzt; produktiver easyVerein-Adapter gesperrt; manuelle Designabnahme offen** · Voraussetzung: Schritte 01–07 abgenommen.

Vor Umsetzung [README](README.md), [Grundlagen](GRUNDLAGEN.md), [Abnahme](ABNAHME.md), [Design-Zuordnung](DESIGN-MAPPING.md) und die Originalreferenzen lesen. Aktuellen Repository-/PR-Stand und AGENTS.md erneut prüfen.

## Umfang

Vor externen Schreibaktionen aktuellen easyVerein-Vertrag, Rechte und Benachrichtigungsverhalten mit Primärdokumentation belegen; konkrete API-Felder in Adaptervertrag festhalten, nicht aus Mockup-Labels ableiten. Versandverbot gilt: unvermeidbarer Versand sperrt echte Operation. Ohne geklärten API-Vertrag bleiben Adapter/Fakes und verständliche Funktionssperre.

Wizard Eckdaten→Kalender/Anmeldung→Preisgruppen→Formular/Team→Prüfen umsetzen, Entwurf ohne externe Anlage speicherbar. Vorhandenen Termin verknüpfen oder eindeutigen dauerhaften Erstellauftrag absenden. Feld- und Preisvorschau nennt echte externe Änderungen. Reise-Termin-Zuordnung auf lokaler Datenbankebene eindeutig; vorhandene Bindung prüfen. Anmeldung erst nach bestätigter Referenz, nicht bei angenommener Operation. Timeout/Absturz: unklar, abgleichen/verknüpfen statt blind erneut erstellen.

## Betroffene Bereiche

ExternalEventPort/easyVerein-Adapter, Auftrag-Service/Worker-CLI, sync_jobs/external_event_links, Wizard und Team-&-Termin-UI, OpenAPI; Konfiguration nur serverseitig. Betrieb über Server-Scheduler, keine GitHub-Sync-Actions.

## Abnahme

Nur Entwurf speichert keinen API-POST. Doppelclick/Wiederholung führt zu einem lokalen Auftrag. Derselbe Termin kann nicht an zwei aktive Reisen gebunden werden. Fake-Timeout mit tatsächlich angelegtem Termin führt über Abgleich zur vorhandenen Referenz, niemals zweiter POST. Zwei parallele Worker bearbeiten nicht beide denselben Job. Fehler, unklar, läuft und fehlende Referenz sperren Anmeldung.

## Grenzen und Übergabe

Referenzen D07/D12, M07/M12, Z01. Team/Formularvorschläge sind lokal vor Prüfschritt; externe Anlage ist getrennte bewusst ausgelöste Aktion. Kein echter API-Test mit Mails.

Nach Abschluss Prüfprotokoll mit konkreten Änderungen, Tests, Datenmigration, offenen Punkten und Betriebsanweisungen ergänzen. Keine echten Mails oder Testtermine/-teilnahmen erzeugen. Keine folgenden Schritte vorziehen.
