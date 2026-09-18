# FuF Gruppenreise-Kalkulator – UI-Redesign (Handoff)

Zielbild für die bestehende Bootstrap-5-App (`index.html` + `app.js`). Die Mockups unter
`mockups/` sind statisches HTML mit Beispieldaten und zeigen den Sollzustand pixelgenau –
sie sind **Referenz**, nicht Copy-Paste-Code. Die fachliche Logik (IDs, Endpunkte, Berechnung)
bleibt wie sie ist; es ändern sich Layout, Gruppierung, Styling und ein paar Interaktionsmuster.

## Mockups

| Datei | Zeigt |
|---|---|
| `mockups/01-reiseliste.html` | Startseite: Kopfleiste, Kennzahlen, Reisetabelle |
| `mockups/02-reise-planung.html` | Tab „Planung“ komplett, inkl. Kalkulation, Anmeldungen (eine Zeile aufgeklappt), Zimmerbedarf |
| `mockups/03-reise-abrechnung.html` | Tab „Abrechnung“ komplett |
| `mockups/04-modals.html` | Benutzerverwaltung, Nutzer einladen, Globale Settings |
| `assets/fuflogo.png` | Vereinslogo (hell, für dunklen Hintergrund) |

Die Mockups im Browser öffnen; alle Farben, Abstände und Icons stehen als Inline-CSS bzw. im
`<style>`-Block im Kopf jeder Datei.

## Design-Tokens

Als CSS Custom Properties anlegen (z. B. `assets/css/fuf.css`, nach Bootstrap laden) und
Bootstrap-Variablen überschreiben, statt Bootstrap zu entfernen.

```css
:root {
  /* Farben */
  --fuf-brown-900: #3D2817;   /* Kopfleiste, Fließtext */
  --fuf-brown-700: #5C4A3A;   /* Labels */
  --fuf-brown-500: #7A6651;   /* Sekundärtext */
  --fuf-brown-400: #8A7762;   /* Hilfetext, Tabellenköpfe */
  --fuf-green-700: #1A3D2A;
  --fuf-green-600: #2D5A3D;   /* Primär: Buttons, Links, aktive Tabs, positive Werte */
  --fuf-green-100: #E6EFE9;   /* grüne Chips, Highlight-KPI */
  --fuf-orange-600: #E76F1E;  /* Akzent: „Neue Reise“, Übernehmen, Kinder-Icon */
  --fuf-orange-100: #FCE3D0;
  --fuf-amber-700: #B45309;   /* Warnhinweise, Abweichung nach unten */
  --fuf-amber-500: #F5B547;   /* Akzent auf dunklem Grund */
  --fuf-amber-100: #FAEEDA;   /* Chips „Manuell“, Kurabgabe-Box */
  --fuf-red-700: #9B2C2C;     /* Löschen, Defizit */
  --fuf-sand-50:  #FDFBF7;    /* Section-Header, Zebra */
  --fuf-sand-100: #F6F1E7;    /* Input-Suffix, Summenzeilen */
  --fuf-sand-200: #F3EFE6;    /* Seitenhintergrund */
  --fuf-sand-300: #EFE8DA;    /* feine Trennlinien */
  --fuf-sand-400: #E2D9C8;    /* Card-Rahmen */
  --fuf-sand-500: #D4CBB9;    /* Input-Rahmen */

  /* Typografie */
  --fuf-font-body: 'Source Sans 3', system-ui, sans-serif;   /* Google Fonts, 400/600/700 */
  --fuf-font-display: Georgia, serif;                         /* h1, große Zahlen */

  /* Form */
  --fuf-radius-card: 12px;
  --fuf-radius-control: 8px;
  --fuf-radius-chip: 999px;
  --fuf-control-height: 42px;

  /* Bootstrap-Mapping */
  --bs-body-bg: var(--fuf-sand-200);
  --bs-body-color: var(--fuf-brown-900);
  --bs-body-font-family: var(--fuf-font-body);
  --bs-primary: var(--fuf-green-600);
  --bs-link-color: var(--fuf-green-600);
  --bs-border-color: var(--fuf-sand-400);
  --bs-border-radius: var(--fuf-radius-control);
}
```

Textkontrast: Hilfetext `#8A7762` auf Weiß ist die hellste erlaubte Textfarbe (≥ 4,5:1).
Weißer Text nur auf `--fuf-green-600`, `--fuf-brown-900`, `--fuf-orange-600`.

## Komponenten (Wiederverwendung)

**Kopfleiste** – 64 px hoch, `--fuf-brown-900` mit feiner vertikaler Holzmaserung
(`repeating-linear-gradient`, siehe Mockup), 3 px Unterkante in `--fuf-green-600`. Links Logo +
„Gruppenreise-Kalkulator“, Navigation *Reisen · Benutzer · Globale Settings*; rechts „Neue Reise“
(orange), Avatar mit E-Mail + Rolle, Abmelden als Icon-Button. Ersetzt die heutige Buttonreihe
oben; „Benutzer“, „Nutzer einladen“ und „Globale Settings“ öffnen weiterhin die Modals.

**Section-Card** (`.sec`) – weiße Karte, 1 px `--fuf-sand-400`, Radius 12. Kopfzeile
(`.sec-h`) mit Icon, Titel, Zusammenfassung (`.sum`, z. B. „10 % · 5 % · Kurabgabe 2,85 €“)
und Chevron zum Ein-/Ausklappen (Bootstrap Collapse). Zugeklappt bleibt die Zusammenfassung
sichtbar. Jede Section hat eine `id` für die Sprungnavigation.

**Feld** (`.f` + `.in`) – Label 13 px/600, Control 42 px, Einheit als Suffix im Feld
(`.u`: %, €, Jahre, Pers., J.). Entspricht Bootstrap `input-group` + `input-group-text`, nur
flacher. Hilfetext 12 px darunter. Geändertes, ungespeichertes Feld: Rahmen
`--fuf-orange-600` + 3 px Glow `rgba(231,111,30,.15)`.

**KPI-Kachel** (`.kpi`) – Label Uppercase 12 px, Wert Georgia 24 px, optionale Unterzeile.
Positive Kennzahl (Überschuss) auf `--fuf-green-100`, negative (Defizit) auf `#FBE9E4` mit
Wert in `--fuf-red-700`.

**Chip** (`.chip`) – Pille 12 px/700. Varianten: `c-green` (Status ok / Zimmertyp),
`c-amber` (Manuell, Entwurf), `c-grey` (CSV, Zähler), `c-brown` (Abgerechnet), `c-orange` (Kind).

**Buttons** (`.btn-*`) – Primär grün (Speichern, Hinzufügen), Akzent orange (Übernehmen &
neu berechnen, Abrechnung abschließen, Neue Reise), Outline grün (Neu berechnen, Öffnen),
Ghost grau (Schließen, Zurücksetzen), Ghost rot (Löschen). Höhe 40 px, Icons 16 px inline-SVG
(Feather/Lucide-Stil, stroke 2). Icon-only-Buttons 34×34 mit `aria-label`.

**Tabelle** (`.tb`) – Kopf Uppercase 12 px `--fuf-brown-400`, 2 px Unterkante; Zeilen 1 px
`--fuf-sand-300`; Zahlen rechtsbündig mit `font-variant-numeric: tabular-nums`; Summenzeile
auf `--fuf-sand-100`, ohne Unterkante.

**Sticky-Spalte** (rechts, 340 px, `position: sticky; top: 24px`) – oben Sprungnavigation
(„Auf dieser Seite“, Links auf die Section-IDs, aktiver Abschnitt grün hinterlegt, Status-Punkt
je Abschnitt), darunter Kalkulations-Panel mit Live-Zahlen und dem primären Speichern-Button.
Auf < 1100 px Breite wandert die Spalte unter den Inhalt (nicht sticky).

## Seitenstruktur & Mapping auf bestehende Elemente

### Reiseliste (`#trip-list-section`)
Kennzahlen-Reihe (4 KPIs) über der Tabelle. Tabelle: ID · Reise · Zeitraum (+ Nächte) ·
Status-Chip · Teilnehmer (angemeldet / geplant) · Überschuss/Defizit · Aktionen (Öffnen,
Duplizieren, Löschen). Suche + Statusfilter rechts über der Tabelle. Leerzustand: gestrichelte
Karte „Neue Reise anlegen“. *Neu:* Status, Teilnehmer, Überschuss je Reise (aus vorhandenen
Berechnungen; wenn nicht verfügbar, Spalte weglassen).

### Reise · Planung (`#panel-planung`)
Kopf über den Tabs: Breadcrumb „Alle Reisen › Reise #1“, Titel, Status-Chip, Kurzinfo
(Zeitraum · Nächte · Teilnehmer). Tabs Planung/Abrechnung mit Icons, 3 px grüner Unterstrich.

Linke Spalte, Reihenfolge der Sections (jede einklappbar):
1. **Eckdaten** – `#name`, `#startDate`, `#endDate` (2fr 1fr 1fr).
2. **Aufschläge & Abgaben** – `#markupPercent`, `#clubFeePercent`, `#distributionMethod`;
   darunter Kurabgabe-Box (sand) mit `#spaTaxPerPerson`, `#spaTaxAgeThreshold`, `#spaTaxCount`.
3. **Unterkunft & Teilnehmer** – Grid *Kategorie · Anzahl · Preis/Nacht · Summe (n Nächte)*
   für DZ / MBZ / Kinder (`#adultDoubleCount/Price`, `#adultMultiCount/Price`,
   `#childCount/Price`), Summe live berechnet; `#adultAgeThreshold` in der Fußzeile.
4. **Zusatzausgaben** – `#expenseLabel`, `#expenseAmount`, Löschen-Icon; „Weiteren Posten
   hinzufügen“ (nur wenn das Datenmodell mehrere Posten erlaubt, sonst weglassen);
   `#trip-save-btn` rechts.
5. **Kalkulation & Verkaufspreise** – ersetzt `#calculate-btn`, `#result-panel`,
   „Berechnungsdetails“ und „Kostenübersicht“. Inhalt: 4 KPIs (`#result-total-participants`,
   `-revenue`, `-costs`, `-surplus`), dann pro Kategorie eine Akkordeon-Zeile
   *Kategorie · Endpreis berechnet · Verkaufspreis (Input `#salesAdultDouble` etc., mit
   Abweichung zum berechneten Preis) · Einnahmen*; aufgeklappt der Rechenweg in zwei Spalten
   (Grundpreis × Nächte, Kurabgabe, Anteil Gruppenausgaben, Zwischensumme, Aufschlag,
   Vereinsgebühr, Endpreis, Vorbelegung). Kopfzeile: „Neu berechnen“ (Outline);
   Fußzeile: `#sales-apply-btn` (orange). „Berechnen“ kann automatisch nach Speichern laufen.
6. **Anmeldungen** – Kopfzeile mit `#recalculate-billings-btn` und `#delete-registrations-btn`
   als kleine Ghost-Buttons. 3 KPIs (`#reg-count`, `#reg-participant-count`,
   `#reg-billing-total`). CSV-Dropzone (`#csv-upload-form`) links, Manuell-Formular
   (`#manual-registration-form`, Teilnehmer-Zeilen mit + Button) rechts. Filter-Chips
   *Alle / CSV / Manuell* + Suche. Akkordeon (`#registrations-accordion`): Zeile =
   Name · Chip Zimmerkategorie · Chip „n Pers.“ · Chip Quelle · Betrag · Bearbeiten/Löschen ·
   Chevron; Inhalt = Tabelle *Teilnehmer · Geburtsdatum (+ Alter bei Anreise) ·
   Abrechnungskategorie-Chip · Preis* plus Kommentar.
7. **Zimmerbedarf** (`#room-summary-section`) – zwei Karten nebeneinander; „Zimmer nach Typ“
   mit Balken, „Personen nach Abrechnungskategorie“ mit Soll (geplant) / Ist und Häkchen-Chip
   bei Übereinstimmung.

Rechte Spalte: Sprungnavigation (7 Einträge, Anmeldungen mit Chip „n / n“), Kalkulations-Panel
(Teilnehmer, Nächte, Kostenaufstellung, Kosten-vs-Überschuss-Balken, dunkelgrüne Box mit
Überschuss und Verkaufspreisen), `Reise speichern` (46 px), Hinweis „Ungespeicherte Änderungen“.

### Reise · Abrechnung (`#panel-abrechnung`)
4 KPIs (`#settlement-total-revenue`, `-planned-costs`, `-total-expenses`, `-surplus`).
Sections: **Geplante Kosten** (Tabelle Position · Grundlage · Betrag, `#planned-costs-body`),
**Zusätzliche Ausgaben** (Inline-Formular `#actual-expense-form` in sand Box, Tabelle mit
Bearbeiten/Löschen, `#actual-expenses-body`), **Gesamtabrechnung** (Rechenkette als Zeilen,
Gesamtausgaben auf sand, Überschuss auf dunkelgrün), **Rückerstattung / Nachzahlung**
(`#retentionPercent` in amber Box links, Herleitung rechts in 2 Spalten, Tabelle `#refund-body`).
Rechte Spalte: Sprungnavigation, Panel mit Anteilsbalken (Geplant / Zusätzlich / Einbehalt /
Verteilbar), Kennzahlen, „Abrechnung abschließen“ (orange, *neu – optional*).

### Modals
Gleiche Karten-Optik: Radius 14, Kopf mit Titel (Georgia 20) + Untertitel, Fuß auf sand mit
Buttons rechts. `#users-modal` bekommt Suche + „Nutzer einladen“ im Body, Rolle als Select in
der Zeile, Status-Chip. `#invite-modal` mit Rolle-Select (*neu – optional*) und grüner
Erfolgsmeldung. `#settings-modal` in zwei Gruppen: Aufschläge / Kurabgabe & Altersgrenzen.

## Bewusst dazuerfunden (optional, kann gestrichen werden)
Rollen für Benutzer · Statusfilter und Status-Chips in der Reiseliste · mehrere Zusatzposten in
der Planung · Filter-Chips und Suche bei Anmeldungen · „Abrechnung abschließen“ · CSV-Export
der Rückerstattungen · Duplizieren einer Reise.

## Umsetzungsreihenfolge (Vorschlag)
1. `fuf.css` mit Tokens + Komponentenklassen, Google-Font einbinden, Kopfleiste.
2. Reiseliste.
3. Planung: Sections + Sticky-Spalte (nur Layout, IDs unverändert), dann Kalkulation
   als Akkordeon, dann Anmeldungen/Zimmerbedarf.
4. Abrechnung.
5. Modals.
Nach jedem Schritt: `app.js` muss ohne Änderung an den `getElementById`-Aufrufen weiterlaufen.
