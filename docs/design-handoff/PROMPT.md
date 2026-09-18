# Prompt für Claude Code

Ordner `design-handoff/` ins Repo legen (z. B. `docs/design-handoff/`), dann:

```
Setze das UI-Redesign aus docs/design-handoff/ um.

Lies zuerst docs/design-handoff/DESIGN.md (Tokens, Komponenten, Seitenstruktur, Mapping auf
bestehende Element-IDs) und danach NAVIGATION.md (sticky Kopfleiste, Wechsel zwischen Planung
und Abrechnung, Plan/Ist-Kennzahlen). Die fünf Dateien unter mockups/ sind statische HTML-Mockups mit
Beispieldaten – sie zeigen den Sollzustand; öffne sie und orientiere dich an ihren Styles,
übernimm aber nicht die Beispieldaten und schreib keine Inline-Styles in unsere index.html.

Rahmenbedingungen:
- Bootstrap 5 bleibt, wird aber per CSS Custom Properties übersteuert (neue Datei
  assets/css/fuf.css, nach bootstrap.min.css laden). Google Font „Source Sans 3“ einbinden.
- Alle bestehenden Element-IDs, Formular-Namen und data-bs-Attribute bleiben erhalten,
  app.js soll ohne Änderungen an getElementById-Aufrufen weiterlaufen. Wo das Design neue
  Elemente braucht (Sprungnavigation, Section-Zusammenfassungen, Live-Summen, Abweichung
  Verkaufspreis vs. Endpreis), ergänze app.js minimal und markiere die Stellen mit // DESIGN.
- Umsetzung in der Reihenfolge aus DESIGN.md, Abschnitt „Umsetzungsreihenfolge“. Nach jedem
  Schritt Commit; zeig mir vor Schritt 3 einen Screenshot der Planungsseite.
- Alles unter „Bewusst dazuerfunden“ in DESIGN.md zunächst weglassen, außer den Filter-Chips
  bei Anmeldungen.
- Icons als inline-SVG (Lucide-Stil, stroke 2), keine Icon-Font.

Fang mit fuf.css und der Kopfleiste an.
```

Wenn nur die Navigation umgesetzt werden soll, reicht NAVIGATION.md allein – die drei
Punkte dort sind unabhängig vom restlichen Redesign und in der dort genannten Reihenfolge
einzeln lieferbar.
