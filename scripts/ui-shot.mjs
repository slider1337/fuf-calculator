/*
 * Screenshots und ID-Pruefung fuer die Oberflaeche.
 *
 * Meldet sich per POST /login an, uebergibt das Session-Cookie an ein
 * headless Chrome und fotografiert die angegebenen Routen. Prueft ausserdem,
 * dass alle Elemente vorhanden sind, an die app.js auf Modulebene Listener
 * bindet - genau daran wuerde ein Redesign unbemerkt scheitern.
 *
 * Kommt ohne npm-Pakete aus: Node >= 22 hat WebSocket eingebaut.
 *
 * Aufruf:
 *   node scripts/ui-shot.mjs --base http://127.0.0.1:8123 --out build/screenshots \
 *     --email demo@fuf-erding.de --password demo12345 \
 *     / :liste /trips/1 :planung
 *
 * Mit --eval laeuft vor der Aufnahme noch JavaScript in der Seite. Nur so lassen
 * sich Zustaende fotografieren, die erst durch eine Eingabe entstehen:
 *   --eval "document.getElementById('trip-search').value='xy';
 *           document.getElementById('trip-search').dispatchEvent(new Event('input'))"
 */

import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as sleep } from 'node:timers/promises';

// Elemente, an die app.js beim Laden des Skripts Listener bindet. Fehlt eines,
// ist der zugehoerige Bedienschritt tot - ohne jede Fehlermeldung.
const REQUIRED_IDS = [
  'actual-expense-cancel-btn', 'actual-expense-form', 'actual-expenses-body',
  'add-participant-row-btn', 'back-to-list-btn', 'calculate-btn',
  'csv-upload-form', 'delete-registrations-btn', 'invite-form',
  'manual-registration-form', 'new-trip-btn', 'open-invite-btn',
  'open-settings-btn', 'open-users-btn', 'recalculate-billings-btn',
  'registrations-accordion', 'remove-participant-row-btn',
  'result-breakdowns-container', 'retentionPercent',
  'sales-apply-btn', 'settings-form', 'tab-abrechnung', 'trip-form',
  'trip-list-body', 'users-table-body',
  // Modal-Wurzeln, die bootstrapPage() an new bootstrap.Modal(...) uebergibt
  'settings-modal', 'invite-modal', 'users-modal',
];

function parseArgs(argv) {
  const opts = {
    base: 'http://127.0.0.1:8123',
    out: 'build/screenshots',
    email: 'demo@fuf-erding.de',
    password: 'demo12345',
    width: 1440,
    height: 1200,
    eval: null,
    targets: [],
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--base') { opts.base = argv[++i]; continue; }
    if (arg === '--out') { opts.out = argv[++i]; continue; }
    if (arg === '--email') { opts.email = argv[++i]; continue; }
    if (arg === '--password') { opts.password = argv[++i]; continue; }
    if (arg === '--width') { opts.width = Number(argv[++i]); continue; }
    if (arg === '--height') { opts.height = Number(argv[++i]); continue; }
    if (arg === '--eval') { opts.eval = argv[++i]; continue; }
    opts.targets.push(arg);
  }

  // Ziele kommen paarweise: Pfad, dann :name fuer die Datei
  const targets = [];
  for (let i = 0; i < opts.targets.length; i += 2) {
    targets.push({ path: opts.targets[i], name: (opts.targets[i + 1] || '').replace(/^:/, '') });
  }
  opts.targets = targets.length > 0 ? targets : [{ path: '/', name: 'start' }];

  return opts;
}

async function login(base, email, password) {
  const response = await fetch(`${base}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ email, password }).toString(),
    redirect: 'manual',
  });

  const cookies = response.headers.getSetCookie();
  const session = cookies.map((line) => line.split(';')[0]).find((pair) => pair.startsWith('fufsid='));

  if (!session) {
    throw new Error(`Login fehlgeschlagen (HTTP ${response.status}) - kein fufsid-Cookie erhalten.`);
  }

  return { name: 'fufsid', value: session.slice('fufsid='.length) };
}

class Cdp {
  #ws;
  #id = 0;
  #pending = new Map();

  static async connect(url) {
    const cdp = new Cdp();
    cdp.#ws = new WebSocket(url);

    await new Promise((resolve, reject) => {
      cdp.#ws.addEventListener('open', resolve, { once: true });
      cdp.#ws.addEventListener('error', () => reject(new Error('CDP-Verbindung fehlgeschlagen')), { once: true });
    });

    cdp.#ws.addEventListener('message', (event) => {
      const message = JSON.parse(event.data);
      const pending = cdp.#pending.get(message.id);
      if (!pending) return;
      cdp.#pending.delete(message.id);
      if (message.error) pending.reject(new Error(message.error.message));
      else pending.resolve(message.result);
    });

    return cdp;
  }

  send(method, params = {}) {
    const id = ++this.#id;
    return new Promise((resolve, reject) => {
      this.#pending.set(id, { resolve, reject });
      this.#ws.send(JSON.stringify({ id, method, params }));
    });
  }

  close() {
    this.#ws.close();
  }
}

async function chromeWebSocketUrl(port) {
  // Bewusst /json/list und nicht /json/version: Letzteres liefert den
  // Browser-Endpunkt, der die Page-Domain nicht kennt. Gebraucht wird ein
  // Page-Target.
  for (let attempt = 0; attempt < 80; attempt += 1) {
    try {
      const response = await fetch(`http://127.0.0.1:${port}/json/list`);
      const targets = await response.json();
      const page = targets.find((t) => t.type === 'page' && t.webSocketDebuggerUrl);
      if (page) return page.webSocketDebuggerUrl;
    } catch {
      // Chrome startet noch
    }
    await sleep(100);
  }
  throw new Error('Chrome hat kein Page-Target bereitgestellt.');
}

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  await mkdir(opts.out, { recursive: true });

  const cookie = await login(opts.base, opts.email, opts.password);
  const port = 9222 + Math.floor(Math.random() * 500);
  const profile = await mkdtemp(join(tmpdir(), 'fuf-ui-chrome-'));

  const chrome = spawn('google-chrome', [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-gpu',
    '--hide-scrollbars',
    `--window-size=${opts.width},${opts.height}`,
    `--user-data-dir=${profile}`,
    'about:blank',
  ], { stdio: 'ignore' });

  let failures = 0;

  try {
    const cdp = await Cdp.connect(await chromeWebSocketUrl(port));
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');

    // Laufzeitfehler einsammeln, bevor irgendein Skript der Seite laeuft
    await cdp.send('Page.addScriptToEvaluateOnNewDocument', {
      source: `
        window.__fufErrors = [];
        addEventListener('error', (e) => {
          window.__fufErrors.push(String(e.message || e.error));
        });
        addEventListener('unhandledrejection', (e) => {
          window.__fufErrors.push('unhandled rejection: ' + String(e.reason));
        });
      `,
    });

    const { origin, hostname } = new URL(opts.base);
    await cdp.send('Network.setCookie', {
      name: cookie.name,
      value: cookie.value,
      domain: hostname,
      path: '/',
    });

    for (const target of opts.targets) {
      const url = `${origin}${target.path}`;
      await cdp.send('Page.navigate', { url });

      // Auf das Laden warten, dann den fetch-Kaskaden der SPA Zeit geben
      await cdp.send('Runtime.evaluate', {
        expression: 'new Promise((r) => document.readyState === "complete" ? r(1) : addEventListener("load", () => r(1)))',
        awaitPromise: true,
      });
      await sleep(1200);

      // Optionaler Eingriff vor der Aufnahme: Feld fuellen, Section aufklappen.
      if (opts.eval) {
        await cdp.send('Runtime.evaluate', { expression: opts.eval, awaitPromise: true });
        await sleep(300);
      }

      const idCheck = await cdp.send('Runtime.evaluate', {
        expression: `JSON.stringify(${JSON.stringify(REQUIRED_IDS)}.filter((id) => !document.getElementById(id)))`,
        returnByValue: true,
      });
      const missing = JSON.parse(idCheck.result.value);

      const errorCheck = await cdp.send('Runtime.evaluate', {
        expression: 'JSON.stringify(window.__fufErrors || [])',
        returnByValue: true,
      });
      const jsErrors = JSON.parse(errorCheck.result.value);

      const metrics = await cdp.send('Page.getLayoutMetrics');
      const full = metrics.cssContentSize;

      const shot = await cdp.send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        clip: { x: 0, y: 0, width: opts.width, height: Math.min(full.height, 6000), scale: 1 },
      });

      const file = `${opts.out}/${target.name || 'shot'}.png`;
      await writeFile(file, Buffer.from(shot.data, 'base64'));

      const status = missing.length === 0 ? 'ok' : `FEHLEND: ${missing.join(', ')}`;
      console.log(`${target.path} -> ${file} (${Math.round(full.height)}px) ${status}`);

      if (missing.length > 0) failures += 1;
      if (jsErrors.length > 0) {
        console.log(`  JS-Fehler: ${jsErrors.join(' | ')}`);
        failures += 1;
      }
    }

    cdp.close();
  } finally {
    chrome.kill();
    // Chrome schreibt nach dem Signal noch kurz weiter; Aufraeumen ist Kosmetik
    // und darf den Lauf nicht scheitern lassen.
    await sleep(300);
    await rm(profile, { recursive: true, force: true }).catch(() => {});
  }

  if (failures > 0) {
    console.error(`\n${failures} Pruefung(en) fehlgeschlagen.`);
    process.exit(1);
  }
}

await main();
