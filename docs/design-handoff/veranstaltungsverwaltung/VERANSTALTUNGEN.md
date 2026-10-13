# Veranstaltungsverwaltung – Design-Übergabe

Ergänzung zu `DESIGN.md`, `RESPONSIVE.md` und `NAVIGATION.md`. Grundlage: `docs/concepts/09-veranstaltungsverwaltung-formulare.md`.
Referenz: Design-Canvas „FuF Calculator · Veranstaltungsverwaltung“ (35 Zeichenflächen auf vier Seiten). Alle Daten sind die fiktiven Beispieldaten aus Abschnitt 11 des Konzepts. Es ist keine API und keine externe Schreibaktion umgesetzt.

## 1. Zeichenflächen

| Kürzel | Ansicht | Desktop | Mobil |
|---|---|---|---|
| Start | Übersicht und Einstieg in alle Ansichten | `Main` | – |
| 1 | Veranstaltungsliste (Karten/Tabelle, Aktiv/Archiviert) | `D01` | `M01` |
| 2 | Veranstaltung · Reisen | `D02` | `M02` |
| 3 | Formular-Editor (Feldliste · Konfiguration · Vorschau), klickbar | `D03` | `M03` Feldliste, `M04` Feld bearbeiten |
| 4 | Formularvorschau mit Beispielpersonen, klickbar | `D04` | `M05` |
| 5 | Versionshistorie, Vergleich, Veröffentlichen | `D05` | – (Vergleich als Tabelle mit Scroll-Box) |
| 6 | Reise-Versionswechsel | `D06` | `M06` |
| 7 | Wizard Reise/Termin, klickbar | `D07` | `M07` (Schritt „Prüfen“) |
| 8 | Reise · Übersicht | `D08` | `M14` |
| 9 | Anmeldungen + Buchungsdetail (Seitenpanel) | `D09` | `M08`, `M09` |
| 10 | Auswertung, klickbar (Grundgesamtheit, Version, Formularvariante) | `D10` | `M10` |
| 11 | Antwort-Drilldown | `D11` | `M11` |
| 12 | Team & Termin, externer Konflikt | `D12` | `M12` |
| 13 | Standardteam / Reiseteam | `D13` | `M13` |
| 14 | Planung · Anmeldedaten übernehmen | `D14` | Einstieg aus `M14` |
| 15 | Reisen (veranstaltungsübergreifend) | `D15` | – |
| W | Website-Mitgliederformular, klickbar | `W01` | `W02` |
| W | Website-Zustände | `W03` | – |
| Z | Calculator-Zustände | `Z01` | – |
| K | Komponenten | `K01` | – |

Die Website-Ansichten verwenden die Tokens aus dem Website-Redesign „Holz & Lagerfeuer“ (Zilla Slab, Figtree, Creme `#FFFBF3`, Holz `#8A5A34`, Orange `#E4702A`, Grün `#3F7F3A`). Alles andere nutzt die bestehenden Calculator-Tokens.

## 2. Navigation

- Hauptnavigation: **Veranstaltungen · Reisen · Mein Konto**, dazu **Benutzer** und **Globale Einstellungen** nach Berechtigung.
- Veranstaltungs-Tabs: **Reisen · Formular · Standardteam · Einstellungen**. Formulare gibt es nur innerhalb ihrer Veranstaltung.
- Reise-Tabs: **Übersicht · Planung · Anmeldungen · Auswertung · Abrechnung · Team & Termin**. Breadcrumb `Veranstaltungen › Winterfreizeit › Winterfreizeit 2027`. Planung und Abrechnung behalten das bestehende Design; neu ist nur der Übernahme-Dialog.
- Mobil: Reise-Tabs als horizontal scrollbare Leiste (`.mtabs`, 46 px, aktiver Tab grün unterstrichen und fett). Keine gequetschten Buttons.
- Hauptaktion mobil immer in der Bottom-Bar (`.bbar`, safe-area-inset).

## 3. Neue Komponenten und Tokens

| Element | Regel |
|---|---|
| `c-red` | `#FBE9E4` / `#9B2C2C`. Nur für Fehler und „Klärung nötig“, nie für Werte. |
| Status-Chips | Buchung und Synchronisation immer als **zwei getrennte Chips**. |
| Auswertungskachel | Titel · Typ-/Geltungs-Chips · großer Wert (Georgia 34) **mit Einheit oder Bezugsgröße** · Abdeckungszeile · ggf. Hinweis (ungeprüft, nicht abgefragt) · Fußzeile „Antworten anzeigen →“. |
| Feldzeile (`.fld`) | Typ-Icon, Label, „Typ · Geltungsbereich“, Auswahl grün umrandet, „neu“-Chip bei ungespeichertem Feld. Daneben **Nach oben/Nach unten** als echte Buttons mit `aria-label`. |
| Versionsdiff | hinzugefügt `#E6EFE9`, geändert `#FAEEDA`, entfernt `#FBE9E4` + durchgestrichen. Jede Zeile nennt die Auswirkung auf die Auswertung. |
| Externe Aktion | dunkelgrüne Box `.b-dark` mit „Diese externe Änderung wird ausgeführt“. Erscheint nur im letzten Wizard-Schritt. |
| Dialoge | wie bestehende Modals; mobil Vollbild-Sheet mit Schließen-Kreuz links oben und Aktionen in der Bottom-Bar. |

## 4. Feldregeln (Formular-Editor)

| Typ | Einstellungen | Pflichtregel | Standardauswertung | Anzeige in der Auswertung |
|---|---|---|---|---|
| Zahl | Ganzzahl/Dezimal, Einheit, Min/Max, Startwert | optional / Antwort erforderlich | Summe | „17 Personen · 11 von 12 Buchungen beantwortet · 1 fehlt“ |
| Checkbox | Ja-Text | optional / **Muss angehakt sein** | X von N | „9 von 10 Buchungen · 9 angehakt · 0 nicht · 1 fehlt“ |
| Ja/Nein | – | optional / **Antwort erforderlich** (Ja und Nein gültig) | Ja · Nein · fehlt | drei Werte getrennt |
| Einzelauswahl | Optionen mit stabiler Options-ID | optional / erforderlich | Anzahl je Option | Balken je Option + „Antwort fehlt“ |
| Mehrfachauswahl | Optionen, Auswahlgrenzen | optional / erforderlich | Anzahl je Option | Hinweis „Summe kann größer als Personenzahl sein“ |
| Kurzer/langer Text | Länge, Hilfetext | optional / erforderlich | Liste | durchsuchbare Liste, keine Summe |
| Datum | gültiger Bereich | optional / erforderlich | Liste | Detail-/Listenansicht |
| Hinweis/Abschnitt | Text | – | keine | – |

Für jedes Antwortfeld: fachliche ID (angezeigt, gesperrt), Label, Hilfetext, Pflichtregel, Geltungsbereich **pro Buchung / pro Person**, Sichtbarkeit (immer · wenn Kinder angemeldet · wenn Person ist Kind/Erwachsener · wenn vorherige Auswahl = Option), Auswertung an/aus, optional „Zuordnung zur Kalkulation“ (nur feste Ziele, nur kompatible Typen).

- Textkorrektur bei gleicher Bedeutung: ID bleibt.
- Änderung von Typ, Einheit, Bedeutung oder Geltungsbereich: neue ID, eigene Auswertungsserie.
- Entfernte Felder bleiben in alten Versionen und deren Antworten sichtbar.
- Kein globales Foto- oder Allergiefeld.
- Zimmerwunsch (Antwort) und Zimmerzuteilung (Orga) sind zwei Werte und werden nebeneinander gezeigt.

## 5. Auswertungsregeln im Design

- Standard-Grundgesamtheit: bestätigte, aktive Teilnahmen. Die Alternative „inkl. offen & storniert“ erzeugt immer ein gelbes Banner „nicht die reguläre Summe“.
- Buchungsfelder zählen einmal je Buchung, Personenfelder je aktiver Person. Die Bezugsgröße steht immer im Text („von 10 Buchungen mit Kindern“, „von 42 Personen“), nie nur im Tooltip.
- Fehlend ≠ 0 ≠ nicht anwendbar ≠ nicht abgefragt. Jeder Fall hat eigene Formulierung:
  - **fehlt** – anwendbar, aber leer (zählt im Nenner)
  - **nicht anwendbar** – durch Sichtbarkeitsregel verborgen (nicht im Nenner)
  - **in älterer Version nicht abgefragt** – Feld existierte in der Version nicht
  - **ungeprüft** – Buchungsmenge nach Teilstorno, bis korrigiert
- Versionsübergreifend nur bei gleicher ID, Typ, Einheit und Geltungsbereich.
- „Antworten anzeigen“ öffnet den Drilldown mit identischen Filtern; die Filter stehen dort als Chips.
- Export: Reise, Buchungs-/Teilnahmereferenz, Status, Version, Feld, Antwort, Zeitstand, aktive Filter, Einheit. Fehlende Werte leer. Revisionen nur als eigener Export.

## 6. Zustände

| Zustand | Wo | Darstellung |
|---|---|---|
| Keine Veranstaltung / keine Reise / kein Formular | `Z01` | gestrichelte Karte mit Erklärung und einer Aktion |
| Entwurf / veröffentlicht / archiviert | `D05`, `Z01` | Chips amber / grün / grau mit Archiv-Icon |
| Ungespeicherte Änderungen | `D03`, `M03`, `Z01` | oranger Feldrahmen, Chip „Ungespeicherte Änderungen“, Dialog beim Verlassen |
| Pflichtfehler | `Z01`, `W03`, `W01` | roter Rahmen, Text unter dem Feld, Sammelhinweis oben mit Anzahl |
| Veraltete Formularversion | `Z01`, `W03` | Hinweis „Formular wurde aktualisiert“, übernommene Angaben markiert, nur neue Fragen offen |
| Fehlende Antworten | `D10`, `Z01` | Chip „fehlt“, eigene Zahl in der Abdeckungszeile |
| Keine Daten | `Z01`, `D10` (v2 + Ankunftszeit) | gestrichelte Fläche, Export gesperrt |
| Teilstorno | `D08`, `D09`, `Z01` | Hinweis „ungeprüft“, Korrektur anfordern |
| Teilfehler | `D09`, `M09`, `W01`, `Z01` | Status je Person, „Nur Lena erneut übertragen“ |
| Terminauftrag läuft | `D07` (nach letztem Schritt), `Z01` | Auftragsreferenz, Fortschritt, Anmeldung gesperrt |
| Unklarer Ausgang | `Z01` | rote Box „Klärung nötig“, nur Abgleichen oder Verknüpfen |
| Externer Konflikt | `D12`, `M12` | feldweise Vergleich, „Extern übernehmen“ / „Calculator-Stand übertragen“ |
| Fehlende Berechtigung | `Z01` | Schloss, gilt auch für Direktlink, Export, Statistik |
| Abgerechnete Reise | `D02`, `Z01` | Chip „Abgerechnet“, Übernahme-Button deaktiviert, Antworten lesbar |

## 7. Mobil

Es gelten alle Regeln aus `RESPONSIVE.md`. Zusätzlich:

- Feldreihenfolge nur per „Nach oben/unten“ (48 px), kein Drag-and-drop.
- Editor als Folge: Feldliste → Feld bearbeiten → Vorschau.
- Buchungen als Karten (Name, Personenzahl, Buchungs- und Sync-Chip, eine Hinweiszeile).
- Auswertungskacheln untereinander; Vergleichstabellen nur in eigener Scroll-Box.
- Termin-Konflikt als Karte je Feld, beide Werte nebeneinander, beide Aktionen als volle Buttons.

## 8. Annahmen im Design

- Preisgruppen im Beispiel: Erwachsene, Kind 3–13, Gast. Preise auf der Website (389 € / 189 €) sind Platzhalter.
- Neues Beispielfeld „Skikurs gewünscht?“ in Version 4 (Ja/Nein, pro Kind) zeigt Personenfelder und Versionswechsel. Es ist kein Vorschlag für das echte Formular.
- „Ergänzung anfordern“ erscheint als Hinweis in „Mein Bereich“ der Website, nicht als E-Mail.
- Benachrichtigungsverhalten von easyVerein ist offen (Konzept Abschnitt 13); das Design zeigt die Warnung, nicht ein festes Verhalten.
