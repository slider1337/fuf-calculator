# Responsive / Mobile first — Design

Stand: 2026-09-22 (Schritte 1–4 umgesetzt). Grundlage: `docs/design-handoff/RESPONSIVE.md` und
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

**Umgesetzt sind daraus zwei Grenzen, 901 px und 992 px, nicht die 768 px dieser Tabelle.**
Tokens, Bottom-Bar und Blatt schalten bei 992 px (`lg`), alle Spaltenlayouts und der
Kartenmodus aus Schritt 4 bei 901 px. Die 901 stammen aus Schritt 3a: darunter brechen schon
die Feldbeschriftungen um. Eine dritte Grenze bei 768 px hätte ein Band erzeugt, in dem die
sechsspaltige Reiseliste wieder Tabelle wäre, aber noch keine 900 px Platz hat.

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

| Route | vor Schritt 1 | nach Schritt 1a | nach Schritt 2 | nach Schritt 3 | nach Schritt 4 |
|---|---|---|---|---|---|
| `/` (390 und 375 px) | 50 | 50 | 39 | 39 | **0** |
| `/trips/1` (390 und 375 px) | 37 | 35 | 23 | **0** | **0** |

`/` bleibt in Schritt 3 stehen, weil Schritt 3 die Reiseliste nicht anfasst — sie besteht aus
der Tabelle und der Filterleiste und fällt mit Schritt 4.

**Diese Reihe hat bis Schritt 4 nur einen von vier Zuständen gemessen.** Der Check lädt die
Seite und misst, was sichtbar ist — und sichtbar ist auf `/trips/1` nur die Planung mit
zugeklappten Abschnitten. Die Abrechnung ist eine `.tab-pane` ohne `.show`, also
`display: none`; die Abschnitte starten seit 3d zugeklappt; das Benutzer-Modal ist zu. Alles,
was 4a umgebaut hat, lag damit außerhalb der Messung. Mit `--eval` sind es fünf Zustände,
und alle fünf stehen nach Schritt 4 bei 0:

| Zustand | `--eval` | nach Schritt 3 | nach Schritt 4 |
|---|---|---|---|
| Liste | — | 39 | **0** |
| Reise, Abschnitte zugeklappt | — | 0 | **0** |
| Reise, Planung aufgeklappt | alle `#panel-planung .collapse` auf `show` | 14 | **0** |
| Reise, Abrechnung aufgeklappt | Tab wechseln, dann alle `#panel-abrechnung .collapse` | 0 ¹ | **0** |
| Benutzer-Modal | `#open-users-btn` klicken | 0 | **0** |

¹ Die Abrechnung war vor Schritt 4 sauber und wurde durch 4c kurzzeitig auf 2 gebracht
(Abweichung in der Kachel plus, als Folge der zu breiten Seite, der Speichern-Knopf der
Bottom-Bar). Beides im selben Schritt behoben.

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

## Schritt 3 — Bottom-Bar plus Blatt

Vier Commits. Regel 5 (wichtigste Zahl und Hauptaktion unten, in Daumenreichweite) und
Regel 6 (`env(safe-area-inset-bottom)`) werden hier eingelöst.

### 3a. Die `max-width`-Queries umgedreht

`fuf.css` hat seitdem keine `max-width`-Query mehr. Die Spaltenlayouts stehen einspaltig in
der Basis, ihre Spaltenzahl kommt in `@media (min-width: 901px)` bzw. `(min-width: 1101px)`
direkt beim Baustein dazu. Es waren **fünf** Blöcke, nicht vier wie nach Schritt 2 notiert.

**Dabei ist toter Code aufgefallen.** Der Eintrag `.fuf-grid-spa` im alten
`max-width: 900px`-Block wurde von der Definition in Abschnitt 11 überstimmt: gleiche
Spezifität, aber später in der Datei. Die Kurabgabe-Zeile stand deshalb bei *keiner* Breite
einspaltig. Mit der Rückstellung am Baustein greift sie jetzt.

Diese Falle ist in Schritt 3 noch zweimal zugeschnappt und ist die wichtigste Lehre des
Schritts: **eine mobile Regel gehört an denselben Ort wie die Definition, die sie
überschreibt — sonst gewinnt die Definition.**

### 3b. Das Blatt

Beide Sticky-Panels stecken in einer Hülle `div.calc-sheet.offcanvas-lg.offcanvas-bottom`
(`#calc-sheet-planung`, `#calc-sheet-abrechnung`). Dasselbe Muster wie das Menü in
Schritt 2, also bleibt jede ID einmalig und kein Handler musste umgehängt werden. Ab `lg`
lösen sich Hülle und `offcanvas-body` per `display: contents` auf, `.pg-side` sieht aus wie
zuvor. Backdrop, Escape und Scroll-Sperre kommen von Bootstrap; Bootstrap blendet den
`offcanvas-header` ab `lg` selbst aus.

Der Griff ist zugleich der Schließen-Knopf (`data-bs-dismiss`) und die Wischfläche: sichtbar
38 × 4 px wie im Mockup, treffen kann man die ganze 44-px-Kopfzeile. Der Wisch nach unten
sind drei Touch-Handler in `app.js`, Schwelle 60 px.

**`display: contents` löst die Hülle als Box auf, nicht als Vorfahre im DOM.** Ein Selektor
`.calc-sheet .pnl` greift deshalb auch auf dem Desktop. Was im Blatt anders aussehen soll,
muss also mobile-first am Baustein selbst stehen: `.pnl` ist in der Basis flach und wird
erst ab `lg` zur Karte.

### 3c. Die Bottom-Bar

`div.trip-bar` am Ende von `#trip-editor-section`, `position: fixed`, 65 px plus
`env(safe-area-inset-bottom)`, nur unter `lg`. Links die Kennzahl des aktiven Bereichs mit
Pfeil nach oben, rechts `Speichern` als vierter `trip-form`-Submit — auf beiden Tabs, weil
der Stale-Hinweis in der Abrechnung genau diesen Speicherstand meint.

Die Kennzahl wird an derselben Stelle abgelesen wie die im Reisekopf, damit es keine zweite
Rechnung gibt, die auseinanderlaufen könnte. Welches Blatt sie aufzieht, entscheidet
`app.js` statt eines `data-bs-target` am Knopf — sonst müsste der Tab-Wechsel ein Attribut
nachziehen.

Neuer Token `--fuf-bar-space` hält am Seitenende den Platz frei und ist ab `lg` `0`.
`z-index: 1030` hält die Bar unter Offcanvas (1045) und Backdrop (1040), das Blatt deckt sie
also zu.

### 3d. Abschnitte starten zugeklappt

Die Sprungnavigation entfällt unter `lg`; ihr Baum bleibt im DOM, damit `renderJumpState`
und der Section-Observer unberührt weiterlaufen. Stattdessen klappt `app.js` beim Öffnen
einer Reise alle Abschnitte mit Chevron zu. Gemessen wird `window.innerWidth` **einmal beim
Öffnen, nicht bei jedem Resize** — wer am Desktop das Fenster schmal zieht, soll nicht
mitten in der Arbeit alles zufallen sehen. `sec-kalkulation` blieb zunächst offen, weil die
Section selbst ein Akkordeon ist; **diese Ausnahme ist mit 4h aufgehoben** — siehe dort.

Damit wird der Section-Kopf zur ganzen sichtbaren Oberfläche des Abschnitts, und drei Dinge
daran waren dafür nicht tauglich:

- **Der Kopf brach nicht um.** Ein Aktionsknopf schob den Chevron bis zu 227 px aus dem Bild
  — und damit den einzigen Weg, den zugeklappten Abschnitt zu öffnen. Jetzt bilden Titel und
  Chevron die erste Zeile, Zusammenfassung und Knöpfe rücken per `order` dahinter.
- **Der Chevron war ein 18-px-Ziel**, Regel 3 verlangt 48 px.
- **Die Fußzeile** stellte Hinweis und Knopf nebeneinander; „Verkaufspreise übernehmen & neu
  berechnen" passt so auf 375 px nicht, weil `.btn` `white-space: nowrap` trägt.

### 3e. Wechsel-Karte am Seitenende

Vom Wechsel Planung ↔ Abrechnung bleiben zwei Einstiege statt der drei aus RESPONSIVE.md.
Die **fix über der Bottom-Bar angepinnte Wechsel-Zeile entfällt** — ein zweites festes
Element hätte nochmal rund 56 px Daumenzone gekostet und mit dem Blatt um dieselbe Stelle
konkurriert. Das Segmented Control steht oben ohnehin permanent. Die Karte am Seitenende
steht mobil einzeilig: Icon, Titel, Stand, Pfeil.

### Verifikation

- Überlauf: `/trips/1` von 23 auf 0 bei 390 und 375 px. `/` unverändert bei 39.
- Desktop pixelgleich: `ui-shot.mjs` bei 1440 px liefert für `/` und `/trips/1` byteweise
  dieselben Aufnahmen (`compare -metric AE` = 0). Für 3a zusätzlich bei 1101, 1100 und
  901 px; bei 900 px unterscheidet sich nur die Kurabgabe-Zeile, der oben genannte Fehler.
- `ui-shot.mjs` findet alle `REQUIRED_IDS`, keine JS-Fehler.
- `composer test`: 223 Tests grün.

**Zwei Pixelfallen dabei**, beide vom selben Typ wie der tote Code aus 3a:

- `justify-content: center` auf `.chev` verschob das Icon in der Kalkulationszeile um 3 px:
  der Knopf steckt dort in einer 24-px-Grid-Spur und streckt sich darauf. Das Zentrieren
  gilt deshalb nur im Section-Kopf.
- Der `order`-Sprung im Section-Kopf braucht eine Rückstellung ab `lg`, sonst stehen
  Zusammenfassung und Knöpfe auch auf dem Desktop hinter dem Chevron.

## Schritt 4 — Karten statt Tabellen

Sieben Commits. Umschaltpunkt ist überall **901 px**, nicht die 768 px aus `RESPONSIVE.md`:
die Datei hat nach 3a keinen einzigen 768er-Punkt, jedes Raster stellt bei 901 px zurück.
Bei 768 px entstünde ein Band, in dem die sechsspaltige Reiseliste wieder Tabelle wäre,
aber noch keine 900 px Platz hat.

Es sind **acht** Tabellen, nicht sieben: die Teilnehmertabelle im aufgeklappten
Anmeldungs-Akkordeon ist ebenfalls eine `.tb`.

### 4a. Kartenmodus auf `.tb`

Abschnitt 10 ist mobile-first. Eine `.tb` ist in der Basis eine Liste von Blöcken — pro
Zeile ein Block, pro Zelle eine Zeile aus Spaltenbeschriftung und Wert. Die Beschriftung
kommt aus **`data-col`** am `<td>`, die erste Zelle trägt statt dessen `.tb-head` und wird
die Überschrift des Blocks. Ab 901 px stellt ein Block am Ende desselben Abschnitts die
echte Tabelle wieder her.

Das Attribut heißt `data-col` und nicht `data-label`, weil der Bearbeiten-Knopf der
Zusatzausgaben schon ein `data-label` für seinen Handler trägt.

Deckt sechs Tabellen mit einem Mechanismus: geplante Kosten, zusätzliche Ausgaben,
Rückerstattung, Zimmer nach Typ, Personen nach Abrechnungskategorie, Benutzer-Modal,
Teilnehmerliste. Rückerstattung zusätzlich mit `.tb-refund` — „Gezahlt … Anteil …" als
eine Hilfstext-Zeile unter dem Namen, die Erstattung darunter rechts.

**`font-size: inherit` an `.tb td` überstimmt eine Klasse an der Zelle selbst.** `#1` in der
Reiseliste und die Plan-Spalten der Zimmerkarte sind `.help`; `.tb td` (0,1,1) schlägt
`.help` (0,1,0). Sie verloren ihre 13 px — 280 bzw. 1.700 Pixel Unterschied am Desktop. Der
Schrift-Reset gilt deshalb nur für `.tb-head`.

### 4b. Reiseliste als Kartenliste

`.tb-trips`: Name als Überschrift, Zeitraum und Teilnehmer als eine Zeile mit Icons,
Überschuss durch eine Trennlinie abgesetzt, Aktionen zuletzt. Spalte „ID" entfällt. Die
Icons stehen **inline im Markup** (Hausregel: Icons sind inline-SVG) und sind ab 901 px per
`.tb-ico` ausgeblendet.

**Status-Chip und Filter-Chip-Leiste aus `RESPONSIVE.md` entfallen ersatzlos** — es gibt
keinen Reisestatus im Datenmodell und keinen Filter in der Liste. Dieselbe Begründung wie
beim Status-Chip in Schritt 2.

Dazu eine zweite Bar `#trip-list-bar` am Ende von `#trip-list-section`: dieselbe `.trip-bar`,
nur mit `Neue Reise` über die volle Breite. `.app-footer` hält den Platz jetzt in beiden
Ansichten frei, nicht mehr nur unter `body.is-trip-view`. Der Knopf im Menü bleibt als
zweiter Weg.

### 4c. Kennzahlen 2 × 2

`.fuf-grid-3` und `.fuf-grid-4` fallen aus der Einspalten-Basis von 3a heraus und stehen
schon mobil in zwei Spuren; die dritte Kachel von drei nimmt beide Spuren. Die Abrechnung
wird 277 px kürzer.

**Die Beträge bleiben genau.** `RESPONSIVE.md` erlaubt sie mobil ohne Nachkommastellen, aber
die Formatierung hängt an einer Stelle in `app.js`; eine breitenabhängige zweite Variante
liefe beim Resize auseinander und gewinnt bei 390 px kaum Platz.

`.kpi-line` bekommt `flex-wrap`: in der halben Zeile ist neben „-11.649,80 €" kein Platz für
„-13.784,20 €", und `.kpi-dev` trägt `white-space: nowrap`.

### 4d. Unterkunft als drei Karten mit Stepper

Pro Kategorie eine Karte: Icon und Name, Zeile „Personen" mit − / + Stepper und
mittig-fetter Zahl, Zeile „€ / Nacht", darunter die Summe mit Rechenweg als Hilfstext. Die
Karte ist ein Raster aus zwei Spuren.

Drei Hüllen im Template — `.room-row`, `.stepper`, `.room-sum-line` — verschwinden ab 901 px
per `display: contents`. Danach stehen die vier Zellen wieder direkt im Raster; was nur zur
Karte gehört (Beschriftungen, Stepper-Knöpfe, Rechenweg) ist ab 901 px `display: none`,
sonst wären es sieben Rasterkinder pro Zeile.

Der Stepper: Knöpfe à 48 px, Wert über `numberFromField()`, bei 0 geklemmt, danach ein
`input`-Event — so laufen Vorschau, Summenspalte und Dirty-State genauso mit wie beim
Tippen. Nur unter 901 px, damit die Tabelle am Desktop unverändert bleibt.

**Bei 901 px ist die Section zugeklappt** (3d greift bis 992 px). Der Pixelvergleich taugt
dort nicht als Nachweis; für 4d sind 1440, 1101, 1100 und 992 px die Prüfpunkte.

### 4e. Kalkulationszeile auf zwei Zeilen

Reines CSS, `calcRow()` unberührt. Flächenraster aus zwei Zeilen: oben Kategorie und
berechneter Endpreis, darunter Verkaufspreis-Feld mit Abweichung und Einnahmen, der Chevron
rechts über beide. Die Spaltenköpfe entfallen unter 901 px. Das Spaltenmaß steckt jetzt im
Token `--fuf-calc-cols`, weil die Rückstellung bei `.calc-row-head` stehen muss und das Maß
sonst zweimal in der Datei stünde.

### 4f. Anmeldungen

Die Ziehfläche wird ein Datei-Knopf: kein gestrichelter Rahmen, kein Icon, `CSV auswählen`
und `Importieren` untereinander über die volle Breite. Der Knopf ist ein **zweites
`<label for="csvFile">`** — mehrere Labels auf dasselbe Feld sind erlaubt, `#csvFile` bleibt
unberührt und `app.js` musste nichts wissen.

Die Kopfzeile einer Anmeldung stellt Umschalter und Chevron in die erste Zeile, die
Aktionsknöpfe darunter rechts; im Umschalter steht der Name oben, Chips und Betrag brechen
darunter um. Ursache der Überläufe war `.reg-name { min-width: 190px }` — am Desktop richtet
das die Namen aneinander aus, mobil ist es genau eine Spalte zu viel.

Die Teilnehmerliste trägt `.tb-regs`: Name fett mit dem Preis rechts daneben, darunter
Geburtsdatum mit Alter und Kategorie-Chip in einer Zeile. Die Spaltenbeschriftungen aus 4a
entfallen hier — ein Datum, ein Chip und ein Betrag sagen selbst, was sie sind.

Manuell-Formular, Filterleiste und Fußzeile stehen mobil untereinander; die Chip-Leiste darf
waagerecht scrollen.

### 4g. Zwei Desktop-first-Reste aus 3a

Beim Umdrehen der Queries in 3a übersehen, weil keiner der drei Bausteine je in einem
`max-width`-Block stand — sie waren von Anfang an unbedingt zweispaltig:

- `.expense-row` (Zusatzausgaben, Planung): Betrag mit festen 180 px neben der Bezeichnung.
- `.expense-form` (Ausgabe erfassen, Abrechnung): `flex: 0 0 170px` am Betrag; auf 390 px
  überlagerten sich die Feldbeschriftungen.
- `.refund-calc`: harte zwei Spuren bei jeder Breite.

Alle drei stehen mobil einspaltig mit Rückstellung ab 901 px.

### Ein vorbestehender Strukturfehler, gefunden bei 4b

`templates/index.html.php` hatte hinter den drei kommentierten Schließern von `.pg`,
`#panel-abrechnung` und `.tab-content` **ein `</div>` zu viel**. Der Parser schloss damit
`<section id="trip-editor-section">` vorzeitig, und alles danach rutschte eine Ebene nach
außen: die Speichern-Bar aus 3c landete in `.app-shell` und wurde auch auf der Reiseliste
gezeichnet, das folgende `</section>` schloss `.app-shell`, und die drei Modals samt Fußzeile
standen außerhalb der Hülle. Gefunden, weil die neue `Neue Reise`-Bar von der alten verdeckt
wurde.

Eigener Commit vor 4b. **Der Desktop ändert sich dadurch, und das ist die Korrektur:**
`.app-shell` ist `min-height: 100vh`, die Fußzeile trägt `margin-top: auto`. Außerhalb der
Hülle hing sie hinter einem 100vh-Block, jede Seite war 47 px zu hoch. Die Seitenhöhe fällt
bei 1440, 901 und 900 px von 1247 auf 1200 px. Das Template ist seitdem vollständig
wohlgeformt.

### 4h. `Kalkulation & Verkaufspreise` zuklappbar

Die einzige Section ohne Chevron. Die Begründung aus 3d — die Section sei selbst das
Akkordeon, ihre Zeilen ohnehin zu — trägt nicht mehr: mit vier Kennzahl-Kacheln, drei Zeilen
und der Fußzeile ist sie auch mit zugeklappten Zeilen lang, und eine einzige Ausnahme unter
sieben Abschnitten liest sich wie ein Fehler.

Chevron in `.sec-h`, `.sec-b` **und** `.sec-f` in ein `<div class="collapse show">` — die
Fußzeile gehört bei allen anderen Sections ebenfalls in den Collapse. Damit greift
`collapseSectionsOnMobile()` ohne weiteres Zutun, es läuft über alle `.chev` mit
collapse-Ziel. `renderJumpState` liest den Zustand an `#result-panel` ab, nicht am Collapse,
und bleibt unberührt.

Die Planung wird mobil von 2.519 auf 1.410 px kürzer.

**Der Desktop ändert sich hier, und zwar gewollt:** der Chevron ist neue Sichtbarkeit, und
die Zusammenfassung in der Kopfzeile rückt um seine Breite nach links. 6.998 Pixel,
sämtlich in der Kopfzeile von `#sec-kalkulation` (y 1315–1339 bei 1440 px); Geometrie und
Seitenhöhe sind unverändert.

Beim Nachmessen eine Lehre zum Werkzeug: **`compare` ohne `-compose src` taugt nicht zur
Ortung.** Die Voreinstellung zeichnet das unveränderte Bild blass durch, und die
Zusammenhangskomponenten darauf zeigten auf das rechte Sticky-Panel statt auf die
Kopfzeile — eine Viertelstunde in die falsche Richtung. Mit `-compose src` bleibt nur die
Abweichung übrig.

### 4i. Nachbesserungen aus der Abnahme

**Kein Rahmen im Rahmen.** Die Reiseliste stand als Kartenliste in der weißen `.sec`-Hülle,
also ein Rahmen im Rahmen. Die Hülle trägt jetzt `.sec-cards` und ist unter 901 px selbst
keine Karte mehr — die weißen Karten stehen direkt auf dem Sandgrund, wie in
`mockups/06-mobil.html`. Die Fußzeile wird dabei zu schlichtem Hilfstext.

Die Selektoren sind doppelt geschrieben (`.sec.sec-cards` statt `.sec-cards`), damit sie
`.sec` und `.sec > .tb` **unabhängig von der Reihenfolge** in der Datei schlagen. Über die
Reihenfolge ist in Schritt 4 dreimal etwas schiefgegangen; Spezifität ist hier das
robustere Werkzeug.

**Neue Reise startet mit zwei offenen Abschnitten.** `Eckdaten` und `Aufschläge & Abgaben`
bleiben offen, damit man nicht erst zweimal tippen muss, um anfangen zu können.

Aus `collapseSectionsOnMobile()` wird dabei `setSectionsOnMobile(openIds)`: die Funktion
**setzt** den Stand, statt nur zuzuklappen. Wer erst eine bestehende Reise ansieht und dann
`Neue Reise` drückt, fände die Abschnitte sonst zugeklappt vor — sie sind es aus dem vorigen
Aufruf, und eine Regel, die nur schließt, bekommt sie nie wieder auf.

Der frühe Ausstieg ab 992 px bleibt: am Desktop stehen ohnehin alle Abschnitte offen, und
ein Eingriff dort würde die Pixelgleichheit kosten, ohne etwas zu gewinnen.

### Verifikation

- Überlauf bei 390 und 375 px: **alle fünf Zustände bei 0** (siehe die Zustandstabelle unter
  „Basiswert").
- Desktop pixelgleich bei 1440, 1101, 1100, 992 und 901 px (`compare -metric AE` = 0). Die
  einzige gewollte Änderung ist die Seitenhöhe aus dem Strukturfehler-Commit.
- Bei 900 px ist der Unterschied planmäßig groß — dort greifen Kartenmodus, Kennzahlen 2 × 2
  und Zimmerkarten.
- `ui-shot.mjs` findet alle `REQUIRED_IDS`, keine JS-Fehler, bei 390 und 1440 px.
- `composer test`: 223 Tests grün.

**Die Lehre aus 3a ist in Schritt 4 dreimal zugeschnappt**, jedes Mal gleich: eine mobile
Regel stand weiter oben in der Datei als die Definition, die sie überschreiben sollte, und
bei gleicher Spezifität gewinnt die spätere Regel.

- Die Rückstellung der Zimmerkarten stand bei `.fuf-grid-room` in Abschnitt 5a — der Desktop
  zeigte drei Karten nebeneinander statt der Tabelle.
- `.dropzone-icon { display: flex }` steht hinter dem `.dropzone`-Block; das Icon blieb mobil
  sichtbar.
- `.dropzone-text label` traf auch den Datei-Knopf und schlug dessen eigene Regel — der Knopf
  war unterstrichen.

**Vier weitere Fallen**, alle vom Typ „die mobile Regel wirkt auch am Desktop":

- `.tb-sub` inline zu stellen verschob am Desktop die ganze Reiseliste (5.627 Pixel).
- `order` für die zweizeilige Anmeldungs-Kopfzeile zog am Desktop den Chevron vor die
  Aktionsknöpfe (2.739 Pixel) — dieselbe Falle wie beim `order`-Sprung in 3d.
- `display: contents` löst die Hülle als Box auf, **nicht als Treffer im Selektor**:
  `.room-row > .room-cat` passt weiterhin, und das `grid-column: 1 / -1` der Karte ließ die
  Kategoriezelle am Desktop alle vier Spuren spannen. Lehre aus 3b.
- Eine feste `flex-basis` ist in der Spalte eine **Höhe**, nicht eine Breite:
  `flex: 0 0 170px` am Kategoriefeld ergab ein 170 px hohes Feld. Und `align-items: end` aus
  `.box-sand` ist in der Spalte die Querachse — die Felder wurden rechtsbündig und nur so
  breit wie ihr Inhalt.

## Schritt 5 — noch offen

**Modals als Vollbild-Sheets.** Drei Modals: `.modal-fullscreen-md-down` auf dem
`.modal-dialog`, Kopf mit Titel und Schließen-Kreuz, Inhalt scrollt, Buttons in fester
Leiste unten über volle Breite. Details in `RESPONSIVE.md` und `mockups/04-modals.html`.

Der Kartenmodus aus 4a greift im Benutzer-Modal schon; die Hülle ist das, was fehlt.

## Status

- [x] Handoff aktualisiert (`RESPONSIVE.md`, `mockups/06-mobil.html`, `tools/check-overflow.js`, `PROMPT.md`)
- [x] Schritt 1a — globale Regeln in `fuf.css`
- [x] Schritt 1b — Zahlenfelder mit `inputmode`
- [x] Schritt 1c — `scripts/check-overflow.mjs`
- [x] Schritt 2 — Kopfleiste und Segmented Control
- [x] Schritt 3a — `max-width`-Queries umgedreht
- [x] Schritt 3b — Blatt statt Sticky-Spalte
- [x] Schritt 3c — Bottom-Bar
- [x] Schritt 3d — Abschnitte starten zugeklappt
- [x] Schritt 3e — Wechsel-Karte am Seitenende kompakt
- [x] Schritt 4a — Kartenmodus auf `.tb`
- [x] Template — überzähliges `</div>` vor der Bottom-Bar entfernt
- [x] Schritt 4b — Reiseliste als Kartenliste plus Bottom-Bar
- [x] Schritt 4c — Kennzahlen 2 × 2
- [x] Schritt 4d — Unterkunft als drei Karten mit Stepper
- [x] Schritt 4e — Kalkulationszeile auf zwei Zeilen
- [x] Schritt 4f — Anmeldungen
- [x] Schritt 4g — zwei Desktop-first-Reste aus 3a
- [x] Schritt 4h — `Kalkulation & Verkaufspreise` zuklappbar
- [x] Schritt 4i — Kartenliste ohne Hülle, neue Reise mit zwei offenen Abschnitten
- [ ] Schritt 5 — Modals als Vollbild-Sheets
