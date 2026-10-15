# Festhängenden Terminauftrag klären

## Fehlerbild und Grenzen der Diagnose

Der Screenshot zeigt einen Terminauftrag im Zustand `unknown`, ohne bestätigte externe Terminbindung. Trotzdem war darunter „Teilnahmen abgleichen“ aktiv. Dessen Endpunkt verlangt eine bestätigte Bindung und antwortete folgerichtig mit `confirmed_term_required`; die Oberfläche zeigte den technischen JSON-Fehler ungefiltert an.

Warum die ursprüngliche Anlage nicht bestätigt wurde, ist aus dem Screenshot allein nicht belegbar. Der bisherige Worker fasste sämtliche Ausnahmen als `external_outcome_unknown` zusammen, auch eindeutig abgelehnte Event-POSTs und fehlgeschlagene Leseprüfungen vor dem ersten POST. Die ursprüngliche Providerantwort lässt sich daraus nicht rekonstruieren. Es wird kein neues Produktiv-Testevent erzeugt und keine bestehende unbekannte Anlage blind wiederholt.

## Korrektur

- Teilnahmeabgleich/Gastfreigaben werden erst nach bestätigter Terminbindung geladen. Solange diese fehlt, steht dort eine kurze Erklärung. Der lesende Teilnahme-Status meldet `confirmed_term_required` als Sperrursache; vorhandene Clients können dadurch ihre Abgleichaktion ebenfalls deaktivieren.
- Unklare/gesperrte Aufträge bieten „Vorhandenen Termin suchen und zuordnen“. Der Dialog sucht über den bestehenden Katalog, verlangt eine ausdrückliche Trefferauswahl und nutzt den bereits vorhandenen geprüften Link-Endpunkt. Keine Freitext-ID, kein neuer Event-POST. Suchwechsel und veraltete Suchantworten entfernen bzw. verhindern eine überholte Auswahl. Vor der Zuordnung Datum und Termin prüfen; falls kein passender Termin existiert, die Anlage direkt in easyVerein prüfen/fertigstellen und anschließend suchen.
- Die Zuordnung ist während `pending`, `running` und nun auch `reconciling` serverseitig gesperrt. Ein Auftrag in `unknown` darf bewusst einem vorhandenen Termin zugeordnet werden. Der ursprüngliche Auftrag wird dabei abgeschlossen; der Worker legt keinen zweiten Termin an. Rechte-/Archiv-/Abschluss- und Eindeutigkeitsprüfungen bleiben bestehen.
- Eine neue interne Ausnahme signalisiert ausschließlich den bewiesenen Fall „Event wurde nicht angelegt“: Fehler bei der Kalender-Vorprüfung oder eindeutige Ablehnung des ersten Event-POSTs. Der Worker stoppt dann mit `blocked` und verständlichem Fehlercode. HTTP 401, 403 und 400/422 werden in Tokenablehnung, fehlendes Schreibrecht und abgelehnte Termindaten unterschieden. Gespeichert werden nur fest definierte Codes, keine Antworttexte oder Tokens.
- Timeouts, verlorene Antworten sowie Fehler bei Preisgruppen/Aktivierung/Readback nach einer möglichen Eventanlage bleiben `unknown` und werden ausschließlich abgeglichen. Bekannte, nicht sensible Lesefehler bleiben als definierte Fehlercodes sichtbar. Bestehende unbekannte Aufträge werden nicht nachträglich zu „sicher fehlgeschlagen“ umgedeutet.
- Relevante Fehler im globalen und im Dialogstatus erscheinen als Handlungsanweisung statt als technischer JSON-Text.

Die offizielle API-Dokumentation beschreibt HTTP 400 als fehlgeschlagene Feldvalidierung und führt die Zugangsarten auf: https://easyverein.com/api/v3.0/documentation. Die Provider-Nutzlast wird hier nicht aufgrund einer unbewiesenen Vermutung geändert.

## Validierung

`node tests/Frontend/term-setup.cjs`: 18 Szenarien erfolgreich. Zusätzlich zum bisherigen Einrichtungsablauf: keine Teilnahmeabfrage vor bestätigter Bindung, Klärungsaktion bei `unknown`, ausschließlich lesende Suche, zwingende Trefferauswahl, stabiles ID-Linking ohne Create-Auftrag, Verwerfen überholter Treffer und verständliche Fehlertexte.

51 gezielte PHPUnit-Tests / 3.812 Assertions erfolgreich: sichere Vorprüfungs-/POST-Ablehnung, Unterscheidung der HTTP-Ursachen, mögliche Teilanlage bleibt unklar, manueller Abschluss eines unbekannten Auftrags ohne zweiten Create sowie Sperre paralleler Anlage/Abgleiche und bestehende Mitgliederabläufe. Vollständige PHPUnit-Suite: 384 Tests / 6.159 Assertions ohne Fehler oder Fehlschläge erfolgreich; 12 bestehende OIDC-Deprecations. PHP-/JavaScript-Syntax und `git diff --check` geprüft.

Keine Migration oder neue API-Route. Keine echten Testtermine, Teilnahmen oder Mails; keine Browser-/Lighthouse-Automation. Der vorhandene Produktivauftrag wurde nicht verändert oder manuell zugeordnet. Pull-Request-Erstellung führt keinen Merge und kein Deployment aus.
