# Navigation zwischen Planung und Abrechnung

Ergänzung zu `DESIGN.md`. Betrifft nur den Wechsel zwischen den beiden Tabs einer Reise
(`#panel-planung` / `#panel-abrechnung`) und die Kennzahlen darin. Referenz:
`mockups/05-navigation.html`, eingebaut auch in `mockups/02-reise-planung.html`
und `mockups/03-reise-abrechnung.html`.

## Ausgangslage

Beide Bereiche werden parallel bearbeitet: Belege und tatsächliche Ausgaben werden schon
vor und während der Reise erfasst, nicht erst danach. Der Wechsel ist also ein normaler
Sprung zwischen zwei Arbeitsbereichen und kein Übergabe-Moment am Ende der Planung.

Das Problem ist rein mechanisch: Der Umschalter sitzt oben rechts auf einer Seite von rund
3.300 px Höhe. Wer unten bei den Anmeldungen arbeitet, muss zum Wechseln ganz nach oben
scrollen. Zweitens fehlt beim Wechsel der Anlass – man sieht dem inaktiven Tab nicht an,
ob dort etwas liegt.

## 1 · Sticky Kontextleiste

Der Reise-Kopf (Breadcrumb, Titel, Status, Tabs) wird beim Scrollen zu einer schlanken,
oben klebenden Leiste.

- Höhe **64 px** im Ruhezustand, **52 px** im gescrollten Zustand. Umschaltpunkt:
  `IntersectionObserver` auf einem Sentinel-Div direkt über dem Kopf, oder
  `window.scrollY > 120`. Übergang `transition: height .15s, box-shadow .15s`.
- Gescrollt entfallen Breadcrumb und Kurzinfo-Zeile. Es bleiben: Zurück-Pfeil,
  Reisename (15 px, fett), Status-Chip, rechts die wichtigste Kennzahl
  (Planung: Überschuss; Abrechnung: Überschuss Ist), der Tab-Umschalter und `Speichern`.
- `position: sticky; top: 0; z-index: 30;` plus
  `box-shadow: 0 6px 14px -6px rgba(61,40,23,.25)` nur im gescrollten Zustand.
- Die App-Kopfleiste darüber scrollt normal weg (nicht doppelt sticky).
- Die rechte Sticky-Spalte bekommt `top: 76px`, damit sie nicht unter die Leiste rutscht.

Der Umschalter setzt `#planung` bzw. `#abrechnung` in die URL
(`history.pushState`), sodass Zurück-Button, Neuladen und geteilte Links funktionieren.
Beim Laden wird der Hash ausgewertet und der passende Tab aktiviert.

## 2 · Wechsel-Button an drei Stellen

Immer derselbe Zielort, drei Situationen. Kein neues Bedienkonzept, nur drei Einstiege.

**a) Am Seitenende** – nach der letzten Section, vor dem Footer, eine volle Zeile als
`<a>`: Icon-Kachel (42 px, `--fuf-green-100`), darin Label in Versalien
(„Anderer Bereich“), Titel des Ziels, Untertitel mit dem aktuellen Stand, rechts ein
Outline-Button „Öffnen →“. Auf der Abrechnung spiegelverkehrt (Pfeil und Button links).
Untertitel:
- Planung → Abrechnung: „Belege und tatsächliche Ausgaben erfassen · *n* Posten · *Summe*“
- Abrechnung → Planung: „Kalkulation, Verkaufspreise und Anmeldungen · *n* Teilnehmer“

Der ganze Block ist ein Link; der „Öffnen“-Button darin ist ein `<span>` mit
`pointer-events: none`, damit kein verschachtelter interaktiver Knoten entsteht.

**b) Unten in der Sprungnavigation** – nach einer 1 px Trennlinie ein letzter Eintrag
mit Icon, grün und fett: „Zur Abrechnung“ bzw. „Zur Planung“, rechts als Hilfetext der
Zähler („3 Ausgaben“ / „56 Pers.“). Die Trennlinie ist wichtig: Sie zeigt, dass das kein
Abschnitt dieser Seite mehr ist.

**c) In der sticky Leiste** – der inaktive Tab trägt einen kleinen Zähler-Chip
(`c-amber`, 10 px) mit der Zahl der Posten bzw. Anmeldungen, die dort liegen. Das ist der
eigentliche Grund hinüberzuwechseln und aus jeder Scrollposition sichtbar. Ist der
inaktive Bereich leer, entfällt der Chip.

Tastatur: `1` und `2` wechseln die Bereiche, solange der Fokus nicht in einem Eingabefeld
liegt.

Ungespeicherte Änderungen: Beim Wechsel mit „dirty“ Formular ein kurzer Dialog
„Änderungen speichern?“ mit `Speichern und wechseln` / `Verwerfen` / `Abbrechen` –
nicht still verwerfen.

## 3 · Plan und Ist nebeneinander

Der häufigste Grund zum Wechseln ist Vergleichen. Zeigt man beide Zahlen, entfällt er.

Die KPI-Kacheln der **Abrechnung** zeigen den Ist-Wert groß, die Abweichung als farbige
Zahl daneben und den Planwert klein darunter:

| Kachel | Ist (groß) | Abweichung | darunter |
|---|---|---|---|
| Einnahmen | Abrechnungssumme Anmeldungen | gegen Plan | „geplant *Betrag*“ |
| Kosten | geplante + zusätzliche Ausgaben | gegen Plan | „geplant *Betrag*“ |
| Teilnehmer | angemeldet | gegen Plan | „geplant *n*“ |
| Überschuss | Ist-Überschuss (grün hinterlegt) | gegen Plan | „geplant *Betrag*“ |

Farben der Abweichung: `--fuf-brown-400` bei ± 0, `--fuf-amber-700` bei ungünstiger
Abweichung, `--fuf-green-600` bei günstiger. Vorzeichen immer mitschreiben („+ 735,50 €“,
„− 735,50 €“, „± 0,00 €“). Zahlen mit `font-variant-numeric: tabular-nums`.

Umgekehrt zeigt die **Planung** in ihrer Kalkulations-Spalte rechts, sobald Ist-Daten
vorliegen, unter dem geplanten Überschuss eine Zeile „Ist: *Betrag*“ als Referenz.

## Was nicht umgesetzt wird

Die zwischenzeitlich diskutierte Phasenleiste (Planung → Anmeldungen → Abrechnung) und die
Übergabe-Karte „Die Reise ist vorbei – jetzt abrechnen“ entfallen: Sie unterstellen einen
zeitlichen Ablauf, den es hier nicht gibt.

## Umsetzungsreihenfolge

1. Sticky-Leiste inkl. Hash-Routing (Punkt 1) – wirkt sofort, unabhängig vom Rest.
2. Wechsel-Button an den drei Stellen (Punkt 2).
3. Plan/Ist-Kacheln (Punkt 3), sobald die Planwerte in der Abrechnung verfügbar sind.
