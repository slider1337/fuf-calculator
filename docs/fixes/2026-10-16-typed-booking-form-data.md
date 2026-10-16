# Typisierte Buchungs- und Formulardaten

Buchungen, Personen, Antwortrevisionen, Idempotenzbelege, Formularversionen und
Formularbindungen besitzen präzise PHPStan-Verträge. Die SQLite-Adapter prüfen
Text- und Integerwerte beim Einlesen und interpretieren beschädigte Werte nicht
mehr stillschweigend als null oder 0. Formulardefinitionen prüfen ihre grundlegenden
Metadaten beim Laden. Unbekannte Geburtstage und nullable Provider-Referenzen
bleiben unverändert erhalten.

Der Antwortvalidator beschreibt seine Antwortkarten und sein vollständiges
Ergebnis. Fehlende Datensätze werden in betroffenen Aufrufern explizit behandelt.
Der Analytics-Code behandelt die Buchungsebene anhand des fehlenden Personenobjekts,
statt von einer zufälligen ID-Typableitung abhängig zu sein.

PHPStan Level 10: Baseline von **1634 auf 1300** reduziert, also **334 Altbefunde**
behoben. Keine neuen oder erhöhten Baseline-Ausnahmen, keine zusätzlichen
Pfadausschlüsse, keine Type-Casts zur Unterdrückung von Fehlern.

Drei neue Regressionstests prüfen beschädigte Revisionen, ungültige gespeicherte
Formulardefinitionen und die Erhaltung unbekannter Geburtstage. Die betroffenen
72 vorhandenen Buchungs-/Formular-/Analyse-/Teilnahmetests sind erfolgreich.
Die vollständige Suite besteht mit **437 Tests / 6960 Assertions**, ohne
Deprecations oder PHPUnit-Hinweise. `composer analyse` ist erfolgreich.

Dieser PR baut auf `refactor/booking-build-maintenance` auf. Die 1300 übrigen
Altbefunde bleiben sichtbar und müssen in weiteren fachlichen Schritten abgebaut
werden; die gesamte Anwendung wird damit noch nicht als baselinefrei bezeichnet.
