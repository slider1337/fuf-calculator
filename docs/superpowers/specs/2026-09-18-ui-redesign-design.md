# UI-Redesign FuF Gruppenreise-Kalkulator — Design

Stand: 2026-09-18. Grundlage: `docs/design-handoff/` (`DESIGN.md`, `PROMPT.md`, vier Mockups).

## Ziel

Die bestehende Bootstrap-5-Oberfläche auf das Zielbild des Handoffs bringen: eigene
Design-Tokens, Section-Cards mit Sprungnavigation, Sticky-Kalkulationspanel, neue Tabellen- und
Chip-Optik. Fachliche Logik, Endpunkte und Berechnungen bleiben unverändert; eine Ausnahme ist
die bewusst erweiterte Reiseliste (Abschnitt 4).

Abweichung vom Handoff: `DESIGN.md` spricht von `index.html` + `app.js`. Tatsächlich liegt das
Markup in `templates/index.html.php` (per `require` aus `config/routes.php` gerendert) und das
Frontend in `public/assets/js/app.js`. Alle Angaben im Handoff sind entsprechend zu lesen.

## Rahmenbedingungen

- Bootstrap 5 bleibt geladen und wird per CSS Custom Properties übersteuert, nicht ersetzt.
- Alle bestehenden Element-IDs, Formularnamen und `data-bs-*`-Attribute bleiben erhalten.
  `app.js` läuft ohne Änderung an seinen `getElementById`-Aufrufen weiter.
- Keine Inline-Styles und keine `<style>`-Blöcke in den Templates. Was in den Mockups inline
  steht, wird zur Klasse in `fuf.css`.
- Icons ausschließlich als inline-SVG im Lucide/Feather-Stil, `stroke-width: 2`. Keine Icon-Font.
- Nach jedem Umsetzungsschritt ein Commit.

## Getroffene Entscheidungen

| Frage | Entscheidung |
|---|---|
| Datengrundlage Reiseliste | Volle Daten: Zeitraum, Nächte, Teilnehmer (angemeldet/geplant), Überschuss. Endpunkt wird erweitert. |
| Status / Statusfilter | Entfällt komplett. |
| KPI-Reihe über der Reisetabelle | Entfällt komplett; Tabelle sitzt direkt unter der Kopfzeile. |
| Überschuss in der Liste | Hybrid: Abrechnung wenn Anmeldungen vorliegen, sonst Kalkulation, dann mit Chip „geplant". |
| Löschen-Aktion Reiseliste | Button vorhanden, aber `disabled`. Endpunkt und Anbindung folgen separat. |
| Bearbeiten-Aktion Anmeldung | Analog: Button vorhanden, aber `disabled` — es existiert kein Update-Endpunkt. |
| Schrift „Source Sans 3" | Lokal vendort als WOFF2 unter `public/assets/fonts/`, kein CDN-Request zur Laufzeit. |

### Bewusst nicht umgesetzt

Aus „Bewusst dazuerfunden" in `DESIGN.md` entfallen laut `PROMPT.md` alle Punkte **außer den
Filter-Chips bei Anmeldungen**: Rollenverwaltung, Statusfilter und Status-Chips der Reiseliste,
mehrere Zusatzposten in der Planung, „Abrechnung abschließen", CSV-Export der Rückerstattungen,
Duplizieren einer Reise.

Die Rolle wird weiterhin nur angezeigt (`#current-user-role-badge`, `role`-Spalte existiert in
`users`), aber nicht editierbar gemacht. Das Rolle-Select in der Benutzerzeile des Mockups
bleibt deshalb ein reines Anzeigeelement bzw. `disabled`.

## 1. CSS-Layer und Assets

### Dateien

- `public/assets/css/fuf.css` — neu, geladen **nach** `bootstrap.min.css`.
- `public/assets/fonts/` — neu, Source Sans 3 in 400/600/700 als WOFF2.
- `public/assets/img/fuflogo.png` — neu, aus `docs/design-handoff/assets/`.

`fuf.css` wird in `templates/index.html.php` **und** in `templates/auth/login.html.php`,
`templates/auth/forgot-password.html.php`, `templates/auth/set-password.html.php` eingebunden,
damit der Login nicht im alten Look stehenbleibt.

### Aufbau von fuf.css

1. **Tokens** — `:root` exakt wie in `DESIGN.md` inklusive `--bs-*`-Mapping
   (`--bs-body-bg`, `--bs-body-color`, `--bs-body-font-family`, `--bs-primary`,
   `--bs-link-color`, `--bs-border-color`, `--bs-border-radius`).
2. **`@font-face`** — drei Schnitte, `font-display: swap`.
3. **Komponenten** unter den Namen aus `DESIGN.md`: `.sec`, `.sec-h`, `.sum`, `.sec-b`, `.f`,
   `.in`, `.u`, `.help`, `.k`, `.kpi`, `.tb`, `.chip` mit `.c-green/.c-amber/.c-brown/.c-grey/
   .c-orange`, `.jump`, `.cl`, `.chev`, `.ib`, `.dot`.
4. **Layout** — die in den Mockups inline gesetzten Strukturen als Klassen: App-Shell
   (`.app-shell`, `.app-header`, `.app-nav`, `.app-main`, `.app-footer`), zweispaltiges
   Planungs-/Abrechnungsraster (`.pg` = `minmax(0,1fr) 340px`, `.pg-side` sticky `top: 24px`),
   Grid-Helfer `.fuf-grid` plus Varianten.

### Namenskollisionen mit Bootstrap

Von den Klassennamen des Handoffs kollidieren genau zwei mit Bootstrap 5:

- **`.row`** — bei Bootstrap das Flex-Grid, im Mockup ein CSS-Grid. Wird zu `.fuf-grid`
  (`.fuf-grid-2`, `-3`, `-4` sowie Sonderraster wie `2fr 1fr 1fr` und
  `1.6fr 1fr 1.2fr 1fr 24px`). Bootstrap-`.row` bleibt damit für die Auth-Templates intakt.
- **`.btn`** — Kollision wird bewusst in Kauf genommen: `.btn` wird auf 40 px Höhe, Radius 8 und
  Gewicht 700 restyled, die Modifier `.btn-p/.btn-a/.btn-o/.btn-g/.btn-d/.btn-s` kommen
  daneben. Folge: Buttons, die ein späterer Schritt noch nicht angefasst hat, sehen trotzdem
  schon stimmig aus statt als Bootstrap-Fremdkörper.

Alle übrigen Handoff-Namen (`.sec`, `.f`, `.in`, `.u`, `.tb`, `.kpi`, `.chip`, `.help`, `.k`,
`.jump`, `.cl`, `.ib`, `.sum`, `.chev`) sind in Bootstrap 5 unbelegt und werden unverändert
übernommen, damit Handoff und Code dieselbe Sprache sprechen.

### Bootstraps verbleibende Rolle

Nur noch JS-Verhalten (Modal, Collapse, Tab — die `data-bs-*`-Attribute bleiben unberührt) und
die Utility `d-none`, die `app.js` über 40-mal zum Ein- und Ausblenden benutzt. Kein
Bootstrap-Grid und keine `.card`/`.form-control`-Optik mehr im Hauptscreen.

### Kopfleiste

64 px hoch, `--fuf-brown-900` mit vertikaler Holzmaserung per `repeating-linear-gradient`, 3 px
Unterkante in `--fuf-green-600`. Links Logo und Georgia-Titel „Gruppenreise-Kalkulator", daneben
Navigation *Reisen · Benutzer · Globale Settings*. Rechts „Neue Reise" (orange), Avatar mit
Initialen und E-Mail plus Rolle, Abmelden als Icon-Button mit `aria-label`.

ID-Mapping: `#new-trip-btn`, `#open-users-btn`, `#open-settings-btn`, `#current-user-email`,
`#current-user-role-badge` wandern unverändert in die neue Leiste. „Nutzer einladen" hat im
Mockup keinen Nav-Punkt; `#open-invite-btn` zieht deshalb in den Body von `#users-modal`, wie es
`DESIGN.md` vorsieht. Die Abmelden-Form (`POST /logout`) bleibt eine echte Form.

Die Fußzeile aus Mockup 01 übernimmt die bisherigen Links auf `/docs` und `/openapi.yaml`, die
heute unter der `h1` hängen. Eine Versionsnummer wird nicht angezeigt — es gibt keine Quelle
dafür.

## 2. app.js

Keine Änderung an `getElementById`-Aufrufen, Endpunkten, Routing oder Rechenlogik. Aber: Ein
großer Teil des Designs steckt in Markup, das `app.js` selbst per `innerHTML` erzeugt. Diese
Renderer werden auf das neue Markup umgestellt — gleiche Signatur, gleiche gelesenen Felder,
nur anderes HTML:

| Renderer | Zeile | Umstellung |
|---|---|---|
| `renderTripList` | 412 | neue Spalten, `.tb`-Optik, Aktionen |
| `renderCalculationResult` | 92 | KPIs, Kategorietabelle, Rechenweg als Akkordeon mit Verkaufspreis-Inputs |
| `renderRegistrations` | 538 | Akkordeonzeile auf Chips, Teilnehmertabelle mit Alter bei Anreise |
| `renderRoomSummary` | 484 | Balken statt Tabelle, Soll/Ist mit Häkchen-Chip |
| `renderSettlement` | 695 | Geplante Kosten, Zusätzliche Ausgaben |
| `renderRefundDistribution` | 772 | Herleitung zweispaltig, `#refund-body` |
| `loadUsers` | 879 | Benutzerzeilen mit Rolle-Anzeige und Status-Chip |
| `addParticipantRow` | 1201 | Teilnehmerzeilen im Manuell-Formular |
| `resetCalculationResult`, `resetRegistrations`, `resetRoomSummary`, `resetSettlement`, `resetRefundDistribution` | 54, 461, 474, 666, 758 | Platzhalter-Markup |

### Neue Funktionalität, jeweils mit `// DESIGN` markiert

- **Sprungnavigation**: aktiver Abschnitt per `IntersectionObserver` auf die Section-IDs,
  Status-Punkt je Abschnitt, Chip „n / n" beim Eintrag Anmeldungen.
- **Section-Zusammenfassungen** (`.sum`): live aus den Feldwerten, z. B.
  „10 % · 5 % · Kurabgabe 2,85 €". Bleiben im zugeklappten Zustand sichtbar.
- **Live-Summen**: im Unterkunft-Grid je Kategorie `Anzahl × Preis/Nacht × Nächte`, sowie die
  Zahlen im Sticky-Panel inklusive Kosten-vs-Überschuss-Balken.
- **Abweichung Verkaufspreis vs. berechneter Endpreis** je Kategorie; Abweichung nach unten in
  `--fuf-amber-700`.
- **Ungespeicherte Änderungen**: geändertes Feld bekommt Rahmen `--fuf-orange-600` und 3 px Glow
  `rgba(231,111,30,.15)`; Hinweis über dem Speichern-Button.
- **Filter-Chips** *Alle / CSV / Manuell* plus Suche bei den Anmeldungen, rein clientseitig über
  `state.currentRegistrations`.
- **Alter bei Anreise** pro Teilnehmer, aus `participant.birthDate` und `trip.startDate`
  gerechnet. Kein Backend nötig.

`data-bs-parent="#registrations-accordion"` bleibt, es ist also weiterhin genau eine
Anmeldung gleichzeitig aufgeklappt — so zeigt es auch Mockup 02.

### Die eigentliche Bruchgefahr: Bindungen auf Modulebene

„IDs bleiben erhalten" ist als Regel zu schwach. `app.js` registriert 24 Listener direkt auf
Modulebene, also beim Laden des Skripts. Diese Elemente **müssen im statischen Template
stehen** und dürfen nicht in JS-gerendertes Markup wandern, sonst greift die Bindung ins Leere —
ohne Fehlermeldung, der Button ist einfach tot:

`actual-expense-cancel-btn`, `actual-expense-form`, `actual-expenses-body`,
`add-participant-row-btn`, `back-to-list-btn`, `calculate-btn`, `csv-upload-form`,
`delete-registrations-btn`, `invite-form`, `manual-registration-form`, `new-trip-btn`,
`open-invite-btn`, `open-settings-btn`, `open-users-btn`, `recalculate-billings-btn`,
`registrations-accordion`, `remove-participant-row-btn`, `retentionPercent`,
`sales-apply-btn`, `settings-form`, `tab-abrechnung`, `trip-form`, `trip-list-body`,
`users-table-body`.

Dazu die drei Modal-Wurzeln `#settings-modal`, `#invite-modal`, `#users-modal`, die in
`bootstrapPage()` an `new bootstrap.Modal(...)` übergeben werden.

Zwei Konsequenzen für das Layout:

- `#trip-form` bleibt **ein** Formular, das die Sections 1 bis 4 umschließt. Das Raster wird
  also innerhalb des Formulars aufgebaut, nicht um es herum.
- `#open-invite-btn` darf in den Body von `#users-modal` ziehen (statisches Template), nicht
  aber in die per `loadUsers()` gerenderte Tabelle.

## 3. Seitenstruktur

### 3.1 Reiseliste (`#trip-list-section`)

Kopfzeile: `h1` „Alle Reisen" in Georgia 28, darunter Hilfetext mit Reise- und
Teilnehmeranzahl. Rechts Suchfeld (clientseitig über Name). Kein Statusfilter.

Tabelle `.tb`: ID · Reise · Zeitraum (+ Nächte) · Teilnehmer (angemeldet / geplant) ·
Überschuss / Defizit · Aktion. Sortierung wie heute: Startdatum absteigend, dann ID absteigend.
Fußzeile der Card mit Sortierhinweis und Trefferzahl.

Aktionen je Zeile: „Öffnen" (`.btn-o .btn-s`, trägt `data-trip-id` wie heute), Löschen als
`.ib`-Icon-Button `disabled` mit `title`, der erklärt warum. Kein Duplizieren.

Überschussspalte: positiv in `--fuf-green-600`, negativ in `--fuf-red-700`, bei
`surplusBasis === "calculation"` zusätzlich Chip „geplant". Fehlt der Wert (`null`), steht „–".

Leerzustand: `#trip-list-empty` wird zur gestrichelten Karte „Neue Reise anlegen" aus Mockup 01.

### 3.2 Planung (`#panel-planung`)

Über den Tabs: Breadcrumb „Alle Reisen › Reise #n" (der Link ersetzt `#back-to-list-btn`, ID
bleibt am Breadcrumb-Link), Titel `#trip-editor-title`, Kurzinfo Zeitraum · Nächte ·
Teilnehmer. Tabs `#tab-planung` / `#tab-abrechnung` mit Icon und 3 px grünem Unterstrich; die
`data-bs-toggle="tab"`-Attribute bleiben.

Linke Spalte, sieben einklappbare Sections (Bootstrap Collapse, jede mit eigener `id` für die
Sprungnavigation):

1. **Eckdaten** (`#sec-eckdaten`) — `#name`, `#startDate`, `#endDate` im Raster `2fr 1fr 1fr`.
2. **Aufschläge & Abgaben** (`#sec-aufschlaege`) — `#markupPercent`, `#clubFeePercent`,
   `#distributionMethod`; darunter Kurabgabe-Box in Sand mit `#spaTaxPerPerson`,
   `#spaTaxAgeThreshold`, `#spaTaxCount`.
3. **Unterkunft & Teilnehmer** (`#sec-unterkunft`) — Raster *Kategorie · Anzahl · Preis/Nacht ·
   Summe (n Nächte)* für DZ, MBZ, Kinder mit `#adultDoubleCount`/`#adultDoublePrice`,
   `#adultMultiCount`/`#adultMultiPrice`, `#childCount`/`#childPrice`. Summe je Zeile live.
   `#adultAgeThreshold` in der Fußzeile.
4. **Zusatzausgaben** (`#sec-zusatz`) — `#expenseLabel`, `#expenseAmount`. **Kein** „Weiteren
   Posten hinzufügen": `tripPayloadFromForm()` baut genau einen Posten, mehrere Posten sind als
   „dazuerfunden" gestrichen. `#trip-save-btn` rechts.
5. **Kalkulation & Verkaufspreise** (`#sec-kalkulation`) — ersetzt `#calculate-btn`,
   `#result-panel`, „Berechnungsdetails" und „Kostenübersicht". Vier KPIs
   (`#result-total-participants`, `#result-total-revenue`, `#result-total-costs`,
   `#result-surplus`), darunter je Kategorie eine Akkordeonzeile *Kategorie · Endpreis
   berechnet · Verkaufspreis · Einnahmen* mit `#salesAdultDouble`, `#salesAdultMulti`,
   `#salesChild` in der Zeile und der Abweichung zum berechneten Preis. Aufgeklappt der
   Rechenweg zweispaltig aus `priceBreakdowns` (Grundpreis × Nächte, Kurabgabe, Anteil
   Gruppenausgaben, Zwischensumme, Aufschlag, Vereinsgebühr, Endpreis, Vorbelegung).
   Kopfzeile „Neu berechnen" (`#calculate-btn`, `.btn-o`), Fußzeile `#sales-apply-btn`
   (`.btn-a`). Die Detailfelder `#result-distribution-method`, `#result-spa-tax-age`,
   `#result-adult-age`, `#result-total-group-expenses` bleiben erhalten und wandern in die
   Zusammenfassung (`.sum`) dieser Section; `#result-start-date`, `#result-end-date`,
   `#result-nights` wandern ins Sticky-Panel. `renderCalculationResult` schreibt unverändert in
   alle sieben. `#result-placeholder` und `#result-panel` bleiben für den `d-none`-Wechsel.
6. **Anmeldungen** (`#sec-anmeldungen`, heute `#registrations-section`) — Kopfzeile mit
   `#recalculate-billings-btn` und `#delete-registrations-btn` als kleine Ghost-Buttons. Drei
   KPIs (`#reg-count`, `#reg-participant-count`, `#reg-billing-total`). CSV-Dropzone
   (`#csv-upload-form`, `#csvFile`) links, Manuell-Formular (`#manual-registration-form`) rechts.
   Filter-Chips *Alle / CSV / Manuell* plus Suche. Akkordeon `#registrations-accordion`:
   Zeile = Name · Chip Zimmerkategorie · Chip „n Pers." · Chip Quelle · Betrag · Bearbeiten
   (`disabled`) / Löschen · Chevron. Inhalt = Tabelle *Teilnehmer · Geburtsdatum (+ Alter bei
   Anreise) · Abrechnungskategorie-Chip · Preis* plus Kommentar.
7. **Zimmerbedarf** (`#room-summary-section`) — zwei Karten nebeneinander. „Zimmer nach Typ"
   mit Balken (`#room-detail-body`, `#room-detail-total`), „Personen nach
   Abrechnungskategorie" mit Soll (geplant) und Ist (`#cat-summary-adult-double`,
   `#cat-summary-adult-multi`, `#cat-summary-child`, `#cat-summary-total`) sowie Häkchen-Chip
   bei Übereinstimmung.

Rechte Spalte (`.pg-side`): Sprungnavigation mit sieben Einträgen, darunter Kalkulationspanel
mit Teilnehmer, Nächte, Kostenaufstellung, Kosten-vs-Überschuss-Balken und dunkelgrüner Box mit
Überschuss und Verkaufspreisen, dann „Reise speichern" (46 px) und der Hinweis
„Ungespeicherte Änderungen". Unter 1100 px Breite rutscht die Spalte unter den Inhalt und ist
nicht mehr sticky.

**Zwei Speichern-Buttons:** Mockup 02 zeigt „Reise speichern" zweimal — in der Fußzeile der
Zusatzausgaben (Zeile 147) und im Sticky-Panel mit 46 px (Zeile 345). Das ist beabsichtigt und
wird so gebaut. Auflösung: `#trip-save-btn` bleibt der Button in der Fußzeile, also
`type="submit"` innerhalb von `#trip-form` — damit bleibt die bestehende Submit-Bindung auf
`#trip-form` unberührt. Der Sticky-Button bekommt eine neue ID `#trip-save-btn-sticky`,
`type="submit"` und das Attribut `form="trip-form"`; so löst er dasselbe Submit aus, obwohl er
außerhalb des Formulars steht — ohne eine Zeile JS. Einzige Ergänzung: `fillTripForm()` setzt
heute `#trip-save-btn` auf „Reise aktualisieren"; eine `// DESIGN`-Zeile spiegelt das auf den
Sticky-Button, damit nicht zwei verschiedene Beschriftungen für dieselbe Aktion stehen.

### 3.3 Abrechnung (`#panel-abrechnung`)

Vier KPIs: `#settlement-total-revenue`, `#settlement-planned-costs`,
`#settlement-total-expenses`, `#settlement-surplus`.

Sections:
- **Geplante Kosten** — Tabelle Position · Betrag über `#planned-costs-body`,
  `#planned-costs-sum`. Eine Spalte „Grundlage" wie im Mockup entfällt: `plannedCostItems`
  liefert nur `label` und `amount`, die Grundlage steckt bereits im Label.
- **Zusätzliche Ausgaben** — Inline-Formular `#actual-expense-form` in Sand-Box
  (`#actualExpenseId`, `#actualExpenseLabel`, `#actualExpenseAmount`,
  `#actual-expense-save-btn`, `#actual-expense-cancel-btn`), Tabelle `#actual-expenses-body`
  mit Bearbeiten/Löschen, `#additional-expenses-sum`, `#actual-expenses-empty`.
- **Gesamtabrechnung** — Rechenkette als Zeilen (`#settlement-summary-revenue`,
  `-planned`, `-additional`), Gesamtausgaben auf Sand
  (`#settlement-summary-total-expenses`), Überschuss auf dunkelgrün
  (`#settlement-summary-surplus`).
- **Rückerstattung / Nachzahlung** (`#refund-section`) — `#retentionPercent` in Amber-Box
  links, Herleitung rechts zweispaltig (`#refund-surplus`, `#refund-revenue-base`,
  `#refund-retention-percent-display`, `#refund-retention-label`, `#refund-retention`,
  `#refund-distributable-formula`, `#refund-distributable`), Tabelle `#refund-body` mit
  `#refund-total-billing`, `#refund-total-amount`, `#refund-empty`.

Rechte Spalte: Sprungnavigation, Panel mit Anteilsbalken (Geplant / Zusätzlich / Einbehalt /
Verteilbar) und Kennzahlen. **Ohne** „Abrechnung abschließen" — gestrichen.

### 3.4 Modals

Einheitliche Kartenoptik: Radius 14, Kopf mit Georgia-20-Titel und Untertitel, Fuß auf Sand mit
Buttons rechts. `data-bs-dismiss` und die Modal-IDs bleiben.

- `#users-modal` — Suche und „Nutzer einladen" (`#open-invite-btn`) im Body, Rolle als
  Anzeige/`disabled`-Select in der Zeile, Status-Chip, `#users-status`, `#users-table-body`.
- `#invite-modal` — `#invite-form`, `#invite-email`, `#invite-status`; Erfolgsmeldung grün.
  Kein Rolle-Select (dazuerfunden).
- `#settings-modal` — `#settings-form` in zwei Gruppen: *Aufschläge* mit
  `#defaultMarkupPercent`, `#defaultClubFeePercent`, `#defaultDistributionMethod`;
  *Kurabgabe & Altersgrenzen* mit `#defaultSpaTaxPerPerson`, `#defaultSpaTaxAgeThreshold`,
  `#defaultAdultAgeThreshold`.

## 4. Backend: Reiseliste

### Response

`GET /api/trips` liefert je Reise zusätzlich `endDate`, `nights`, `plannedParticipants`,
`registeredParticipants`, `surplus`, `surplusBasis`:

```json
{ "id": 1, "name": "Winterfreizeit", "startDate": "2027-02-10", "endDate": "2027-02-14",
  "nights": 4, "plannedParticipants": 56, "registeredParticipants": 56,
  "surplus": 3020.0, "surplusBasis": "settlement" }
```

`surplusBasis` ist `"settlement"` oder `"calculation"`. Ist der Überschuss nicht bestimmbar,
ist `surplus` `null` und `surplusBasis` `null`.

### Umsetzung

- `SqliteTripRepository::findAll()` (`src/Infrastructure/Persistence/SqliteTripRepository.php:183`)
  selektiert zusätzlich `end_date` und gibt `endDate` zurück.
- Neue Klasse `src/Application/TripListService.php` mit einer öffentlichen Methode
  `listTrips(): array`. Aufgabe: aus den Reisen das Listen-Read-Model bauen. Abhängigkeiten
  (per `autowire()` in `config/container.php`): `TripRepositoryInterface`,
  `RegistrationRepositoryInterface`, `PriceCalculatorService`, `ActualExpenseService`.
- `TripController::list` benutzt `TripListService`. `TripService::listTrips()` entfällt — kein
  anderer Aufrufer, und zwei Pfade für dieselbe Liste wären eine Fehlerquelle. `TripService`
  bleibt damit auf Schreiben/CRUD fokussiert.

Je Reise:

- `nights` aus `Trip::nights()` — nicht neu gerechnet, eine Quelle der Wahrheit.
- `plannedParticipants` = Summe von `RoomBooking::count()` über alle Buchungen.
- `registeredParticipants` = Summe von `count($registration->participants())`.
- `surplus`: liegen Anmeldungen vor, `ActualExpenseService::getSettlement($id)['surplus']` mit
  `surplusBasis: "settlement"`; sonst `PriceCalculatorService::calculate($trip)['surplus']` mit
  `"calculation"`.

`getSettlement()` wird absichtlich komplett aufgerufen, obwohl das Reise und Anmeldungen ein
zweites Mal lädt. Die Alternative wäre, die Überschussformel im Read-Model nachzubauen — in
einer Domäne, die jeden Rechenschritt einzeln rundet, ist doppeltes Laden der günstigere
Fehler. Bei der realen Reisezahl eines Vereins ist der Unterschied nicht messbar.

### Robustheit

Die Ermittlung je Reise wird einzeln gekapselt. Eine Reise mit unvollständigen Daten (etwa
`endDate` null, also `nights` 0) liefert `surplus: null` statt die gesamte Liste mit einem
500er zu beenden.

### Vertrag und Tests

- `TripListItem` in `docs/openapi.yaml:643` um die neuen Felder erweitern. `OpenApiContractTest`
  prüft Spec-Felder gegen die Response, nicht umgekehrt — die Erweiterung bricht nichts, macht
  die Felder aber ab dann testpflichtig.
- Neue Integrationstests auf `GET /api/trips`: neue Felder vorhanden und korrekt; `surplusBasis`
  schlägt von `calculation` auf `settlement` um, sobald eine Anmeldung existiert.

## 5. Verifikation

Für das Backend gilt der bestehende Weg: `composer test`.

Für die Oberfläche gibt es keine Testabdeckung, deshalb Screenshots gegen eine laufende
Instanz:

- Wegwerf-Datenbank über die vorhandene Env-Variable `DB_PATH`
  (`config/container.php:45`), migriert per `vendor/bin/phinx migrate` und mit Demodaten
  befüllt. `database/fuf.sqlite` wird nie angefasst.
- Server per `php -S 127.0.0.1:<port> -t public public/router.php`.
- Screenshot per headless Chrome über das DevTools-Protokoll. Node 24 hat `WebSocket` eingebaut,
  es braucht also kein npm-Paket: Chrome mit `--remote-debugging-port` starten, Login über
  `POST /login`, Session-Cookie per `Network.setCookie` setzen, dann `Page.captureScreenshot`.
  Das Skript liegt unter `scripts/` und ist Teil der Entwicklungswerkzeuge, nicht der App.

`PROMPT.md` verlangt vor Schritt 3 einen Screenshot der Planungsseite. Der zeigt zu diesem
Zeitpunkt die noch alte Planungsstruktur mit den neuen Tokens — als Ausgangspunkt gewollt.
Nach dem Layoutteil von Schritt 3 folgt ein zweiter Screenshot zum Vergleich.

## 6. Umsetzungsreihenfolge

Reihenfolge aus `DESIGN.md`, Commit nach jedem Schritt:

1. `fuf.css` mit Tokens und Komponentenklassen, Schrift lokal einbinden, Kopfleiste und Fußzeile,
   `fuf.css` in alle vier Templates einhängen.
2. Reiseliste — zuerst Backend (`TripListService`, `findAll()`, OpenAPI, Tests), dann Markup und
   `renderTripList`.
3. Planung — Sections und Sticky-Spalte als Layout bei unveränderten IDs, **Screenshot**, dann
   Kalkulation als Akkordeon, dann Anmeldungen und Zimmerbedarf.
4. Abrechnung.
5. Modals.

Nach jedem Schritt muss `app.js` ohne Änderung an seinen `getElementById`-Aufrufen weiterlaufen.
