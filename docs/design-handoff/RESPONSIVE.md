# Responsive – mobile first

Ergänzung zu `DESIGN.md` und `NAVIGATION.md`. Referenz: `mockups/06-mobil.html`
(fünf Handy-Ansichten bei 390 × 844).

**Wichtig:** Der Alltag findet auf dem Handy statt – Belege erfassen, Anmeldungen
nachtragen, schnell den Überschuss prüfen. Die Desktop-Ansicht ist die Ausnahme, nicht
der Normalfall. Also: Mobile zuerst bauen und testen, Desktop über `min-width`-Queries
ergänzen. Nicht umgekehrt.

## Breakpoints

Bootstrap-Standard, drei relevante Stufen:

| Bereich | Layout |
|---|---|
| `< 768px` (bis `md`) | eine Spalte, Karten statt Tabellen, Bottom-Sheet, Bottom-Bar |
| `768–991px` (`md`) | zwei Spalten für Kennzahlen und kurze Felder, Sidebar noch unter dem Inhalt |
| `≥ 992px` (`lg`) | Layout wie in DESIGN.md, rechte Sticky-Spalte 340 px |

`<meta name="viewport" content="width=device-width, initial-scale=1">` ist schon gesetzt.
Kein `maximum-scale`, kein `user-scalable=no` – Zoom muss möglich bleiben.

## Die sechs Regeln, an denen auf dem Handy alles hängt

1. **Eingabefelder 48 px hoch, Schriftgröße 16 px.** Unter 16 px zoomt iOS Safari beim
   Fokus in das Feld hinein und die Seite steht schief. Das betrifft `input`, `select`
   und `textarea` ausnahmslos. Auf `lg` dürfen es wieder 42 px / 15 px sein.
2. **Richtige Tastatur.** Geldbeträge `inputmode="decimal"`, Anzahlen
   `inputmode="numeric"`, Prozente `inputmode="decimal"`, E-Mail `type="email"`.
   `type="number"` vermeiden (Scroll-Rad ändert Werte, Komma-Probleme im deutschen
   Layout) – stattdessen `type="text"` plus `inputmode` und Validierung in `app.js`.
3. **Touch-Ziele mindestens 48 × 48 px**, auch Icon-Buttons wie Löschen und
   Bearbeiten. Zwischen zwei Zielen mindestens 8 px Abstand.
4. **Nichts nur per Hover.** Jede Aktion, die heute erst beim Überfahren erscheint, ist
   auf dem Handy dauerhaft sichtbar oder steckt in einem Menü.
5. **Wichtigste Zahl und Hauptaktion unten**, in Daumenreichweite – nicht oben.
6. **`padding-bottom: env(safe-area-inset-bottom)`** an der Bottom-Bar, sonst liegt sie
   auf dem iPhone unter der Home-Indicator-Leiste.
7. **Nichts darf seitlich herausragen** – und das passiert fast immer aus zwei Gründen:
   - Eine Grid-Spur `1fr` schrumpft *nicht* unter die Breite ihres Inhalts. Für jedes
     Raster, das schmal werden können muss, gehört `minmax(0, 1fr)` hin, nie das nackte
     `1fr`. Also `grid-template-columns: repeat(2, minmax(0, 1fr))`.
   - Ein Flex-Kind hat implizit `min-width: auto`, also ebenfalls die Inhaltsbreite als
     Untergrenze. Jeder Container, der schrumpfen soll – Feldgruppe, Eingabefeld,
     Kartenkopf, Zeile mit Text und Wert – braucht `min-width: 0`. Auch `text-overflow:
     ellipsis` wirkt ohne `min-width: 0` nicht.

   Beides zusammen war im ersten Entwurf die Ursache für sämtliche Überläufe. Prüfen
   lässt es sich mit `tools/check-overflow.js` (siehe unten), nicht mit dem Auge.

8. **Selects ohne doppelten Pfeil.** Wenn das Feld einen eigenen Chevron im Suffix trägt,
   gehört `appearance: none; -webkit-appearance: none;` auf das `select`, sonst zeichnet
   das System seinen eigenen daneben.

## Was sich pro Baustein ändert

**Kopfleiste** – auf dem Handy 52 px, nur Zurück-Pfeil, Reisename (mit
`text-overflow: ellipsis`) und ein Status-Chip. Navigation (Reisen / Benutzer /
Settings) wandert hinter ein Menü-Icon. Die App-Kopfzeile der Liste zeigt Logo, Suche,
Menü.

**Tab-Umschalter** – wird ein Segmented Control über die volle Breite, direkt unter der
Kopfleiste, auf weißem Grund: `Planung | Abrechnung`, der inaktive mit Zähler-Chip.
Bleibt beim Scrollen mit der Kopfleiste zusammen sticky (zusammen ca. 100 px).

**Rechte Sticky-Spalte → Bottom-Sheet.** Unter `lg` gibt es keine Spalte. Stattdessen:
- eine feste **Bottom-Bar** (links die Kennzahl „Überschuss + 3.020,00 €“ mit kleinem
  Pfeil nach oben, rechts `Speichern`),
- ein Tippen auf die Kennzahl zieht das **Sheet** auf: Grabber, Kennzahlen, Kostenliste,
  dunkelgrüne Überschuss-Box, `Speichern`. Schließen per Wisch nach unten, Tippen auf den
  Hintergrund oder Escape.
- Die Sprungnavigation entfällt auf dem Handy – bei einer Spalte ist Scrollen schneller
  als eine Liste von Sprungzielen. Stattdessen sind alle Sections standardmäßig
  **zugeklappt** und zeigen nur ihre Zusammenfassung; man öffnet, was man braucht.

**Wechsel Planung ↔ Abrechnung** – drei Einstiege wie in NAVIGATION.md, nur anders
platziert: Segmented Control oben (immer sichtbar), eine Wechsel-Zeile direkt über der
Bottom-Bar (kompakt, eine Zeile mit Icon, Titel, Stand, Pfeil), und das Seitenende. Die
Sprungnavigations-Variante entfällt mit der Sprungnavigation.

**Kennzahlen** – vier Kacheln werden 2 × 2. Plan/Ist wie gehabt: Ist groß, Abweichung
klein daneben, Plan darunter. Beträge dürfen auf dem Handy ohne Nachkommastellen
stehen („20.365 €“), die genaue Zahl steht in der Detailansicht.

**Unterkunft & Teilnehmer** – aus dem vierspaltigen Raster werden drei Karten, eine je
Kategorie. Pro Karte: Icon und Name, Zeile „Personen“ mit − / + Stepper (je 48 px) und
zentriertem Feld, Zeile „€ / Nacht“, darunter die Summe mit Rechenweg als Hilfetext.
Der Stepper ist auf dem Handy wichtiger als auf dem Desktop – Zahlen tippen nervt.

**Kalkulation & Verkaufspreise** – die Akkordeon-Zeile bricht auf zwei Zeilen um:
oben Kategorie und berechneter Endpreis, darunter Verkaufspreis-Feld, Abweichung und
Einnahmen. Rechenweg bleibt aufklappbar, dort einspaltig statt zweispaltig.

**Anmeldungen** – das Akkordeon funktioniert unverändert, nur die Chips brechen um. Die
Teilnehmer-Tabelle im aufgeklappten Zustand wird zu einer Liste: Name fett, darunter
Geburtsdatum, Alter und Kategorie-Chip in einer Zeile, Preis rechts. CSV-Import und
manuelles Formular liegen untereinander statt nebeneinander; die Drag-and-drop-Zone wird
ein normaler Datei-Button (Ziehen gibt es auf dem Handy nicht).

**Ausgabe erfassen** – der wichtigste mobile Vorgang. Ganz oben in der Abrechnung, mit
`Beleg fotografieren` als erstem, großem Button
(`<input type="file" accept="image/*" capture="environment">`), darunter Bezeichnung und
Betrag. Wenn Belegfotos technisch noch nicht vorgesehen sind: Button weglassen, Reihenfolge
bleibt.

**Tabellen allgemein** (geplante Kosten, Rückerstattung, Zimmerbedarf) – unter `md` kein
horizontales Scrollen, sondern pro Zeile ein Block: Bezeichnung fett links, Wert rechts,
Zusatzinfos als Hilfetext darunter. Für die Rückerstattungstabelle heißt das: Name,
darunter „gezahlt 1.590,00 € · Anteil 7,02 %“, rechts die Erstattung.

**Modals** – unter `md` als Vollbild-Sheet: Kopf mit Titel und Schließen-Kreuz, Inhalt
scrollt, Buttons in einer festen Leiste unten, volle Breite. Bootstrap:
`.modal-fullscreen-md-down` auf dem `.modal-dialog`.

**Reiseliste** – Tabelle wird Kartenliste. Pro Karte: Name, Status-Chip, Zeitraum und
Teilnehmer als Zeile mit Icons, Überschuss abgesetzt unten. Die Spalte „ID“ entfällt.
Filter als horizontal scrollbare Chip-Leiste. `Neue Reise` in der Bottom-Bar.

## Testen

Im Paket liegt `tools/check-overflow.js`. Es lädt eine Seite in Chromium, misst jedes
Element und meldet alles, was seitlich herausragt – inklusive Exit-Code, also auch als
CI-Schritt brauchbar:

```
npm i -D playwright
node tools/check-overflow.js http://localhost:8000/ 390
node tools/check-overflow.js http://localhost:8000/ 375
```

Das bitte nach jeder Section einmal laufen lassen; seitliche Überläufe sieht man am
Desktop-Browser sonst erst, wenn sie im Produktivbetrieb auffallen.

Manuell mindestens: iPhone SE (375 × 667 – der engste realistische Fall), iPhone 14/15
(390 × 844), ein Android um 412 px, und Querformat. Prüfen:

- Zoomt ein Feld beim Antippen? Dann ist die Schrift zu klein.
- Ist die Bottom-Bar über der Home-Indicator-Leiste?
- Gibt es horizontales Scrollen? (`overflow-x: hidden` ist Symptombekämpfung – die
  Ursache ist meist eine feste Breite oder eine zu breite Tabelle.)
- Sind alle Buttons mit dem Daumen zu treffen, ohne die Hand umzugreifen?
- Funktioniert das Sheet mit eingeblendeter Tastatur noch?

## Reihenfolge

1. Die sechs Regeln oben global in `fuf.css` (Feldhöhen, Schriftgrößen, Touch-Ziele).
2. Kopfleiste und Segmented Control.
3. Bottom-Bar plus Sheet (ersetzt die Sticky-Spalte unter `lg`).
4. Karten statt Tabellen, Section für Section.
5. Modals als Vollbild-Sheets.
