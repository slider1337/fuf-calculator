# Responsive / Mobile first — Design

Stand: 2026-09-21. Grundlage: `docs/design-handoff/RESPONSIVE.md` und
`docs/design-handoff/mockups/06-mobil.html` (fünf Handy-Ansichten bei 390 × 844).

Baut auf `2026-09-18-ui-redesign-design.md` auf. Alles dort Festgelegte gilt weiter —
insbesondere: Bootstrap bleibt und wird per Custom Properties übersteuert, Element-IDs und
Formularnamen bleiben erhalten, keine Inline-Styles in den Templates, Icons als inline-SVG,
nach jedem Schritt ein Commit.

## Ziel

Die App wird überwiegend am Handy benutzt. Desktop ist die Ausnahme, nicht der Normalfall.
Neue Regeln werden deshalb mobile-first geschrieben und über `min-width`-Queries zum Desktop
hin ergänzt.

## Ausgangslage (2026-09-21, vor Schritt 1)

- `fuf.css` ist desktop-first: fünf `max-width`-Queries (Z. 414, 424, 1373, 2028, 2218).
- `--fuf-control-height: 42px`, Feldschrift 15 px — beides unter der iOS-Zoom-Schwelle.
- 20 × `type="number"` im Template, 0 × `inputmode`.
- 17 Grid-Spuren `1fr`, davon erst 13 als `minmax(0, …)`.
- Icon-/Kompakt-Controls bei 32–34 px.
- 7 Tabellen, 3 Modals.
- `app.js` hat mit `numberFromField()` **einen** zentralen Zahlenleser und ruft nirgends
  `checkValidity()`/`reportValidity()` auf. Eine zweite Lesestelle liegt im Payload-Bau
  (`Number(form.<name>.value)`).
- Belegfotos existieren im Code nicht → der „Beleg fotografieren"-Button aus RESPONSIVE.md
  entfällt, wie RESPONSIVE.md es für diesen Fall selbst vorsieht.

## Getroffene Entscheidungen

| Frage | Entscheidung |
|---|---|
| Umfang dieser Runde | Nur Schritt 1 (globale Regeln). Schritte 2–5 folgen einzeln. |
| Regel 2 (`inputmode`) | Gehört mit in Schritt 1, obwohl sie Template und `app.js` betrifft, nicht CSS. Sie löst das iOS-Zoom- und Komma-Problem; Regel 1 allein verhindert nur den Zoom. |
| Regel 4 (kein Hover-only) | **Entfällt ersatzlos.** `fuf.css` hat keine `:hover`-Regel, die etwas per `opacity`/`visibility`/`display` erst einblendet. Nichts zu tun. |
| Bestehende `max-width`-Queries | Bleiben in Schritt 1 **stehen**. Sie schalten Spaltenlayouts, deren mobiles Gegenstück (Bottom-Bar, Sheet, Segmented Control) erst in Schritt 2/3 entsteht. Umdrehen vor dem Umbau ließe die App dazwischen kaputt. |
| Überlauf-Check (Regel 7) | Npm-frei als `scripts/check-overflow.mjs` über denselben CDP-Weg wie `scripts/ui-shot.mjs`, statt `tools/check-overflow.js` + `npm i -D playwright`. Das Projekt hat bewusst kein `package.json`; CI hat keinen Node-Schritt. |
| Beleg fotografieren | Entfällt (kein Foto-Feature im Code). Reihenfolge in der Abrechnung bleibt wie in RESPONSIVE.md. |

## Breakpoints

Bootstrap-Standard, drei Stufen (aus RESPONSIVE.md):

| Bereich | Layout |
|---|---|
| `< 768px` | eine Spalte, Karten statt Tabellen, Bottom-Sheet, Bottom-Bar |
| `768–991px` | zwei Spalten für Kennzahlen und kurze Felder, Sidebar unter dem Inhalt |
| `≥ 992px` | Layout wie in `DESIGN.md`, rechte Sticky-Spalte 340 px |

`<meta name="viewport" content="width=device-width, initial-scale=1">` ist gesetzt.
Kein `maximum-scale`, kein `user-scalable=no`.

## Schritt 1 — globale Regeln

### 1a. `fuf.css` (Regeln 1, 3, 7, 8)

- **Regel 1 — Feldhöhe/Schrift.** `--fuf-control-height: 48px` und neues
  `--fuf-control-font: 16px` als Default; ab `@media (min-width: 992px)` zurück auf
  `42px` / `15px`. Die `.in`-Regeln hängen sich an die Tokens; zusätzlich eine globale Regel
  für `input, select, textarea` außerhalb von `.in`. Unter 16 px zoomt iOS Safari beim Fokus.
- **Regel 3 — Touch-Ziele.** Icon-Buttons und die 32/34-px-Controls mobil auf
  `min-height`/`min-width: 48px`, ab `lg` wieder aufs heutige Maß. `gap: 8px` wo Ziele
  aneinanderstoßen.
- **Regel 7 — kein seitlicher Überlauf.** Restliche nackte `1fr`-Spuren auf
  `minmax(0, 1fr)`; `min-width: 0` an den schrumpfenden Flex-Kindern. Ohne `min-width: 0`
  wirkt auch `text-overflow: ellipsis` nicht.
- **Regel 8 — Selects.** `appearance: none; -webkit-appearance: none;` auf `select` innerhalb
  `.in`. Die Voraussetzung dafür — ein eigener Chevron im Suffix — galt nur für zwei der drei
  Selects; `#distributionMethod` hatte keinen und stand deshalb seit dem UI-Redesign ganz ohne
  Aufklapp-Anzeige da (`appearance: none` war schon vorher gesetzt). Der `<span class="u">`
  mit dem Lucide-Chevron aus `mockups/02-reise-planung.html` ist nachgetragen.

`overflow-x: hidden` ist kein zulässiges Mittel; es kaschiert die Ursache.

### 1b. Template + `app.js` (Regel 2)

- 20 × `type="number"` → `type="text"` plus `inputmode="decimal"` (Geld, Prozent) bzw.
  `inputmode="numeric"` (Personenzahlen, Jahre). `step`/`min` entfallen (auf `text`
  wirkungslos), `required` bleibt. IDs, `name` und `data-bs-*` unverändert.
- `numberFromField()` wird komma-fähig: enthält der Wert ein Komma, werden Punkte als
  Tausendertrenner entfernt und das Komma zum Punkt („1.234,56" → `1234.56`); sonst
  unverändert wie bisher („12.50" → `12.5`). Damit bleibt jeder gespeicherte Wert lesbar.
- Die zweite Lesestelle im Payload-Bau geht auf denselben Helfer.
- Rückschreiben ins Formular formatiert mit Komma, damit Anzeige und erwartete Eingabe
  zusammenpassen.
- Validierung minimal: nicht-leerer, nicht-parsebarer Wert bekommt beim `blur` ein
  `.is-invalid`. Der bisherige Fallback auf `0` in der Berechnung bleibt.
- Alle Ergänzungen in `app.js` mit `// DESIGN` markiert.

### 1c. Verifikation

- `scripts/check-overflow.mjs`, neu: dieselbe Messlogik wie
  `docs/design-handoff/tools/check-overflow.js`, aber über den npm-freien CDP-Weg von
  `scripts/ui-shot.mjs` inklusive Login. Exit-Code 1 bei Überlauf.
- Läuft gegen Liste, Planung und Abrechnung bei 390 px und 375 px.

**Die Breite muss per `Emulation.setDeviceMetricsOverride` kommen, nicht per
`--window-size`.** Chrome erzwingt eine Mindest-Fensterbreite von rund 500 px und ignoriert
kleinere Werte stillschweigend: ein Lauf mit `--window-size=390` misst 500 px und behauptet,
es seien 390. Genau die gesuchten Überläufe fallen dann durch. Das ist am 2026-09-21 einmal
passiert; `scripts/lib/browser.mjs` trägt seitdem einen Kommentar dazu.

#### Basiswert

Der Check kann bei 390 px erst nach Schritt 4 grün werden — Tabellen und `app-nav` ragen
konstruktionsbedingt heraus. Es gilt deshalb ein Basiswert: **Schritt 1 darf ihn nicht
erhöhen, jeder spätere Schritt muss ihn senken, Schritt 4 muss ihn auf 0 bringen.**

Gemessen mit der Fixture-DB (`scripts/ui-fixture.php`, Reise #1):

| Route | vor Schritt 1 | nach Schritt 1a | nach Schritt 2 |
|---|---|---|---|
| `/` (390 und 375 px) | 50 | 50 | 39 |
| `/trips/1` (390 und 375 px) | 37 | 35 | 23 |

**Die Messfunktion überspringt seit Schritt 2 auch `visibility: hidden`.** Ein
geschlossenes Offcanvas-Panel parkt per `translateX(100%)` rechts neben dem Viewport. Sein
eigener Rahmen fällt schon durch die `position: fixed`-Ausnahme, seine Kinder aber nicht —
die 28 Elemente des Menüs zählten beim ersten Lauf allesamt als Überlauf und trieben die
Zahl auf 67 bzw. 51. `visibility` vererbt sich, ein Test deckt deshalb den ganzen Teilbaum
ab; ein *geöffnetes* Panel und die Bottom-Bar aus Schritt 3 werden weiter gemessen. Mit der
neuen Messung ergibt der Stand vor Schritt 2 unverändert 50 und 35, die Reihe bleibt also
vergleichbar.

Reproduzieren:

```
DB_PATH=/tmp/fuf-ui.sqlite php vendor/bin/phinx migrate
DB_PATH=/tmp/fuf-ui.sqlite php scripts/ui-fixture.php
DB_PATH=/tmp/fuf-ui.sqlite php -S 127.0.0.1:8123 -t public public/router.php &
node scripts/check-overflow.mjs --base http://127.0.0.1:8123 --width 390 --width 375 / /trips/1
```
- `composer test` bleibt grün (PHP unberührt).
- `scripts/ui-shot.mjs` muss alle `REQUIRED_IDS` weiter finden — das ist der eigentliche
  Regressionsschutz für den Template-Umbau.

## Schritt 2 — Kopfleiste und Segmented Control

Umschaltpunkt ist `lg` (992 px), dieselbe Grenze wie bei den Tokens aus Schritt 1. Die
berührten Bausteine sind dabei **echt mobile-first** geschrieben: Basiswerte = Handy,
Desktop-Werte in einem eigenen `@media (min-width: 992px)` direkt beim Baustein
(Abschnitt 4 für die Kopfleiste, 5b für Reisekopf und Umschalter). Kein sechster
`max-width`-Block; die Regeln für `.app-header`, `.app-main` und `.trip-head` sind aus dem
`max-width: 900px`-Block herausgefallen, weil die Basis sie abdeckt.

- **Kopfleiste mobil 52 px, sticky.** Reiseansicht: Zurück-Pfeil, Reisename mit
  `text-overflow: ellipsis`. Liste: Logo. `app.js` setzt dazu `body.is-trip-view`.
- **Menü als `offcanvas-lg`.** Reisen, Benutzer, Globale Settings, `Neue Reise` und der
  Benutzerblock stehen unter `lg` in einem Panel, ab `lg` macht Bootstrap daraus wieder die
  waagerechte Kopfleiste. Damit bleibt jede ID einmalig und an ihrem Platz — kein einziger
  Event-Handler musste umgehängt werden. Die beiden Hüllen des Offcanvas lösen sich ab `lg`
  per `display: contents` auf, sonst stünde die Navigation rechts statt neben der
  Wortmarke.
- **Segmented Control** über die volle Breite, zwei gleich breite Segmente à 44 px,
  Zähler-Chip bleibt. Kopfleiste und Umschalter zusammen **111 px** sticky. Die ~100 px aus
  `RESPONSIVE.md` wären nur mit kleineren Touch-Zielen zu haben; Regel 3 hat Vorrang.
- **Die Meta-Zeile** (`Zeitraum · Nächte · Teilnehmer`) steht mobil als
  `#trip-editor-meta-mobile` außerhalb von `.trip-head` und scrollt mit — im Kopf hätte sie
  die feste Zone auf 135 px aufgebläht. Sie ist dupliziert, nicht verschoben: auf dem
  Desktop gehört sie neben die Überschrift.

Zwei Punkte aus `mockups/06-mobil.html` entfallen:

- **Status-Chip** am Reisenamen — es gibt keinen Reisestatus im Datenmodell. Entfällt wie
  der „Beleg fotografieren"-Button in Schritt 1.
- **Suche-Icon** in der Kopfleiste der Liste — das Suchfeld steht in `.page-head` und gehört
  mit der Reiseliste zu Schritt 4. `Neue Reise` sitzt bis dahin im Menü; mit Schritt 3/4
  zieht der Knopf in die Bottom-Bar.

Der Desktop ist dabei **pixelgleich** geblieben: `scripts/ui-shot.mjs --width 1440` liefert
für `/` und `/trips/1` byteweise dieselben Aufnahmen wie vor dem Umbau (`compare -metric AE`
= 0). Zwei Fallen dabei:

- Die Navigation sitzt ab `lg` nicht mehr in `.app-header-left`, ihr Abstand zur Wortmarke
  setzt sich seitdem aus dem `gap` der Kopfleiste (12 px) und `margin-left: 16 px` zusammen.
- `.app-header` braucht ab `lg` neben `position: static` auch `z-index: auto`. Bleibt der
  `z-index` der Handy-Regel stehen, zeichnet Chrome die Kopfleiste auf einer eigenen Ebene
  und die Kantenglättung von Schrift und Holzmaserung fällt anders aus — 1.654 Pixel
  Unterschied bei unveränderter Geometrie.

## Schritte 3–5 — noch offen

Reihenfolge aus `RESPONSIVE.md`, Abschnitt „Reihenfolge". Details dort und in
`mockups/06-mobil.html`; hier nur das, was beim Aufsetzen zu beachten ist.

**Schritt 3 — Bottom-Bar plus Sheet.** Ersetzt die rechte Sticky-Spalte unter `lg`.
Feste Bottom-Bar (links Kennzahl „Überschuss" mit Pfeil nach oben, rechts `Speichern`),
`padding-bottom: env(safe-area-inset-bottom)` (Regel 6). Tippen auf die Kennzahl zieht das
Sheet auf: Grabber, Kennzahlen, Kostenliste, dunkelgrüne Überschuss-Box, `Speichern`;
Schließen per Wisch nach unten, Hintergrund-Tipp oder Escape. Die Sprungnavigation entfällt
mobil — stattdessen sind alle Sections zugeklappt und zeigen nur ihre Zusammenfassung.
Regel 5 (wichtigste Zahl und Hauptaktion unten, in Daumenreichweite) wird hier eingelöst.

**Ab hier greifen die `max-width`-Queries.** Von den fünf aus Schritt 1 stehen nach
Schritt 2 noch vier (zwei bei 1100 px, zwei bei 900 px). Sie gehören spätestens mit
Schritt 3 auf `min-width` gedreht, weil dann das mobile Gegenstück existiert.

**Schritt 4 — Karten statt Tabellen, Section für Section.**
Sieben Tabellen. Unter `md` kein horizontales Scrollen, sondern pro Zeile ein Block:
Bezeichnung fett links, Wert rechts, Zusatzinfos als Hilfetext darunter. Betrifft Kennzahlen
(2 × 2), Unterkunft & Teilnehmer (drei Karten mit − / + Stepper à 48 px), Kalkulation &
Verkaufspreise (Akkordeon-Zeile auf zwei Zeilen), Anmeldungen (Teilnehmertabelle wird Liste,
CSV-Import und manuelles Formular untereinander, Drag-and-drop-Zone wird Datei-Button),
geplante Kosten, Rückerstattung, Zimmerbedarf, sowie die Reiseliste (Kartenliste, Spalte „ID"
entfällt, Filter als horizontal scrollbare Chip-Leiste, `Neue Reise` in der Bottom-Bar).

**Schritt 5 — Modals als Vollbild-Sheets.**
Drei Modals: `.modal-fullscreen-md-down` auf dem `.modal-dialog`, Kopf mit Titel und
Schließen-Kreuz, Inhalt scrollt, Buttons in fester Leiste unten über volle Breite.

## Status

- [x] Handoff aktualisiert (`RESPONSIVE.md`, `mockups/06-mobil.html`, `tools/check-overflow.js`, `PROMPT.md`)
- [x] Schritt 1a — globale Regeln in `fuf.css`
- [x] Schritt 1b — Zahlenfelder mit `inputmode`
- [x] Schritt 1c — `scripts/check-overflow.mjs`
- [x] Schritt 2 — Kopfleiste und Segmented Control
- [ ] Schritt 3 — Bottom-Bar plus Sheet
- [ ] Schritt 4 — Karten statt Tabellen
- [ ] Schritt 5 — Modals als Vollbild-Sheets
