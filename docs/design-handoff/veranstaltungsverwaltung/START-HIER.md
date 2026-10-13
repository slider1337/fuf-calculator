# FuF Calculator – Veranstaltungsverwaltung · Design-Handover
Stand: 12.10.2026 · Grundlage: Konzept 09 (`referenz/09-veranstaltungsverwaltung-formulare.md`)

## Reihenfolge
1. `VERANSTALTUNGEN.md` – Übergabe: Zeichenflächen, Navigation, neue Komponenten, Feldregeln, Auswertungsregeln, Zustände, Mobilregeln, Annahmen. Gedacht als Ergänzung zu `docs/design-handoff/` (neben DESIGN.md, RESPONSIVE.md, NAVIGATION.md).
2. `mockups/index.html` im Browser öffnen – Startseite mit Links auf alle 35 Ansichten. Alle Seiten sind untereinander verlinkt.
3. `canvas-quellen/` – Originaldateien des Design-Canvas (`.dc.html` + `canvas.json`), nur für den Fall, dass der Canvas neu aufgebaut werden soll.

## Mockups
| Präfix | Inhalt |
|---|---|
| `Main` | Übersicht und Navigation |
| `D01`–`D15` | Calculator Desktop |
| `M01`–`M14` | Calculator Mobil (390 px, im Browser zentriert) |
| `W01`–`W03` | Website-Mitgliederformular „Holz & Lagerfeuer“ |
| `Z01` | Zustände Calculator |
| `K01` | Komponenten |

Klickbar (mit Zustand): `D03` Formular-Editor, `D04` Vorschau, `D07` Wizard, `D10` Auswertung, `W01`/`W02` Website-Anmeldung.

## Hinweise
- Die Mockups sind **Referenz, kein Produktivcode**. Sie enthalten eine kleine eingebettete Demo-Runtime (Template + `renderVals()`), damit die klickbaren Ansichten ohne Build laufen. Für die Umsetzung zählen Layout, Tokens, Texte und Zustände – nicht diese Runtime.
- Schriften kommen von Google Fonts (Source Sans 3, Zilla Slab, Figtree); ohne Internet greifen die Fallback-Schriften.
- Alle Namen und Zahlen sind fiktive Beispieldaten aus Abschnitt 11 des Konzepts. Preise auf der Website sind Platzhalter.
- Keine API, keine echten easyVerein-Termine, keine Mails.
- Planung und Abrechnung behalten das bestehende Design (`docs/design-handoff/mockups/02` und `03`); neu ist nur der Dialog „Anmeldedaten übernehmen“ (`D14`).
- Links auf Abrechnung, Mein Konto und Veranstaltungs-Einstellungen führen auf `Z01`, weil es dafür keine eigene neue Ansicht gibt.

Canvas (online, kommentierbar): https://claude.ai/artifact/SPegBr16wz68rZLJm849Hr
