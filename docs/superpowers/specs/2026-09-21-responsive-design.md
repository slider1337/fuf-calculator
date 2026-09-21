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
  `.in` — die tragen bereits einen eigenen Suffix-Chevron.

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
- `composer test` bleibt grün (PHP unberührt).
- `scripts/ui-shot.mjs` muss alle `REQUIRED_IDS` weiter finden — das ist der eigentliche
  Regressionsschutz für den Template-Umbau.

## Schritte 2–5 — noch offen

Reihenfolge aus `RESPONSIVE.md`, Abschnitt „Reihenfolge". Details dort und in
`mockups/06-mobil.html`; hier nur das, was beim Aufsetzen zu beachten ist.

**Schritt 2 — Kopfleiste und Segmented Control.**
Kopfleiste mobil 52 px: Zurück-Pfeil, Reisename mit `text-overflow: ellipsis`, Status-Chip.
Reisen/Benutzer/Settings wandern hinter ein Menü-Icon. Tab-Umschalter wird ein Segmented
Control über die volle Breite direkt unter der Kopfleiste, inaktive Seite mit Zähler-Chip,
zusammen mit der Kopfleiste sticky (~100 px).

**Schritt 3 — Bottom-Bar plus Sheet.** Ersetzt die rechte Sticky-Spalte unter `lg`.
Feste Bottom-Bar (links Kennzahl „Überschuss" mit Pfeil nach oben, rechts `Speichern`),
`padding-bottom: env(safe-area-inset-bottom)` (Regel 6). Tippen auf die Kennzahl zieht das
Sheet auf: Grabber, Kennzahlen, Kostenliste, dunkelgrüne Überschuss-Box, `Speichern`;
Schließen per Wisch nach unten, Hintergrund-Tipp oder Escape. Die Sprungnavigation entfällt
mobil — stattdessen sind alle Sections zugeklappt und zeigen nur ihre Zusammenfassung.
Regel 5 (wichtigste Zahl und Hauptaktion unten, in Daumenreichweite) wird hier eingelöst.

**Ab hier greifen die `max-width`-Queries.** Die in Schritt 1 stehengelassenen fünf Queries
gehören spätestens mit Schritt 3 auf `min-width` gedreht, weil dann das mobile Gegenstück
existiert.

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
- [ ] Schritt 1a — globale Regeln in `fuf.css`
- [ ] Schritt 1b — Zahlenfelder mit `inputmode`
- [ ] Schritt 1c — `scripts/check-overflow.mjs`
- [ ] Schritt 2 — Kopfleiste und Segmented Control
- [ ] Schritt 3 — Bottom-Bar plus Sheet
- [ ] Schritt 4 — Karten statt Tabellen
- [ ] Schritt 5 — Modals als Vollbild-Sheets
