<?php

/**
 * FuF Erding – Migration Phase 2, Schritt 2: IMPORT (v3)
 * -------------------------------------------------------
 * Fixes gegenüber v2:
 *   - Datum: date + date_gmt werden gesetzt, Status direkt 'publish'
 *   - Autor-Kürzel als Freitext-Zeile im Content (unter dem Bild)
 *   - Bild-Upload mit Retry bei 503 (Strato Throttling)
 *   - Längere Pausen zwischen Requests (2s statt 0.5s)
 *   - Teaser als WordPress-Excerpt gesetzt
 *
 * Aufruf:   php 02-import.php
 */

declare(strict_types=1);

// ==========================================================================
// KONFIGURATION – HIER ANPASSEN!
// ==========================================================================
const WP_URL = 'https://fuferding-687yg9ejg3.live-website.com';
const WP_USER = 'benna';
define('WP_APP_PASS', getenv('FUF_WP_APP_PASSWORD') ?: '');

const BERICHTE_JSON = __DIR__ . '/output/berichte.json';
const CATEGORY_NAME = 'Berichte';

// Wie viele Berichte importieren? 0 = alle, 3 = Test-Modus
const TEST_LIMIT = 0;

const IMPORTED_LOG = __DIR__ . '/output/imported.json';

// Pause zwischen Beiträgen in Sekunden (Strato mag keine schnellen Requests)
const REQUEST_PAUSE = 2;

// Retry-Versuche bei Bild-Upload (503 von Strato)
const UPLOAD_RETRIES = 3;
const UPLOAD_RETRY_WAIT = 5; // Sekunden warten bei Retry

// ==========================================================================
if (WP_APP_PASS === '') {
    echo "FEHLER: Bitte FUF_WP_APP_PASSWORD als Umgebungsvariable setzen!\n";
    exit(1);
}

function log_line(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}

function wp_api(string $method, string $endpoint, array $data = []): array
{
    $url = rtrim(WP_URL, '/') . '/wp-json/wp/v2/' . ltrim($endpoint, '/');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERPWD => WP_USER . ':' . WP_APP_PASS,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        throw new RuntimeException("cURL-Fehler: $err");
    }
    $json = json_decode($response, true);
    if ($http_code >= 400) {
        $msg = $json['message'] ?? substr($response, 0, 200);
        throw new RuntimeException("WP API Fehler (HTTP $http_code): $msg");
    }
    return $json ?? [];
}

/**
 * Bild hochladen mit Retry bei 503.
 */
function upload_image(string $image_url, string $filename_hint): int
{
    // Bild runterladen
    $ch = curl_init($image_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'FuF-Migration/3.0',
    ]);
    $image_data = curl_exec($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
    $dl_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($dl_code !== 200 || !$image_data) {
        throw new RuntimeException("Bild-Download fehlgeschlagen (HTTP $dl_code): $image_url");
    }

    // Dateiname
    $ext = 'jpg';
    if (str_contains($content_type, 'png')) {
        $ext = 'png';
    } elseif (str_contains($content_type, 'gif')) {
        $ext = 'gif';
    } elseif (str_contains($content_type, 'webp')) {
        $ext = 'webp';
    }
    $safe = preg_replace('/[^a-z0-9\-]/i', '-', $filename_hint);
    $safe = preg_replace('/-{2,}/', '-', trim($safe, '-'));
    $filename = substr($safe, 0, 60) . '.' . $ext;

    // Upload mit Retry
    $url = rtrim(WP_URL, '/') . '/wp-json/wp/v2/media';
    for ($attempt = 1; $attempt <= UPLOAD_RETRIES; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERPWD => WP_USER . ':' . WP_APP_PASS,
            CURLOPT_HTTPHEADER => [
                'Content-Disposition: attachment; filename="' . $filename . '"',
                'Content-Type: ' . $content_type,
            ],
            CURLOPT_POSTFIELDS => $image_data,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException("Upload cURL-Fehler: $err");
        }

        if ($http_code === 503 && $attempt < UPLOAD_RETRIES) {
            log_line("    503 bei Upload – warte " . UPLOAD_RETRY_WAIT . "s (Versuch $attempt/" . UPLOAD_RETRIES . ")");
            sleep(UPLOAD_RETRY_WAIT);
            continue;
        }

        $json = json_decode($response, true);
        if ($http_code >= 400) {
            $msg = $json['message'] ?? substr($response, 0, 200);
            throw new RuntimeException("Upload fehlgeschlagen (HTTP $http_code): $msg");
        }
        return (int)$json['id'];
    }
    throw new RuntimeException("Upload nach " . UPLOAD_RETRIES . " Versuchen fehlgeschlagen");
}

function get_or_create_category(string $name): int
{
    $cats = wp_api('GET', 'categories?search=' . urlencode($name) . '&per_page=100');
    if (is_array($cats)) {
        foreach ($cats as $cat) {
            if (strtolower(trim($cat['name'])) === strtolower(trim($name))) {
                return (int)$cat['id'];
            }
        }
    }
    $new = wp_api('POST', 'categories', ['name' => $name]);
    return (int)$new['id'];
}

/**
 * Baut den HTML-Inhalt für einen WordPress-Beitrag.
 */
function build_post_content(array $b): string
{
    $parts = [];

    // 1) Hauptbild
    if (!empty($b['main_image'])) {
        $img_url = htmlspecialchars($b['main_image']);
        $alt = htmlspecialchars($b['title']);
        $parts[] = '<figure class="wp-block-image size-large"><img src="' . $img_url . '" alt="' . $alt . '"/></figure>';
    }

    // 2) Autor + Datum Zeile (Freitext)
    $meta_parts = [];
    if (!empty($b['date'])) {
        $meta_parts[] = $b['date'];
    }
    if (!empty($b['autor'])) {
        $meta_parts[] = 'Autor: ' . $b['autor'];
    }
    if (!empty($meta_parts)) {
        $meta = htmlspecialchars(implode(' · ', $meta_parts));
        $parts[] = '<p style="color: #888; font-style: italic; font-size: 0.9em;">' . $meta . '</p>';
    }

    // 3) Body-Blöcke
    if (empty($b['body'])) {
        if (!empty($b['teaser'])) {
            $parts[] = '<p>' . htmlspecialchars($b['teaser']) . '</p>';
        }
        return implode("\n\n", $parts);
    }

    foreach ($b['body'] as $block) {
        $tag = $block['tag'];
        $html = $block['html'];

        // Relative Bild-URLs absolut machen
        $html = preg_replace_callback(
            '/src\s*=\s*"(fileservlet[^"]+)"/i',
            fn($m) => 'src="https://www.fuf-erding.de/clubdesk/' . htmlspecialchars($m[1]) . '"',
            $html
        );

        $parts[] = match ($tag) {
            'p' => "<p>$html</p>",
            'ul' => "<ul>$html</ul>",
            'ol' => "<ol>$html</ol>",
            'h2' => "<h2>$html</h2>",
            'h3' => "<h3>$html</h3>",
            'h4' => "<h4>$html</h4>",
            'blockquote' => "<blockquote>$html</blockquote>",
            default => "<p>$html</p>",
        };
    }

    return implode("\n\n", $parts);
}

/**
 * Deutsches Datum → ISO 8601 (für WP date + date_gmt).
 */
function date_to_iso(string $de_date): string
{
    $parts = explode('.', $de_date);
    if (count($parts) !== 3) {
        return '';
    }
    [$d, $m, $y] = $parts;
    // Validierung
    if (!checkdate((int)$m, (int)$d, (int)$y)) {
        return '';
    }
    return sprintf('%s-%s-%sT12:00:00', $y, $m, $d);
}

// ==========================================================================
// HAUPTABLAUF
// ==========================================================================
log_line('FuF Migration – Import-Skript v3');
log_line('WordPress: ' . WP_URL);
log_line('Test-Limit: ' . (TEST_LIMIT > 0 ? TEST_LIMIT . ' Berichte' : 'ALLE'));

if (!file_exists(BERICHTE_JSON)) {
    log_line('FEHLER: ' . BERICHTE_JSON . ' nicht gefunden.');
    exit(1);
}
$berichte = json_decode(file_get_contents(BERICHTE_JSON), true);
log_line('Berichte geladen: ' . count($berichte));

$imported = [];
if (file_exists(IMPORTED_LOG)) {
    $imported = json_decode(file_get_contents(IMPORTED_LOG), true) ?: [];
}

log_line('Teste WordPress-Verbindung ...');
try {
    $me = wp_api('GET', 'users/me');
    log_line('Verbunden als: ' . ($me['name'] ?? '?') . ' (ID ' . ($me['id'] ?? '?') . ')');
} catch (Throwable $e) {
    log_line('FEHLER: ' . $e->getMessage());
    exit(1);
}

log_line('Kategorie "' . CATEGORY_NAME . '" sicherstellen ...');
$cat_id = get_or_create_category(CATEGORY_NAME);
log_line('Kategorie-ID: ' . $cat_id);

$limit = TEST_LIMIT > 0 ? TEST_LIMIT : count($berichte);
$count = 0;
$errors = 0;

foreach ($berichte as $b) {
    if ($count >= $limit) {
        break;
    }

    $news_id = $b['news_id'] ?? 'unknown';

    if (isset($imported[$news_id])) {
        log_line(
            sprintf(
                'SKIP %s (%s) – bereits importiert als WP #%d',
                $news_id,
                $b['title'],
                $imported[$news_id]
            )
        );
        continue;
    }

    $count++;
    log_line(sprintf('[%d/%d] Importiere: %s (%s)', $count, $limit, $b['title'], $b['date']));

    // 1) Bild hochladen
    $featured_media_id = 0;
    if (!empty($b['main_image'])) {
        try {
            log_line('  Bild hochladen ...');
            $img_hint = 'fuf-' . $news_id . '-' . substr(preg_replace('/[^a-z0-9]/i', '-', $b['title']), 0, 40);
            $featured_media_id = upload_image($b['main_image'], $img_hint);
            log_line('  Bild hochgeladen: Media-ID ' . $featured_media_id);
        } catch (Throwable $e) {
            log_line('  WARNUNG Bild: ' . $e->getMessage());
        }
        sleep(1); // Extra-Pause nach Bild-Upload
    }

    // 2) Post-Inhalt
    $content = build_post_content($b);

    // 3) Beitrag erstellen (direkt als publish mit Originaldatum)
    $iso_date = date_to_iso($b['date']);
    $post_data = [
        'title' => $b['title'],
        'content' => $content,
        'excerpt' => $b['teaser'] ?? '',
        'status' => 'publish',
        'categories' => [$cat_id],
        'featured_media' => $featured_media_id,
    ];

    // Datum: sowohl date als auch date_gmt setzen
    if ($iso_date !== '') {
        $post_data['date'] = $iso_date;
        $post_data['date_gmt'] = $iso_date;
    }

    try {
        $result = wp_api('POST', 'posts', $post_data);
        $wp_id = (int)$result['id'];
        $wp_date = $result['date'] ?? '?';
        log_line(sprintf('  ✓ WP #%d – Datum: %s', $wp_id, $wp_date));
        $imported[$news_id] = $wp_id;
    } catch (Throwable $e) {
        log_line('  ✗ FEHLER: ' . $e->getMessage());
        $errors++;
    }

    file_put_contents(IMPORTED_LOG, json_encode($imported, JSON_PRETTY_PRINT));
    sleep(REQUEST_PAUSE);
}

log_line('');
log_line('FERTIG.');
log_line('  Importiert: ' . count($imported) . ' Berichte');
log_line('  Fehler:     ' . $errors);
log_line('  Log:        ' . IMPORTED_LOG);
if (TEST_LIMIT > 0 && $count >= TEST_LIMIT) {
    log_line('');
    log_line('TEST-MODUS: Nur ' . TEST_LIMIT . ' Berichte importiert.');
    log_line('Wenn alles passt: TEST_LIMIT auf 0 setzen und erneut starten.');
}
