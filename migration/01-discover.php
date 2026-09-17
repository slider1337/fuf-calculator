<?php
/**
 * FuF Erding – Migration Phase 2, Schritt 1: DISCOVERY (v2)
 * ---------------------------------------------------------
 * Lädt die News-Übersichtsseite von fuf-erding.de, parst alle Bericht-Kacheln
 * (.cd-tile-h-box) inkl. Titel, Datum, Autor, Teaser, Bild und Detail-Link
 * aus dem onclick-Handler. Für Berichte mit Detail-Link wird die Detailseite
 * geladen und der Volltext extrahiert.
 *
 * Aufruf:   php 01-discover.php
 * Ergebnis: ./output/berichte.json
 *           ./output/raw/<news_id>.html
 *           ./output/raw/_overview.html
 *
 * Voraussetzungen: PHP >= 8.0 mit DOM-Extension. Keine externen Pakete nötig.
 */

declare(strict_types=1);

// --------------------------------------------------------------------------
// KONFIGURATION
// --------------------------------------------------------------------------
const BASE_HOST      = 'https://www.fuf-erding.de';
const NEWS_LIST_URL  = BASE_HOST . '/?b=1000122&c=NL&s=djEtbbduisV-RfYMVmdEpC6D6eDSWc3xm9QnSS1WOakMKjA=';
const OUTPUT_DIR     = __DIR__ . '/output';
const RAW_DIR        = __DIR__ . '/output/raw';
const USER_AGENT     = 'FuF-Migration/2.0 (admin@fuf-erding.de)';
const REQUEST_DELAY_SECONDS = 1;

// --------------------------------------------------------------------------
@mkdir(OUTPUT_DIR, 0775, true);
@mkdir(RAW_DIR, 0775, true);

function log_line(string $msg): void {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function http_get(string $url): string {
    $ctx = stream_context_create([
                                     'http'  => ['header' => "User-Agent: " . USER_AGENT . "\r\n", 'timeout' => 30],
                                     'https' => ['header' => "User-Agent: " . USER_AGENT . "\r\n", 'timeout' => 30],
                                 ]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html === false) throw new RuntimeException("HTTP GET fehlgeschlagen: $url");
    return $html;
}

function load_dom(string $html): DOMDocument {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    return $dom;
}

/**
 * Wahr, wenn $b im Dokument nach $a steht.
 */
function node_follows(DOMNode $a, DOMNode $b): bool {
    $xp = new DOMXPath($a->ownerDocument);
    foreach ($xp->query('following::node()', $a) as $n) {
        if ($n === $b) return true;
    }
    foreach ($xp->query('descendant::node()', $a) as $n) {
        if ($n === $b) return true;
    }
    return false;
}

/**
 * Macht eine relative ClubDesk-URL absolut.
 * Im HTML steht <base href="/clubdesk/"/>, d.h. relative Pfade wie
 * "fileservlet?type=image&id=..." liegen unter /clubdesk/.
 */
function abs_url(string $url): string {
    if ($url === '') return $url;
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }
    if (str_starts_with($url, '/')) {
        return BASE_HOST . $url;
    }
    return BASE_HOST . '/clubdesk/' . ltrim($url, '/');
}

function clean_inner_html(DOMNode $node): string {
    $dom  = $node->ownerDocument;
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $dom->saveHTML($child);
    }
    return trim(preg_replace('/\s+/u', ' ', $html));
}

// --------------------------------------------------------------------------
// 1) ÜBERSICHT PARSEN: alle Kacheln einlesen
// --------------------------------------------------------------------------
function parse_overview(string $html): array {
    $dom = load_dom($html);
    $xp  = new DOMXPath($dom);

    $tiles = $xp->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' cd-tile-h-box ')]");
    $results = [];

    foreach ($tiles as $tile) {
        /** @var DOMElement $tile */
        $entry = [
            'news_id'    => null,
            'title'      => '',
            'date'       => '',
            'autor'      => '',
            'teaser'     => '',
            'main_image' => null,
            'detail_url' => null,
        ];

        // News-ID aus dem inneren Anchor
        $anchor = $xp->query(".//span[contains(@id, 'newsitem-')]", $tile)->item(0);
        if ($anchor) {
            if (preg_match('/newsitem-(\d+)/', $anchor->getAttribute('id'), $m)) {
                $entry['news_id'] = $m[1];
            }
        }

        // Detail-URL aus onclick (window.location.href='...')
        $onclick = $tile->getAttribute('onclick');
        if ($onclick !== '' && preg_match("/window\.location\.href\s*=\s*'([^']+)'/", $onclick, $m)) {
            $entry['detail_url'] = abs_url($m[1]);
        }

        // Titel
        $title_node = $xp->query(".//div[contains(@class, 'cd-tile-h-main-heading')]", $tile)->item(0);
        if ($title_node) {
            $entry['title'] = trim($title_node->textContent);
        }

        // Datum + Autor aus subheading
        $subheading = $xp->query(".//div[contains(@class, 'cd-tile-h-main-subheading')]", $tile)->item(0);
        if ($subheading) {
            $time_node = $xp->query(".//time", $subheading)->item(0);
            if ($time_node) {
                $entry['date'] = trim($time_node->textContent);
            }
            $full = trim(preg_replace('/\s+/u', ' ', $subheading->textContent));
            if ($entry['date'] !== '') {
                $rest = trim(str_replace($entry['date'], '', $full));
                $rest = ltrim($rest, " \t,");
                $entry['autor'] = trim($rest);
            }
        }

        // Teaser
        $teaser_node = $xp->query(".//div[contains(@class, 'cd-tile-h-detail-value')]", $tile)->item(0);
        if ($teaser_node) {
            $entry['teaser'] = trim(preg_replace('/\s+/u', ' ', $teaser_node->textContent));
        }

        // Hauptbild
        $img_node = $xp->query(".//img[contains(@src, 'fileservlet')]", $tile)->item(0);
        if ($img_node) {
            $entry['main_image'] = abs_url($img_node->getAttribute('src'));
        }

        // Konsistenz-Check
        if ($entry['news_id'] === null && $entry['title'] === '') continue;

        $results[] = $entry;
    }
    return $results;
}

// --------------------------------------------------------------------------
// 2) DETAIL-SEITE PARSEN: Volltext extrahieren
// --------------------------------------------------------------------------
function parse_detail_body(string $html): array {
    $dom = load_dom($html);
    $xp  = new DOMXPath($dom);

    $h1 = $xp->query("//main//h1")->item(0);
    if (!$h1) $h1 = $xp->query("//h1")->item(0);
    if (!$h1) return [];

    $blocks = $xp->query("//main//p | //main//ul | //main//ol | //main//h2 | //main//h3 | //main//h4 | //main//blockquote | //main//hr");
    if ($blocks->length === 0) {
        $blocks = $xp->query("//p | //ul | //ol | //h2 | //h3 | //h4 | //blockquote | //hr");
    }

    $body = [];
    foreach ($blocks as $node) {
        if (!node_follows($h1, $node)) continue;
        if ($node->nodeName === 'hr') break;
        $text = trim(preg_replace('/\s+/u', ' ', $node->textContent));
        if ($text === '') continue;
        if (preg_match('/^(Impressum|Datenschutz|© 20\d{2})/u', $text)) break;
        $body[] = [
            'tag'  => $node->nodeName,
            'html' => clean_inner_html($node),
            'text' => $text,
        ];
    }
    return $body;
}

// --------------------------------------------------------------------------
// HAUPTABLAUF
// --------------------------------------------------------------------------
log_line('Lade Übersichtsseite ...');
$overview_html = http_get(NEWS_LIST_URL);
file_put_contents(RAW_DIR . '/_overview.html', $overview_html);
log_line('Übersicht geladen (' . strlen($overview_html) . ' Bytes).');

$berichte = parse_overview($overview_html);
log_line('Berichte in Übersicht gefunden: ' . count($berichte));

if (count($berichte) === 0) {
    log_line('WARNUNG: keine Kacheln gefunden. Bitte output/raw/_overview.html prüfen.');
    exit(1);
}

$total = count($berichte);
$with_detail = 0;
$without_detail = 0;
foreach ($berichte as $i => &$entry) {
    $idx = $i + 1;
    if (empty($entry['detail_url'])) {
        $without_detail++;
        log_line(sprintf('[%d/%d] %s (%s) - KEINE Detailseite, nur Teaser', $idx, $total, $entry['title'], $entry['date']));
        $entry['body'] = [];
        continue;
    }
    $with_detail++;
    log_line(sprintf('[%d/%d] News %s - %s', $idx, $total, $entry['news_id'] ?? '?', $entry['title']));

    try {
        $detail_html = http_get($entry['detail_url']);
    } catch (Throwable $e) {
        log_line('  FEHLER: ' . $e->getMessage());
        $entry['body'] = [];
        sleep(REQUEST_DELAY_SECONDS);
        continue;
    }

    $news_id = $entry['news_id'] ?? ('unknown_' . $idx);
    file_put_contents(RAW_DIR . "/{$news_id}.html", $detail_html);

    $entry['body'] = parse_detail_body($detail_html);
    log_line(sprintf('  -> %d Body-Blöcke', count($entry['body'])));

    sleep(REQUEST_DELAY_SECONDS);
}
unset($entry);

$out_file = OUTPUT_DIR . '/berichte.json';
file_put_contents(
    $out_file,
    json_encode($berichte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

log_line('FERTIG.');
log_line('  Mit Detail-Seite:  ' . $with_detail);
log_line('  Nur Teaser:        ' . $without_detail);
log_line('  Gespeichert in:    ' . $out_file);
log_line('  Roh-HTML in:       ' . RAW_DIR);
