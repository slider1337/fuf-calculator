# Veranstaltungsverwaltung, versionierte Formulare und Orga-Auswertung

Stand: 12.10.2026 · Design- und Fachkonzept · noch keine Implementierung.

## 1. Ziel und verbindliches Grundmodell

Der FuF Calculator verwaltet künftig Veranstaltungen als dauerhaften Rahmen. Jede Veranstaltung kann beliebig viele Reisen enthalten. Jede Reise ist eine konkrete Durchführung mit eigener Kalkulation, Anmeldungen, Orga und genau einem zugeordneten easyVerein-Termin. Das versionierte Anmeldeformular gehört zur Veranstaltung und wird für die konkrete Reise ausdrücklich ausgewählt.

Beispiel: **Winterfreizeit** enthält **Winterfreizeit 2027** und **Winterfreizeit 2028**. Beide verwenden ein Formular der Winterfreizeit, können aber unterschiedliche veröffentlichte Versionen verwenden. Auch mehrere Reisen im selben Jahr sind erlaubt. „Reise“ bleibt der bestehende technische Begriff für eine Durchführung; Tagesveranstaltungen können ohne Unterkunft und ohne Nächte angelegt werden.

Die Veranstaltung entwickelt die „Reisegruppe“ aus [Konzept 4](04-reisegruppen-orga-defaults.md) weiter. Es entsteht keine zusätzliche parallele Ebene „Reisegruppe“. Die Regeln für Teamvorlagen und historische Rechte bleiben bestehen. Für Formularhaltung, Antwortspeicherung und Auswertung ersetzt dieses Konzept die zuvor vorgeschlagene primäre Speicherung in WordPress. [Konzept 8](08-familienanmeldung-wordpress.md) bleibt Grundlage der Website-Oberfläche, wird aber um den Calculator als zentrales Backend ergänzt. Alte Beschreibungen sind historische Konzepte, kein aktueller Implementierungsnachweis.

### Begriffe für das Design

| Begriff | Inhalt | Beispiel |
| --- | --- | --- |
| Veranstaltung | Dauerhafter Rahmen, Beschreibung, Standardteam, Formular mit Versionshistorie | Winterfreizeit |
| Reise | Konkrete Durchführung, Zeitraum, Kalkulation, Team, Antworten | Winterfreizeit 2027 |
| Termin | Externer easyVerein-Kalendereintrag der Reise | easyVerein-Termin-ID |
| Formularversion | Unveränderliche veröffentlichte Definition der Zusatzfragen | Version 3 |
| Buchung | Gemeinsame Anmeldung durch ein Mitglied | Familie mit vier Personen |
| Teilnahme | Einzelne Person innerhalb der Buchung mit easyVerein-Referenz | Ein Kind |
| Antwortrevision | Historischer Stand einer abgegebenen Antwort | Original und spätere Korrektur |

## 2. Zuständigkeit der Systeme

| System | Verantwortlich für |
| --- | --- |
| Calculator | Veranstaltungen, Reisen, Formularversionen, Antworten, Buchungsgruppen, Orga-Rechte, Auswertungen, Kalkulation und Abrechnung |
| easyVerein | Mitgliederdaten, zulässige Familienbeziehungen, Kalendertermin, Teilnahmen, Preisgruppen und Vereinsrechnungen |
| WordPress | Öffentliche Terminseiten und bestehende Mitglieder-Anmeldung; rendert das ausgewählte Formular und übermittelt an den Calculator |

Die Website bleibt der Einstieg für Mitglieder. Sie braucht dieselben Zusatzfragen nicht noch einmal selbst zu konfigurieren. Der Calculator bietet eine gemeinsame Formular- und Buchungs-API; eine dortige Anmeldung verwendet denselben fachlichen Ablauf. Kein öffentliches Calculator-Verwaltungskonto ist für normale Mitglieder nötig.

Die Anmeldung im Calculator mit E-Mail/Passwort oder dem bereits ergänzten easyVerein-Login bleibt erhalten. Mitgliederlogin auf der Website und Orga-Zugriff sind unterschiedliche Rechte. Eine verknüpfte easyVerein-Identität gewährt nicht automatisch Orga-Rechte.

Eine gemeinsame Buchungs-ID verbindet Calculator, Website und easyVerein-Teilnahmen. Antworten werden nicht als maßgebliche Daten in eine unstrukturierte Terminnotiz geschrieben. Ein optionaler interner Link zur Calculator-Reise in easyVerein muss weiterhin eine geschützte Ansicht öffnen.

## 3. Navigation und zu gestaltende Seiten

Die vorhandenen FuF-Farben, Karten, Kennzahlen, Tabellen, Typografie und Mobilregeln aus [DESIGN.md](../design-handoff/DESIGN.md) und [RESPONSIVE.md](../design-handoff/RESPONSIVE.md) weiterverwenden. Die neue Funktion erweitert das Produkt und darf bestehende Planung/Abrechnung nicht als reines Layoutprojekt behandeln.

Hauptnavigation: **Veranstaltungen · Reisen · Mein Konto**. **Benutzer** und **Globale Einstellungen** erscheinen gemäß Berechtigung. „Reisen“ bleibt eine veranstaltungsübergreifende Schnellübersicht. Formulare werden innerhalb ihrer Veranstaltung verwaltet.

| Ansicht | Aufbau und zentrale Aktionen |
| --- | --- |
| Veranstaltungen | Suche, Aktiv/Archiviert-Filter; Karten oder Tabelle mit Name, sichtbaren Reisen, nächster Reise, Formularstatus; „Veranstaltung anlegen“ |
| Veranstaltung: Übersicht | Breadcrumb, Titel, Beschreibung; Tabs „Reisen“, „Formular“, „Standardteam“, „Einstellungen“ |
| Veranstaltung: Reisen | Zeitraum, Reise-/Anmeldestatus, Personen/Buchungen, Formularversion, Terminstatus; „Neue Reise“, „Öffnen“, „Duplizieren“ |
| Reise anlegen | Veranstaltung wählen, Eckdaten, Teamvorschlag, Formularversion, Termin anlegen oder vorhandenen verknüpfen; abschließende Zusammenfassung |
| Reise: Übersicht | Kennzahlen, Anmeldelink, Team, ausgewählte Formularversion, easyVerein-Verbindung und offene Klärfälle |
| Reise: Planung | Bestehende Kosten- und Preisberechnung; Vorschau zur Übernahme realer Anmeldezahlen |
| Reise: Anmeldungen | Suche, Status-/Versions-/Quellenfilter; Buchungszeilen mit aufklappbaren Personen und Zusatzantworten |
| Reise: Auswertung | Zahlen-Summen und Checkbox-Verhältnisse; Filter, Grundgesamtheit, fehlende Antworten, Detailansicht und Export |
| Reise: Abrechnung | Bestehende Abrechnung; Abschlussregeln aus Konzept 3 |
| Reise: Team & Termin | Konkrete Rollen; Kalender, externe Referenz, Konflikt-/Synchronisationsstatus und Bearbeitungsaktionen |

Reisetabs: **Übersicht · Planung · Anmeldungen · Auswertung · Abrechnung · Team & Termin**. Mobil scrollbare Tabnavigation mit klarer aktiver Beschriftung. Keine sechs horizontal gequetschten Buttons.

### Desktop und Mobil

Desktop: Listen mit Suche/Filtern, Reisetitel dauerhaft im Kontext, Auswertungen als kompakte Kennzahlen plus Tabelle. Anmeldedetail in Seitenpanel oder eigener Detailseite. Formular-Editor mit Feldliste links, Feldkonfiguration rechts und Vorschau.

Mobil: Veranstaltungskarten, Buchungskarten mit Name/Personenzahl/Status, Antwortdetails untereinander. Editor als Feldliste → Feld bearbeiten → Vorschau; Reihenfolge per „Nach oben/unten“ bedienbar. Tabellen bei Bedarf gezielt horizontal scrollbar, nicht die gesamte Seite. Summen und Nenner müssen ohne Tooltip verständlich sein. Symbole durch Text ergänzen; alle Aktionen mit Tastatur bedienbar.

## 4. Versionierter Formular-Editor

Die Veranstaltung besitzt eine Formularfamilie mit beliebig vielen Versionen. Eine veröffentlichte Version kann sofort als Ausgangspunkt für einen neuen Entwurf kopiert werden.

### Feldtypen und Einstellungen

| Typ | Einstellungen | Standardauswertung |
| --- | --- | --- |
| Zahl | Ganzzahl/Dezimalzahl, Einheit, Minimum/Maximum, optionaler Startwert | Summe |
| Checkbox | Klarer Ja/Nein-Text; optional zwingend anzuhaken | Anzahl angehakt / Anzahl anwendbar |
| Ja/Nein-Auswahl | Explizite Entscheidung erforderlich oder optional | Ja/Nein/fehlend getrennt |
| Einzelauswahl | Stabile Optionen und Beschriftungen | Anzahl je Option |
| Mehrfachauswahl | Optionen, optionale Auswahlgrenzen | Anzahl je Option; Summe kann größer als Personenzahl sein |
| Kurzer/langer Text | Länge, Hilfetext, optional | Liste und Suche, keine Zahlensumme |
| Datum | Gültiger Bereich | Detail-/Listenansicht |
| Hinweis/Abschnitt | Text und Reihenfolge | Keine Antwort oder Auswertung |

Jedes Antwortfeld erhält eine stabile fachliche ID, Label, Hilfetext, Pflichtregel, Geltungsbereich **pro Buchung** oder **pro Person**, Sichtbarkeitsregel und Auswertung an/aus. Auswahloptionen besitzen eigene stabile IDs. Zahlen zeigen ihre Einheit auch in der Auswertung.

Bedingungen zunächst einfach: Kinder anwesend, Person ist Kind/Erwachsener oder vorherige Auswahl entspricht einer Option. Verborgene, nicht anwendbare Felder sind keine fehlenden Antworten. Pflichtvalidierung läuft auch serverseitig.

„Antwort erforderlich“ bei Ja/Nein akzeptiert beide Entscheidungen. „Muss angehakt sein“ ist eine getrennte Regel für eine tatsächliche Bestätigung. Die Foto-Abfrage wird nicht global eingebaut; ein entsprechendes Feld wäre nur Bestandteil eines ausdrücklich dafür veröffentlichten Veranstaltungsformulars. Allergien werden nicht als Standardfeld aufgenommen.

### Lebenszyklus

1. **Entwurf:** speichern, Felder ändern, sortieren, Desktop-/Mobilvorschau mit Beispielpersonen.
2. **Veröffentlicht:** Versionsnummer, Zeitpunkt, Herausgeber und Änderungsbeschreibung werden festgehalten; Definition unveränderlich.
3. **Neue Version:** Kopie einer veröffentlichten Version bearbeiten; Änderungen vor Veröffentlichung gegenüberstellen.
4. **Archiviert:** keine neue Auswahl für Reisen; bestehende Buchungen bleiben lesbar.

Eine Reise bindet eine veröffentlichte Version fest. Neue Veröffentlichungen ändern vorhandene Reisen nicht automatisch. Ein Versionswechsel zeigt vorab hinzugefügte/entfernte/geänderte Fragen und betroffene Buchungen. Neue Anmeldungen verwenden danach die neue Version. Bestehende Antworten behalten ihre Version; gegebenenfalls wird sichtbar eine Ergänzung benötigt. Ohne bewusste Aktion keine Umdeutung und kein rückwirkend erfülltes Pflichtfeld.

Ein im Browser bereits geöffnetes Formular kann beim Absenden veraltet sein: Versionskonflikt anzeigen, kompatible Eingaben erhalten und neue Pflichtangaben nachfordern. Das Backend darf alte Definitionen nicht stillschweigend gegen die neue Version validieren.

Reine Textkorrektur bei gleicher Bedeutung darf die Feld-ID erhalten. Änderung von Bedeutung, Typ, Einheit oder Geltungsbereich braucht eine neue fachliche ID bzw. ausdrücklich getrennte Auswertungsserie. Gelöschte Felder bleiben in alten Versionen sichtbar.

## 5. Anmeldungen und Antworten

Die Kontaktperson wählt berechtigte Familienmitglieder und gegebenenfalls zugelassene Gäste. Genau eine zulässige Preisgruppe pro Person, sofern der Termin Preisgruppen hat. Ohne Preisgruppen entfällt diese Auswahl. Gastzulassung und Gastpreis werden durch das Orgateam entschieden; Gäste melden sich nicht selbst an. Rechnungsempfänger ist das Vereinsmitglied.

Ablauf: **Personen → Zusatzangaben → Zusammenfassung → Verbindlich anmelden → Ergebnis**. Gemeinsame Felder einmal zeigen, persönliche Felder pro Person. Die Zusammenfassung benennt, für wen Angaben und Bestätigungen gelten.

Antworten werden mit Buchung, optionaler Person, Formularversion, Feld-ID, typisiertem Wert, Zeitpunkt und Herkunft gespeichert. Bei Bestätigungen bleibt der konkrete Wortlaut über die unveränderliche Version nachweisbar. Korrekturen erzeugen eine Revision mit handelnder Person und Änderungsgrund für Orga-Eingriffe. Auswertungen verwenden nur die aktuelle gültige Antwort, nicht alle Revisionen.

Buchungs- und Synchronisationsstatus getrennt anzeigen: **in Bearbeitung, bestätigt, teilweise bestätigt, storniert, Klärung nötig** sowie **synchronisiert, ausstehend, Fehler**. Bei Teilfehlern pro Person zeigen, was gelungen ist. Wiederholen legt weder Buchung noch Teilnahme doppelt an.

Bei Teilstorno werden personengebundene Antworten der stornierten Person aus der aktuellen Auswertung ausgeschlossen. Buchungsfelder bleiben relevant, solange mindestens eine Person aktiv ist. Buchungsbezogene Mengen wie „vegetarische Essen“ werden nicht proportional geraten: Bei Teilstorno Korrektur anfordern und bis zur Klärung als ungeprüft ausweisen.

Direkt in easyVerein angelegte Teilnahmen erscheinen nach Abgleich mit „Zusatzangaben fehlen“. CSV/manuelle Alt-Anmeldungen bleiben sichtbar, mit klarer Herkunft und ohne erfundene Antworten. Beim Export sind fehlende Werte keine Nullen.

## 6. Auswertungsregeln

Standard ist die aktuelle Reise mit bestätigten, aktiven Anmeldungen. Stornierte, offene oder fehlgeschlagene Teilnahmen gehören nicht in die reguläre Summe; Statusfilter erlauben eine ausdrücklich beschriftete alternative Sicht. Bei teilweise bestätigten Buchungen zählen nur bestätigte Personen. Buchungsfelder dieser Gruppen sind separat als ungeprüft auszuweisen, bis ihre Mengen zu den bestätigten Personen passen.

### Zahlenfelder

Summe aller gültigen aktuellen Werte für das ausgewählte Feld im gewählten Filter. Buchungsfelder werden einmal je Buchung gezählt; Personenfelder einmal je aktiver Person. Dazu immer **Einheit, beantwortet/anwendbar und fehlend/ungeprüft** zeigen. Null ist ein gültiger Wert. Leere oder nicht anwendbare Antworten sind keine Null.

Beispiel: „Vegetarische Essen: **17 Personen** · 11 von 12 Buchungen beantwortet · 1 Angabe fehlt“. Bei personenbezogener vegetarisch-Checkbox wird statt der Summe direkt die Anzahl aktiver Personen ermittelt.

### Checkboxen

**X von N angehakt**. N ist die Zahl der im Filter enthaltenen Buchungen oder Personen, für die das Feld anwendbar ist — einschließlich fehlender Antworten. Zusätzlich aufschlüsseln: angehakt, nicht angehakt, Antwort fehlt. Bei einer expliziten Ja/Nein-Auswahl entsprechend Ja, Nein, fehlt. Prozent optional; Verhältnis bleibt primär.

Beispiel: „Vegetarisches Essen: **17 von 42 Personen** · 23 Nein · 2 Antworten fehlen“. Eine familienbezogene Checkbox bei 12 Buchungen wird als „9 von 12 Buchungen“ angezeigt, niemals als „9 von 42 Personen“.

### Versionsübergreifend und veranstaltungsweit

Reisefilter und Versionsfilter kombinierbar. Nur identische fachliche Feld-IDs mit kompatiblem Typ, Einheit, Bedeutung und Geltungsbereich gemeinsam aggregieren. Ein Feld, das in Version 1 noch nicht existierte, ist für deren unveränderte Buchungen nicht anwendbar; separat „in älteren Versionen nicht abgefragt“ anzeigen.

Auf Veranstaltungsebene mehrere Reisen auswählen: zunächst Kennzahlen pro Reise, optional Gesamtwert. Gesamtwerte bedeuten **Buchungen/Teilnahmen über Reisen**, keine Anzahl einzigartiger Mitglieder; dieselbe Person kann mehrfach vorkommen. Zugriff nur auf Reisen, die die Orga sehen darf.

### Oberfläche und Export

Je ausgewertetem Feld: Titel, großer Wert, Bezugsgröße, Antwortabdeckung, Versionshinweis; „Antworten anzeigen“ öffnet genau die zugrunde liegenden Datensätze mit identischen Filtern. Textfelder als durchsuchbare Liste, keine dekorativen Diagramme ohne Nutzen.

Export enthält Reise, Buchungs-/Teilnahmereferenz, Status, Version, Feld/Antwort und Zeitstand; historische Revisionen nur als eigener Export. Aktive Filter und Einheit mit ausgeben. Excel/CSV-Export und Druckansicht respektieren dieselben Rechte wie die Bildschirmansicht.

## 7. Verbindung zur Kalkulation

Reale Personenzahlen, Alterskategorien, Zimmerwahl und weitere kalkulationsrelevante Antworten können als Eingabequelle dienen. Ein beliebiges Formularfeld verändert nicht automatisch den Preis.

Im Feldeditor optional „Zuordnung zur Kalkulation“ mit festem Ziel anbieten: beispielsweise Zimmerkategorie oder Teilnehmerzahl je Kategorie. Nur kompatible Typen zulassen, keine frei ausführbaren Formeln. Zimmerwunsch und tatsächlich zugeteiltes Zimmer sind getrennte Werte.

In Planung: **Anmeldedaten übernehmen** öffnet eine Gegenüberstellung „Geplant → Angemeldet“ und zeigt Preis-/Budgetauswirkung. Explizit übernehmen, neu berechnen und Kalkulationsstand festhalten. Spätere Buchungen markieren den Stand als veraltet. Abgerechnete Reisen ändern keine Finanzwerte automatisch.

Calculator-Verkaufspreis und easyVerein-Preisgruppe sind bewusst zugeordnet. Preisänderungen benötigen Vorschau und Konfliktprüfung; bestätigte Buchungen werden nicht rückwirkend unbemerkt neu bepreist. Vereinsrechnung und interne Calculator-Abrechnung bleiben fachlich getrennt.

## 8. easyVerein-Termin aus dem Calculator

Eine neue Reise kann zunächst Entwurf ohne externen Termin sein. Für freigeschaltete Anmeldungen muss genau ein gültiger Termin zugeordnet sein. Ein Termin darf nicht gleichzeitig mehreren aktiven Calculator-Reisen zugeordnet werden.

Wizard: **Eckdaten → Kalender & Anmeldung → Preisgruppen → Formular & Team → Prüfen**. Anzeigen: Titel, Start/Ende, Ort, Beschreibung, Kalender, Sichtbarkeit, Anmeldefristen, Kapazitäten, Begleitpersonen-Regel, Preisgruppen. Zwei Wege: „Termin erstellen“ oder „Vorhandenen Termin verknüpfen“.

Letzte Seite zeigt die tatsächliche externe Änderung und mögliche Benachrichtigungen. Keine Termin-/Teilnahmeanlage im Hintergrund beim bloßen Speichern eines Entwurfs. Im Rahmen dieses Konzeptauftrags werden keine realen Termine erstellt und keine Mails verschickt. Für eine spätere Umsetzung muss das tatsächliche Benachrichtigungsverhalten des verwendeten API-Schreibwegs geprüft werden; ist Versand nicht unterdrückbar, darf eine Aktion mit Versandverbot nicht ausgeführt werden.

Externe Anlage mit dauerhafter Auftragsreferenz und lokal gespeicherter Termin-ID. Nach Timeout zuerst prüfen/abgleichen; bei unklarem Ausgang „Klärung nötig“ statt blindem Neuanlegen. Ohne bestätigte externe Referenz keine Anmeldungen freigeben.

Calculator pflegt die dort bearbeiteten Termindaten; Änderungen direkt in easyVerein werden als Abweichung angezeigt. Feldweise Vergleichsansicht mit „Extern übernehmen“/„Calculator-Stand übertragen“. Keine automatische gegenseitige Überschreibschleife. easyVerein bleibt maßgeblich für aktuelle Teilnahme-/Kapazitäts- und Preisgruppenprüfung.

## 9. Team und Rechte

Standardteam der Veranstaltung wird beim Anlegen einer Reise als Vorschlag kopiert. Änderungen am Standardteam verändern historische Reiseberechtigungen nicht. Rechte werden serverseitig geprüft, einschließlich Export, Statistik, Vorschau und Integrations-API.

| Rolle/Fähigkeit | Umfang |
| --- | --- |
| Globale Verwaltung | Veranstaltungen und explizite Zugriffsvergabe; Integrationskonfiguration |
| Veranstaltungsverwaltung | Stammdaten, Standardteam, Formularentwürfe/Veröffentlichung für zugewiesene Veranstaltung |
| Reise-Orga | Anmeldungen und Auswertung der eigenen Reise, Korrekturen, Planung/Abrechnung gemäß bisherigen Orga-Regeln |
| Abrechnungsteam | Finanzsicht der zugewiesenen Reise; kein pauschaler Zugriff auf alle Zusatzantworten |
| Mitglied/Kontaktperson | Eigene Buchung und ausdrücklich berechtigte Personen; keine Orga-Ansicht |
| Website-Integration | Reisegebundene Formulardefinition, eigene Buchungsoperationen nach geprüfter Mitgliederidentität; keine globale Antwortliste |

Formularverwaltung verleiht nicht automatisch Zugriff auf Antworten aller Reisen. Eine allein für Terminbearbeitung zugelassene Person braucht ebenfalls ein gesondertes Antwortrecht. SSO-Subject und easyVerein-Mitglieds-ID müssen über eine geprüfte Zuordnung verbunden werden; der Browser darf keine fremde Person-ID als Berechtigungsnachweis liefern.

## 10. Datenmodell und Integrationsvertrag für die spätere Umsetzung

Vorhandene PHP-8.4-/Slim-/DDD-/SQLite-Struktur weiterverwenden. Geprüft wurden aktueller master, Reise-/Registrierungsdomäne, Routen, Login-Dokumentation und bestehende Konzepte; keine AGENTS.md im Repository gefunden. README nennt database/schema.sql, aktuell liegen Schemaänderungen unter migrations/.

Vorgeschlagene Erweiterungen:

| Entität | Kerndaten |
| --- | --- |
| events | Veranstaltung, Beschreibung, Archivstatus |
| event_default_team | Veranstaltung, Benutzer, vorgeschlagene Fähigkeiten |
| trips | Bestehende Reise plus event_id und aktive form_version_id |
| trip_access | Konkrete Reisefreigaben |
| form_versions / form_fields | Veranstaltung, Versionsnummer, Status, unveränderliche Definition, stabile Feld-/Options-IDs |
| trip_form_history | Gewählte Versionen und Aktivierungszeitpunkte je Reise |
| registrations | Bestehende Buchung erweitert um Kontaktidentität, Status, Formularversion, Herkunft und Idempotenzreferenz |
| participants | Einzelpersonen, externe Mitglieds-/Adress- und Teilnahmereferenzen, Preisgruppe und Status |
| answer_revisions | Buchung, optional Person, Version/Feld, typisierter Wert, Revision, Akteur, Zeitpunkt |
| external_event_links / sync_jobs | Eindeutige Reise-/Terminzuordnung, Auftragszustand, Konflikte |
| audit_events | Veröffentlichung, Rechteänderung, Korrektur, Storno, Übernahme und Abrechnungsabschluss |

Neue API-Verträge zunächst in docs/openapi.yaml spezifizieren: Veranstaltungen/Reisen, Formularentwurf und Veröffentlichung, aktive Reiseformulardefinition, validierte Gruppenbuchung, eigene Buchung/Korrektur, Orga-Antwortliste/Aggregate sowie Terminauftrag und Konfliktauflösung. Das sind geplante Verträge, keine existierenden Endpunkte.

Website-Server authentifiziert sich separat; Mitgliedsidentität und Delegation müssen prüfbar übertragen werden. Kein Vertrauen in frei eingegebene Mitglieds-IDs. Geheimnisse bleiben serverseitig. Calculator-Sitzungsrechte und Mitgliedsbuchungsrechte nicht vermischen.

Lokale Transaktion speichert Buchung/Antworten plus ausstehenden Synchronisationsauftrag; externe API-Aufrufe sind keine gemeinsame Datenbanktransaktion. Abgleich verarbeitet einzelne Personen und erhält Wiederholbarkeit. UI weist ausstehende/teilweise Synchronisation sichtbar aus.

Migration erhält bestehende Reisen, CSV-/manuelle Anmeldungen und Abrechnungen. Bestehende Reise kann später einer Veranstaltung zugeordnet werden. Altbestand ohne Formularversion wird als „Altanmeldung ohne Zusatzformular“ angezeigt; keine rückwirkenden Antworten erfinden. Optionales Zimmerfeld muss für Tagesveranstaltungen möglich werden: heutige Registration-Domäne verlangt eine nichtleere roomCategory. Auch die derzeit nur csv/manual zulässigen Quellen müssen erweitert werden.

## 11. Verbindliche Beispieldaten für das Design

Alle Namen und Zahlen hier sind fiktive Gestaltungsdaten.

| Element | Inhalt |
| --- | --- |
| Veranstaltung | Winterfreizeit |
| Reisen | Winterfreizeit 2027 und Winterfreizeit 2028 |
| Reise 2027 | 12 Buchungen, 42 aktive Personen; Formular v2/v3 |
| Zahlenfeld | Vegetarische Essen, pro Buchung, Summe 17; 11/12 beantwortet |
| Checkbox | Aufsicht übernommen, pro Buchung mit Kindern: 9/10 angehakt, 1 fehlt |
| Personenfeld | Vegetarisches Essen: 17/42 angehakt, 23 Nein, 2 fehlen |
| Auswahl | Zimmerkategorie DZ/Mehrbettzimmer; Wunsch getrennt von Zuteilung |
| Version 3 | Neues Feld „Ankunftszeit“, bei älteren Buchungen nicht abgefragt |
| Klärfall | Eine von vier Personen noch nicht in easyVerein bestätigt |
| Archiv | Abgerechnete Reise mit lesbaren Antworten und gesperrter Finanzübernahme |

Zahlenfeld und personenbezogene vegetarisch-Checkbox sind alternative Formularvarianten zur Demonstration, nicht zwei gleichzeitig verpflichtende Abfragen.

## 12. Design-Lieferumfang und Abnahme

Erforderliche Desktop- und Mobilansichten: Veranstaltungsliste; Veranstaltungsdetail mit Reisen; Formular-Editor/Feldkonfiguration; Formularvorschau; Versionsvergleich und Reise-Versionswechsel; Wizard für Reise/Termin; Reise-Anmeldungen und Buchungsdetail; Reise-Auswertung und Antwort-Drilldown; Standardteam und Reiseteam; Mitgliederformular und Zusammenfassung.

Zustände mitgestalten: keine Veranstaltung/Reise, kein Formular, Entwurf, veröffentlicht, archiviert, ungespeicherte Änderungen, Pflichtfehler, veraltete Formularversion, fehlende Antworten, keine Daten, Teilstorno, Teilfehler, Terminauftrag läuft, unklarer Ausgang, externer Konflikt, fehlende Berechtigung, abgerechnete Reise.

Abnahmebeispiele:

- Zwei Reisen derselben Veranstaltung können unterschiedliche Formularversionen verwenden.
- Neue Version verändert weder alte Antworten noch vorhandene Reisen automatisch.
- Eine Vierpersonenbuchung zählt einmal bei Buchungsfeldern und viermal bei Personenfeldern.
- Checkbox-Anteil enthält anwendbare fehlende Antworten im Nenner und zeigt sie getrennt.
- Storno und Antwortkorrektur ändern aktuelle Auswertungen ohne doppelte Revisionen zu zählen.
- Änderung von Einheit/Bedeutung führt nicht zu vermischten Versionssummen.
- Orga sieht ausschließlich erlaubte Reisen, auch über Direktlink, Export und Gesamtstatistik.
- Termin-Wiederholung nach Timeout erzeugt keinen ungeprüften zweiten Termin.
- Planungsübernahme zeigt Auswirkungen und respektiert den Abrechnungsabschluss.

## 13. Offene technische Entscheidungen vor Implementierung

Das Design kann auf den oben beschriebenen Produktregeln aufbauen. Vor Implementierung sind festzulegen: konkreter easyVerein-API-Vertrag und Benachrichtigungsverhalten; sichere Website-Identitätsdelegation; Abgleichintervall; Buchungs-/Änderungsfristen je Reise; Aufbewahrung und Löschregeln; Behandlung externer Preisänderungen. Keine unbelegten API-Feldnamen als fertige Schnittstelle darstellen.

**Aktualisierte Umsetzungsreihenfolge zur Design-Übergabe (12.10.2026):** Zuerst alle Calculator-Grundlagen und Funktionen einschließlich Buchungen, Antworten/Auswertungen, kontrollierter Kalkulationsübernahme, Termin-/Teilnahmeabgleich und geprüfter Mitglieder-API fertigstellen. Erst danach die Website anpassen. Maßgeblich sind die [elf Umsetzungsschritte](../implementation/veranstaltungsverwaltung/README.md) mit Grundlagen, Design-Zuordnung und Abnahme. Die Originalfassung im Design-Handover bleibt als Quellenstand unverändert erhalten.

## 14. Direkt verwendbarer Design-Prompt

> Erstelle ein Desktop- und Mobil-Design für die Erweiterung des FuF Calculators gemäß diesem Konzept. Verwende die bestehenden FuF-Tokens und Komponenten aus docs/design-handoff/DESIGN.md und RESPONSIVE.md. Die Veranstaltung ist der dauerhafte Rahmen; sie enthält beliebig viele konkrete Reisen, einen Formular-Editor mit unveränderlichen veröffentlichten Versionen und ein Standard-Orga-Team. Jede Reise hat eine ausgewählte Formularversion, eigene Berechtigungen, Kalkulation, Anmeldungen, Auswertung, Abrechnung und einen easyVerein-Termin. Gestalte die in Abschnitt 12 genannten Ansichten und Zustände mit den fiktiven Daten aus Abschnitt 11. Zeige bei Zahlenfeldern Summe und Antwortabdeckung; bei Checkboxen „X von N“ mit klarer Bezugsgröße und fehlenden Antworten. Trenne Buchungs- und Personenfelder. Zeige Versionierung, Terminauftrag, Teilfehler und Kalkulationsübernahme als verständliche Produktabläufe. Die bestehende Mitgliederanmeldung auf der Website bleibt erhalten und nutzt das zentral definierte Formular. Liefere klickbare Navigation, Komponenten, Desktop-/Mobilreferenzen und eine Übergabe mit Feldregeln und Zuständen. Implementiere noch keine API oder externe Schreibaktion.
