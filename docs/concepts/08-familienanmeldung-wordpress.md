# Konzept 8: Eigenes WordPress-Plugin für Familienanmeldungen

**Status:** Entwurf, 25.09.2026. Keine Implementierung.

## Voraussetzung: gemeinsamer easyVerein-Login

Das [SSO-Konzept](01-sso-benutzerprovisionierung.md) wird vor der Anmeldung um WordPress als eigenen OIDC-Client erweitert. Der bestehende Kalkulator und WordPress verwenden denselben easyVerein-Identitätsanbieter, aber getrennte Client-Konfigurationen und lokale Sitzungen. Eine stabile Kennung `(issuer, sub)` verbindet die Anmeldung mit einem lokalen WordPress-Benutzer; gleiche E-Mail-Adressen werden nicht automatisch zusammengeführt. Der Login ist auch für Nichtmitglieder mit einem dafür zugelassenen easyVerein-Konto zu prüfen. Fehlt dieser Zugang, braucht es eine bewusst entschiedene Alternative vor Freigabe der Gastanmeldung; Gäste selbst benötigen keinen Login.

Die öffentliche Veranstaltungsanzeige aus [Konzept 7](07-veranstaltungen-easyverein-clubdesk.md) braucht keinen Login. Erst das eigene Anmeldeplugin verlangt ihn. Ein angemeldeter Erwachsener ist Kontaktperson und kann mehrere Personen gemeinsam anmelden; dies beweist nicht automatisch eine rechtliche Vertretungsberechtigung oder eine technische Familienbeziehung in easyVerein.

## Ablauf

1. Veranstaltung öffnen und anmelden; nach easyVerein-Login zur gleichen Veranstaltung zurückkehren.
2. Kontaktperson aus dem Konto anzeigen. Weitere berechtigte Familienmitglieder auswählen, soweit easyVerein diese Beziehung über verfügbare Daten/Rechte belastbar liefert. Sonst Personen ausdrücklich erfassen und Zuordnung manuell prüfen.
3. Gäste zusätzlich mit mindestens Name und den für die Veranstaltung erforderlichen Angaben hinzufügen; Gast und Mitglied klar kennzeichnen. Keine eigene easyVerein-Anmeldung pro Gast.
4. **Für jede Person einzeln** eine gültige easyVerein-Preisgruppe wählen. Verfügbarkeit, Preis, Beschreibung, Regeln und zulässige Mehrfachauswahl aus dem Termin übernehmen. Keine automatisch angenommene gemeinsame Familien-Preisgruppe.
5. Zusammenfassung mit Einzelpreisen, Gesamtbetrag, Datenschutz-/Teilnahmehinweisen und verbindlicher Bestätigung zeigen. Danach eine Gruppenbestätigung mit den einzelnen Teilnehmern ausgeben.
6. Änderungen und Storno für die eigene Gruppe bis zu den geltenden Fristen ermöglichen; Verfügbarkeit und Preis bei Bestätigung erneut prüfen. Bei Teilfehlern keine stillen Doppelanmeldungen erzeugen.

## Daten und Synchronisation

Das Plugin hält eine Gruppenanmeldung mit Kontaktperson, easyVerein-Termin-ID, einzelnen Teilnehmern, deren Preisgruppen, Status, Zeitstempeln und externer Referenz. Es speichert nur die notwendigen personenbezogenen Daten. Serverseitige Anfragen laufen mit getrennten, minimal berechtigten Zugangsdaten; Geheimnisse nicht im Browser oder Repository. Wiederholte Übermittlung nutzt einen idempotenten Schlüssel.

**Ziel:** Jede Person ist als eigene Teilnahme mit ihrer Preisgruppe in easyVerein nachvollziehbar; die Gruppe verbindet sie in WordPress. Vor Umsetzung muss im konkreten easyVerein-Account geprüft werden, ob API und Berechtigungen das Anlegen/Ändern von Teilnahmen, Gästen, Preisgruppen und Kapazitätsprüfungen erlauben. Ist das nicht möglich, wird der Anmeldeprozess zunächst als überprüfbare Anfrage mit Moderation entworfen; keine scheinbar bestätigte easyVerein-Teilnahme behaupten. Ein paralleles, unabhängiges Buchungssystem wäre eine gesonderte Produktentscheidung.

## Sonderfälle und Schutz

- Ausgebuchte Preisgruppe, volle Veranstaltung, Warteliste, Anmeldeschluss, Altersgrenzen und andere Terminregeln sichtbar und serverseitig prüfen.
- Doppelanmeldung einer Person zum selben Termin erkennen; Storno und Preisänderungen protokollieren.
- Bei Mitgliedern Identität und Freigabe zur Anmeldung Dritter prüfen; für Gäste Einwilligung/Information und sparsame Datenverarbeitung vorsehen.
- Kontaktperson sieht nur ihre Gruppe; Orga sieht notwendige Anmeldedaten. Gastdaten werden nicht öffentlich dargestellt.
- Zahlungsart, Fälligkeit, Erstattung und Rechnungsstellung bleiben vor Umsetzung zu entscheiden; die Anzeige des Gesamtpreises löst noch keine Zahlung aus.

## Abnahme

Eine Familie kann sich mit einem easyVerein-Login gemeinsam anmelden, Gäste hinzufügen und für jede Person separat eine zulässige Preisgruppe wählen. Nach Bestätigung entsprechen die einzelnen easyVerein-Teilnahmen der Gruppenübersicht; erneutes Absenden erzeugt keine Duplikate. Fehler und nicht verfügbare Preisgruppen verhindern eine falsche Bestätigung.

## Quellen

- [easyVerein Identity Provider](https://hilfe.easyverein.com/en/articles/2746882)
- [easyVerein API](https://hilfe.easyverein.com/en/articles/360898)
- [Preisgruppen zu Terminen](https://hilfe.easyverein.com/en/articles/755074)
- [Externe Anmeldungen](https://hilfe.easyverein.com/en/articles/755202)
