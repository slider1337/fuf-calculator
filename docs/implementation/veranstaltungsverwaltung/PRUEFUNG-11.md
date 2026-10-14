# Schritt 11 – blockiert am Calculator-Abnahme-Gate

Stand: 13.10.2026. Beauftragt sind die restlichen Schritte; die Voraussetzungen dieses Schrittes sind weiterhin nicht erfüllt. [AUFGABE-11](AUFGABE-11-website.md) verlangt „Schritt 10 vollständig abgenommen und kompatibler Calculator bereitgestellt“. [ABNAHME](ABNAHME.md) verlangt unter anderem belegte externe Schreib-/Abgleichverträge und Benachrichtigungssperren, manuelle Abnahme und abgeschlossene Migration/Bereitstellung.

Vorbereitet: API 1.5.0, signierter Mitgliedervertrag mit stabiler Termin-ID-Zuordnung, Offline-Testclient, tatsächlicher Familien-/Korrektur-/Storno-/Ergänzungsablauf und negative Tests im Calculator. [Handover](../../integration/member-api.md) dokumentiert Serverkonfiguration, Regeln, Übergang und offene Voraussetzungen. Website `main`, AGENTS.md, vorhandener Login und `anmeldung-kontext.php` wurden ausschließlich gelesen; Referenzen W01/W02/W03 gelesen.

Keine Websiteänderung und keine produktive Umstellung vorgenommen. Die gesperrten Provideradapter, Gast-/Personenkonflikt-/Integrationsverträge und manuellen Abnahmen werden nicht durch ein vorzeitig aktiviertes WordPress-Formular ersetzt. Dieser Schritt ist **nicht fertig**. Die Vorbereitung ist reviewbar, die Sperren in [PRUEFUNG-09](PRUEFUNG-09.md) und [PRUEFUNG-10](PRUEFUNG-10.md) konkret erfasst.

Aktualisierung 14.10.: öffentliche vollständige API-Doku erfolgreich ausgelesen; lesender Mitglieder-Adapter implementiert. Gemeinsamer Anmeldeschluss und Reise-Archivierung gemäß Nutzerentscheidung, kein zusätzlicher Lösch-/Ablaufauftrag. Siehe [Folgeprüfung](PRUEFUNG-14-10.md) und [Providerprüfung](../../integration/easyverein-v3-review.md).


Aktueller Stand nach Abschlussarbeiten 14.10.: reale Adapter, Gastfreigaben, konkrete Personen-/Preisklärung, feldweise Termin-PATCH-Aufträge, Mitgliederfreigabe in der Oberfläche und Serverbetrieb sind implementiert. OpenAPI 1.7.0 / unveränderter Mitgliedervertrag 1.6.0. Diese sind keine verbleibenden Implementierungssperren mehr. Offen bleiben die ausdrücklich geforderte manuelle Design-/Providerabnahme sowie tatsächlicher Merge und kompatible Bereitstellung. Keine Websiteänderung, kein parallel aktivierter Schreibweg. Siehe [Abschlussprüfung](PRUEFUNG-ABSCHLUSS.md) und [Betrieb](BETRIEB.md).
