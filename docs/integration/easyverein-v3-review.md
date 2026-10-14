# easyVerein v3.0 – öffentlicher Vertragsabruf korrigiert

Stand: 14.10.2026. Quelle: https://easyverein.com/api/v3.0/documentation . Die Seite ist **öffentlich ohne Login erreichbar**. Der frühere Textabruf zeigte nur „Input“ und wurde irrtümlich als fehlender Detailzugang gewertet. Ein direkter GET liefert HTML mit vollständigem eingebettetem `var spec = …` (OpenAPI 3.0). Ein Export vom Nutzer ist nicht erforderlich.

Ein funktionaler Auszug mit Endpunkten, Feldnamen/-typen, HTTP-Status und SHA-256 des abgerufenen HTML steht in [easyverein-v3-contract.json](easyverein-v3-contract.json). Die komplette fremde Dokumentation wird nicht im Projekt dupliziert.

## Nachgewiesen

- Event POST/PATCH dokumentiert Name, Zeitrahmen, Ort, Prolog, Kapazität, Anmeldefrist, Zugang und Benachrichtigungsfelder. `send_mail_check` betrifft neue Teilnahmen an die Vereinsadresse; `confirmation_to_addresses` benennt weitere Benachrichtigungsadressen. Diese beiden Felder beweisen nicht allein, dass keinerlei Mitgliedsbestätigung oder App-Nachricht ausgelöst wird.
- Teilnahme POST/PATCH dokumentiert Adresse, Status, Beschreibung, Begleitperson und deren Name/E-Mail. Statuswerte 0–5 sind beschrieben. Teilnahme- und Event-Schreibaktionen sowie separate Benachrichtigungsaktionen existieren.
- `create-participation-with-price-groups` ist dokumentiert, hat in diesem veröffentlichten OpenAPI-Dokument allerdings **kein requestBody-Schema**; Standard-Teilnahme-POST zeigt ebenfalls nicht alle vom bestehenden Websitecode verwendeten Referenzfelder. Responses enthalten hauptsächlich Statusbeschreibungen statt vollständiger Feldschemas.
- Preisgruppen können nach Event gefiltert werden; Eventreferenz, Name, Verkaufspreis und Stückzahl sind im Preisgruppen-POST beschrieben.
- Der bestehende Website-Vertrag verwendet Mitglieds-ID, eigene Kontaktreferenz und explizite Familienfreigaben. Der neue lesende Directory-Adapter bildet diese Checks im Calculator nach, liest freigegebene Kinder zusätzlich neu und lehnt unklare Referenzen/Geburtsdaten/Kontaktmehrdeutigkeit ab.

## Implementierter Zugriff

`EasyVereinReadHttpClient` ist ein ausschließlich lesender Transport: fester HTTPS-Origin, explizite Ressourcen-Allowlist, keine Redirects, Zeit-/Größenlimits, keine Response-/Tokenwerte in Fehlermeldungen. Konfiguration `EASYVEREIN_API_TOKEN`, nur auf dem Calculator-Server. `EasyVereinMemberDirectory` verwendet diesen Transport. Ohne Token gibt es 503; es gibt kein geratenes Profil. Tests verwenden fiktive Antworten, keine echten Mitgliederdaten.

## Noch gesperrt

Echte Termin-/Teilnahme-Schreibadapter bleiben deaktiviert, bis das Benachrichtigungsverhalten sämtlicher verwendeter Wege eindeutig geklärt ist. Ein vorhandener separater Mail-Endpunkt beweist nicht, dass ein normales POST/PATCH nie automatisch eine Mail sendet. Die bestehende Nutzervorgabe „keine Mails“ gilt unverändert. Der jetzt erfolgreiche öffentliche Abruf beseitigt die **Zugangsfrage zur Dokumentation**, er ist kein echter Test von Providerseiteneffekten. Keine globalen Einstellungen der Vereinsorganisation werden verändert, um eine fehlende einzelne Schreiboption zu umgehen.

Nutzerentscheidungen am 14.10.: Änderungen/Stornos bis Anmeldeschluss; Archivierung statt zusätzlichem automatischem Lösch-/Ablaufkonzept. Diese Entscheidungen sind umgesetzt und keine offenen Rückfragen mehr.

Aktualisierung nach Mailpräzisierung: Termin-/Teilnahmeschreibadapter mit anschließender lesender Ergebnisprüfung sind implementiert. Produktionsfreigabe separat per EASYVEREIN_WRITE_MODE; echte Tests verwenden Fakes, normale produktive Benachrichtigungen sind erwünscht. Siehe [neuestes Prüfprotokoll](../implementation/veranstaltungsverwaltung/PRUEFUNG-14-10-SCHREIBWEGE.md).
