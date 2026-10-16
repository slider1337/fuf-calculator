# Direkte Terminanlage und mobile Buttons

Die Wizard-Fußleiste erhält ein Raster: Navigation, Hauptaktion über die ganze Breite, dann Abbrechen und Entwurf speichern. Die bisherige globale nowrap-Regel quetschte alle Aktionen nebeneinander.

Der Wizard sendet executeImmediately=true. Nach dem dauerhaften Speichern führt der Controller ausschließlich den eigenen Reiseauftrag aus. Atomare Leases, Idempotenz und die bestehenden Regeln für unklare Ergebnisse bleiben erhalten. Der Hintergrundworker bleibt als Wiederanlauf verfügbar. Bestehende pending-Aufträge lassen sich direkt über „Termin prüfen und anlegen“ ausführen. GET-Abfragen schreiben weiterhin keine Termine.

Der Dialog schließt erst bei bestätigter Verknüpfung. Ablehnungen und erlaubte Feldfehler erscheinen unmittelbar im Dialog. Ein erneuter Klick setzt den vorhandenen Auftrag fort. Während der Ausführung steht „Termin wird geprüft …“ am Hauptbutton.

Verifiziert mit Frontend-Logiktests, Integrationstests für gezielte Ausführung, vorhandene Aufträge, Idempotenz, aktive Leases und Provider-Ablehnung sowie PHPStan und PHPUnit. Keine automatisierten Browsertests. Produktionsdiagnostik wurde durch die automatische Freigabeprüfung abgelehnt; der konkrete bisherige Produktionsauftrag konnte nicht eingesehen werden.
