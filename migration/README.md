# FuF Migration – Phase 2, Schritt 1: Discovery

Dieses Skript scannt die alte ClubDesk-Seite **fuf-erding.de** und sammelt
alle Berichte in eine strukturierte JSON-Datei ein. Es schreibt nichts nach
WordPress – das ist Aufgabe von Schritt 2.

## Voraussetzungen (XAMPP)

- PHP 8.0 oder neuer (ist in jedem XAMPP der letzten Jahre dabei)
- Die DOM-Extension (Standard, muss man nicht aktivieren)
- Internet-Verbindung zu `www.fuf-erding.de`

## Lauf

XAMPP starten und in einer Shell:

```bash
cd /pfad/zu/diesem/ordner
php 01-discover.php
```

Während des Laufs siehst du Zeilen wie:

```
[19:23:12] Lade Übersichtsseite ...
[19:23:13] Detail-Links gefunden: 64
[19:23:13] [1/64] News 1001202 -> https://www.fuf-erding.de/willkommen?...
[19:23:14] [2/64] News 1001188 -> https://www.fuf-erding.de/willkommen?...
...
```

Bei ca. 64 Berichten und 1 Sekunde Wartezeit zwischen Requests dauert das
**ungefähr 1–2 Minuten**.

## Ergebnisse

Nach dem Lauf liegt im Unterordner `output/`:

- `berichte.json` – Strukturierte Daten aller Berichte
- `raw/_overview.html` – Roh-HTML der Übersichtsseite
- `raw/<news_id>.html` – Roh-HTML jedes einzelnen Berichts (zur Kontrolle)

## Struktur der berichte.json

```json
[
  {
    "news_id": "1000039",
    "title": "2. Mitgliederversammlung",
    "date": "15.03.2024",
    "autor": "TM",
    "main_image": "https://www.fuf-erding.de/clubdesk/fileservlet?type=image&id=1000553&s=...&imageFormat=_2048x2048",
    "body": [
      {
        "tag": "p",
        "html": "Pressemeldung: Junger Verein auf Expansionskurs",
        "text": "Pressemeldung: Junger Verein auf Expansionskurs"
      },
      {
        "tag": "p",
        "html": "Am 15. März 2024 fand die zweite Mitgliederversammlung ...",
        "text": "Am 15. März 2024 fand die zweite Mitgliederversammlung ..."
      },
      ...
    ],
    "detail_url": "https://www.fuf-erding.de/willkommen?b=1000122&c=NL%2CND1000039&s=..."
  },
  ...
]
```

## Wenn was nicht klappt

- Falls "HTTP GET fehlgeschlagen" kommt: kann an `allow_url_fopen` liegen.
  In `php.ini` muss `allow_url_fopen = On` stehen (XAMPP-Default ist On).
- Falls "WARNUNG: keine Detail-Links gefunden" kommt: Schick mir die Datei
  `output/raw/_overview.html`, dann passe ich den Parser an.
- Bei einzelnen Berichten, wo das Datum/Autor fehlt: die `output/raw/<news_id>.html`
  schicken, dann ergänze ich die Heuristik.
