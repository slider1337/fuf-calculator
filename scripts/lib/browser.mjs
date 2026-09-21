/*
 * Gemeinsame Mechanik fuer die UI-Werkzeuge: Login, headless Chrome, CDP.
 *
 * Kommt ohne npm-Pakete aus - Node >= 22 hat WebSocket eingebaut. Herausgeloest
 * aus scripts/ui-shot.mjs, damit scripts/check-overflow.mjs dieselbe Anmeldung
 * und denselben Browserstart benutzt, statt sie zu kopieren.
 */

import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as sleep } from 'node:timers/promises';

export { sleep };

/** Meldet sich per Formular-POST an und liefert das Session-Cookie. */
export async function login(base, email, password) {
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

export class Cdp {
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

/**
 * Startet headless Chrome, uebergibt eine verbundene CDP-Sitzung an fn und
 * raeumt danach auf - auch wenn fn wirft.
 */
export async function withChrome({ width, height }, fn) {
  const port = 9222 + Math.floor(Math.random() * 500);
  const profile = await mkdtemp(join(tmpdir(), 'fuf-ui-chrome-'));

  const chrome = spawn('google-chrome', [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-gpu',
    '--hide-scrollbars',
    `--window-size=${width},${height}`,
    `--user-data-dir=${profile}`,
    'about:blank',
  ], { stdio: 'ignore' });

  try {
    const cdp = await Cdp.connect(await chromeWebSocketUrl(port));
    try {
      return await fn(cdp);
    } finally {
      cdp.close();
    }
  } finally {
    chrome.kill();
    // Chrome schreibt nach dem Signal noch kurz weiter; Aufraeumen ist Kosmetik
    // und darf den Lauf nicht scheitern lassen.
    await sleep(300);
    await rm(profile, { recursive: true, force: true }).catch(() => {});
  }
}

/**
 * Setzt das Session-Cookie, aktiviert die CDP-Domains und legt den Viewport fest.
 *
 * Die Breite kommt hier per Emulation und nicht ueber --window-size: Chrome
 * erzwingt eine Mindest-Fensterbreite von rund 500px und ignoriert kleinere
 * Werte stillschweigend. Ein Lauf mit --window-size=390 misst also 500px und
 * behauptet, es seien 390 - genau die Ueberlaeufe, die man sucht, fallen dann
 * durch. Wer die Emulation wieder entfernt, bekommt diesen Fehler zurueck.
 */
export async function prepareSession(cdp, base, cookie, viewport) {
  await cdp.send('Page.enable');
  await cdp.send('Runtime.enable');
  await cdp.send('Network.enable');

  if (viewport) {
    await cdp.send('Emulation.setDeviceMetricsOverride', {
      width: viewport.width,
      height: viewport.height,
      deviceScaleFactor: 1,
      mobile: viewport.width < 768,
    });
  }

  const { hostname } = new URL(base);
  await cdp.send('Network.setCookie', {
    name: cookie.name,
    value: cookie.value,
    domain: hostname,
    path: '/',
  });
}

/** Navigiert und wartet auf load plus die fetch-Kaskade der SPA. */
export async function navigate(cdp, url, settleMs = 1200) {
  await cdp.send('Page.navigate', { url });
  await cdp.send('Runtime.evaluate', {
    expression: 'new Promise((r) => document.readyState === "complete" ? r(1) : addEventListener("load", () => r(1)))',
    awaitPromise: true,
  });
  await sleep(settleMs);
}

/** Wertet einen Ausdruck aus und gibt das JSON-geparste Ergebnis zurueck. */
export async function evalJson(cdp, expression) {
  const result = await cdp.send('Runtime.evaluate', {
    expression: `JSON.stringify(${expression})`,
    returnByValue: true,
  });
  return JSON.parse(result.result.value);
}
