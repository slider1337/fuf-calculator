# Dauerhafte GitHub-Freigabe für das FuF-Projekt

Der Nutzer hat am 13.10.2026 ausdrücklich folgende universelle Freigabe für das FuF-Projekt erteilt:

> Du kannst immer nach github laden und pr erstellen, Speicher das gleich im Projekt als Universal Freigabe

Diese Freigabe gilt dauerhaft für beauftragte Arbeiten in den zum FuF-Projekt gehörenden GitHub-Repositories, einschließlich `slider1337/fuf-calculator` und `slider1337/fuf-website`.

- Änderungen dürfen in einem eigenen Arbeitsbranch committet und auf GitHub hochgeladen werden.
- Pull Requests dürfen erstellt und im Verlauf des Auftrags aktualisiert werden.
- Für diese GitHub-Uploads und Pull Requests ist keine erneute Rückfrage erforderlich.

Die Freigabe umfasst Branch-Uploads und Pull Requests. Merge und produktives Deployment richten sich weiterhin nach dem jeweiligen Nutzerauftrag. Zugangsdaten und andere Geheimnisse gehören nicht in GitHub-Uploads.

# Mailfreigabe vom 14.10.2026

Der Nutzer hat präzisiert: „Du kannst schon Mails verschicken aber nur an meine Adresse, später wenn das Ding umgesetzt ist, soll das natürlich schon passieren“.

- Während Arbeiten/Tests dürfen tatsächliche Mails ausschließlich an `me@benna.me` gehen.
- Normale Benachrichtigungen an Mitglieder sind für den später fertiggestellten produktiven Anmeldeweg vorgesehen.
- Externe easyVerein-Schreibtests verwenden Fakes. Wo Provider-Benachrichtigungen nicht sicher auf diese Testadresse begrenzbar sind, keine echten Testtermine/-teilnahmen oder globalen Provider-Konfigurationsänderungen zum Umleiten durchführen.
- Produktive Schreib-/Mailfreigabe erfolgt über die ausdrücklich konfigurierte Betriebsart nach Fertigstellung/Bereitstellung, nicht automatisch in der Entwicklungsumgebung.

# easyVerein-Testfreigabe vom 15.10.2026

Der Nutzer hat ausdrücklich klargestellt: „das ist alles noch im Test also ja du darfst Termine anlegen und auch löschen“.

- Für beauftragte Fehleranalyse dürfen echte easyVerein-Testtermine angelegt und eigene eindeutig zugeordnete Testtermine wieder gelöscht werden. Dies ersetzt für diese Termine die frühere Einschränkung auf reine Fake-Schreibtests.
- Der Nutzer hat das Auslesen des fehlgeschlagenen Produktionsauftrags einschließlich ausgewählter Termindaten in GitHub-Aktionsprotokollen freigegeben.
- Zugangsdaten bleiben geheim. Unbeteiligte Termine, Mitgliederdaten und Teilnahmen sind nicht von der Löschfreigabe erfasst. Lokale automatisierte Tests verwenden weiterhin Fakes.

# Vereinfachter easyVerein-Ablauf vom 15.10.2026

Der Nutzer möchte keine manuelle Personenzuordnung und keine zusätzliche individuelle Gastfreigabe. Direkte easyVerein-Teilnahmen werden über ihre stabilen Provider-Referenzen automatisch übernommen. Wenn der easyVerein-Termin Gäste erlaubt, dürfen diese im Mitgliederweg direkt angemeldet werden; das anmeldende Mitglied bleibt Kontakt. Frühere Konzepte mit zusätzlicher Orga-Zulassung sind insoweit überholt. Namen sind weiterhin kein Identitätsnachweis; Formularantworten und nicht belegte Buchungs-/Familienzugehörigkeit dürfen nicht erfunden werden.

# Statische Analyse

- `composer analyse` prüft auf PHPStan Level 10 und muss vor einem PR erfolgreich sein.
- Der Typisierungsrückstand ist vollständig behoben. PHPStan läuft ohne Baseline. Keine neuen Baselines, Fehlerunterdrückungen oder ausgeschlossenen Prüfpfade hinzufügen; neue Befunde an der Ursache beheben.
- Nach Typ-/Vertragsänderungen die betroffenen Tests und vor Abschluss die PHPUnit-Suite ausführen.

# Einfache Terminbearbeitung vom 16.10.2026

Der Nutzer möchte Terminänderungen unmittelbar speichern und das Ergebnis bzw. den konkreten Fehler direkt sehen. Keine manuelle Vorschau, keine Feld-Übertragen-Checkboxen und keine Eingabe eines Grundes im Terminbereich, auch nicht beim Abgleich oder Auflösen alter Aufträge. Audittexte werden intern erzeugt. Beginn und Ende kommen aus der gespeicherten Reise und werden nicht separat im Termin eingetippt. Normale externe Termindaten werden automatisch abgeglichen; Buchungspreise dürfen dabei nicht automatisch geändert werden. Der Nutzer hat die Live-Bereitstellung dieser Vereinfachung beauftragt.

## Verbindliche Softwarequalität seit 19.10.2026

Diese Regeln gelten für jede Implementierungsphase im FuF-Projekt:

1. **Fehler vor der Korrektur reproduzieren.** Für jeden gemeldeten Fehler zuerst einen passenden Regressionstest erstellen und sein Fehlschlagen am ursprünglichen Verhalten nachweisen. Dann die Ursache beheben und denselben Test erfolgreich ausführen. Ist die Reproduktion nicht möglich, das ausdrücklich melden und zunächst die Diagnose verbessern; keine unbelegte Behauptung, der Fehler sei behoben.
2. **Vollständige Benutzerabläufe prüfen.** Betroffene Abläufe über ihre Komponentengrenzen absichern: Reise/Veranstaltung/Termin anlegen und bearbeiten; Mitglied und Gast anmelden, übertragen und abmelden; Preisgruppen ändern; externe Abweichungen anzeigen und auflösen; archivieren/löschen; Beschreibung vom Editor bis zur Website. Auch Wiederholung, Timeout und teilweise erfolgreiche Übertragung prüfen. Wiederholungen dürfen keine Doppelbuchungen oder doppelte externe Schreibaktionen erzeugen. Ein isolierter Unit-Test belegt keinen vollständigen Ablauf.
3. **Einheitliche Qualitätsprüfung.** Vor Abschluss müssen passende Regressionstests, betroffene Integrationsprüfungen und die im Repository vorgeschriebenen Build-/Analyseprüfungen erfolgreich sein. Im Calculator gilt PHPStan Level 10 ohne neue Baseline oder Unterdrückungen. Bei Oberflächenänderungen den tatsächlichen Bedienablauf prüfen, soweit die geltenden Freigaben dies erlauben. Die besondere Website-Regel bleibt erhalten: Agenten starten keine Browser-/Lighthouse-Prüfungen; diese als „optional, durch Nutzer manuell“ ausweisen. Nicht ausführbare Prüfungen ausdrücklich nennen und keinen bestandenen Status erfinden.
4. **Nachvollziehbare Fehler.** In der Oberfläche betroffene Person, fehlgeschrittenen Vorgang, verständliche Ursache und nächsten möglichen Schritt zeigen, soweit bekannt und zugriffsberechtigt. „Klärung nötig“ allein genügt nicht. Technische Protokolle müssen Auftragsreferenz, technischen Fehlercode und relevante Stackstelle nachvollziehbar machen, ohne Secrets, vollständige Providerantworten oder unnötige personenbezogene Daten zu protokollieren. Unbekannte Ursachen nicht erfinden.
5. **Kleine Änderungen und belegbarer Abschluss.** Zusammengehörige Änderungen in überschaubaren PRs halten. Der Abschluss nennt Ursache/Problem, Korrektur, tatsächlich ausgeführte Prüfungen und verbleibende Unsicherheiten. Die Zustände „implementiert“, „automatisiert getestet“ und „im Zielsystem bestätigt“ unterscheiden. Ein Fehler gilt erst als behoben, wenn der ursprüngliche Ablauf nachweislich funktioniert und ein passender Regressionstest einen Rückfall erkennt; fehlende Nachweise klar benennen.

### Manuelle reale API-Vertragsprüfung

- Der gemeinsame Test liegt in `slider1337/fuf-calculator`: `scripts/manual/api_contract_test.py`; Anleitung: `docs/testing/manual-api-contract.md`. Er prüft ausgewählte echte HTTP-Aufrufe gegen Calculator und easyVerein. Sein Umfang ist kein Nachweis des gesamten Buchungsablaufs.
- Diesen Test vorerst ausschließlich manuell und auf ausdrückliche Aufforderung des Nutzers ausführen. Wenn der Nutzer dazu auffordert, darf der Agent ihn jederzeit starten, ohne erneut dieselbe Freigabe einzuholen; benötigte Konfiguration und bestehende Mail-/Datengrenzen beachten. Die Erstellung des Tests ist keine Aufforderung zum echten Lauf.
- **Am Ende jeder Implementierungsphase dem Nutzer ausdrücklich vorschlagen, diesen API-Test auszuführen.** Der Vorschlag startet ihn nicht. Kein automatischer Start über Push, PR, Cron, Deployment oder die normale Testsuite.
- Nur eindeutig gekennzeichnete eigene Testdaten anlegen. Sie bei Erfolg, Fehler und behandelbarem Abbruch wieder bereinigen und die Entfernung prüfen. Bei hartem Prozessabbruch oder API-Ausfall mit dem beständigen Laufjournal die Bereinigung wiederholen; zurückgebliebene/ungeklärte Daten sichtbar melden und den Lauf als fehlgeschlagen behandeln. Keine fremden Termine, Teilnahmen oder Mitgliedsdaten verändern/löschen. Keine automatische Wiederholung mehrdeutiger POST-Aufrufe.
- Zugangsdaten ausschließlich aus der Umgebung, niemals ausgeben oder committen. Bestehende Mailgrenze bleibt: tatsächliche Testmails ausschließlich an `me@benna.me`. Der Test verwendet einen dafür vorgesehenen Kalender und einen ausdrücklich konfigurierten Testkontakt mit dieser Adresse; keine globalen Provider-/Mailkonfigurationen verändern.

## Gemeinsamer Reverse Proxy seit 26.10.2026

- `server-proxy` verwaltet öffentliche Ports, Routing und Zertifikate unabhängig. Calculator darf den Proxy nie stoppen, ersetzen, als orphan entfernen oder dessen Volumes/Images bereinigen.
- Produktions-Compose nutzt ausschließlich internes HTTP :80, externes `benna-web` mit Alias `fuf-calculator` und das ausdrücklich benannte bestehende SQLite-Volume. `proxy.env` wird lokal gepflegt und nicht durch write-env.py ersetzt.
- Deployment-Sperre vor Dateiübertragung bleibt bis zur freigegebenen und live geprüften Migration bestehen. Änderungen an Ports/Netzen/Zertifikaten benötigen einen konkreten Migrations- und Rollbackplan. Keine automatische Migration beim Merge.
- Für Infrastrukturänderungen zusätzlich `python3 -m unittest discover -s tests/Deployment -v`, Compose config --quiet und Caddy validate ausführen. Live-Prüfungen und fehlende Werkzeuge getrennt melden.
